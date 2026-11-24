<?php

namespace App\Filament\Resources\BlogPosts;

use App\Domain\Content\Models\BlogPost;
use App\Filament\Forms\MediaUpload;
use App\Filament\Resources\BlogPosts\Pages\CreateBlogPost;
use App\Filament\Resources\BlogPosts\Pages\EditBlogPost;
use App\Filament\Resources\BlogPosts\Pages\ListBlogPosts;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

/** Contenido → Blog: artículos en Markdown con imagen destacada, categoría, programación y SEO. */
class BlogPostResource extends Resource
{
    protected static ?string $model = BlogPost::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static string|UnitEnum|null $navigationGroup = 'Contenido';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Blog';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'artículo';

    protected static ?string $pluralModelLabel = 'Artículos del blog';

    protected static ?string $slug = 'blog-posts';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Grid::make(1)->columnSpan(2)->schema([
                Section::make()->schema([
                    TextInput::make('title')->label('Título')->required()->maxLength(160)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (?string $state, Set $set, string $operation) {
                            if ($operation === 'create') {
                                $set('slug', Str::slug((string) $state));
                            }
                        }),
                    TextInput::make('slug')->label('URL amigable')->required()->maxLength(160)->alphaDash()->unique(ignoreRecord: true)
                        ->prefix('/blog/')
                        ->helperText('Si la cambias en un artículo publicado, la dirección antigua redirige a la nueva.'),
                    Textarea::make('excerpt')->label('Resumen')->rows(2)->maxLength(300)
                        ->helperText('Se muestra en el listado del blog y al compartir.'),
                    MarkdownEditor::make('body')->label('Contenido')->required()->maxLength(100000)
                        ->toolbarButtons([['bold', 'italic', 'link'], ['heading', 'bulletList', 'orderedList', 'blockquote', 'table'], ['undo', 'redo']])
                        ->helperText('Formato simple: títulos, negrita, listas, enlaces y tablas. Las imágenes van en la imagen destacada.'),
                ]),
                Section::make('SEO')->collapsed()->columns(2)->schema([
                    TextInput::make('seo_title')->label('Metatítulo')->maxLength(70)->helperText('Si queda vacío se usa el título.'),
                    TextInput::make('seo_description')->label('Metadescripción')->maxLength(160)->helperText('Si queda vacía se usa el resumen.'),
                ]),
            ]),
            Grid::make(1)->columnSpan(1)->schema([
                Section::make('Publicación')->schema([
                    Select::make('status')->label('Estado')->options(BlogPost::STATUSES)->default('draft')->required(),
                    DateTimePicker::make('published_at')->label('Fecha de publicación')->seconds(false)->native(false)->displayFormat('d-m-Y H:i')
                        ->helperText('Una fecha futura deja el artículo programado. Vacía al publicar = ahora.'),
                    Select::make('category_id')->label('Categoría')->relationship('category', 'name')->searchable()->preload()
                        ->createOptionForm([
                            TextInput::make('name')->label('Nombre')->required()->maxLength(80),
                            TextInput::make('slug')->label('URL amigable')->required()->maxLength(100)->alphaDash()->unique('blog_categories', 'slug'),
                        ])
                        ->createOptionModalHeading('Nueva categoría del blog'),
                    TextInput::make('author_name')->label('Autor visible')->maxLength(120)
                        ->helperText('Vacío = nombre de la tienda.'),
                ]),
                Section::make('Imagen destacada')->schema([
                    MediaUpload::make('featured_media_id', 'Imagen', 'blog')->helperText('Recomendado 1200 x 630.'),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('category'))
            ->defaultSort('published_at', 'desc')
            ->columns([
                TextColumn::make('title')->label('Título')->searchable()->sortable()->description(fn (BlogPost $p) => $p->storefrontPath())->wrap(),
                TextColumn::make('category.name')->label('Categoría')->placeholder('—'),
                TextColumn::make('status')->label('Estado')->badge()
                    ->getStateUsing(fn (BlogPost $p) => self::state($p))
                    ->color(fn (string $state) => match ($state) {
                        'Publicado' => 'success', 'Programado' => 'info', default => 'gray',
                    }),
                TextColumn::make('published_at')->label('Publicación')->dateTime('d-m-Y H:i')->placeholder('—')->sortable(),
                TextColumn::make('updated_at')->label('Modificado')->dateTime('d-m-Y H:i')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->label('Estado')->options(BlogPost::STATUSES),
                SelectFilter::make('category_id')->label('Categoría')->relationship('category', 'name'),
            ])
            ->recordActions([
                Action::make('view')->label('Ver en la tienda')->icon(Heroicon::OutlinedArrowTopRightOnSquare)->color('gray')
                    ->visible(fn (BlogPost $p) => $p->isVisible())
                    ->url(fn (BlogPost $p) => rtrim((string) config('flux.storefront_url'), '/').$p->storefrontPath(), shouldOpenInNewTab: true),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function state(BlogPost $p): string
    {
        if ($p->status !== 'published') {
            return 'Borrador';
        }

        return $p->published_at?->isFuture() ? 'Programado' : 'Publicado';
    }

    /** Al publicar sin fecha se usa la actual; el autor del panel queda registrado. */
    public static function prepare(array $data): array
    {
        if (($data['status'] ?? null) === 'published' && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        return $data;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBlogPosts::route('/'),
            'create' => CreateBlogPost::route('/create'),
            'edit' => EditBlogPost::route('/{record}/edit'),
        ];
    }
}
