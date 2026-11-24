<?php

namespace App\Filament\Resources\BlogCategories;

use App\Domain\Content\Models\BlogCategory;
use App\Filament\Resources\BlogCategories\Pages\ManageBlogCategories;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

/** Categorías del blog. Se administran desde el listado de artículos (no aparecen en el menú). */
class BlogCategoryResource extends Resource
{
    protected static ?string $model = BlogCategory::class;

    protected static string|UnitEnum|null $navigationGroup = 'Contenido';

    protected static bool $shouldRegisterNavigation = false;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'categoría del blog';

    protected static ?string $pluralModelLabel = 'Categorías del blog';

    protected static ?string $slug = 'blog-categories';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label('Nombre')->required()->maxLength(80)
                ->live(onBlur: true)
                ->afterStateUpdated(function (?string $state, Set $set, string $operation) {
                    if ($operation === 'create') {
                        $set('slug', Str::slug((string) $state));
                    }
                }),
            TextInput::make('slug')->label('URL amigable')->required()->maxLength(100)->alphaDash()->unique(ignoreRecord: true)
                ->helperText('Se usa en /blog?categoria={slug}'),
            TextInput::make('position')->label('Orden')->numeric()->integer()->minValue(0)->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('posts'))
            ->defaultSort('position')
            ->columns([
                TextColumn::make('name')->label('Nombre')->searchable()->sortable(),
                TextColumn::make('slug')->label('URL'),
                TextColumn::make('posts_count')->label('Artículos')->alignEnd(),
                TextColumn::make('position')->label('Orden')->sortable()->alignEnd(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->visible(fn (BlogCategory $c) => $c->posts_count === 0)
                    ->modalDescription('Solo se pueden eliminar categorías sin artículos.'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageBlogCategories::route('/')];
    }
}
