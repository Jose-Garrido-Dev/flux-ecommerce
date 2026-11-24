<?php

namespace App\Filament\Resources\Menus;

use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Category;
use App\Domain\Content\Models\Menu;
use App\Domain\Content\Models\MenuItem;
use App\Domain\Content\Models\Page;
use App\Domain\Content\Services\BlockSchema;
use App\Filament\Resources\Menus\Pages\EditMenu;
use App\Filament\Resources\Menus\Pages\ListMenus;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** Contenido → Menús: el menú principal y el del pie de página, con enlaces a categorías, marcas o páginas. */
class MenuResource extends Resource
{
    protected static ?string $model = Menu::class;

    protected static ?string $slug = 'menus';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBars3;

    protected static string|UnitEnum|null $navigationGroup = 'Contenido';

    protected static ?int $navigationSort = 2;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'menú';

    protected static ?string $pluralModelLabel = 'Menús';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Enlaces')
                ->description('Arrastra para ordenar. En el menú principal cada enlace puede tener un submenú. Si un enlace apunta a algo que ya no está visible (una categoría inactiva o una página sin publicar), la tienda lo omite.')
                ->schema([
                    Repeater::make('items')->hiddenLabel()->label('Enlaces')
                        ->schema([...self::linkFields(), Repeater::make('children')->label('Submenú')->schema(self::linkFields())->columns(3)
                            ->visible(fn ($livewire) => ($livewire->record->location ?? null) === 'header')->collapsed()->maxItems(20)->addActionLabel('Agregar al submenú')->columnSpanFull()
                            ->itemLabel(fn (array $state) => $state['label'] ?? null)])
                        ->columns(3)->maxItems(20)->addActionLabel('Agregar enlace')->collapsible()->reorderableWithButtons()
                        ->itemLabel(fn (array $state) => $state['label'] ?? null),
                ]),
        ]);
    }

    public static function linkFields(): array
    {
        return [
            TextInput::make('label')->label('Texto')->required()->maxLength(80),
            Select::make('link_type')->label('Lleva a')->options(MenuItem::LINK_TYPES)->required()->native(false)->live()->default('category'),
            Select::make('link_target')->label('Destino')->searchable()->required()
                ->visible(fn (Get $get) => in_array($get('link_type'), ['category', 'brand', 'page'], true))
                ->options(fn (Get $get) => match ($get('link_type')) {
                    'category' => Category::query()->orderBy('name')->pluck('name', 'id')->all(),
                    'brand' => Brand::query()->orderBy('name')->pluck('name', 'id')->all(),
                    'page' => Page::query()->where('type', '!=', 'home')->orderBy('title')->get()
                        ->mapWithKeys(fn (Page $p) => [$p->id => $p->title.($p->status === 'published' ? '' : ' (sin publicar)')])->all(),
                    default => [],
                }),
            TextInput::make('url')->label('Dirección')->required()->maxLength(500)->placeholder('/catalogo?on_sale=1 o https://…')
                ->visible(fn (Get $get) => $get('link_type') === 'url')
                ->rule(fn () => function (string $attribute, $value, \Closure $fail) {
                    if (! BlockSchema::validLink((string) $value)) {
                        $fail('Usa una ruta de la tienda que empiece con / o una dirección https://.');
                    }
                }),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('items'))
            ->columns([
                TextColumn::make('name')->label('Menú')->description(fn (Menu $record) => Menu::LOCATIONS[$record->location] ?? null),
                TextColumn::make('items_count')->label('Enlaces'),
                TextColumn::make('updated_at')->label('Modificado')->dateTime('d-m-Y H:i'),
            ])
            ->recordActions([EditAction::make()])
            ->paginated(false);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMenus::route('/'),
            'edit' => EditMenu::route('/{record}/edit'),
        ];
    }
}
