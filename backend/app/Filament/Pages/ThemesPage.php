<?php

namespace App\Filament\Pages;

use App\Domain\Content\Models\Theme;
use App\Domain\Content\Services\ThemePresets;
use App\Domain\Content\Services\ThemeService;
use App\Filament\Concerns\HasHelpManual;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use UnitEnum;

/** Contenido → Temas: elegir el tema de la tienda y ajustar sus colores principales. */
class ThemesPage extends Page implements HasForms
{
    use HasHelpManual, InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    protected static string|UnitEnum|null $navigationGroup = 'Contenido';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Temas';

    protected static ?string $title = 'Temas de la tienda';

    protected static ?string $slug = 'themes';

    protected string $view = 'filament.pages.themes';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('content.update') ?? false;
    }

    public function mount(ThemeService $themes): void
    {
        $themes->syncPresets();
        $this->fillOverrides();
    }

    protected function helpManual(): string
    {
        return 'temas';
    }

    protected function getHeaderActions(): array
    {
        return [$this->helpManualAction()];
    }

    /** @return Collection<int, Theme> */
    public function getThemes(): Collection
    {
        return Theme::query()->orderByDesc('is_active')->orderBy('id')->get();
    }

    public function activeTheme(): ?Theme
    {
        return Theme::active();
    }

    /** Muestras de color para la tarjeta de cada tema. */
    public function swatches(Theme $theme): array
    {
        $t = app(ThemeService::class)->tokens($theme)['light'];

        return [$t['bg'], $t['fg'], $t['accent'], $t['field'], $t['sale']];
    }

    public function fontLabel(Theme $theme): string
    {
        return ThemePresets::FONTS[$theme->tokens['font_display'] ?? 'brand'] ?? '';
    }

    public function activateAction(): Action
    {
        return Action::make('activate')->label('Usar este tema')->size('sm')
            ->authorize(fn () => auth()->user()?->can('content.publish'))
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments) => 'Usar el tema '.(Theme::query()->find($arguments['theme'] ?? 0)?->name ?? ''))
            ->modalDescription('La tienda cambia de colores, tipografía de títulos y forma de los bordes en unos segundos. Los productos y páginas no cambian.')
            ->modalSubmitActionLabel('Usar este tema')
            ->action(function (array $arguments) {
                $theme = Theme::query()->findOrFail($arguments['theme'] ?? 0);
                app(ThemeService::class)->activate($theme);
                $this->fillOverrides();
                Notification::make()->success()->title("Tema {$theme->name} activado")->send();
            });
    }

    public function form(Schema $schema): Schema
    {
        $color = fn (string $key, string $label) => ColorPicker::make(str_replace('.', '__', $key))->label($label)->hex()
            ->regex('/^#[0-9a-fA-F]{6}$/')->placeholder(fn () => $this->baseColor($key));

        return $schema->statePath('data')->components([
            Section::make('Ajustar colores del tema activo')
                ->description('Opcional. Vacío = el color del tema. Al guardar se revisa que el texto se lea bien (contraste mínimo 4,5).')
                ->schema([
                    Grid::make(2)->schema([
                        Section::make('Modo claro')->compact()->columns(2)->schema([
                            $color('accent', 'Color principal (botones y enlaces)'),
                            $color('accent-fg', 'Texto sobre el color principal'),
                            $color('field', 'Fondo del titular de la portada'),
                            $color('field-fg', 'Texto del titular de la portada'),
                        ]),
                        Section::make('Modo oscuro')->compact()->columns(2)->schema([
                            $color('dark.accent', 'Color principal'),
                            $color('dark.accent-fg', 'Texto sobre el color principal'),
                        ]),
                    ]),
                ]),
        ]);
    }

    private function baseColor(string $key): ?string
    {
        $tokens = $this->activeTheme()?->tokens;

        return str_starts_with($key, 'dark.') ? ($tokens['dark'][substr($key, 5)] ?? null) : ($tokens['light'][$key] ?? null);
    }

    private function fillOverrides(): void
    {
        $overrides = $this->activeTheme()?->overrides ?? [];
        $this->form->fill(collect(ThemeService::OVERRIDABLE)->mapWithKeys(fn ($k) => [str_replace('.', '__', $k) => $overrides[$k] ?? null])->all());
    }

    public function save(): void
    {
        $theme = $this->activeTheme();
        if (! $theme || ! auth()->user()?->can('content.publish')) {
            return;
        }
        $state = $this->form->getState();
        $overrides = collect(ThemeService::OVERRIDABLE)->mapWithKeys(fn ($k) => [$k => $state[str_replace('.', '__', $k)] ?? null])->all();
        try {
            app(ThemeService::class)->saveOverrides($theme, $overrides);
        } catch (ValidationException $e) {
            Notification::make()->danger()->title('No se guardaron los colores')->body(collect($e->errors())->flatten()->implode(' '))->persistent()->send();

            return;
        }
        Notification::make()->success()->title('Colores guardados')->body('La tienda se actualiza en unos segundos.')->send();
    }

    public function resetOverrides(): void
    {
        if ($theme = $this->activeTheme()) {
            app(ThemeService::class)->saveOverrides($theme, []);
            $this->fillOverrides();
            Notification::make()->success()->title('Se volvió a los colores del tema')->send();
        }
    }
}
