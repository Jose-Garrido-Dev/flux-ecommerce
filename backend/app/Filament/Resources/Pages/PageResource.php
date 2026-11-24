<?php

namespace App\Filament\Resources\Pages;

use App\Domain\Content\Models\Page;
use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Filament\Resources\Pages\Pages\ListPages;
use App\Filament\Resources\Pages\RelationManagers\VersionsRelationManager;
use App\Filament\Support\BlockForm;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use UnitEnum;

/** Contenido → Páginas: portada, landings y páginas institucionales hechas con bloques. */
class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static ?string $slug = 'pages';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static string|UnitEnum|null $navigationGroup = 'Contenido';

    protected static ?int $navigationSort = 1;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'página';

    protected static ?string $pluralModelLabel = 'Páginas';

    protected static ?string $recordTitleAttribute = 'title';

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->with(['latestVersion', 'publishedVersion']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Página')->columns(3)->schema([
                TextInput::make('title')->label('Título')->required()->maxLength(160)->live(onBlur: true)
                    ->afterStateUpdated(function (?string $state, Get $get, Set $set, ?Page $record) {
                        if (! $record && blank($get('slug')) && $get('type') !== 'home') {
                            $set('slug', Str::slug((string) $state));
                        }
                    }),
                Select::make('type')->label('Tipo')->options(Page::TYPES)->required()->default('institutional')->native(false)->live()
                    ->helperText('Solo puede haber una portada.'),
                TextInput::make('slug')->label('Dirección')->prefix('/')->maxLength(160)
                    ->hidden(fn (Get $get) => $get('type') === 'home')
                    ->required(fn (Get $get) => $get('type') !== 'home')
                    ->helperText('Letras minúsculas, números y guiones. Ej.: nosotros'),
            ]),
            Section::make('Buscadores y redes')->columns(2)->collapsed()->schema([
                TextInput::make('seo_title')->label('Título para buscadores')->maxLength(160)->helperText('Vacío = el título de la página.'),
                Textarea::make('seo_description')->label('Descripción para buscadores')->maxLength(320)->rows(2),
            ]),
            Section::make('Contenido')->schema([BlockForm::builder()])
                ->visibleOn('edit'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['latestVersion', 'editor']))
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('title')->label('Título')->searchable()->sortable()
                    ->description(fn (Page $record) => $record->storefrontPath()),
                TextColumn::make('type')->label('Tipo')->badge()->color('gray')->formatStateUsing(fn (string $state) => Page::TYPES[$state] ?? $state),
                TextColumn::make('status')->label('Estado')->badge()
                    ->formatStateUsing(fn (Page $record) => $record->status === 'published'
                        ? ($record->hasUnpublishedChanges() ? 'Publicada, con cambios sin publicar' : 'Publicada') : 'Borrador')
                    ->color(fn (Page $record) => $record->status === 'published' ? ($record->hasUnpublishedChanges() ? 'warning' : 'success') : 'gray'),
                TextColumn::make('latestVersion.number')->label('Versión')->prefix('v'),
                TextColumn::make('updated_at')->label('Modificada')->dateTime('d-m-Y H:i')->sortable()
                    ->description(fn (Page $record) => $record->editor?->name),
            ])
            ->filters([
                SelectFilter::make('type')->label('Tipo')->options(Page::TYPES),
                SelectFilter::make('status')->label('Estado')->options(Page::STATUSES),
            ])
            ->recordActions([EditAction::make()])
            ->emptyStateHeading('Aún no hay páginas')
            ->emptyStateDescription('Crea la portada o una página como "Nosotros" o "Preguntas frecuentes".');
    }

    public static function getRelations(): array
    {
        return [VersionsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPages::route('/'),
            'create' => CreatePage::route('/create'),
            'edit' => EditPage::route('/{record}/edit'),
        ];
    }
}
