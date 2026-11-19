<?php

namespace App\Filament\Support;

use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Inventory\Enums\MovementType;
use App\Domain\Inventory\Models\InventoryLevel;
use App\Domain\Inventory\Models\Warehouse;
use App\Domain\Inventory\Services\InventoryService;
use App\Domain\System\Services\SettingsRepository;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

/**
 * Acciones de inventario reutilizables (Existencias, variantes del producto).
 * Todas pasan por InventoryService: el stock nunca se edita directo, siempre queda un movimiento.
 */
final class InventoryActions
{
    /** Movimiento manual. Si $target es null, el formulario pide producto y bodega. */
    public static function movement(string $name = 'movement', ?Closure $target = null): Action
    {
        $fields = [];
        if (! $target) {
            $fields[] = VariantPicker::make();
            $fields[] = self::warehouseSelect();
        } else {
            $fields[] = self::warehouseSelect()->visible(fn ($record) => ! $record instanceof InventoryLevel);
        }

        return Action::make($name)->label('Registrar movimiento')->icon(Heroicon::OutlinedArrowsUpDown)
            ->modalHeading('Registrar movimiento de stock')
            ->modalSubmitActionLabel('Registrar')
            ->authorize(fn () => auth()->user()?->can('inventory.create'))
            ->schema([
                ...$fields,
                Select::make('type')->label('Tipo de movimiento')->options(MovementType::manual())->required()
                    ->helperText('Para corregir un error usa "Revertir" en el historial de movimientos.'),
                TextInput::make('quantity')->label('Cantidad')->numeric()->integer()->minValue(1)->required()
                    ->helperText('Siempre positiva: el tipo define si suma o resta.'),
                TextInput::make('reason')->label('Motivo')->required()->maxLength(255)->placeholder('Compra a proveedor, producto dañado, etc.'),
                TextInput::make('reference')->label('Documento de respaldo')->maxLength(100)->placeholder('Factura 1234, guía 567')
                    ->helperText('Opcional.'),
            ])
            ->action(function (array $data, $record = null) use ($target) {
                [$variantId, $warehouseId] = $target ? $target($record, $data) : [$data['variant_id'], $data['warehouse_id']];
                self::run(fn () => app(InventoryService::class)->move(
                    (int) $variantId, (int) $warehouseId, MovementType::from($data['type']), (int) $data['quantity'],
                    $data['reason'], filled($data['reference'] ?? null) ? 'document' : null, $data['reference'] ?? null,
                ));
                Notification::make()->success()->title('Movimiento registrado')->send();
            });
    }

    /** Conteo físico: deja el stock en la cantidad contada. */
    public static function count(string $name = 'count', ?Closure $target = null): Action
    {
        return Action::make($name)->label('Conteo')->icon(Heroicon::OutlinedClipboardDocumentCheck)
            ->modalHeading('Ajustar por conteo físico')
            ->modalDescription('Escribe cuántas unidades contaste. La diferencia se registra como ajuste.')
            ->modalSubmitActionLabel('Ajustar')
            ->authorize(fn () => auth()->user()?->can('inventory.create'))
            ->schema(fn ($record) => [
                ...($record instanceof InventoryLevel ? [] : [self::warehouseSelect()]),
                TextInput::make('counted')->label('Unidades contadas')->numeric()->integer()->minValue(0)->required()
                    ->default($record instanceof InventoryLevel ? $record->on_hand : null),
                TextInput::make('reason')->label('Motivo')->maxLength(255)->placeholder('Ajuste por conteo físico'),
            ])
            ->action(function (array $data, $record) use ($target) {
                [$variantId, $warehouseId] = $target ? $target($record, $data) : [$record->variant_id, $record->warehouse_id];
                $movement = self::run(fn () => app(InventoryService::class)->countTo((int) $variantId, (int) $warehouseId, (int) $data['counted'], $data['reason'] ?? null));
                $movement
                    ? Notification::make()->success()->title('Stock ajustado')->body(($movement->quantity > 0 ? '+' : '').$movement->quantity.' unidades.')->send()
                    : Notification::make()->info()->title('Sin diferencias')->body('El conteo coincide con el sistema.')->send();
            });
    }

    /** Controlar stock, sobreventa y stock mínimo de una variante. */
    public static function configure(string $name = 'configure', ?Closure $variant = null): Action
    {
        $resolve = $variant ?? fn ($record) => $record->variant_id;

        return Action::make($name)->label('Configurar')->icon(Heroicon::OutlinedAdjustmentsHorizontal)
            ->modalHeading('Configuración de inventario')
            ->modalSubmitActionLabel('Guardar')
            ->authorize(fn () => auth()->user()?->can('inventory.update'))
            ->fillForm(fn ($record) => app(InventoryService::class)->item((int) $resolve($record))->only(['track_stock', 'allow_backorder', 'min_stock']))
            ->schema(self::itemFields())
            ->action(function (array $data, $record) use ($resolve) {
                app(InventoryService::class)->item((int) $resolve($record))->update([
                    'track_stock' => (bool) $data['track_stock'],
                    'allow_backorder' => (bool) ($data['allow_backorder'] ?? false),
                    'min_stock' => (int) ($data['min_stock'] ?? 0),
                ]);
                Notification::make()->success()->title('Configuración guardada')->send();
            });
    }

    /** Campos de la ficha de inventario (también en la pestaña Inventario del producto). */
    public static function itemFields(): array
    {
        return [
            Toggle::make('track_stock')->label('Controlar stock')->live()
                ->default(fn () => (bool) app(SettingsRepository::class)->get('inventory', 'track_stock_default', true))
                ->helperText('Si está apagado, el producto se vende sin límite y no se descuenta stock.'),
            Toggle::make('allow_backorder')->label('Permitir sobreventa')->visible(fn ($get) => (bool) $get('track_stock'))
                ->helperText('Se puede comprar aunque no haya stock (venta por encargo).'),
            TextInput::make('min_stock')->label('Stock mínimo')->numeric()->integer()->minValue(0)
                ->default(fn () => (int) app(SettingsRepository::class)->get('inventory', 'min_stock_default', 0))
                ->visible(fn ($get) => (bool) $get('track_stock'))
                ->helperText('Bajo este número el producto aparece en "Stock bajo".'),
        ];
    }

    public static function warehouseSelect(string $name = 'warehouse_id'): Select
    {
        return Select::make($name)->label('Bodega')->required()
            ->options(fn () => Warehouse::query()->orderByDesc('is_default')->orderBy('priority')->orderBy('name')->pluck('name', 'id'))
            ->default(fn () => Warehouse::query()->where('is_default', true)->value('id'));
    }

    /** Ejecuta y muestra los errores del servicio en el modal abierto. */
    public static function run(Closure $callback): mixed
    {
        try {
            return $callback();
        } catch (ValidationException $e) {
            $messages = collect($e->errors())->flatten()->implode(' ');
            Notification::make()->danger()->title('No se pudo registrar')->body($messages)->send();
            throw ValidationException::withMessages(collect($e->errors())
                ->mapWithKeys(fn ($m, $key) => ['mountedActions.0.data.'.$key => $m])->all());
        }
    }

    public static function variantLabel(?ProductVariant $variant): string
    {
        return $variant ? VariantPicker::label($variant) : '—';
    }
}
