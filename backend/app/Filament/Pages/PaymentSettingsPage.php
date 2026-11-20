<?php

namespace App\Filament\Pages;

use App\Domain\Orders\Services\PaymentSettings;
use App\Domain\Payments\Contracts\PaymentGatewayDriver;
use App\Domain\Payments\Models\PaymentGateway;
use App\Domain\Payments\Services\GatewayRegistry;
use App\Domain\Payments\Services\GatewayVerifier;
use App\Domain\System\Services\SettingsRepository;
use App\Filament\Concerns\HasHelpManual;
use App\Support\Rut;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
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

/**
 * Configuración → Pagos: transferencia bancaria y pasarelas (Fase 6: Webpay Plus).
 * Una pasarela solo aparece en el checkout si está activa y su conexión se probó con éxito
 * con las mismas credenciales y el mismo ambiente que tiene guardados.
 */
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
        $webpay = $this->webpay();
        $this->form->fill([
            'bank_transfer_hours' => PaymentSettings::DEFAULT_HOURS,
            ...array_intersect_key($settings->group('payments'), array_flip(self::FIELDS)),
            'webpay_enabled' => $webpay->enabled,
            'webpay_environment' => $webpay->environment,
            'webpay_commerce_code' => $webpay->credential('commerce_code'),
        ]);
    }

    protected function helpManual(): string
    {
        return 'configuracion-pagos';
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->helpManualAction(),
            Action::make('webpayTestCredentials')->label('Usar credenciales de prueba de Transbank')->icon(Heroicon::OutlinedBeaker)->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Credenciales de prueba de Transbank')
                ->modalDescription('Se cargan el código de comercio y la llave públicos que Transbank entrega para su ambiente de integración. Sirven para probar la tienda: no cobran dinero real. Para vender de verdad necesitas tus credenciales de producción.')
                ->modalSubmitActionLabel('Usar credenciales de prueba')
                ->action(function () {
                    $webpay = $this->webpay();
                    $webpay->environment = 'integration';
                    $webpay->credentials = $this->driver($webpay)->integrationCredentials();
                    $webpay->save();
                    $this->data['webpay_environment'] = 'integration';
                    $this->data['webpay_commerce_code'] = $webpay->credential('commerce_code');
                    Notification::make()->success()->title('Credenciales de prueba cargadas')->body('Ahora usa "Probar conexión con Webpay".')->send();
                }),
            Action::make('webpayVerify')->label('Probar conexión con Webpay')->icon(Heroicon::OutlinedSignal)
                ->requiresConfirmation()
                ->modalHeading('Probar conexión con Webpay')
                ->modalDescription('Se guardan los cambios y se crea una transacción de prueba de $50 en Transbank, sin cobrar. Si funciona, Webpay queda verificada para el ambiente elegido.')
                ->modalSubmitActionLabel('Probar conexión')
                ->action(function (SettingsRepository $settings) {
                    $this->save($settings, notify: false);
                    $webpay = $this->webpay();
                    $error = app(GatewayVerifier::class)->verify($webpay);
                    if ($error !== null) {
                        Notification::make()->danger()->title('La conexión con Webpay falló')->body($error)->persistent()->send();

                        return;
                    }
                    Notification::make()->success()->title('Webpay verificada')
                        ->body($webpay->enabled ? 'Los clientes ya pueden pagar con Webpay.' : 'Activa "Ofrecer Webpay" y guarda para mostrarla en el checkout.')->send();
                }),
        ];
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
            Section::make('Webpay Plus (Transbank)')
                ->description('Pago con tarjetas de débito, crédito y prepago. El cliente paga en el sitio de Transbank y vuelve a la tienda.')
                ->columns(2)->schema([
                    Text::make(fn () => 'Estado: '.$this->webpay()->statusLabel().'.'
                        .($this->webpay()->verified_at && $this->webpay()->isVerified() ? ' Última prueba: '.$this->webpay()->verified_at->timezone('America/Santiago')->format('d-m-Y H:i').'.' : '')
                        .($this->webpay()->last_error ? ' Último error: '.$this->webpay()->last_error : ''))->columnSpanFull(),
                    Toggle::make('webpay_enabled')->label('Ofrecer Webpay en el checkout')->columnSpanFull()
                        ->helperText('Solo aparece a los clientes después de probar la conexión con éxito.'),
                    Select::make('webpay_environment')->label('Ambiente')->options(PaymentGateway::ENVIRONMENTS)->required()->native(false)
                        ->helperText('Integración = pruebas de Transbank, no cobra. Producción = cobros reales.'),
                    TextInput::make('webpay_commerce_code')->label('Código de comercio')->maxLength(20)->regex('/^\d{6,20}$/')
                        ->validationMessages(['regex' => 'El código de comercio son solo números.']),
                    TextInput::make('webpay_api_key')->label('Llave secreta (API Key)')->password()->maxLength(200)->columnSpanFull()
                        ->placeholder(fn () => $this->webpay()->credential('api_key') ? 'Guardada. Déjala vacía para mantenerla.' : 'Pega aquí la llave que te entregó Transbank')
                        ->helperText('Se guarda cifrada y nunca se vuelve a mostrar.'),
                ]),
            Section::make('Otras pasarelas')->schema([
                Text::make('Mercado Pago, Flow y Khipu se agregan con el mismo contrato que Webpay. Ninguna está disponible ni verificada todavía.'),
            ]),
        ]);
    }

    public function save(SettingsRepository $settings, bool $notify = true): void
    {
        abort_unless(static::canAccess(), 403);

        $state = $this->form->getState();
        $settings->putMany('payments', [
            ...array_intersect_key($state, array_flip(self::FIELDS)),
            'bank_transfer_enabled' => (bool) ($state['bank_transfer_enabled'] ?? false),
            'bank_transfer_hours' => (int) $state['bank_transfer_hours'],
        ]);

        // Webpay: si cambian el ambiente o las credenciales, la verificación anterior deja de valer (ver PaymentGateway::isVerified)
        $webpay = $this->webpay();
        $credentials = $webpay->credentials ?? [];
        $credentials['commerce_code'] = filled($state['webpay_commerce_code'] ?? null) ? trim($state['webpay_commerce_code']) : null;
        if (filled($state['webpay_api_key'] ?? null)) {
            $credentials['api_key'] = trim($state['webpay_api_key']);
        }
        $webpay->fill([
            'enabled' => (bool) ($state['webpay_enabled'] ?? false),
            'environment' => $state['webpay_environment'] ?? 'integration',
            'credentials' => array_filter($credentials),
        ])->save();
        $this->data['webpay_api_key'] = null;

        if ($notify) {
            $body = $webpay->enabled && ! $webpay->isVerified() ? 'Webpay no se mostrará a los clientes hasta que pruebes la conexión.' : null;
            Notification::make()->title('Medios de pago guardados')->body($body)->success()->send();
        }
    }

    private function webpay(): PaymentGateway
    {
        return PaymentGateway::for('webpay_plus');
    }

    private function driver(PaymentGateway $gateway): PaymentGatewayDriver
    {
        return app(GatewayRegistry::class)->driver($gateway->code, $gateway);
    }
}
