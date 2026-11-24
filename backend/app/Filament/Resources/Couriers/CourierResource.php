<?php

namespace App\Filament\Resources\Couriers;

use App\Domain\Shipping\Models\Courier;
use App\Domain\Shipping\Services\CourierRegistry;
use App\Filament\Resources\Couriers\Pages\ManageCouriers;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Logística → Couriers. Sin API verificada, cada courier funciona en modo manual:
 * el número de seguimiento se ingresa en el despacho y el cliente lo consulta en la página del courier.
 */
class CourierResource extends Resource
{
    protected static ?string $model = Courier::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|UnitEnum|null $navigationGroup = 'Logística';

    protected static ?int $navigationSort = 12;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'courier';

    protected static ?string $pluralModelLabel = 'Couriers';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Toggle::make('enabled')->label('Trabajo con este courier')
                ->helperText('Aparece para elegirlo en los métodos de envío y al crear un despacho.'),
            TextInput::make('tracking_url_template')->label('Página de seguimiento')->maxLength(255)->url()
                ->placeholder('https://…{numero}')
                ->helperText('Copia la dirección de la página de seguimiento del courier y reemplaza el número de envío por {numero}. Así el cliente recibe un enlace directo. Vacío = solo se muestra el número.')
                ->rule(fn () => fn (string $attribute, mixed $value, \Closure $fail) => filled($value) && ! str_contains((string) $value, '{numero}')
                    ? $fail('Incluye {numero} donde va el número de seguimiento.') : null),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->paginated(false)
            ->columns([
                TextColumn::make('name')->label('Courier'),
                TextColumn::make('enabled')->label('Uso')->badge()
                    ->formatStateUsing(fn ($state) => $state ? 'En uso' : 'Sin usar')->color(fn ($state) => $state ? 'success' : 'gray'),
                TextColumn::make('api')->label('Conexión por API')->badge()->color('warning')
                    ->state(fn (Courier $r) => $r->verified_at ? 'Verificada' : 'No verificada')
                    ->color(fn (Courier $r) => $r->verified_at ? 'success' : 'warning'),
                TextColumn::make('capabilities')->label('Funciones disponibles')->wrap()
                    ->state(fn (Courier $r) => collect(CourierRegistry::CAPABILITIES)
                        ->map(fn ($label, $key) => $label.': '.($r->isVerified($key) ? 'sí' : 'no verificada'))->implode(' · ')),
                TextColumn::make('tracking_url_template')->label('Página de seguimiento')->placeholder('Sin configurar')->limit(50),
            ])
            ->recordActions([
                EditAction::make()->label('Configurar'),
                Action::make('verify')->label('Probar conexión')->icon(Heroicon::OutlinedSignal)->color('gray')
                    ->authorize(fn () => auth()->user()?->can('settings.manage'))
                    ->action(function (Courier $record) {
                        $error = app(CourierRegistry::class)->adapter($record->code)->verify($record);
                        if ($error !== null) {
                            $record->update(['last_error' => mb_substr($error, 0, 500)]);
                            Notification::make()->warning()->title('No verificada')->body($error)->persistent()->send();

                            return;
                        }
                        Notification::make()->success()->title('Conexión verificada')->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageCouriers::route('/')];
    }
}
