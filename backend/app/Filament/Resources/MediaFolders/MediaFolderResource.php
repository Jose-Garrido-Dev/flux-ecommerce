<?php

namespace App\Filament\Resources\MediaFolders;

use App\Domain\Content\Models\MediaFolder;
use App\Filament\Resources\MediaFolders\Pages\ManageMediaFolders;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Carpetas de la biblioteca (Contenido → Biblioteca → Carpetas). */
class MediaFolderResource extends Resource
{
    protected static ?string $model = MediaFolder::class;

    protected static ?string $slug = 'media-folders';

    protected static bool $shouldRegisterNavigation = false;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'carpeta';

    protected static ?string $pluralModelLabel = 'Carpetas de la biblioteca';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nombre')->required()->maxLength(120),
            Select::make('parent_id')->label('Dentro de')->placeholder('Ninguna (carpeta principal)')
                ->options(fn (?MediaFolder $record) => MediaFolder::query()->whereNull('parent_id')->when($record, fn ($q) => $q->whereKeyNot($record->id))->orderBy('name')->pluck('name', 'id')->all()),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('parent')->withCount('media'))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Carpeta')->state(fn (MediaFolder $record) => $record->path())->searchable(['name']),
                TextColumn::make('media_count')->label('Imágenes'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->modalDescription('Las imágenes no se borran: quedan sin carpeta.'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageMediaFolders::route('/')];
    }
}
