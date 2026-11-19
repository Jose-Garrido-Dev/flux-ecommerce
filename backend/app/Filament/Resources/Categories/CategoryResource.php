<?php

namespace App\Filament\Resources\Categories;

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Filament\Forms\MediaUpload;
use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Categories\Pages\ListCategories;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use UnitEnum;

/** Categorías en árbol (módulo 3). Hasta 4 niveles; las jerarquías circulares se rechazan. */
class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Catálogo';

    protected static ?int $navigationSort = 20;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'categoría';

    protected static ?string $pluralModelLabel = 'Categorías';

    protected static ?string $recordTitleAttribute = 'name';

    /** @return array<int, string> id => "Ropa › Poleras", excluyendo la categoría y sus descendientes */
    public static function parentOptions(?Category $except = null): array
    {
        $all = Category::query()->orderBy('depth')->orderBy('position')->orderBy('name')->get(['id', 'name', 'parent_id', 'path', 'depth']);
        $byId = $all->keyBy('id');
        $prefix = $except ? $except->path.$except->id.'/' : null;

        return $all
            ->reject(fn (Category $c) => $except && ($c->id === $except->id || str_starts_with($c->path, $prefix)))
            ->reject(fn (Category $c) => $c->depth >= Category::MAX_DEPTH - 1)
            ->mapWithKeys(function (Category $c) use ($byId) {
                $names = collect(explode('/', trim($c->path, '/')))->filter()->map(fn ($id) => $byId[$id]->name ?? '?');

                return [$c->id => $names->push($c->name)->implode(' › ')];
            })
            ->sort()
            ->all();
    }

    /** @return array<int, string> todas las categorías: id => "Ropa › Poleras" */
    public static function allOptions(): array
    {
        $all = Category::query()->get(['id', 'name', 'path']);
        $byId = $all->keyBy('id');

        return $all->mapWithKeys(function (Category $c) use ($byId) {
            $names = collect(explode('/', trim($c->path, '/')))->filter()->map(fn ($id) => $byId[$id]->name ?? '?');

            return [$c->id => $names->push($c->name)->implode(' › ')];
        })->sort()->all();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('Datos')->columnSpan(2)->columns(2)->schema([
                TextInput::make('name')->label('Nombre')->required()->maxLength(120)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (?string $state, Set $set, string $operation) {
                        if ($operation === 'create') {
                            $set('slug', Str::slug((string) $state));
                        }
                    }),
                TextInput::make('slug')->label('URL amigable')->required()->maxLength(160)->alphaDash()
                    ->unique(ignoreRecord: true)->helperText('/categoria/{slug}'),
                Select::make('parent_id')->label('Categoría superior')
                    ->options(fn (?Category $record) => self::parentOptions($record))
                    ->searchable()
                    ->placeholder('Ninguna (categoría principal)')
                    ->rules([fn (?Category $record) => function (string $attribute, $value, Closure $fail) use ($record) {
                        if (! $value) {
                            return;
                        }
                        if (! array_key_exists((int) $value, self::parentOptions($record))) {
                            $fail('No se puede elegir esa categoría: crearía un ciclo o superaría '.Category::MAX_DEPTH.' niveles.');
                        }
                    }])
                    ->helperText('Máximo '.Category::MAX_DEPTH.' niveles. No puedes elegir la misma categoría ni una de sus subcategorías.'),
                TextInput::make('position')->label('Orden')->numeric()->minValue(0)->default(0),
                Textarea::make('description')->label('Descripción')->rows(3)->columnSpanFull(),
            ]),
            Section::make('Visibilidad')->columnSpan(1)->schema([
                Select::make('status')->label('Estado')->options(Category::STATUSES)->default('active')->required()
                    ->helperText('Desactivada: no se muestra en la tienda, ni sus subcategorías.'),
                Toggle::make('in_menu')->label('Aparece en el menú')->default(true),
                Toggle::make('on_home')->label('Aparece en la portada'),
            ]),
            Section::make('Imágenes y estilo')->columnSpan(2)->columns(2)->schema([
                MediaUpload::make('image_media_id', 'Imagen', 'categories')
                    ->helperText('Se usa en la portada y en el menú (recomendado 1200 x 900).'),
                MediaUpload::make('banner_media_id', 'Banner', 'categories')
                    ->helperText('Cabecera de la página de la categoría (recomendado 1920 x 600).'),
                TextInput::make('icon')->label('Ícono')->maxLength(64)
                    ->helperText('Nombre de un ícono Heroicons, por ejemplo shopping-bag. Opcional.'),
                ColorPicker::make('color')->label('Color')->regex('/^#[0-9a-fA-F]{6}$/'),
            ]),
            Section::make('SEO')->columnSpan(1)->schema([
                TextInput::make('seo_title')->label('Metatítulo')->maxLength(70)->helperText('Vacío = nombre de la categoría.'),
                Textarea::make('seo_description')->label('Metadescripción')->maxLength(160)->rows(3),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount(['products', 'children']))
            ->defaultSort(fn (Builder $query) => $query->orderBy('depth')->orderBy('position')->orderBy('name'))
            ->reorderable('position')
            ->columns([
                TextColumn::make('name')->label('Nombre')->searchable()->sortable()
                    ->description(fn (Category $c) => $c->parent_id ? 'en '.(once(fn () => self::parentOptions())[$c->parent_id] ?? '…') : 'Categoría principal'),
                TextColumn::make('products_count')->label('Productos')->alignEnd()->sortable(),
                TextColumn::make('children_count')->label('Subcategorías')->alignEnd(),
                IconColumn::make('in_menu')->label('Menú')->boolean(),
                IconColumn::make('on_home')->label('Portada')->boolean(),
                TextColumn::make('status')->label('Estado')->badge()
                    ->formatStateUsing(fn (string $state) => Category::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => $state === 'active' ? 'success' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('parent_id')->label('Dentro de')
                    ->options(fn () => ['root' => '(Categorías principales)'] + self::parentOptions())
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        null, '' => $query,
                        'root' => $query->whereNull('parent_id'),
                        default => $query->where('parent_id', $data['value']),
                    }),
                SelectFilter::make('status')->label('Estado')->options(Category::STATUSES),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('moveProducts')
                    ->label('Mover productos')
                    ->icon(Heroicon::OutlinedArrowsRightLeft)
                    ->color('gray')
                    ->visible(fn (Category $c) => $c->products_count > 0)
                    ->authorize(fn () => auth()->user()->can('products.update'))
                    ->schema(fn (Category $record) => [
                        Select::make('target')->label('Mover a')->required()->searchable()
                            ->options(collect(self::parentOptions())->except($record->id)->all() + self::leafOptions($record)),
                    ])
                    ->modalDescription('Los productos de esta categoría pasan a la categoría elegida (como principal y en su listado).')
                    ->action(function (Category $record, array $data) {
                        $moved = self::moveProducts($record, (int) $data['target']);
                        Notification::make()->success()->title("$moved productos movidos")->send();
                    }),
                Action::make('toggleStatus')
                    ->label(fn (Category $c) => $c->status === 'active' ? 'Desactivar' : 'Activar')
                    ->icon(fn (Category $c) => $c->status === 'active' ? Heroicon::OutlinedEyeSlash : Heroicon::OutlinedEye)
                    ->color('gray')
                    ->requiresConfirmation()
                    ->authorize(fn () => auth()->user()->can('categories.archive'))
                    ->action(fn (Category $c) => $c->update(['status' => $c->status === 'active' ? 'inactive' : 'active'])),
                DeleteAction::make()
                    ->visible(fn (Category $c) => $c->products_count === 0 && $c->children_count === 0)
                    ->modalDescription('Solo se eliminan categorías sin productos ni subcategorías.'),
            ]);
    }

    /** Categorías del último nivel (no aparecen en parentOptions porque no admiten hijos). */
    private static function leafOptions(Category $except): array
    {
        return Category::query()->where('depth', Category::MAX_DEPTH - 1)->whereKeyNot($except->id)->get()
            ->mapWithKeys(fn (Category $c) => [$c->id => $c->fullName()])->all();
    }

    public static function moveProducts(Category $from, int $toId): int
    {
        $to = Category::query()->findOrFail($toId);

        return DB::transaction(function () use ($from, $to) {
            $ids = $from->products()->pluck('products.id');
            Product::query()->where('primary_category_id', $from->id)->update(['primary_category_id' => $to->id]);
            $to->products()->syncWithoutDetaching($ids->all());
            $from->products()->detach($ids->all());

            return $ids->count();
        });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCategories::route('/'),
            'create' => CreateCategory::route('/create'),
            'edit' => EditCategory::route('/{record}/edit'),
        ];
    }
}
