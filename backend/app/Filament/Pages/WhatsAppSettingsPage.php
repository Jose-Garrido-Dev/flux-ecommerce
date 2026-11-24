<?php

namespace App\Filament\Pages;

use App\Domain\Content\Jobs\RevalidateStorefront;
use App\Domain\Marketing\Services\WhatsAppSettings;
use App\Domain\System\Services\SettingsRepository;
use App\Filament\Concerns\HasHelpManual;
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
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Marketing → WhatsApp: botón flotante que abre una conversación (enlace wa.me).
 * No usa la API de WhatsApp Business: no envía mensajes automáticos ni requiere cuenta empresarial.
 */
class WhatsAppSettingsPage extends Page implements HasForms
{
    use HasHelpManual, InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'WhatsApp';

    protected static ?string $title = 'Botón de WhatsApp';

    protected static ?string $slug = 'whatsapp';

    protected string $view = 'filament.pages.business-settings';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('marketing.update') ?? false;
    }

    public function mount(SettingsRepository $settings): void
    {
        $s = $settings->group('whatsapp');
        $this->form->fill([
            'enabled' => (bool) ($s['enabled'] ?? false),
            'phone' => $s['phone'] ?? null,
            'label' => $s['label'] ?? 'Escríbenos por WhatsApp',
            'message' => $s['message'] ?? 'Hola, tengo una consulta.',
            'product_message' => $s['product_message'] ?? 'Hola, me interesa {producto}: {enlace}',
            'position' => $s['position'] ?? 'right',
            'pages' => $s['pages'] ?? 'all',
        ]);
    }

    protected function helpManual(): string
    {
        return 'whatsapp';
    }

    protected function getHeaderActions(): array
    {
        return [$this->helpManualAction()];
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('Botón en la tienda')->columns(2)->schema([
                Toggle::make('enabled')->label('Mostrar el botón de WhatsApp')->live()->columnSpanFull(),
                TextInput::make('phone')->label('Número de WhatsApp')->placeholder('+56 9 1234 5678')->maxLength(25)
                    ->required(fn (Get $get) => (bool) $get('enabled'))
                    ->rule(fn () => function (string $attribute, $value, Closure $fail) {
                        if (filled($value) && ! WhatsAppSettings::isValidPhone($value)) {
                            $fail('Escribe el número con código de país, por ejemplo +56 9 1234 5678.');
                        }
                    })
                    ->helperText('Con código de país. Un celular chileno de 9 dígitos se completa con +56.'),
                TextInput::make('label')->label('Texto del botón')->maxLength(40),
                Select::make('position')->label('Posición')->options(WhatsAppSettings::POSITIONS)->required(),
                Select::make('pages')->label('Dónde se muestra')->options(WhatsAppSettings::PAGES)->required(),
            ]),
            Section::make('Mensaje inicial')->schema([
                Textarea::make('message')->label('Mensaje general')->rows(2)->maxLength(300)
                    ->helperText('Lo que aparece escrito al abrir WhatsApp; el cliente puede cambiarlo antes de enviar.'),
                Textarea::make('product_message')->label('Mensaje desde una ficha de producto')->rows(2)->maxLength(300)
                    ->helperText('Puedes usar {producto} y {enlace}.'),
            ]),
        ]);
    }

    public function save(SettingsRepository $settings): void
    {
        abort_unless(static::canAccess(), 403);
        $state = $this->form->getState();
        $settings->putMany('whatsapp', [
            'enabled' => (bool) $state['enabled'],
            'phone' => filled($state['phone']) ? '+'.WhatsAppSettings::digits($state['phone']) : null,
            'label' => $state['label'] ?: null,
            'message' => $state['message'] ?: null,
            'product_message' => $state['product_message'] ?: null,
            'position' => $state['position'],
            'pages' => $state['pages'],
        ]);
        RevalidateStorefront::dispatch(['store']);
        Notification::make()->title('Botón de WhatsApp guardado')->success()->send();
    }
}
