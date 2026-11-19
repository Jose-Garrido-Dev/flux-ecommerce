<?php

namespace App\Filament\Pages;

use App\Domain\Inventory\Services\InventoryService;
use App\Domain\System\Services\SettingsRepository;
use App\Filament\Concerns\HasHelpManual;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** Configuración → Inventario (módulo 6.5 y 24). */
class InventorySettings extends Page implements HasForms
{
    use HasHelpManual, InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?string $navigationLabel = 'Inventario';

    protected static ?string $title = 'Configuración de inventario';

    protected static ?string $slug = 'inventory-settings';

    protected string $view = 'filament.pages.business-settings';

    public const FIELDS = ['reservation_minutes', 'track_stock_default', 'min_stock_default'];

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('settings.manage') ?? false;
    }

    public function mount(SettingsRepository $settings): void
    {
        $this->form->fill([
            'reservation_minutes' => (int) $settings->get('inventory', 'reservation_minutes', InventoryService::DEFAULT_RESERVATION_MINUTES),
            'track_stock_default' => (bool) $settings->get('inventory', 'track_stock_default', true),
            'min_stock_default' => (int) $settings->get('inventory', 'min_stock_default', 0),
        ]);
    }

    protected function helpManual(): string
    {
        return 'configuracion-inventario';
    }

    protected function getHeaderActions(): array
    {
        return [$this->helpManualAction()];
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('Reservas')->schema([
                TextInput::make('reservation_minutes')->label('Duración de la reserva')->suffix('minutos')
                    ->numeric()->integer()->minValue(5)->maxValue(1440)->required()
                    ->helperText('Tiempo que el stock queda apartado para un carrito o un pago pendiente. Al vencer se libera solo.'),
            ]),
            Section::make('Productos nuevos')->columns(2)->schema([
                Toggle::make('track_stock_default')->label('Controlar stock por defecto')
                    ->helperText('Valor inicial para productos y variantes nuevos. Se puede cambiar en cada producto.'),
                TextInput::make('min_stock_default')->label('Stock mínimo por defecto')->numeric()->integer()->minValue(0)->required(),
            ]),
        ]);
    }

    public function save(SettingsRepository $settings): void
    {
        abort_unless(static::canAccess(), 403);

        $state = $this->form->getState();
        $settings->putMany('inventory', [
            'reservation_minutes' => (int) $state['reservation_minutes'],
            'track_stock_default' => (bool) $state['track_stock_default'],
            'min_stock_default' => (int) $state['min_stock_default'],
        ]);

        Notification::make()->title('Configuración de inventario guardada')->success()->send();
    }
}
