<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Domain\Catalog\Models\Attribute;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Catalog\Services\ProductWriter;
use App\Domain\Catalog\Services\VariantGenerator;
use App\Filament\Resources\Products\ProductResource;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema as DbSchema;
use Illuminate\Validation\ValidationException;

/**
 * Pestaña "Variantes" (módulo 2, pestaña C): cada combinación de opciones con su SKU,
 * precio, stock físico (fase de inventario) y dimensiones. Incluye el generador de combinaciones.
 */
class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    protected static ?string $title = 'Variantes';

    protected static ?string $modelLabel = 'variante';

    protected static ?string $pluralModelLabel = 'variantes';

    public const STATUSES = ['active' => 'Activa', 'inactive' => 'Inactiva'];

    /** Sin permiso de edición de productos la pestaña queda solo de lectura. */
    public function isReadOnly(): bool
    {
        return ! auth()->user()?->can('products.update');
    }

    public function form(Schema $schema): Schema
    {
        $axes = Attribute::query()->where('is_variant_axis', true)->orderBy('position')->with('values')->get();

        return $schema->columns(3)->components([
            Section::make('Opciones')->columnSpanFull()->columns(3)
                ->description('Combinación que identifica a esta variante. Deja vacío lo que no aplique.')
                ->visible($axes->isNotEmpty())
                ->schema($axes->map(fn (Attribute $a) => Select::make("options.{$a->id}")
                    ->label($a->name)
                    ->options($a->values->pluck('label', 'id'))
                    ->searchable()
                )->all()),
            TextInput::make('sku')->label('SKU')->required()->maxLength(64)->regex('/^[A-Za-z0-9._\-]+$/')
                ->validationMessages(['regex' => 'Solo letras, números, punto, guion y guion bajo.']),
            TextInput::make('barcode')->label('Código de barras')->maxLength(64),
            Select::make('status')->label('Estado')->options(self::STATUSES)->default('active')->required(),
            TextInput::make('price_amount')->label('Precio (IVA incluido)')->prefix('$')->numeric()->integer()->minValue(1)->required(),
            TextInput::make('compare_at_amount')->label('Precio anterior')->prefix('$')->numeric()->integer()
                ->gt('price_amount')->validationMessages(['gt' => 'Debe ser mayor al precio.']),
            TextInput::make('cost_amount')->label('Costo')->prefix('$')->numeric()->integer()->minValue(0)
                ->helperText('Interno; no se muestra en la tienda.'),
            TextInput::make('sale_amount')->label('Precio de oferta')->prefix('$')->numeric()->integer()->minValue(1)
                ->lt('price_amount')->validationMessages(['lt' => 'Debe ser menor al precio.']),
            DateTimePicker::make('sale_starts_at')->label('Oferta desde')->seconds(false),
            DateTimePicker::make('sale_ends_at')->label('Oferta hasta')->seconds(false)->after('sale_starts_at'),
            Grid::make(4)->columnSpanFull()->schema([
                TextInput::make('weight_g')->label('Peso (g)')->numeric()->integer()->minValue(0),
                TextInput::make('length_mm')->label('Largo (mm)')->numeric()->integer()->minValue(0),
                TextInput::make('width_mm')->label('Ancho (mm)')->numeric()->integer()->minValue(0),
                TextInput::make('height_mm')->label('Alto (mm)')->numeric()->integer()->minValue(0),
            ]),
            Select::make('image_media_id')->label('Imagen de la variante')->columnSpanFull()
                ->options(fn () => $this->getOwnerRecord()->media()->get()
                    ->mapWithKeys(fn ($m) => [$m->id => $m->alt ?: ($m->original_name ?: 'Imagen '.$m->id)]))
                ->helperText('Se muestra al elegir esta variante. Sube las imágenes en la pestaña Imágenes.'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['prices', 'attributeValues.attribute', 'image']))
            ->reorderable('position')
            ->defaultSort('position')
            ->columns([
                ImageColumn::make('image')->label('')->state(fn (ProductVariant $r) => $r->image?->thumbUrl())->square()->size(40),
                TextColumn::make('options')->label('Opciones')
                    ->state(fn (ProductVariant $r) => $r->attributeValues->sortBy('attribute.position')
                        ->map(fn ($v) => "{$v->attribute->name}: {$v->label}")->implode(' · ') ?: 'Sin opciones'),
                TextColumn::make('sku')->label('SKU')->searchable()->copyable(),
                TextColumn::make('barcode')->label('Código de barras')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('price')->label('Precio')
                    ->state(fn (ProductVariant $r) => $r->priceIn('CLP')?->price_amount)
                    ->formatStateUsing(fn ($state) => ProductResource::money($state)),
                TextColumn::make('sale')->label('Oferta')
                    ->state(fn (ProductVariant $r) => $r->priceIn('CLP')?->sale_amount)
                    ->formatStateUsing(fn ($state) => ProductResource::money($state))->placeholder('—'),
                TextColumn::make('weight_g')->label('Peso')->suffix(' g')->placeholder('—'),
                IconColumn::make('is_default')->label('Principal')->boolean(),
                TextColumn::make('status')->label('Estado')->badge()
                    ->formatStateUsing(fn ($state) => self::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => $state === 'active' ? 'success' : 'gray'),
            ])
            ->headerActions([
                $this->generateAction(),
                CreateAction::make()->label('Agregar variante')
                    ->using(fn (array $data) => $this->persist(new ProductVariant([
                        'product_id' => $this->getOwnerRecord()->id,
                        'is_default' => false,
                        'position' => (int) $this->getOwnerRecord()->variants()->max('position') + 1,
                    ]), $data)),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateRecordDataUsing(fn (array $data, ProductVariant $record) => $this->fillData($record, $data))
                    ->using(fn (ProductVariant $record, array $data) => $this->persist($record, $data)),
                Action::make('makeDefault')->label('Hacer principal')->icon(Heroicon::OutlinedStar)
                    ->visible(fn (ProductVariant $r) => ! $r->is_default)
                    ->authorize(fn () => auth()->user()->can('products.update'))
                    ->action(function (ProductVariant $record) {
                        DB::transaction(function () use ($record) {
                            $record->product->variants()->update(['is_default' => false]);
                            $record->update(['is_default' => true]);
                        });
                        Notification::make()->success()->title('Variante principal actualizada')->send();
                    }),
                DeleteAction::make()
                    ->authorize(fn () => auth()->user()->can('products.update'))
                    ->using(function (ProductVariant $record, DeleteAction $action) {
                        if ($reason = $this->deletionBlocker($record)) {
                            Notification::make()->danger()->title('No se puede eliminar')->body($reason)->send();
                            $action->halt();
                        }
                        $product = $record->product;
                        $record->delete();
                        app(ProductWriter::class)->syncHasVariants($product->fresh());

                        return true;
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('changePrice')->label('Cambiar precio')->icon(Heroicon::OutlinedCurrencyDollar)
                        ->authorize(fn () => auth()->user()->can('products.update'))
                        ->schema([
                            Radio::make('mode')->label('Cómo')->required()->default('set')->options([
                                'set' => 'Fijar un precio',
                                'percent' => 'Subir o bajar un porcentaje',
                            ])->live(),
                            TextInput::make('amount')->label(fn (Get $get) => $get('mode') === 'percent' ? 'Porcentaje (usa negativo para bajar)' : 'Nuevo precio')
                                ->numeric()->required()
                                ->suffix(fn (Get $get) => $get('mode') === 'percent' ? '%' : null),
                        ])
                        ->action(fn (Collection $records, array $data) => $this->changePrices($records, $data))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('activate')->label('Activar')->icon(Heroicon::OutlinedCheckCircle)
                        ->authorize(fn () => auth()->user()->can('products.update'))
                        ->action(fn (Collection $records) => $this->setStatus($records, 'active'))->deselectRecordsAfterCompletion(),
                    BulkAction::make('deactivate')->label('Desactivar')->icon(Heroicon::OutlinedNoSymbol)
                        ->authorize(fn () => auth()->user()->can('products.update'))
                        ->action(fn (Collection $records) => $this->setStatus($records, 'inactive'))->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->emptyStateHeading('Sin variantes')
            ->emptyStateDescription('Usa "Generar combinaciones" para crear todas las tallas y colores de una vez.');
    }

    private function generateAction(): Action
    {
        $axes = Attribute::query()->where('is_variant_axis', true)->orderBy('position')->with('values')->get();

        return Action::make('generate')->label('Generar combinaciones')->icon(Heroicon::OutlinedSquares2x2)->color('gray')
            ->authorize(fn () => auth()->user()->can('products.update'))
            ->modalDescription('Marca los valores de cada opción. Se crea una variante por combinación; las que ya existen se omiten. Máximo '.VariantGenerator::MAX_COMBINATIONS.' por vez.')
            ->schema([
                ...$axes->map(fn (Attribute $a) => CheckboxList::make("selection.{$a->id}")
                    ->label($a->name)->options($a->values->pluck('label', 'id'))->columns(4)->bulkToggleable())->all(),
                Grid::make(3)->schema([
                    TextInput::make('price_amount')->label('Precio para todas')->prefix('$')->numeric()->integer()->minValue(1)
                        ->default(fn () => $this->getOwnerRecord()->defaultVariant?->priceIn('CLP')?->price_amount)->required(),
                    TextInput::make('cost_amount')->label('Costo')->prefix('$')->numeric()->integer()->minValue(0),
                    TextInput::make('sku_prefix')->label('Prefijo de SKU')->maxLength(20)
                        ->default(fn () => $this->getOwnerRecord()->defaultVariant?->sku)
                        ->helperText('Ej.: POL → POL-NEGRO-M'),
                ]),
            ])
            ->action(function (array $data, Action $action) {
                try {
                    $result = app(VariantGenerator::class)->generate($this->getOwnerRecord(), $data['selection'] ?? [], $data);
                } catch (ValidationException $e) {
                    Notification::make()->danger()->title(collect($e->errors())->flatten()->first())->send();
                    $action->halt();
                }
                Notification::make()->success()
                    ->title("{$result['created']} variantes creadas")
                    ->body($result['skipped'] ? "{$result['skipped']} ya existían y se omitieron." : null)
                    ->send();
            });
    }

    private function fillData(ProductVariant $record, array $data): array
    {
        $price = $record->priceIn('CLP');

        return [
            ...$data,
            ...($price?->only(ProductWriter::PRICE_FIELDS) ?? []),
            'options' => $record->attributeValues->pluck('id', 'pivot.attribute_id')->all(),
        ];
    }

    private function persist(ProductVariant $variant, array $data): Model
    {
        $product = $this->getOwnerRecord();
        $options = array_filter($data['options'] ?? []);

        try {
            return DB::transaction(function () use ($variant, $data, $options, $product) {
                $this->assertUniqueCombination($product, $variant, $options);
                $writer = app(ProductWriter::class);
                $writer->saveVariant($variant, $data);
                $variant->attributeValues()->sync(collect($options)->mapWithKeys(fn ($valueId, $attributeId) => [
                    (int) $valueId => ['attribute_id' => (int) $attributeId],
                ])->all());
                $writer->syncHasVariants($product->fresh());

                return $variant;
            });
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(collect($e->errors())
                ->mapWithKeys(fn ($m, $key) => [$this->actionErrorKey($key) => $m])->all());
        }
    }

    /** Las claves de error apuntan al campo del modal abierto. */
    private function actionErrorKey(string $key): string
    {
        return 'mountedActions.0.data.'.($key === 'options' ? 'options' : $key);
    }

    private function assertUniqueCombination(Product $product, ProductVariant $variant, array $options): void
    {
        $key = collect($options)->map(fn ($v) => (int) $v)->sortKeys()->all();
        $clash = $product->variants()->with('attributeValues')->get()
            ->reject(fn ($v) => $variant->exists && $v->is($variant))
            ->first(fn ($v) => $v->attributeValues->pluck('id', 'pivot.attribute_id')->map(fn ($id) => (int) $id)->sortKeys()->all() === $key);
        if ($clash) {
            throw ValidationException::withMessages(['sku' => "Ya existe una variante con esas mismas opciones ({$clash->sku})."]);
        }
    }

    private function deletionBlocker(ProductVariant $variant): ?string
    {
        if (DbSchema::hasTable('order_items') && DB::table('order_items')->where('variant_id', $variant->id)->exists()) {
            return 'Tiene pedidos asociados. Desactívala en lugar de eliminarla.';
        }
        if ($variant->product->variants()->count() === 1) {
            return 'Es la única variante del producto.';
        }

        return null;
    }

    private function changePrices(Collection $records, array $data): void
    {
        $writer = app(ProductWriter::class);
        $failed = [];
        foreach ($records as $variant) {
            $price = $variant->priceIn('CLP');
            $current = $price?->price_amount;
            $new = $data['mode'] === 'percent'
                ? ($current ? (int) round($current * (1 + (float) $data['amount'] / 100)) : null)
                : (int) $data['amount'];
            try {
                if (! $new || $new < 1) {
                    throw ValidationException::withMessages(['price_amount' => 'el precio resultante no es válido']);
                }
                $writer->savePrice($variant, [...($price?->only(ProductWriter::PRICE_FIELDS) ?? []), 'price_amount' => $new]);
            } catch (ValidationException $e) {
                $failed[] = "{$variant->sku}: ".collect($e->errors())->flatten()->first();
            }
        }
        $done = $records->count() - count($failed);
        Notification::make()->{$failed ? 'warning' : 'success'}()
            ->title("Precio actualizado en $done variantes")
            ->body($failed ? 'No se cambiaron: '.implode('; ', $failed) : null)
            ->persistent((bool) $failed)->send();
    }

    private function setStatus(Collection $records, string $status): void
    {
        ProductVariant::query()->whereKey($records->modelKeys())->update(['status' => $status]);
        Notification::make()->success()->title('Variantes actualizadas')->send();
    }
}
