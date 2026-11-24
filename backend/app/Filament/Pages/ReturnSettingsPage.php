<?php

namespace App\Filament\Pages;

use App\Domain\Returns\Services\ReturnSettings;
use App\Domain\System\Services\SettingsRepository;
use App\Filament\Concerns\HasHelpManual;
use BackedEnum;
use Filament\Forms\Components\Textarea;
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

/** Configuración → Devoluciones: plazo, cambios e instrucciones para enviar el producto. */
class ReturnSettingsPage extends Page implements HasForms
{
    use HasHelpManual, InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUturnLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?string $navigationLabel = 'Devoluciones';

    protected static ?string $title = 'Configuración de devoluciones';

    protected static ?string $slug = 'return-settings';

    protected string $view = 'filament.pages.business-settings';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('settings.manage') ?? false;
    }

    public function mount(ReturnSettings $settings): void
    {
        $this->form->fill([
            'enabled' => $settings->enabled(),
            'days' => $settings->days(),
            'allow_exchange' => $settings->allowExchange(),
            'instructions' => $settings->instructions(),
            'policy' => $settings->policy(),
        ]);
    }

    protected function helpManual(): string
    {
        return 'devoluciones';
    }

    protected function getHeaderActions(): array
    {
        return [$this->helpManualAction()];
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('Solicitudes desde la tienda')->columns(2)->schema([
                Toggle::make('enabled')->label('Los clientes pueden pedir devoluciones en línea')->columnSpanFull()
                    ->helperText('Desde Mi cuenta o el enlace del pedido, una vez entregado.'),
                TextInput::make('days')->label('Plazo para pedirla')->suffix('días desde la entrega')->integer()->minValue(1)->maxValue(365)->required()
                    ->helperText('La Ley del Consumidor da 10 días de retracto en compras a distancia, salvo que la tienda lo excluya expresamente. Si das más días, ponlos aquí.'),
                Toggle::make('allow_exchange')->label('Ofrecer cambio por otro producto'),
            ]),
            Section::make('Textos')->schema([
                Textarea::make('instructions')->label('Instrucciones para enviar el producto')->rows(4)->maxLength(2000)
                    ->helperText('Se proponen al aprobar cada devolución y se pueden ajustar. Ej.: dirección de la bodega, horario, si el envío lo paga la tienda.'),
                Textarea::make('policy')->label('Política de devoluciones (resumen para el cliente)')->rows(4)->maxLength(2000)
                    ->helperText('Se muestra en el formulario de devolución de la tienda.'),
            ]),
        ]);
    }

    public function save(SettingsRepository $settings): void
    {
        abort_unless(static::canAccess(), 403);
        $state = $this->form->getState();
        $settings->putMany('returns', [
            'enabled' => (bool) $state['enabled'],
            'days' => (int) $state['days'],
            'allow_exchange' => (bool) $state['allow_exchange'],
            'instructions' => $state['instructions'] ?: null,
            'policy' => $state['policy'] ?: null,
        ]);
        Notification::make()->title('Configuración de devoluciones guardada')->success()->send();
    }
}
