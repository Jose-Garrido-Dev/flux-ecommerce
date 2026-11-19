<?php

namespace App\Filament\Resources\Products\Pages;

use App\Domain\Catalog\Import\CatalogExporter;
use App\Filament\Concerns\HasHelpManual;
use App\Filament\Pages\ImportProducts;
use App\Filament\Resources\Products\ProductResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ListProducts extends ListRecords
{
    use HasHelpManual;

    protected static string $resource = ProductResource::class;

    protected function helpManual(): string
    {
        return 'productos';
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->helpManualAction(),
            ActionGroup::make([
                Action::make('import')->label('Importar CSV o Excel')->icon(Heroicon::OutlinedArrowUpTray)
                    ->url(ImportProducts::getUrl())
                    ->visible(fn () => auth()->user()->can('products.import')),
                Action::make('exportXlsx')->label('Exportar a Excel')->icon(Heroicon::OutlinedArrowDownTray)
                    ->visible(fn () => auth()->user()->can('products.export'))
                    ->action(fn () => $this->export('xlsx')),
                Action::make('exportCsv')->label('Exportar a CSV')->icon(Heroicon::OutlinedArrowDownTray)
                    ->visible(fn () => auth()->user()->can('products.export'))
                    ->action(fn () => $this->export('csv')),
            ])->label('Importar / exportar')->button()->color('gray')->icon(Heroicon::OutlinedArrowsUpDown),
            CreateAction::make(),
        ];
    }

    /** Exporta los productos que coinciden con los filtros y la búsqueda actuales. */
    private function export(string $format): BinaryFileResponse
    {
        abort_unless(auth()->user()->can('products.export'), 403);
        $path = tempnam(sys_get_temp_dir(), 'flux-export');
        app(CatalogExporter::class)->export($path, $format, $this->getFilteredTableQuery()->clone()->reorder());

        return response()->download($path, 'productos-'.now()->format('Y-m-d-His').'.'.$format)->deleteFileAfterSend();
    }
}
