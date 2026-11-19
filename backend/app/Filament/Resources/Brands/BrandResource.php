<?php

namespace App\Filament\Resources\Brands;

use App\Domain\Catalog\Models\Brand;
use App\Filament\Forms\MediaUpload;
use App\Filament\Resources\Brands\Pages\ManageBrands;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

/** Marcas (módulo 4). Los nombres se normalizan para no duplicar "Nike" y "nike ". */
class BrandResource extends Resource
{
    protected static ?string $model = Brand::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = 'Catálogo';

    protected static ?int $navigationSort = 30;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'marca';

    protected static ?string $pluralModelLabel = 'Marcas';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label('Nombre')->required()->maxLength(120)
                ->live(onBlur: true)
                ->afterStateUpdated(function (?string $state, Set $set, string $operation) {
                    if ($operation === 'create') {
                        $set('slug', Str::slug((string) $state));
                    }
                })
                ->rules([fn (?Brand $record) => function (string $attribute, $value, Closure $fail) use ($record) {
                    $taken = Brand::query()->where('normalized_name', Brand::normalize((string) $value))
                        ->when($record, fn ($q) => $q->whereKeyNot($record->id))->first();
                    if ($taken) {
                        $fail("Ya existe la marca \"{$taken->name}\".");
                    }
                }]),
            TextInput::make('slug')->label('URL amigable')->required()->maxLength(160)
                ->alphaDash()->unique(ignoreRecord: true)
                ->helperText('Dirección de la página de la marca: /marca/{slug}'),
            Textarea::make('description')->label('Descripción')->rows(3)->columnSpanFull(),
            MediaUpload::make('logo_media_id', 'Logotipo', 'brands'),
            MediaUpload::make('banner_media_id', 'Banner', 'brands')
                ->helperText('Imagen ancha para la página de la marca (recomendado 1920 x 600).'),
            TextInput::make('website')->label('Sitio oficial')->url()->maxLength(255),
            TextInput::make('country')->label('País de origen')->maxLength(80),
            Select::make('status')->label('Estado')->options(Brand::STATUSES)->default('active')->required()
                ->helperText('Una marca archivada no se muestra en la tienda; sus productos siguen visibles.'),
            TextInput::make('position')->label('Orden')->numeric()->minValue(0)->default(0),
            Toggle::make('show_on_home')->label('Mostrar en la portada'),
            Section::make('SEO')->collapsed()->columnSpanFull()->columns(2)->schema([
                TextInput::make('seo_title')->label('Metatítulo')->maxLength(70)
                    ->helperText('Si queda vacío se usa el nombre.'),
                TextInput::make('seo_description')->label('Metadescripción')->maxLength(160),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('logo')->withCount('products'))
            ->defaultSort('position')
            ->columns([
                ImageColumn::make('logo')->label('')->getStateUsing(fn (Brand $b) => $b->logo?->thumbUrl())->imageHeight(36),
                TextColumn::make('name')->label('Nombre')->searchable()->sortable(),
                TextColumn::make('slug')->label('URL')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('products_count')->label('Productos')->sortable()->alignEnd(),
                IconColumn::make('show_on_home')->label('Portada')->boolean(),
                TextColumn::make('status')->label('Estado')->badge()
                    ->formatStateUsing(fn (string $state) => Brand::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => $state === 'active' ? 'success' : 'gray'),
                TextColumn::make('updated_at')->label('Modificada')->dateTime('d-m-Y H:i')->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->label('Estado')->options(Brand::STATUSES),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('toggleArchive')
                    ->label(fn (Brand $b) => $b->status === 'archived' ? 'Reactivar' : 'Archivar')
                    ->icon(fn (Brand $b) => $b->status === 'archived' ? Heroicon::OutlinedArrowUturnLeft : Heroicon::OutlinedArchiveBox)
                    ->color('gray')
                    ->requiresConfirmation()
                    ->authorize(fn () => auth()->user()->can('brands.archive'))
                    ->action(fn (Brand $b) => $b->update(['status' => $b->status === 'archived' ? 'active' : 'archived'])),
                DeleteAction::make()
                    ->visible(fn (Brand $b) => $b->products_count === 0)
                    ->modalDescription('Solo se pueden eliminar marcas sin productos. Esta acción no se puede deshacer.'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageBrands::route('/')];
    }
}
