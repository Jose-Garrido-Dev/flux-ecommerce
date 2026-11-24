<?php

namespace App\Filament\Resources\Orders;

use App\Domain\Notifications\Models\EmailLog;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderEvent;
use App\Domain\Orders\Models\OrderItem;
use App\Domain\Orders\Models\Payment;
use App\Domain\Payments\Gateways\WebpayPlus\WebpayPlusGateway;
use App\Domain\Payments\Models\Refund;
use App\Domain\Returns\Models\ReturnRequest;
use App\Domain\Shipping\Models\Courier;
use App\Domain\Shipping\Models\Shipment;
use App\Domain\Shipping\Models\ShippingIncident;
use App\Domain\Shipping\Services\RateEngine;
use App\Filament\Resources\EmailLogs\EmailLogResource;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Returns\ReturnResource;
use App\Filament\Resources\Shipments\ShipmentResource;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Ventas → Pedidos (módulo 7, versión inicial de la Fase 5: consulta, confirmar transferencia y cancelar). */
class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static string|UnitEnum|null $navigationGroup = 'Ventas';

    protected static ?int $navigationSort = 10;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'pedido';

    protected static ?string $pluralModelLabel = 'Pedidos';

    protected static ?string $recordTitleAttribute = 'number';

    public static function getNavigationBadge(): ?string
    {
        $pending = Order::query()->where('status', 'pending')->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Pendientes de pago';
    }

    public static function money(?int $amount): string
    {
        return $amount === null ? '—' : '$'.number_format($amount, 0, ',', '.');
    }

    public static function statusColor(string $state): string
    {
        return match ($state) {
            'pending' => 'warning', 'processing', 'paid' => 'info', 'completed' => 'success', default => 'gray',
        };
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->with([
            'items', 'shippingLine', 'latestPayment', 'payments', 'refunds.creator', 'events',
            'shipments.items.orderItem', 'returns.items.orderItem', 'emailLogs',
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Grid::make(1)->columnSpan(2)->schema([
                Section::make('Productos')->schema([
                    RepeatableEntry::make('items')->label('Productos')->hiddenLabel()->columns(5)->schema([
                        TextEntry::make('product_name')->label('Producto')->columnSpan(2)
                            ->belowContent(fn (OrderItem $record) => trim(($record->variant_label ? $record->variant_label.' · ' : '').'SKU '.$record->sku)),
                        TextEntry::make('quantity')->label('Cantidad'),
                        TextEntry::make('unit_price')->label('Precio')->formatStateUsing(fn ($state) => self::money($state)),
                        TextEntry::make('line_total')->label('Total')->formatStateUsing(fn ($state) => self::money($state)),
                    ]),
                    Grid::make(4)->schema([
                        TextEntry::make('subtotal')->label('Subtotal')->formatStateUsing(fn ($state) => self::money($state)),
                        TextEntry::make('shipping_total')->label('Envío')->formatStateUsing(fn ($state) => $state ? self::money($state) : 'Gratis'),
                        TextEntry::make('tax_total')->label('IVA incluido')->formatStateUsing(fn ($state) => self::money($state)),
                        TextEntry::make('grand_total')->label('Total')->weight('bold')->formatStateUsing(fn ($state) => self::money($state)),
                    ]),
                ]),
                Section::make('Entrega')->columns(2)->schema([
                    TextEntry::make('shippingLine.method_name')->label('Método')->placeholder('—')
                        ->belowContent(fn (Order $record) => self::shippingDetail($record)),
                    TextEntry::make('address')->label('Dirección')->placeholder('Retiro en tienda')
                        ->state(fn (Order $record) => self::address($record)),
                    TextEntry::make('notes')->label('Comentarios del cliente')->placeholder('—')->columnSpanFull(),
                ]),
                Section::make('Despachos')->visible(fn (Order $record) => $record->shipments()->exists())->schema([
                    RepeatableEntry::make('shipments')->label('Despachos')->hiddenLabel()->columns(4)->schema([
                        TextEntry::make('carrier_name')->label('Entrega')
                            ->belowContent(fn (Shipment $record) => $record->tracking_number ? 'Seguimiento '.$record->tracking_number : null)
                            ->url(fn (Shipment $record) => ShipmentResource::getUrl('view', ['record' => $record])),
                        TextEntry::make('status')->label('Estado')->badge()
                            ->formatStateUsing(fn ($state) => Shipment::STATUSES[$state] ?? $state)
                            ->color(fn ($state) => ShipmentResource::statusColor($state)),
                        TextEntry::make('contents')->label('Contenido')->columnSpan(2)
                            ->state(fn (Shipment $record) => $record->items->map(fn ($i) => $i->quantity.' × '.$i->orderItem?->product_name)->implode(', ')),
                    ]),
                ]),
                Section::make('Pagos')->schema([
                    RepeatableEntry::make('payments')->label('Pagos')->hiddenLabel()->columns(4)->schema([
                        TextEntry::make('method_code')->label('Medio')
                            ->formatStateUsing(fn ($state, Payment $record) => $record->methodLabel().($record->attempt > 1 ? " (intento {$record->attempt})" : '')),
                        TextEntry::make('status')->label('Estado')->badge()
                            ->formatStateUsing(fn ($state) => Payment::STATUSES[$state] ?? $state)
                            ->color(fn ($state) => match ($state) {
                                'paid' => 'success', 'pending' => 'warning', 'failed' => 'danger', default => 'gray'
                            }),
                        TextEntry::make('amount')->label('Monto')->formatStateUsing(fn ($state) => self::money($state))
                            ->belowContent(fn (Payment $record) => $record->refunded_amount ? 'Reembolsado: '.self::money($record->refunded_amount) : null),
                        TextEntry::make('detail')->label('Detalle')->placeholder('—')->state(fn (Payment $record) => self::paymentDetail($record)),
                    ]),
                ]),
                Section::make('Reembolsos')->visible(fn (Order $record) => $record->refunded_total > 0)->schema([
                    RepeatableEntry::make('refunds')->label('Reembolsos')->hiddenLabel()->columns(4)->schema([
                        TextEntry::make('created_at')->label('Fecha')->dateTime('d-m-Y H:i'),
                        TextEntry::make('amount')->label('Monto')->formatStateUsing(fn ($state) => self::money($state)),
                        TextEntry::make('reason')->label('Motivo'),
                        TextEntry::make('creator.name')->label('Hecho por')->placeholder('—')
                            ->belowContent(fn (Refund $record) => $record->restocked ? 'Productos devueltos al stock' : null),
                    ]),
                ]),
                Section::make('Devoluciones')->visible(fn (Order $record) => $record->returns()->exists())->schema([
                    RepeatableEntry::make('returns')->label('Devoluciones')->hiddenLabel()->columns(4)->schema([
                        TextEntry::make('type')->label('Solicitud')
                            ->formatStateUsing(fn ($state, ReturnRequest $record) => $record->label().' · '.(ReturnRequest::TYPES[$state] ?? $state))
                            ->belowContent(fn (ReturnRequest $record) => ReturnRequest::REASONS[$record->reason] ?? null)
                            ->url(fn (ReturnRequest $record) => ReturnResource::getUrl('view', ['record' => $record])),
                        TextEntry::make('status')->label('Estado')->badge()
                            ->formatStateUsing(fn ($state) => ReturnRequest::STATUSES[$state] ?? $state)
                            ->color(fn ($state) => ReturnResource::statusColor($state)),
                        TextEntry::make('contents')->label('Productos')->columnSpan(2)
                            ->state(fn (ReturnRequest $record) => $record->items->map(fn ($i) => $i->quantity.' × '.$i->orderItem?->product_name)->implode(', ')),
                    ]),
                ]),
                Section::make('Historial')->schema([
                    RepeatableEntry::make('events')->label('Historial')->hiddenLabel()->columns(3)->schema([
                        TextEntry::make('created_at')->label('Fecha')->dateTime('d-m-Y H:i'),
                        TextEntry::make('type')->label('Evento')->formatStateUsing(fn ($state, OrderEvent $record) => $record->label()),
                        TextEntry::make('payload')->label('Detalle')->placeholder('—')
                            ->state(fn (OrderEvent $record) => self::eventDetail($record)),
                    ]),
                ]),
                Section::make('Correos')->description('Correos enviados al cliente y a la tienda por este pedido.')
                    ->visible(fn (Order $record) => $record->emailLogs()->exists())->collapsed()->schema([
                        RepeatableEntry::make('emailLogs')->label('Correos')->hiddenLabel()->columns(4)->schema([
                            TextEntry::make('created_at')->label('Fecha')->dateTime('d-m-Y H:i'),
                            TextEntry::make('template_key')->label('Correo')->columnSpan(2)
                                ->formatStateUsing(fn ($state, EmailLog $record) => $record->templateName())
                                ->belowContent(fn (EmailLog $record) => $record->to),
                            TextEntry::make('status')->label('Estado')->badge()
                                ->formatStateUsing(fn ($state) => EmailLog::STATUSES[$state] ?? $state)
                                ->color(fn ($state) => EmailLogResource::statusColor($state))
                                ->belowContent(fn (EmailLog $record) => $record->status === 'failed' ? mb_substr((string) $record->last_error, 0, 120) : null),
                        ]),
                    ]),
            ]),
            Grid::make(1)->schema([
                Section::make('Estado')->schema([
                    TextEntry::make('status')->label('Pedido')->badge()
                        ->formatStateUsing(fn ($state) => Order::STATUSES[$state] ?? $state)->color(fn ($state) => self::statusColor($state)),
                    TextEntry::make('payment_status')->label('Pago')->badge()
                        ->formatStateUsing(fn ($state) => Order::PAYMENT_STATUSES[$state] ?? $state)->color(fn ($state) => self::statusColor($state)),
                    TextEntry::make('latestPayment.expires_at')->label('Plazo de pago')->dateTime('d-m-Y H:i')
                        ->visible(fn (Order $record) => $record->payment_status === 'pending'),
                    TextEntry::make('latestPayment.reference')->label('N.º de operación')->placeholder('—')
                        ->visible(fn (Order $record) => $record->payment_status === 'paid'),
                    TextEntry::make('fulfillment_status')->label('Despacho')->badge()
                        ->formatStateUsing(fn ($state) => Order::FULFILLMENT_STATUSES[$state] ?? $state)
                        ->color(fn ($state) => in_array($state, ['delivered', 'picked_up'], true) ? 'success' : ($state === 'unfulfilled' ? 'gray' : 'info'))
                        ->visible(fn (Order $record) => $record->status !== 'pending'),
                    TextEntry::make('placed_at')->label('Fecha del pedido')->dateTime('d-m-Y H:i'),
                ]),
                Section::make('Cliente')->schema([
                    TextEntry::make('name')->label('Nombre')->state(fn (Order $record) => $record->fullName())
                        ->belowContent(fn (Order $record) => $record->customer_id ? 'Cliente registrado' : 'Compra como invitado'),
                    TextEntry::make('email')->label('Correo')->copyable(),
                    TextEntry::make('phone')->label('Teléfono')->placeholder('—'),
                    TextEntry::make('document_type')->label('Documento')
                        ->formatStateUsing(fn ($state) => Order::DOCUMENT_TYPES[$state] ?? $state)
                        ->belowContent(fn (Order $record) => $record->document_type === 'factura'
                            ? "{$record->billing_name} · RUT {$record->billing_rut} · {$record->billing_activity}" : null),
                ]),
            ]),
        ]);
    }

    public static function address(Order $order): ?string
    {
        $a = $order->shipping_address;
        if (! $a) {
            return null;
        }

        return trim("{$a['recipient']} · {$a['phone']}\n{$a['street']} {$a['number']}".(($a['apartment'] ?? null) ? ", {$a['apartment']}" : '')
            ."\n{$a['commune']}, {$a['region']}".(($a['reference'] ?? null) ? "\nReferencia: {$a['reference']}" : ''));
    }

    public static function shippingDetail(Order $order): ?string
    {
        $line = $order->shippingLine;
        if (! $line) {
            return null;
        }
        $parts = array_filter([
            $line->estimated_days,
            $line->carrier_code ? 'Courier: '.(Courier::for($line->carrier_code)?->name ?? $line->carrier_code) : null,
            $line->zone_name ? "Zona {$line->zone_name}" : null,
            $line->chargeable_weight_g ? 'Peso cobrado '.RateEngine::kg($line->chargeable_weight_g) : null,
            $line->pickup_address ? "Retiro en {$line->pickup_address}" : null,
        ]);

        return $parts ? implode(' · ', $parts) : null;
    }

    public static function paymentDetail(Payment $payment): ?string
    {
        $parts = array_filter([
            $payment->card_last4 ? "Tarjeta terminada en {$payment->card_last4}" : null,
            $payment->payment_type ? (WebpayPlusGateway::PAYMENT_TYPES[$payment->payment_type] ?? null) : null,
            ($payment->installments ?? 0) > 1 ? "{$payment->installments} cuotas" : null,
            $payment->authorization_code ? "Autorización {$payment->authorization_code}" : null,
            $payment->reference ? "Operación {$payment->reference}" : null,
            $payment->gateway_order ? "Orden de compra {$payment->gateway_order}" : null,
            $payment->failure_reason,
        ]);

        return $parts ? implode(' · ', $parts) : null;
    }

    public static function eventDetail(OrderEvent $event): ?string
    {
        $p = $event->payload ?? [];

        return match ($event->type) {
            'payment_confirmed' => trim('Monto '.self::money($p['amount'] ?? null).(isset($p['reference']) ? " · Operación {$p['reference']}" : '')),
            'cancelled', 'payment_failed', 'payment_aborted' => $p['reason'] ?? null,
            'placed' => Payment::METHODS[$p['payment_method'] ?? 'bank_transfer'] ?? null,
            'refunded' => trim('Monto '.self::money($p['amount'] ?? null).(isset($p['reason']) ? " · {$p['reason']}" : '').(! empty($p['restocked']) ? ' · Productos devueltos al stock' : '')),
            'note' => $p['text'] ?? null,
            'shipment_created' => $p['carrier'] ?? null,
            'incident_opened' => ShippingIncident::TYPES[$p['type'] ?? ''] ?? null,
            'return_requested', 'return_status' => $p['label'] ?? null,
            default => null,
        };
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('shippingLine'))
            ->defaultSort('id', 'desc')
            ->searchPlaceholder('N.º, correo o nombre')
            ->columns([
                TextColumn::make('number')->label('N.º')->searchable()->weight('bold'),
                TextColumn::make('placed_at')->label('Fecha')->dateTime('d-m-Y H:i')->sortable(),
                TextColumn::make('customer')->label('Cliente')
                    ->state(fn (Order $record) => $record->fullName())->description(fn (Order $record) => $record->email)
                    ->searchable(query: fn (Builder $query, string $search) => $query->where(fn ($q) => $q
                        ->where('email', 'like', '%'.mb_strtolower($search).'%')
                        ->orWhereRaw('lower(first_name || \' \' || last_name) like ?', ['%'.mb_strtolower($search).'%']))),
                TextColumn::make('grand_total')->label('Total')->alignEnd()->formatStateUsing(fn ($state) => self::money($state))->sortable(),
                TextColumn::make('payment_status')->label('Pago')->badge()
                    ->formatStateUsing(fn ($state) => Order::PAYMENT_STATUSES[$state] ?? $state)->color(fn ($state) => self::statusColor($state)),
                TextColumn::make('status')->label('Estado')->badge()
                    ->formatStateUsing(fn ($state) => Order::STATUSES[$state] ?? $state)->color(fn ($state) => self::statusColor($state)),
                TextColumn::make('fulfillment_status')->label('Despacho')->badge()
                    ->formatStateUsing(fn ($state, Order $record) => $record->status === 'pending' ? '—' : (Order::FULFILLMENT_STATUSES[$state] ?? $state))
                    ->color(fn ($state) => in_array($state, ['delivered', 'picked_up'], true) ? 'success' : ($state === 'unfulfilled' ? 'warning' : 'info')),
                TextColumn::make('shippingLine.method_name')->label('Envío')->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Estado')->options(Order::STATUSES),
                SelectFilter::make('payment_status')->label('Pago')->options(Order::PAYMENT_STATUSES),
                SelectFilter::make('fulfillment_status')->label('Despacho')->options(Order::FULFILLMENT_STATUSES),
                Filter::make('dates')->label('Fechas')
                    ->schema([DatePicker::make('from')->label('Desde'), DatePicker::make('until')->label('Hasta')])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('placed_at', '>=', $d))
                        ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('placed_at', '<=', $d))),
            ])
            ->recordActions([ViewAction::make()])
            ->emptyStateHeading('Sin pedidos')
            ->emptyStateDescription('Los pedidos de la tienda aparecerán aquí.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'view' => ViewOrder::route('/{record}'),
        ];
    }
}
