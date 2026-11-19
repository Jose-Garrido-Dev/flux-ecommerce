<?php

namespace App\Filament\Pages;

use App\Domain\Catalog\Import\CatalogExporter;
use App\Domain\Catalog\Import\CatalogImporter;
use App\Domain\Catalog\Import\ImportColumns;
use App\Domain\Catalog\Jobs\RunCatalogImport;
use App\Domain\Catalog\Models\CatalogImport;
use App\Filament\Concerns\HasHelpManual;
use App\Filament\Resources\Products\ProductResource;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;
use UnitEnum;

/**
 * Catálogo → Importar productos (módulo 2.4).
 * Pasos: subir archivo → análisis previo (mapeo de columnas, vista previa, errores por fila, sin escribir nada)
 * → confirmar → importación en segundo plano con avance → informe final y CSV de errores.
 */
class ImportProducts extends Page implements HasForms
{
    use HasHelpManual, InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static string|UnitEnum|null $navigationGroup = 'Catálogo';

    protected static ?int $navigationSort = 15;

    protected static ?string $navigationLabel = 'Importar productos';

    protected static ?string $title = 'Importar productos';

    protected static ?string $slug = 'import-products';

    protected string $view = 'filament.pages.import-products';

    /** @var array<string, mixed> */
    public ?array $upload = [];

    /** @var array<string, string|null> encabezado del archivo => campo */
    public ?array $mapping = [];

