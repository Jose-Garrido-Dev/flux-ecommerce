<?php

namespace App\Filament\Resources\Customers;

use App\Domain\Customers\Models\Address;
use App\Domain\Customers\Models\Customer;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Resources\Orders\OrderResource;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Clientes → Clientes (módulo 10). Se registran desde la tienda; aquí se consultan y se bloquean. */
class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Clientes';

    protected static ?int $navigationSort = 10;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'cliente';

    protected static ?string $pluralModelLabel = 'Clientes';

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Section::make('Datos')->columns(2)->schema([
                TextEntry::make('full_name')->label('Nombre')->state(fn (Customer $record) => $record->fullName()),
                TextEntry::make('email')->label('Correo')->copyable(),
                TextEntry::make('phone')->label('Teléfono')->placeholder('—'),
                TextEntry::make('rut')->label('RUT')->placeholder('—'),
                IconEntry::make('verified')->label('Correo confirmado')->boolean()->state(fn (Customer $record) => $record->email_verified_at !== null),
                IconEntry::make('marketing_opt_in')->label('Acepta correos de ofertas')->boolean(),
                TextEntry::make('created_at')->label('Registro')->dateTime('d-m-Y H:i'),
                TextEntry::make('last_login_at')->label('Último ingreso')->dateTime('d-m-Y H:i')->placeholder('—'),
                TextEntry::make('status')->label('Estado')->badge()->formatStateUsing(fn ($state) => Customer::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => $state === 'active' ? 'success' : 'danger'),
            ]),
            Section::make('Compras')->columns(2)->schema([
                TextEntry::make('orders_count')->label('Pedidos')->state(fn (Customer $record) => $record->orders()->count()),
                TextEntry::make('paid_total')->label('Total pagado')
                    ->state(fn (Customer $record) => OrderResource::money((int) $record->orders()->where('payment_status', 'paid')->sum('grand_total'))),
                TextEntry::make('orders_link')->label('Ver pedidos')->columnSpanFull()
                    ->state('Abrir la lista de pedidos de este cliente')
                    ->url(fn (Customer $record) => OrderResource::getUrl('index', ['search' => $record->email])),
            ]),
            Section::make('Direcciones')->columnSpanFull()->schema([
                RepeatableEntry::make('addresses')->label('Direcciones')->hiddenLabel()->columns(3)->schema([
                    TextEntry::make('recipient')->label('Recibe')->belowContent(fn (Address $record) => $record->phone),
                    TextEntry::make('street')->label('Dirección')
                        ->state(fn (Address $record) => trim("{$record->street} {$record->number}".($record->apartment ? ", {$record->apartment}" : '')))
                        ->belowContent(fn (Address $record) => "{$record->commune}, {$record->region?->name}"),
                    IconEntry::make('is_default')->label('Por defecto')->boolean(),
                ])->placeholder('Sin direcciones guardadas'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('orders'))
            ->defaultSort('id', 'desc')
            ->searchPlaceholder('Nombre, correo o RUT')
            ->columns([
                TextColumn::make('name')->label('Nombre')->state(fn (Customer $record) => $record->fullName())
                    ->description(fn (Customer $record) => $record->email)
                    ->searchable(query: fn (Builder $query, string $search) => $query->where(fn ($q) => $q
                        ->where('email', 'like', '%'.mb_strtolower($search).'%')
                        ->orWhere('rut', 'like', "%$search%")
                        ->orWhereRaw('lower(first_name || \' \' || last_name) like ?', ['%'.mb_strtolower($search).'%']))),
                TextColumn::make('phone')->label('Teléfono')->placeholder('—'),
                TextColumn::make('orders_count')->label('Pedidos')->sortable(),
                IconColumn::make('email_verified_at')->label('Correo confirmado')->boolean()->state(fn (Customer $record) => $record->email_verified_at !== null),
                TextColumn::make('created_at')->label('Registro')->dateTime('d-m-Y')->sortable(),
                TextColumn::make('status')->label('Estado')->badge()->formatStateUsing(fn ($state) => Customer::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => $state === 'active' ? 'success' : 'danger'),
            ])
            ->filters([
                SelectFilter::make('status')->label('Estado')->options(Customer::STATUSES),
                TernaryFilter::make('marketing_opt_in')->label('Acepta correos de ofertas'),
            ])
            ->recordActions([ViewAction::make()])
            ->emptyStateHeading('Sin clientes')
            ->emptyStateDescription('Los clientes que creen su cuenta en la tienda aparecerán aquí.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomers::route('/'),
            'view' => ViewCustomer::route('/{record}'),
        ];
    }
}
