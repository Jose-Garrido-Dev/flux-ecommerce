<?php

namespace App\Filament\Resources\Coupons;

use App\Domain\Marketing\Models\Coupon;
use App\Domain\Marketing\Models\CouponTarget;
use App\Filament\Resources\Coupons\Pages\ManageCoupons;
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
use Illuminate\Support\Facades\DB;
use UnitEnum;

/** Marketing → Cupones (módulo 21). Las reglas se aplican en DiscountEngine; aquí solo se configuran. */
class CouponResource extends Resource
{
    protected static ?string $model = Coupon::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static string|UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 1;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'cupón';

    protected static ?string $pluralModelLabel = 'Cupones';

    protected static ?string $recordTitleAttribute = 'code';

    /** Campos del formulario que se guardan como CouponTarget (modo, tipo). */
    public const TARGET_FIELDS = [
        'include_products' => ['include', 'product'], 'include_categories' => ['include', 'category'], 'include_brands' => ['include', 'brand'],
        'exclude_products' => ['exclude', 'product'], 'exclude_categories' => ['exclude', 'category'], 'exclude_brands' => ['exclude', 'brand'],
    ];

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('code')->label('Código')->required()->maxLength(40)
                ->regex('/^[A-Za-z0-9_-]+$/')
                ->validationMessages(['regex' => 'El código solo puede tener letras, números, guion y guion bajo (sin espacios).'])
                ->dehydrateStateUsing(fn (?string $state) => Coupon::normalize($state))
                // Se compara el código ya normalizado: "verano10" y "VERANO10" son el mismo cupón
                ->rules([fn (?Coupon $record) => function (string $attribute, $value, \Closure $fail) use ($record) {
                    if (Coupon::query()->where('code', Coupon::normalize((string) $value))->when($record, fn ($query) => $query->whereKeyNot($record->id))->exists()) {
                        $fail('Ya existe un cupón con ese código.');
                    }
                }])
                ->helperText('Lo escribe el cliente en el carrito. No distingue mayúsculas.'),
            TextInput::make('name')->label('Nombre interno')->required()->maxLength(120)
                ->helperText('Para reconocerlo en el panel; el cliente ve el código.'),
            Select::make('type')->label('Tipo de descuento')->options(Coupon::TYPES)->default('percent')->required()->live(),
            TextInput::make('value')->label(fn (Get $get) => $get('type') === 'percent' ? 'Porcentaje' : 'Monto')
                ->numeric()->integer()->required()
                ->minValue(1)->maxValue(fn (Get $get) => $get('type') === 'percent' ? 100 : null)
                ->prefix(fn (Get $get) => $get('type') === 'fixed' ? '$' : null)
                ->suffix(fn (Get $get) => $get('type') === 'percent' ? '%' : null)
                ->visible(fn (Get $get) => $get('type') !== 'free_shipping'),
            TextInput::make('min_subtotal')->label('Compra mínima')->prefix('$')->numeric()->integer()->minValue(0)
                ->helperText('Total de productos del carrito, antes de descuentos. Vacío = sin mínimo.'),
            TextInput::make('max_discount')->label('Descuento máximo')->prefix('$')->numeric()->integer()->minValue(1)
                ->visible(fn (Get $get) => $get('type') === 'percent')
                ->helperText('Tope en pesos para el porcentaje. Vacío = sin tope.'),
            Section::make('Vigencia y límites')->columnSpanFull()->columns(2)->schema([
                DateTimePicker::make('starts_at')->label('Desde')->seconds(false)->native(false)->displayFormat('d-m-Y H:i'),
                DateTimePicker::make('ends_at')->label('Hasta')->seconds(false)->native(false)->displayFormat('d-m-Y H:i')
                    ->after('starts_at')->validationMessages(['after' => 'La fecha de término debe ser posterior a la de inicio.']),
                TextInput::make('usage_limit')->label('Usos totales')->numeric()->integer()->minValue(1)
                    ->helperText('Vacío = ilimitado. Un pedido cancelado libera su uso.'),
                TextInput::make('per_customer_limit')->label('Usos por cliente')->numeric()->integer()->minValue(1)
                    ->helperText('Se cuenta por correo. Vacío = ilimitado.'),
                Toggle::make('first_purchase_only')->label('Solo para la primera compra')
                    ->helperText('Se rechaza si el correo o la cuenta ya tiene un pedido (salvo cancelados).'),
                Toggle::make('stackable')->label('Se combina con promociones automáticas')->default(true)
                    ->helperText('Si no se combina, el cliente recibe el descuento que más le conviene.'),
                Select::make('status')->label('Estado')->options(Coupon::STATUSES)->default('active')->required(),
            ]),
            Section::make('Productos que participan')->columnSpanFull()->columns(3)->collapsed()
                ->description('Sin selección, participa todo el carrito. Una exclusión gana sobre una inclusión.')
                ->schema([
                    CatalogTargets::products('include_products', 'Solo estos productos'),
                    CatalogTargets::categories('include_categories', 'Solo estas categorías'),
                    CatalogTargets::brands('include_brands', 'Solo estas marcas'),
                    CatalogTargets::products('exclude_products', 'Excepto estos productos'),
                    CatalogTargets::categories('exclude_categories', 'Excepto estas categorías'),
                    CatalogTargets::brands('exclude_brands', 'Excepto estas marcas'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount(['activeRedemptions as uses_count']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('code')->label('Código')->searchable()->sortable()->copyable()->copyMessage('Código copiado')->weight('bold'),
                TextColumn::make('name')->label('Nombre')->searchable()->toggleable(),
                TextColumn::make('type')->label('Descuento')->getStateUsing(fn (Coupon $c) => $c->describe()),
                TextColumn::make('uses_count')->label('Usos')->alignEnd()
                    ->formatStateUsing(fn (int $state, Coupon $c) => $c->usage_limit ? "$state de {$c->usage_limit}" : (string) $state),
                TextColumn::make('ends_at')->label('Vence')->dateTime('d-m-Y H:i')->placeholder('Sin vencimiento')->sortable(),
                TextColumn::make('status')->label('Estado')->badge()
                    ->getStateUsing(fn (Coupon $c) => self::state($c))
                    ->color(fn (string $state) => match ($state) {
                        'Vigente' => 'success', 'Programado' => 'info', default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')->label('Estado')->options(Coupon::STATUSES),
                SelectFilter::make('type')->label('Tipo')->options(Coupon::TYPES),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateRecordDataUsing(fn (array $data, Coupon $record) => [...$data, ...self::targetsForForm($record)])
                    ->using(fn (Coupon $record, array $data) => self::persist($record, $data)),
                Action::make('toggle')
                    ->label(fn (Coupon $c) => $c->status === 'active' ? 'Desactivar' : 'Activar')
                    ->icon(fn (Coupon $c) => $c->status === 'active' ? Heroicon::OutlinedPause : Heroicon::OutlinedPlay)
                    ->color('gray')
                    ->authorize(fn (Coupon $c) => auth()->user()->can('update', $c))
                    ->action(fn (Coupon $c) => $c->update(['status' => $c->status === 'active' ? 'inactive' : 'active'])),
                DeleteAction::make()
                    ->visible(fn (Coupon $c) => $c->redemptions()->doesntExist())
                    ->modalDescription('Solo se pueden eliminar cupones que nunca se usaron. Para dejar de ofrecer uno usado, desactívalo.'),
            ]);
    }

    public static function state(Coupon $c): string
    {
        if ($c->status !== 'active') {
            return 'Inactivo';
        }
        if ($c->ends_at && $c->ends_at->isPast()) {
            return 'Vencido';
        }
        if ($c->starts_at && $c->starts_at->isFuture()) {
            return 'Programado';
        }

        return 'Vigente';
    }

    public static function targetsForForm(Coupon $coupon): array
    {
        $targets = $coupon->targets()->get();
        $out = [];
        foreach (self::TARGET_FIELDS as $field => [$mode, $type]) {
            $out[$field] = $targets->where('mode', $mode)->where('target_type', $type)->pluck('target_id')->map(fn ($id) => (string) $id)->values()->all();
        }

        return $out;
    }

    /** Guarda el cupón y reemplaza sus productos, categorías y marcas. */
    public static function persist(?Coupon $coupon, array $data): Coupon
    {
        return DB::transaction(function () use ($coupon, $data) {
            $targets = [];
            foreach (self::TARGET_FIELDS as $field => [$mode, $type]) {
                foreach (CatalogTargets::ids($data[$field] ?? []) as $id) {
                    $targets[] = ['target_type' => $type, 'target_id' => $id, 'mode' => $mode];
                }
                unset($data[$field]);
            }
            if (($data['type'] ?? null) === 'free_shipping') {
                $data['value'] = 0;
            }
            if (($data['type'] ?? null) !== 'percent') {
                $data['max_discount'] = null;
            }
            $coupon ??= new Coupon;
            $coupon->fill($data)->save();
            CouponTarget::query()->where('coupon_id', $coupon->id)->delete();
            foreach ($targets as $t) {
                CouponTarget::query()->create(['coupon_id' => $coupon->id, ...$t]);
            }

            return $coupon;
        });
    }

    public static function getPages(): array
    {
        return ['index' => ManageCoupons::route('/')];
    }
}
