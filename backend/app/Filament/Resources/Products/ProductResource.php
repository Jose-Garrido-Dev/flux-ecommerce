<?php

namespace App\Filament\Resources\Products;

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Attribute;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Services\ProductWriter;
use App\Domain\Pricing\Models\TaxClass;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\RelationManagers\MediaRelationManager;
use App\Filament\Resources\Products\RelationManagers\RelatedProductsRelationManager;
use App\Filament\Resources\Products\RelationManagers\VariantsRelationManager;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use UnitEnum;

/**
 * Productos (módulo 2). El formulario se divide en pestañas; variantes,
 * imágenes y productos relacionados son pestañas adicionales en la edición.
 *
 * Producto simple = una sola variante "por defecto": su SKU, precio y medidas
 * se editan directamente en el formulario (grupo default_variant).
 */
class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static string|UnitEnum|null $navigationGroup = 'Catálogo';

    protected static ?int $navigationSort = 10;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'producto';

    protected static ?string $pluralModelLabel = 'Productos';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'variants.sku', 'variants.barcode'];
    }

    public static function form(Schema $schema): Schema
    {
        $simple = fn (Get $get, ?Product $record) => ! ($record?->has_variants);

        return $schema->columns(1)->components([
            Tabs::make('producto')->persistTabInQueryString()->tabs([
                Tab::make('General')->icon(Heroicon::OutlinedInformationCircle)->columns(3)->schema([
                    Section::make()->columnSpan(2)->columns(2)->schema([
                        TextInput::make('name')->label('Nombre')->required()->maxLength(255)->columnSpanFull()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, Set $set, string $operation) {
                                if ($operation === 'create') {
                                    $set('slug', Str::slug((string) $state));
                                }
                            }),
                        TextInput::make('slug')->label('URL amigable')->required()->maxLength(180)->alphaDash()
                            ->unique(ignoreRecord: true)->prefix('/producto/'),
                        Group::make()->statePath('default_variant')->visible($simple)->columns(2)->schema([
                            TextInput::make('sku')->label('SKU')->required()->maxLength(64)
                                ->regex('/^[A-Za-z0-9][A-Za-z0-9._\-\/]*$/')
                                ->dehydrateStateUsing(fn (?string $state) => Str::upper((string) $state))
                                ->helperText('Código interno único.'),
                            TextInput::make('barcode')->label('Código de barras')->maxLength(64),
                        ]),
                        Textarea::make('short_description')->label('Descripción corta')->maxLength(500)->rows(2)->columnSpanFull()
                            ->helperText('Aparece junto al precio en la ficha y en buscadores.'),
                        RichEditor::make('description')->label('Descripción completa')->columnSpanFull()
                            ->toolbarButtons([['bold', 'italic'], ['h3', 'bulletList', 'orderedList', 'blockquote'], ['undo', 'redo']])
                            ->helperText('Solo se publican párrafos, listas, negritas y subtítulos; el resto se limpia por seguridad.'),
                    ]),
                    Section::make('Organización')->columnSpan(1)->schema([
                        Select::make('status')->label('Estado')
                            ->options(collect(ProductStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()]))
                            ->default(ProductStatus::Draft->value)->required()
                            ->helperText('Publicado: visible en la tienda desde la fecha de publicación.'),
                        DateTimePicker::make('published_at')->label('Fecha de publicación')->seconds(false)
                            ->helperText('Vacío al publicar = ahora. Una fecha futura programa la publicación.'),
                        Toggle::make('featured')->label('Producto destacado'),
                        Select::make('brand_id')->label('Marca')->relationship('brand', 'name')->searchable()->preload()
                            ->createOptionForm([TextInput::make('name')->label('Nombre')->required()->maxLength(120)]),
                        Select::make('primary_category_id')->label('Categoría principal')
                            ->options(fn () => CategoryResource::allOptions())->searchable(),
                        Select::make('categories')->label('Categorías secundarias')->multiple()
                            ->relationship('categories', 'name')
                            ->getOptionLabelFromRecordUsing(fn ($record) => CategoryResource::allOptions()[$record->id] ?? $record->name)
                            ->searchable()->preload(),
                        TagsInput::make('tags')->label('Etiquetas')->separator(',')->splitKeys(['Tab', ','])
                            ->helperText('Palabras para búsquedas y colecciones.'),
                    ]),
                ]),

                Tab::make('Precios')->icon(Heroicon::OutlinedCurrencyDollar)->columns(3)->schema([
                    Select::make('tax_class_id')->label('Impuesto')
                        ->options(fn () => TaxClass::query()->where('is_active', true)->pluck('name', 'id'))
                        ->default(fn () => TaxClass::query()->where('is_default', true)->value('id'))
                        ->required()->live()
                        ->helperText('Los precios se ingresan con IVA incluido.'),
                    Text::make(new HtmlString('<strong>Este producto tiene variantes.</strong> El precio de cada combinación se define en la pestaña <em>Variantes</em>.'))
                        ->visible(fn (?Product $record) => (bool) $record?->has_variants)->columnSpan(2),
                    Group::make()->statePath('default_variant')->visible($simple)->columnSpanFull()->columns(3)->schema([
                        TextInput::make('price_amount')->label('Precio de venta')->prefix('$')->integer()->minValue(1)->required()->live(onBlur: true),
                        TextInput::make('compare_at_amount')->label('Precio anterior (tachado)')->prefix('$')->integer()->minValue(1)
                            ->gt('price_amount')->validationMessages(['gt' => 'Debe ser mayor que el precio de venta.']),
                        TextInput::make('cost_amount')->label('Costo')->prefix('$')->integer()->minValue(0)->live(onBlur: true)
                            ->helperText('Interno. Nunca se muestra en la tienda.'),
                        TextInput::make('sale_amount')->label('Precio promocional')->prefix('$')->integer()->minValue(1)
                            ->lt('price_amount')->validationMessages(['lt' => 'Debe ser menor que el precio de venta.']),
                        DateTimePicker::make('sale_starts_at')->label('Oferta desde')->seconds(false),
                        DateTimePicker::make('sale_ends_at')->label('Oferta hasta')->seconds(false)->after('sale_starts_at'),
                        Text::make(fn (Get $get) => self::marginText($get('price_amount'), $get('cost_amount'), $get('../tax_class_id')))
                            ->columnSpanFull(),
                    ]),
                ]),

                Tab::make('Especificaciones')->icon(Heroicon::OutlinedListBullet)->schema([
                    Text::make('Características que se muestran en la ficha (material, capacidad, medidas...). Los atributos de variantes como Color o Talla se manejan en la pestaña Variantes.'),
                    Repeater::make('attributeValues')->label('Especificaciones')->hiddenLabel()
                        ->relationship('attributeValues')
                        ->addActionLabel('Agregar especificación')
                        ->columns(3)
                        ->defaultItems(0)
                        ->schema([
                            Select::make('attribute_id')->label('Atributo')->required()->live()
                                ->options(fn () => Attribute::query()->orderBy('position')->pluck('name', 'id'))
                                ->distinct(),
                            Select::make('attribute_value_id')->label('Valor')
                                ->options(fn (Get $get) => $get('attribute_id')
                                    ? Attribute::query()->find($get('attribute_id'))?->values()->orderBy('position')->pluck('label', 'id') ?? []
                                    : [])
                                ->visible(fn (Get $get) => in_array(self::attributeType($get('attribute_id')), ['select', 'color', 'image'], true))
                                ->required(fn (Get $get) => in_array(self::attributeType($get('attribute_id')), ['select', 'color', 'image'], true)),
                            TextInput::make('value_text')->label('Valor')->maxLength(255)
                                ->visible(fn (Get $get) => in_array(self::attributeType($get('attribute_id')), ['text', 'measure'], true))
                                ->required(fn (Get $get) => in_array(self::attributeType($get('attribute_id')), ['text', 'measure'], true)),
                            TextInput::make('value_number')->label('Valor')->numeric()
                                ->visible(fn (Get $get) => self::attributeType($get('attribute_id')) === 'number')
                                ->required(fn (Get $get) => self::attributeType($get('attribute_id')) === 'number'),
                        ]),
                ]),

                Tab::make('Envío')->icon(Heroicon::OutlinedTruck)->columns(2)->schema([
                    Select::make('type')->label('Tipo de producto')->options(['physical' => 'Físico (se despacha)'])
                        ->default('physical')->required()->selectablePlaceholder(false)
                        ->helperText('Los productos digitales se habilitarán con su propio módulo.'),
                    Group::make()->statePath('default_variant')->visible($simple)->columnSpanFull()->columns(4)->schema([
                        TextInput::make('weight_g')->label('Peso (g)')->integer()->minValue(0),
                        TextInput::make('length_mm')->label('Largo (mm)')->integer()->minValue(0),
                        TextInput::make('width_mm')->label('Ancho (mm)')->integer()->minValue(0),
                        TextInput::make('height_mm')->label('Alto (mm)')->integer()->minValue(0),
                    ]),
                    Text::make('Peso y medidas se definen por variante en la pestaña Variantes.')
                        ->visible(fn (?Product $record) => (bool) $record?->has_variants),
                    Grid::make(2)->columnSpanFull()->schema([
                        Toggle::make('is_fragile')->label('Es frágil'),
                        Toggle::make('special_packaging')->label('Requiere embalaje especial'),
                        Toggle::make('requires_cold_chain')->label('Requiere despacho refrigerado'),
                        Toggle::make('allows_pickup')->label('Permite retiro en tienda')->default(true),
                    ]),
                    Textarea::make('shipping_notes')->label('Restricciones de despacho')->maxLength(500)->rows(2)->columnSpanFull()
                        ->helperText('Notas para el equipo de despacho, por ejemplo "no se envía a zonas extremas".'),
                ]),

                Tab::make('SEO')->icon(Heroicon::OutlinedMagnifyingGlass)->columns(2)->schema([
                    TextInput::make('seo_title')->label('Metatítulo')->maxLength(70)->live(onBlur: true)
                        ->helperText('Hasta 70 caracteres. Vacío = nombre del producto.'),
                    TextInput::make('canonical_url')->label('URL canónica')->url()->maxLength(255)
                        ->helperText('Solo si este producto duplica otra página. Normalmente vacío.'),
                    Textarea::make('seo_description')->label('Metadescripción')->maxLength(160)->rows(2)->live(onBlur: true)
                        ->columnSpanFull()->helperText('Hasta 160 caracteres. Vacío = descripción corta.'),
                    View::make('filament.products.seo-preview')->columnSpanFull()
                        ->viewData(fn (Get $get) => [
                            'title' => $get('seo_title') ?: $get('name'),
                            'description' => $get('seo_description') ?: $get('short_description'),
                            'slug' => $get('slug'),
                        ]),
                    Text::make('La imagen que se muestra al compartir en redes sociales es la imagen principal, y los datos estructurados de producto (precio, disponibilidad, marca) se generan solos en la tienda.')
                        ->columnSpanFull(),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['brand', 'primaryCategory', 'media', 'variants.prices']))
            ->defaultSort('updated_at', 'desc')
            ->searchPlaceholder('Nombre, SKU o código de barras')
            ->columns([
                ImageColumn::make('thumb')->label('')
                    ->getStateUsing(fn (Product $p) => $p->media->first()?->thumbUrl())->imageSize(44),
                TextColumn::make('id')->label('ID')->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('name')->label('Nombre')->sortable()
                    ->description(fn (Product $p) => $p->has_variants ? $p->variants->count().' variantes' : null)
                    ->searchable(query: fn (Builder $query, string $search) => $query->where(fn (Builder $w) => $w
                        ->where('name', self::like(), "%$search%")
                        ->orWhereHas('variants', fn (Builder $v) => $v->where('sku', self::like(), "%$search%")->orWhere('barcode', $search)))),
                TextColumn::make('sku')->label('SKU')
                    ->getStateUsing(fn (Product $p) => $p->variants->firstWhere('is_default', true)?->sku ?? $p->variants->first()?->sku),
                TextColumn::make('brand.name')->label('Marca')->toggleable(),
                TextColumn::make('primaryCategory.name')->label('Categoría')->toggleable(),
                TextColumn::make('price')->label('Precio')->alignEnd()
                    ->getStateUsing(fn (Product $p) => self::priceSummary($p)['price']),
                TextColumn::make('cost')->label('Costo')->alignEnd()->toggleable()
                    ->getStateUsing(fn (Product $p) => self::priceSummary($p)['cost']),
                TextColumn::make('margin')->label('Margen')->alignEnd()->toggleable()
                    ->getStateUsing(fn (Product $p) => self::priceSummary($p)['margin']),
                TextColumn::make('status')->label('Estado')->badge()
                    ->formatStateUsing(fn (ProductStatus $state) => $state->label())
                    ->color(fn (ProductStatus $state) => match ($state) {
                        ProductStatus::Active => 'success', ProductStatus::Draft => 'warning', ProductStatus::Archived => 'gray',
                    }),
                TextColumn::make('created_at')->label('Creado')->dateTime('d-m-Y')->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')->label('Modificado')->dateTime('d-m-Y H:i')->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Estado')
                    ->options(collect(ProductStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])),
                SelectFilter::make('brand_id')->label('Marca')->relationship('brand', 'name')->searchable()->preload(),
                SelectFilter::make('category')->label('Categoría')
                    ->options(fn () => CategoryResource::allOptions())->searchable()
                    ->query(fn (Builder $query, array $data) => $data['value']
                        ? $query->where(fn (Builder $w) => $w->where('primary_category_id', $data['value'])
                            ->orWhereHas('categories', fn (Builder $c) => $c->where('categories.id', $data['value'])))
                        : $query),
                Filter::make('price')->label('Precio')
                    ->schema([
                        TextInput::make('min')->label('Precio desde')->integer()->prefix('$'),
                        TextInput::make('max')->label('Precio hasta')->integer()->prefix('$'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when(filled($data['min'] ?? null), fn ($q) => $q->whereHas('variants.prices', fn ($p) => $p->where('price_amount', '>=', (int) $data['min'])))
                        ->when(filled($data['max'] ?? null), fn ($q) => $q->whereHas('variants.prices', fn ($p) => $p->where('price_amount', '<=', (int) $data['max']))))
                    ->indicateUsing(fn (array $data) => array_filter([
                        filled($data['min'] ?? null) ? 'Desde $'.number_format((int) $data['min'], 0, ',', '.') : null,
                        filled($data['max'] ?? null) ? 'Hasta $'.number_format((int) $data['max'], 0, ',', '.') : null,
                    ])),
                TernaryFilter::make('featured')->label('Destacado'),
                TernaryFilter::make('has_variants')->label('Con variantes'),
                Filter::make('without_image')->label('Sin imágenes')->toggle()
                    ->query(fn (Builder $query) => $query->whereDoesntHave('media')),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('duplicate')->label('Duplicar')->icon(Heroicon::OutlinedDocumentDuplicate)->color('gray')
                    ->authorize(fn () => auth()->user()->can('products.create'))
                    ->requiresConfirmation()
                    ->modalDescription('Se crea una copia en borrador con SKU nuevos (sufijo -COPIA).')
                    ->action(function (Product $record, $livewire) {
                        $copy = app(ProductWriter::class)->duplicate($record);
                        Notification::make()->success()->title('Producto duplicado')->send();
                        $livewire->redirect(self::getUrl('edit', ['record' => $copy]));
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    self::bulkStatusAction('publish', 'Publicar', Heroicon::OutlinedEye, 'products.update',
                        fn (Product $p, ProductWriter $w) => $w->publish($p)),
                    self::bulkStatusAction('unpublish', 'Despublicar', Heroicon::OutlinedEyeSlash, 'products.update',
                        fn (Product $p, ProductWriter $w) => $w->unpublish($p)),
                    self::bulkStatusAction('archive', 'Archivar', Heroicon::OutlinedArchiveBox, 'products.archive',
                        fn (Product $p, ProductWriter $w) => $w->archive($p)),
                ]),
            ]);
    }

    private static function bulkStatusAction(string $name, string $label, Heroicon $icon, string $permission, \Closure $apply): BulkAction
    {
        return BulkAction::make($name)->label($label)->icon($icon)
            ->authorize(fn () => auth()->user()->can($permission))
            ->requiresConfirmation()
            ->modalDescription(fn (Collection $records) => "Se aplicará \"$label\" a {$records->count()} productos.")
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records) use ($apply, $label) {
                $writer = app(ProductWriter::class);
                $ok = 0;
                $failed = [];
                foreach ($records as $product) {
                    try {
                        $apply($product, $writer);
                        $ok++;
                    } catch (ValidationException $e) {
                        $failed[] = collect($e->errors())->flatten()->first();
                    }
                }
                Notification::make()
                    ->title("$label: $ok de {$records->count()} productos")
                    ->body($failed ? implode("\n", array_slice($failed, 0, 5)) : null)
                    ->{$failed ? 'warning' : 'success'}()
                    ->persistent($failed !== [])
                    ->send();
            });
    }

    /** @return array{price: ?string, cost: ?string, margin: ?string} */
    public static function priceSummary(Product $product): array
    {
        $prices = $product->variants->map(fn ($v) => $v->priceIn('CLP'))->filter();
        if ($prices->isEmpty()) {
            return ['price' => '—', 'cost' => '—', 'margin' => '—'];
        }
        $min = $prices->min(fn ($p) => $p->effectiveAmount());
        $max = $prices->max(fn ($p) => $p->effectiveAmount());
        $first = $prices->sortBy(fn ($p) => $p->effectiveAmount())->first();
        $margin = $first->cost_amount !== null ? self::margin($first->effectiveAmount(), $first->cost_amount, $product->taxClass?->rate_bp ?? 1900) : null;

        return [
            'price' => self::money($min).($max > $min ? ' – '.self::money($max) : ''),
            'cost' => $first->cost_amount !== null ? self::money($first->cost_amount) : '—',
            'margin' => $margin ? $margin['percent'].'%' : '—',
        ];
    }

    /** Margen sobre el precio NETO (sin IVA): el IVA no es ingreso de la tienda. */
    public static function margin(int $priceWithTax, int $cost, int $rateBp): array
    {
        $net = (int) round($priceWithTax * 10000 / (10000 + $rateBp));
        $amount = $net - $cost;

        return ['net' => $net, 'amount' => $amount, 'percent' => $net > 0 ? round($amount * 100 / $net, 1) : 0];
    }

    public static function marginText(mixed $price, mixed $cost, mixed $taxClassId): string
    {
        if (! is_numeric($price) || (int) $price <= 0) {
            return 'Ingresa el precio para ver el precio neto y el margen.';
        }
        $rate = (int) (TaxClass::query()->find($taxClassId)?->rate_bp ?? 1900);
        $net = (int) round((int) $price * 10000 / (10000 + $rate));
        $text = 'Precio neto (sin IVA): '.self::money($net).'.';
        if (is_numeric($cost)) {
            $m = self::margin((int) $price, (int) $cost, $rate);
            $text .= ' Margen: '.self::money($m['amount']).' ('.$m['percent'].'% sobre el neto).';
        }

        return $text;
    }

    /** ILIKE en PostgreSQL (sin distinguir mayúsculas), LIKE en el resto. */
    private static function like(): string
    {
        return DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
    }

    public static function money(int $amount): string
    {
        return '$'.number_format($amount, 0, ',', '.');
    }

    private static function attributeType(mixed $attributeId): ?string
    {
        if (! $attributeId) {
            return null;
        }

        return once(fn () => Attribute::query()->pluck('type', 'id')->all())[$attributeId] ?? null;
    }

    public static function getRelations(): array
    {
        return [
            VariantsRelationManager::class,
            MediaRelationManager::class,
            RelatedProductsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }
}
