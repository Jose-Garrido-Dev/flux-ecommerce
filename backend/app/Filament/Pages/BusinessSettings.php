<?php

namespace App\Filament\Pages;

use App\Domain\Shipping\Models\Region;
use App\Domain\System\Services\SettingsRepository;
use App\Filament\Concerns\HasHelpManual;
use App\Support\Rut;
use BackedEnum;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** Configuración → Negocio (módulo 24, pestaña A; módulo 18.3). */
class BusinessSettings extends Page implements HasForms
{
    use HasHelpManual, InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?string $navigationLabel = 'Datos del negocio';

    protected static ?string $title = 'Datos del negocio';

    protected string $view = 'filament.pages.business-settings';

    public const FIELDS = [
        'store_name', 'tagline', 'announcement', 'legal_name', 'rut', 'business_activity', 'address',
        'region_code', 'commune', 'contact_email', 'contact_phone', 'business_hours',
        'instagram', 'facebook', 'tiktok',
    ];

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('settings.manage') ?? false;
    }

    public function mount(SettingsRepository $settings): void
    {
        $this->form->fill($settings->group('business'));
    }

    protected function helpManual(): string
    {
        return 'datos-del-negocio';
    }

    protected function getHeaderActions(): array
    {
        return [$this->helpManualAction()];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Tienda')->columns(2)->schema([
                    TextInput::make('store_name')->label('Nombre de la tienda')->required()->maxLength(80),
                    TextInput::make('tagline')->label('Frase breve')->maxLength(140)
                        ->helperText('Se muestra como titular de la portada.'),
                    TextInput::make('announcement')->label('Barra de anuncios')->maxLength(300)->columnSpanFull()
                        ->placeholder('Despacho a todo Chile | 3 cuotas sin interés')
                        ->helperText('Mensajes que se muestran sobre el menú de la tienda. Separa varios con | . Déjalo vacío para ocultar la barra.'),
                ]),
                Section::make('Datos legales')->columns(2)->schema([
                    TextInput::make('legal_name')->label('Razón social')->maxLength(150),
                    TextInput::make('rut')->label('RUT')->maxLength(12)->placeholder('76.123.456-7')
                        ->rule(fn () => function (string $attribute, mixed $value, Closure $fail) {
                            if (filled($value) && ! Rut::isValid((string) $value)) {
                                $fail('El RUT no es válido.');
                            }
                        })
                        ->dehydrateStateUsing(fn (?string $state) => filled($state) ? Rut::format($state) : null),
                    TextInput::make('business_activity')->label('Giro')->maxLength(150),
                    TextInput::make('address')->label('Dirección')->maxLength(200),
                    Select::make('region_code')->label('Región')
                        ->options(fn () => Region::query()->orderBy('position')->pluck('name', 'code')),
                    TextInput::make('commune')->label('Comuna')->maxLength(80)
                        ->helperText('Será un selector cuando se cargue el catálogo oficial de comunas.'),
                ]),
                Section::make('Contacto y redes')->columns(2)->schema([
                    TextInput::make('contact_email')->label('Correo de contacto')->email()->maxLength(190),
                    TextInput::make('contact_phone')->label('Teléfono')->tel()->maxLength(30),
                    TextInput::make('business_hours')->label('Horario de atención')->maxLength(120)
                        ->placeholder('Lunes a viernes 9:00 a 18:00'),
                    TextInput::make('instagram')->label('Instagram (URL)')->url()->maxLength(200),
                    TextInput::make('facebook')->label('Facebook (URL)')->url()->maxLength(200),
                    TextInput::make('tiktok')->label('TikTok (URL)')->url()->maxLength(200),
                ]),
            ]);
    }

    public function save(SettingsRepository $settings): void
    {
        abort_unless(static::canAccess(), 403);

        $state = $this->form->getState();
        $settings->putMany('business', array_intersect_key($state, array_flip(self::FIELDS)));

        Notification::make()->title('Datos del negocio guardados')->success()->send();
    }
}