    public ?int $importId = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('products.import') ?? false;
    }

    public function mount(): void
    {
        $this->uploadForm->fill(['mode' => 'upsert', 'create_missing' => true]);
        // Si hay una importación en curso del usuario, se retoma para ver su avance
        $running = CatalogImport::query()->where('user_id', auth()->id())
            ->whereIn('status', ['analyzed', 'queued', 'processing'])->latest()->first();
        if ($running) {
            $this->select($running);
        }
    }

    protected function helpManual(): string
    {
        return 'importar-productos';
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->helpManualAction(),
            Action::make('templateXlsx')->label('Plantilla Excel')->icon(Heroicon::OutlinedDocumentArrowDown)->color('gray')
                ->action(fn () => $this->template('xlsx')),
            Action::make('templateCsv')->label('Plantilla CSV')->icon(Heroicon::OutlinedDocumentArrowDown)->color('gray')
                ->action(fn () => $this->template('csv')),
        ];
    }

    public function uploadForm(Schema $schema): Schema
    {
        return $schema->statePath('upload')->components([
            FileUpload::make('file')->label('Archivo (.csv o .xlsx)')->required()
                ->disk('local')->directory('imports')->storeFiles(false)
                ->acceptedFileTypes([
                    'text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ])
                ->maxSize(20 * 1024)
                ->helperText('Hasta 20 MB y '.number_format(CatalogImporter::MAX_ROWS, 0, ',', '.').' filas. Descarga la plantilla para ver las columnas.'),
            Grid::make(2)->schema([
                Radio::make('mode')->label('Qué hacer')->options(CatalogImport::MODES)->required(),
                Toggle::make('create_missing')->label('Crear marcas, categorías y opciones que no existan')
                    ->helperText('Si lo desactivas, las filas con una marca o categoría desconocida se informan como error.'),
            ]),
        ]);
    }

    public function mappingForm(Schema $schema): Schema
    {
        $headers = $this->import()?->summary['headers'] ?? [];

        return $schema->statePath('mapping')->columns(3)->components(
            collect($headers)->map(fn (string $header, int $i) => Select::make((string) $i)
                ->label($header)
                ->options(ImportColumns::options())
                ->placeholder('No importar esta columna'))->all()
        );
    }

    public function analyze(CatalogImporter $importer): void
    {
        abort_unless(static::canAccess(), 403);
        $state = $this->uploadForm->getState();
        $file = $state['file'];
        if (! $file instanceof TemporaryUploadedFile) {
            return;
        }
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, ['csv', 'txt', 'xlsx'], true)) {
            Notification::make()->danger()->title('Formato no admitido. Usa un archivo .csv o .xlsx.')->send();

            return;
        }

        $path = $file->storeAs('imports', now()->format('Ymd-His').'-'.uniqid().'.'.$extension, 'local');
        $import = CatalogImport::query()->create([
            'user_id' => auth()->id(),
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mode' => $state['mode'],
            'create_missing' => (bool) $state['create_missing'],
        ]);

        $this->runAnalysis($importer, $import);
        $this->uploadForm->fill(['mode' => $state['mode'], 'create_missing' => $state['create_missing']]);
    }

    /** Vuelve a analizar con el mapeo de columnas elegido por el usuario. */
    public function reanalyze(CatalogImporter $importer): void
    {
        abort_unless(static::canAccess(), 403);
        $import = $this->import();
        if (! $import || $import->status !== 'analyzed') {
            return;
        }
        $headers = $import->summary['headers'] ?? [];
        $state = $this->mappingForm->getState();
        $mapping = [];
        $used = [];
        foreach ($headers as $i => $header) {
            $field = $state[(string) $i] ?? null;
            if ($field && isset($used[$field])) {
                Notification::make()->danger()->title("Las columnas \"{$used[$field]}\" y \"$header\" van al mismo campo. Deja solo una.")->send();

                return;
            }
            if ($field) {
                $used[$field] = $header;
            }
            $mapping[$header] = $field;
        }
        $import->update(['mapping' => array_filter($mapping)]);
        $this->runAnalysis($importer, $import);
    }

    public function confirm(): void
    {
        abort_unless(static::canAccess(), 403);
        $import = $this->import();
        if (! $import || $import->status !== 'analyzed') {
            return;
        }
        if (($import->summary['missing_required'] ?? []) !== []) {
            Notification::make()->danger()->title('Faltan columnas obligatorias. Corrige el mapeo antes de importar.')->send();

            return;
        }
        $import->update(['status' => 'queued']);
        RunCatalogImport::dispatch($import);
        Notification::make()->success()->title('Importación en curso')
            ->body('Puedes salir de esta página: la importación sigue en segundo plano.')->send();
    }

    public function startOver(): void
    {
        $this->importId = null;
        $this->mapping = [];
        $this->uploadForm->fill(['mode' => 'upsert', 'create_missing' => true]);
    }

    public function show(string $uuid): void
    {
        $import = CatalogImport::query()->where('uuid', $uuid)->firstOrFail();
        $this->select($import);
    }

    public function downloadErrors(CatalogImporter $importer): ?StreamedResponse
    {
        abort_unless(static::canAccess(), 403);
        $import = $this->import();
        if (! $import) {
            return null;
        }
        $csv = $importer->errorReportCsv($import);

        return response()->streamDownload(fn () => print ($csv), 'errores-importacion-'.$import->created_at->format('Y-m-d-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function import(): ?CatalogImport
    {
        return $this->importId ? CatalogImport::query()->find($this->importId) : null;
    }

    /** @return Collection<int, CatalogImport> */
    public function history(): Collection
    {
        return CatalogImport::query()->with('user:id,name')->latest()->limit(10)->get();
    }

    public function productsUrl(): string
    {
        return ProductResource::getUrl('index');
    }

    private function select(CatalogImport $import): void
    {
        $this->importId = $import->id;
        $headers = $import->summary['headers'] ?? [];
        $this->mapping = collect($headers)->mapWithKeys(fn ($h, $i) => [(string) $i => $import->mapping[$h] ?? null])->all();
    }

    private function runAnalysis(CatalogImporter $importer, CatalogImport $import): void
    {
        try {
            $importer->analyze($import);
        } catch (ValidationException $e) {
            $import->update(['status' => 'failed', 'failure' => collect($e->errors())->flatten()->first()]);
        } catch (Throwable $e) {
            report($e);
            $import->update(['status' => 'failed', 'failure' => 'No se pudo leer el archivo. Revisa que sea un CSV o Excel válido.']);
        }
        $this->select($import->fresh());
    }

    private function template(string $format): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'flux-template');
        app(CatalogExporter::class)->template($path, $format);

        return response()->download($path, "plantilla-productos.$format")->deleteFileAfterSend();
    }
}
