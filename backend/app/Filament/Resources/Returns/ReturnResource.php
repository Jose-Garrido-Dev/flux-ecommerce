<?php

namespace App\Filament\Resources\Returns;

use App\Domain\Returns\Models\ReturnEvidence;
use App\Domain\Returns\Models\ReturnItem;
use App\Domain\Returns\Models\ReturnRequest;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Returns\Pages\ListReturns;
use App\Filament\Resources\Returns\Pages\ViewReturn;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Ventas → Devoluciones: solicitudes de devolución y cambio, con su revisión, recepción y resolución. */
class ReturnResource extends Resource
{
    protected static ?string $model = ReturnRequest::class;

    protected static ?string $slug = 'returns';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUturnLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Ventas';

    protected static ?int $navigationSort = 2;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'devolución';

    protected static ?string $pluralModelLabel = 'Devoluciones';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $open = ReturnRequest::query()->whereIn('status', ['requested', 'in_review', 'received'])->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Devoluciones por revisar o resolver';
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->with(['order.items', 'order.latestPayment', 'items.orderItem', 'evidence.uploader', 'owner', 'refunds']);
    }

    public static function statusColor(string $state): string
    {
        return match ($state) {
            'requested', 'in_review', 'received' => 'warning',
            'approved' => 'info',
            'processed', 'closed' => 'success',
            'rejected' => 'danger',
            default => 'gray',
        };
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Grid::make(1)->columnSpan(2)->schema([
                Section::make('Productos')->schema([
                    RepeatableEntry::make('items')->hiddenLabel()->label('Productos')->columns(4)->schema([
                        TextEntry::make('orderItem.product_name')->label('Producto')->columnSpan(2)
                            ->belowContent(fn (ReturnItem $record) => trim(($record->orderItem?->variant_label ? $record->orderItem->variant_label.' · ' : '').'SKU '.$record->orderItem?->sku)),
                        TextEntry::make('quantity')->label('Cantidad')
                            ->belowContent(fn (ReturnItem $record) => OrderResource::money($record->quantity * ($record->orderItem?->unit_price ?? 0))),
                        TextEntry::make('condition')->label('Al recibir')->placeholder('Sin recibir')
                            ->formatStateUsing(fn ($state) => ReturnRequest::CONDITIONS[$state] ?? $state)
                            ->belowContent(fn (ReturnItem $record) => $record->condition ? ($record->restock ? 'Volvió al stock' : 'No volvió al stock') : null),
                    ]),
                ]),
                Section::make('Solicitud del cliente')->schema([
                    TextEntry::make('reason')->label('Motivo')->formatStateUsing(fn ($state) => ReturnRequest::REASONS[$state] ?? $state),
                    TextEntry::make('customer_notes')->label('Comentario')->placeholder('Sin comentario'),
                ]),
                Section::make('Respaldo')->description('Fotos o documentos guardados en el servidor (no son públicos).')->schema([
                    RepeatableEntry::make('evidence')->hiddenLabel()->label('Respaldo')->placeholder('Sin archivos')->columns(3)->schema([
                        TextEntry::make('original_name')->label('Archivo')->columnSpan(2)
                            ->url(fn (ReturnEvidence $record) => route('admin.return-evidence', $record), shouldOpenInNewTab: true),
                        TextEntry::make('created_at')->label('Subido')->dateTime('d-m-Y H:i')
                            ->belowContent(fn (ReturnEvidence $record) => $record->uploader?->name),
                    ]),
                ]),
            ]),
            Grid::make(1)->schema([
                Section::make('Devolución')->schema([
                    TextEntry::make('status')->label('Estado')->badge()
                        ->formatStateUsing(fn ($state) => ReturnRequest::STATUSES[$state] ?? $state)->color(fn ($state) => self::statusColor($state)),
                    TextEntry::make('type')->label('Tipo')->formatStateUsing(fn ($state) => ReturnRequest::TYPES[$state] ?? $state),
                    TextEntry::make('order.number')->label('Pedido')
                        ->url(fn (ReturnRequest $record) => OrderResource::getUrl('view', ['record' => $record->order])),
                    TextEntry::make('created_at')->label('Solicitada')->dateTime('d-m-Y H:i'),
                    TextEntry::make('owner.name')->label('Responsable')->placeholder('Sin asignar'),
                    TextEntry::make('instructions')->label('Instrucciones enviadas')->placeholder('—')->visible(fn (ReturnRequest $record) => $record->approved_at !== null),
                    TextEntry::make('rejection_reason')->label('Motivo del rechazo')->visible(fn (ReturnRequest $record) => $record->status === 'rejected'),
                    TextEntry::make('received_at')->label('Recibida')->dateTime('d-m-Y H:i')->placeholder('—'),
                    TextEntry::make('resolution')->label('Resolución')->placeholder('—')
                        ->belowContent(fn (ReturnRequest $record) => $record->refund_amount ? 'Reembolso '.OrderResource::money($record->refund_amount) : null),
                ]),
                Section::make('Cliente')->schema([
                    TextEntry::make('order.email')->label('Correo')->copyable()
                        ->belowContent(fn (ReturnRequest $record) => $record->order->fullName()),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['order', 'items', 'owner']))
            ->defaultSort('id', 'desc')
            ->searchPlaceholder('N.º de pedido o correo')
            ->columns([
                TextColumn::make('order.number')->label('Pedido')->weight('bold')
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereHas('order', fn ($o) => $o
                        ->where('number', 'like', "%{$search}%")->orWhere('email', 'like', '%'.mb_strtolower($search).'%'))),
                TextColumn::make('created_at')->label('Solicitada')->dateTime('d-m-Y H:i')->sortable(),
                TextColumn::make('type')->label('Tipo')->formatStateUsing(fn ($state) => ReturnRequest::TYPES[$state] ?? $state)
                    ->description(fn (ReturnRequest $r) => ReturnRequest::REASONS[$r->reason] ?? $r->reason),
                TextColumn::make('units')->label('Unidades')->state(fn (ReturnRequest $r) => $r->items->sum('quantity')),
                TextColumn::make('owner.name')->label('Responsable')->placeholder('Sin asignar'),
                TextColumn::make('status')->label('Estado')->badge()
                    ->formatStateUsing(fn ($state) => ReturnRequest::STATUSES[$state] ?? $state)->color(fn ($state) => self::statusColor($state)),
            ])
            ->filters([
                SelectFilter::make('status')->label('Estado')->options(ReturnRequest::STATUSES),
                SelectFilter::make('type')->label('Tipo')->options(ReturnRequest::TYPES),
            ])
            ->recordActions([ViewAction::make()])
            ->emptyStateHeading('Sin devoluciones')
            ->emptyStateDescription('Los clientes las piden desde Mi cuenta o el enlace de su pedido. También puedes registrarlas desde el pedido.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReturns::route('/'),
            'view' => ViewReturn::route('/{record}'),
        ];
    }
}
