<?php

namespace App\Filament\Resources\Promotions;

use App\Domain\Marketing\Models\Promotion;
use App\Filament\Resources\Promotions\Pages\ManagePromotions;
use App\Filament\Support\CatalogTargets;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/** Marketing → Promociones automáticas (sin código): se aplican solas en el carrito si se cumplen las condiciones. */
class PromotionResource extends Resource
{
    protected static ?string $model = Promotion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 2;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'promoción';

    protected static ?string $pluralModelLabel = 'Promociones';

    protected static ?string $recordTitleAttribute = 'name';

    public const DISCOUNT_TYPES = ['percent' => 'Porcentaje', 'fixed' => 'Monto fijo'];

    public static function form(Schema $schema): Schema
    {
        $is = fn (string ...$types) => fn (Get $get) => in_array($get('type'), $types, true);

        return $schema->columns(2)->components([
            TextInput::make('name')->label('Nombre')->required()->maxLength(120)
                ->helperText('Lo ve el cliente en el carrito, por ejemplo "3x2 en poleras".'),
            Select::make('type')->label('Tipo de promoción')->options(Promotion::TYPES)->default('cart_amount')->required()->live(),
            Section::make('Condición')->columnSpanFull()->columns(2)->schema([
                TextInput::make('min_subtotal')->label('Compra mínima')->prefix('$')->numeric()->integer()->minValue(1)
                    ->required($is('cart_amount'))->visible($is('cart_amount'))
                    ->helperText('Total de productos del carrito, antes de descuentos.'),
                TextInput::make('min_quantity')->label('Unidades mínimas')->numeric()->integer()->minValue(1)
                    ->required($is('qty_discount'))->visible($is('qty_discount'))
                    ->helperText('Suma de unidades de los productos que participan.'),
                TextInput::make('buy_quantity')->label('Lleva (X)')->numeric()->integer()->minValue(1)->maxValue(50)->default(2)
                    ->required($is('buy_x_get_y'))->visible($is('buy_x_get_y')),
                TextInput::make('get_quantity')->label('Con descuento (Y)')->numeric()->integer()->minValue(1)->maxValue(50)->default(1)
                    ->required($is('buy_x_get_y'))->visible($is('buy_x_get_y'))
                    ->helperText('Por cada X+Y unidades, las Y más baratas tienen el descuento. 3x2 = lleva 2, con descuento 1, al 100 %.'),
                CatalogTargets::products('product_ids', 'Productos'),
                CatalogTargets::categories('category_ids', 'Categorías'),
                CatalogTargets::brands('brand_ids', 'Marcas')
                    ->helperText('Sin productos, categorías ni marcas, participa todo el carrito.'),
            ]),
            Section::make('Descuento')->columnSpanFull()->columns(2)->schema([
                Select::make('discount_type')->label('Tipo de descuento')->options(self::DISCOUNT_TYPES)->default('percent')->required()->live()
                    ->hidden($is('buy_x_get_y')),
                TextInput::make('value')->label(fn (Get $get) => $get('type') === 'buy_x_get_y' || $get('discount_type') !== 'fixed' ? 'Porcentaje' : 'Monto')
                    ->numeric()->integer()->required()->minValue(1)
                    ->maxValue(fn (Get $get) => $get('type') === 'buy_x_get_y' || $get('discount_type') !== 'fixed' ? 100 : null)
                    ->suffix(fn (Get $get) => $get('type') === 'buy_x_get_y' || $get('discount_type') !== 'fixed' ? '%' : null)
                    ->prefix(fn (Get $get) => $get('type') !== 'buy_x_get_y' && $get('discount_type') === 'fixed' ? '$' : null)
                    ->default(fn (Get $get) => $get('type') === 'buy_x_get_y' ? 100 : null),
            ]),
            Section::make('Vigencia')->columnSpanFull()->columns(2)->schema([
                DateTimePicker::make('starts_at')->label('Desde')->seconds(false)->native(false)->displayFormat('d-m-Y H:i'),
                DateTimePicker::make('ends_at')->label('Hasta')->seconds(false)->native(false)->displayFormat('d-m-Y H:i')
                    ->after('starts_at')->validationMessages(['after' => 'La fecha de término debe ser posterior a la de inicio.']),
                TextInput::make('priority')->label('Prioridad')->numeric()->integer()->minValue(0)->maxValue(1000)->default(0)
                    ->helperText('Las de mayor prioridad se calculan primero.'),
                Toggle::make('stackable')->label('Se combina con otras promociones y cupones')->default(true)
                    ->helperText('Si no se combina, se aplica sola cuando es la primera por prioridad; si ya se aplicó otra, se omite.'),
                Select::make('status')->label('Estado')->options(['active' => 'Activa', 'inactive' => 'Inactiva'])->default('active')->required(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('priority', 'desc')
            ->columns([
                TextColumn::make('name')->label('Nombre')->searchable()->sortable(),
                TextColumn::make('type')->label('Tipo')->formatStateUsing(fn (string $state) => Promotion::TYPES[$state] ?? $state)->wrap(),
                TextColumn::make('priority')->label('Prioridad')->sortable()->alignEnd(),
                TextColumn::make('ends_at')->label('Vence')->dateTime('d-m-Y H:i')->placeholder('Sin vencimiento')->sortable(),
                TextColumn::make('status')->label('Estado')->badge()
                    ->getStateUsing(fn (Promotion $p) => self::state($p))
                    ->color(fn (string $state) => match ($state) {
                        'Vigente' => 'success', 'Programada' => 'info', default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('type')->label('Tipo')->options(Promotion::TYPES),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateRecordDataUsing(fn (array $data, Promotion $record) => [...$data, ...self::toForm($record)])
                    ->using(fn (Promotion $record, array $data) => self::persist($record, $data)),
                Action::make('toggle')
                    ->label(fn (Promotion $p) => $p->status === 'active' ? 'Desactivar' : 'Activar')
                    ->icon(fn (Promotion $p) => $p->status === 'active' ? Heroicon::OutlinedPause : Heroicon::OutlinedPlay)
                    ->color('gray')
                    ->authorize(fn (Promotion $p) => auth()->user()->can('update', $p))
                    ->action(fn (Promotion $p) => $p->update(['status' => $p->status === 'active' ? 'inactive' : 'active'])),
                DeleteAction::make()->modalDescription('Los pedidos que ya la usaron conservan su descuento.'),
            ]);
    }

    public static function state(Promotion $p): string
    {
        return match (true) {
            $p->status !== 'active' => 'Inactiva',
            $p->ends_at !== null && $p->ends_at->isPast() => 'Vencida',
            $p->starts_at !== null && $p->starts_at->isFuture() => 'Programada',
            default => 'Vigente',
        };
    }

    public static function toForm(Promotion $p): array
    {
        $c = $p->conditions ?? [];
        $a = $p->actions ?? [];
        $ids = fn (string $key) => array_map('strval', $c[$key] ?? []);

        return [
            'min_subtotal' => $c['min_subtotal'] ?? null, 'min_quantity' => $c['min_quantity'] ?? null, 'buy_quantity' => $c['buy_quantity'] ?? null,
            'product_ids' => $ids('product_ids'), 'category_ids' => $ids('category_ids'), 'brand_ids' => $ids('brand_ids'),
            'discount_type' => $a['discount_type'] ?? 'percent', 'value' => $a['value'] ?? null, 'get_quantity' => $a['get_quantity'] ?? null,
        ];
    }

    /** Convierte los campos del formulario en condiciones y acciones (JSON) según el tipo. */
    public static function persist(?Promotion $promotion, array $data): Promotion
    {
        $type = $data['type'];
        $conditions = array_filter([
            'product_ids' => CatalogTargets::ids($data['product_ids'] ?? []),
            'category_ids' => CatalogTargets::ids($data['category_ids'] ?? []),
            'brand_ids' => CatalogTargets::ids($data['brand_ids'] ?? []),
            'min_subtotal' => $type === 'cart_amount' ? (int) $data['min_subtotal'] : null,
            'min_quantity' => $type === 'qty_discount' ? (int) $data['min_quantity'] : null,
            'buy_quantity' => $type === 'buy_x_get_y' ? (int) $data['buy_quantity'] : null,
        ], fn ($v) => $v !== null && $v !== []);
        $actions = $type === 'buy_x_get_y'
            ? ['discount_type' => 'percent', 'value' => (int) $data['value'], 'get_quantity' => (int) $data['get_quantity']]
            : ['discount_type' => $data['discount_type'] ?? 'percent', 'value' => (int) $data['value']];

        $promotion ??= new Promotion;
        $promotion->fill([
            'name' => $data['name'], 'type' => $type, 'conditions' => $conditions, 'actions' => $actions,
            'priority' => (int) ($data['priority'] ?? 0), 'stackable' => (bool) ($data['stackable'] ?? true),
            'starts_at' => $data['starts_at'] ?? null, 'ends_at' => $data['ends_at'] ?? null, 'status' => $data['status'] ?? 'active',
        ])->save();

        return $promotion;
    }

    public static function getPages(): array
    {
        return ['index' => ManagePromotions::route('/')];
    }
}
