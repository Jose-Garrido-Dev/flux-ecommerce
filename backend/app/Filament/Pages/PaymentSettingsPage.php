<?php

namespace App\Filament\Pages;

use App\Domain\Orders\Services\PaymentSettings;
use App\Domain\System\Services\SettingsRepository;
use App\Filament\Concerns\HasHelpManual;
use App\Support\Rut;
use BackedEnum;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** Configuración → Pagos. Fase 5: transferencia bancaria. Las pasarelas llegan en la Fase 6. */
class PaymentSettingsPage extends Page implements HasForms
{
    use HasHelpManual, InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?string $navigationLabel = 'Pagos';

    protected static ?string $title = 'Medios de pago';

    protected static ?string $slug = 'payment-settings';

    protected string $view = 'filament.pages.business-settings';

    public const FIELDS = ['bank_transfer_enabled', 'bank_name', 'account_type', 'account_number', 'account_holder', 'account_rut', 'account_email', 'bank_transfer_instructions', 'bank_transfer_hours'];

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('settings.manage') ?? false;
    }

    public function mount(SettingsRepository $settings): void
    {
        $this->form->fill([
            'bank_transfer_hours' => PaymentSettings::DEFAULT_HOURS,
            ...array_intersect_key($settings->group('payments'), array_flip(self::FIELDS)),
        ]);
    }

    protected function helpManual(): string
    {
        return 'configuracion-pagos';
    }

    protected function getHeaderActions(): array
    {
        return [$this->helpManualAction()];
    }

    public function form(Schema $schema): Schema
    {
        $enabled = fn (Get $get) => (bool) $get('bank_transfer_enabled');

        return $schema->statePath('data')->components([
            Section::make('Transferencia bancaria')
                ->description('El cliente ve estos datos al terminar la compra y en el correo del pedido. El pedido queda pendiente hasta que confirmes el pago en Ventas → Pedidos.')
                ->columns(2)->schema([
                    Toggle::make('bank_transfer_enabled')->label('Ofrecer transferencia bancaria')->live()->columnSpanFull(),
                    TextInput::make('bank_name')->label('Banco')->maxLength(80)->required($enabled)->placeholder('Banco de Chile'),
                    Select::make('account_type')->label('Tipo de cuenta')->required($enabled)
                        ->options(['Cuenta corriente' => 'Cuenta corriente', 'Cuenta vista' => 'Cuenta vista', 'Cuenta RUT' => 'Cuenta RUT', 'Cuenta de ahorro' => 'Cuenta de ahorro']),
                    TextInput::make('account_number')->label('N.º de cuenta')->maxLength(30)->required($enabled),
                    TextInput::make('account_holder')->label('Titular')->maxLength(120)->required($enabled),
                    TextInput::make('account_rut')->label('RUT del titular')->maxLength(12)->required($enabled)
                        ->rule(fn () => function (string $attribute, mixed $value, Closure $fail) {
                            if (filled($value) && ! Rut::isValid((string) $value)) {
                                $fail('El RUT no es válido.');
                            }
                        })
                        ->dehydrateStateUsing(fn (?string $state) => filled($state) ? Rut::format($state) : null),
                    TextInput::make('account_email')->label('Correo para enviar el comprobante')->email()->maxLength(190),
                    TextInput::make('bank_transfer_hours')->label('Plazo para pagar')->suffix('horas')->integer()->minValue(1)->maxValue(168)->required()
                        ->helperText('El stock queda reservado durante este plazo. Si no se confirma el pago, el pedido se anula solo.'),
                    Textarea::make('bank_transfer_instructions')->label('Instrucciones adicionales')->maxLength(500)->rows(2)->columnSpanFull()
                        ->placeholder('Envía el comprobante indicando tu número de pedido.'),
                ]),
            Section::make('Pasarelas de pago')->schema([
                Text::make('Webpay Plus, Mercado Pago, Flow y Khipu se configuran en la Fase 6. Ninguna está verificada todavía.'),
            ]),
        ]);
    }

    public function save(SettingsRepository $settings): void
    {
        abort_unless(static::canAccess(), 403);

        $state = $this->form->getState();
        $settings->putMany('payments', [
            ...array_intersect_key($state, array_flip(self::FIELDS)),
            'bank_transfer_enabled' => (bool) ($state['bank_transfer_enabled'] ?? false),
            'bank_transfer_hours' => (int) $state['bank_transfer_hours'],
        ]);

        Notification::make()->title('Medios de pago guardados')->success()->send();
    }
}
