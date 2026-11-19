<?php

namespace App\Filament\Resources\Attributes;

use App\Domain\Catalog\Models\Attribute;
use App\Filament\Forms\MediaUpload;
use App\Filament\Resources\Attributes\Pages\CreateAttribute;
use App\Filament\Resources\Attributes\Pages\EditAttribute;
use App\Filament\Resources\Attributes\Pages\ListAttributes;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

/** Gestor de atributos y sus valores (módulo 5): Color, Talla, Material... */
class AttributeResource extends Resource
{
    protected static ?string $model = Attribute::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = 'Catálogo';

    protected static ?int $navigationSort = 40;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'atributo';

    protected static ?string $pluralModelLabel = 'Atributos';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        $usesValues = fn (Get $get) => in_array($get('type'), ['select', 'color', 'image'], true);

        return $schema->columns(3)->components([
            Section::make('Atributo')->columnSpan(2)->columns(2)->schema([
                TextInput::make('name')->label('Nombre')->required()->maxLength(80)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (?string $state, Set $set, string $operation) {
                        if ($operation === 'create') {
                            $set('code', Str::slug((string) $state, '_'));
                        }
                    }),
                TextInput::make('code')->label('Código')->required()->maxLength(64)
                    ->regex('/^[a-z0-9_]+$/')->unique(ignoreRecord: true)
                    ->helperText('Se usa en filtros y URLs (?color=negro). Solo minúsculas, números y _.'),
                Select::make('type')->label('Tipo')->options(Attribute::TYPES)->default('select')->required()->live()
                    ->afterStateUpdated(fn (?string $state, Set $set) => $set('display', $state === 'color' ? 'swatch' : 'pill')),
                Select::make('display')->label('Presentación en la tienda')->options(Attribute::DISPLAYS)->default('pill')->required(),
                TextInput::make('unit')->label('Unidad')->maxLength(16)
                    ->visible(fn (Get $get) => in_array($get('type'), ['number', 'measure'], true))
                    ->helperText('Por ejemplo cm, kg, ml.'),
                TextInput::make('position')->label('Orden')->numeric()->minValue(0)->default(0),
            ]),
            Section::make('Uso')->columnSpan(1)->schema([
                Toggle::make('is_variant_axis')->label('Se usa para variantes')
                    ->helperText('Ej.: Color y Talla generan combinaciones con SKU propio.'),
                Toggle::make('is_filterable')->label('Se puede filtrar en el catálogo'),
                Toggle::make('visible_in_catalog')->label('Visible en la ficha (especificaciones)')->default(true),
                Toggle::make('is_required')->label('Obligatorio en todos los productos'),
            ]),
            Repeater::make('values')->label('Valores')
                ->relationship('values')
                ->orderColumn('position')
                ->reorderable()
                ->collapsible()
                ->itemLabel(fn (array $state) => $state['label'] ?? null)
                ->visible($usesValues)
                ->columnSpanFull()
                ->columns(3)
                ->addActionLabel('Agregar valor')
                ->schema([
                    TextInput::make('label')->label('Nombre visible')->required()->maxLength(120)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (?string $state, Set $set, Get $get) => blank($get('value')) ? $set('value', Str::slug((string) $state)) : null),
                    TextInput::make('value')->label('Valor en URL')->maxLength(120)
                        ->helperText('Se genera desde el nombre: "Azul marino" => azul-marino.')
                        ->distinct(),
                    ColorPicker::make('color_hex')->label('Color')
                        ->regex('/^#[0-9a-fA-F]{6}$/')
                        ->visible(fn (Get $get) => $get('../../type') === 'color'),
                    MediaUpload::make('image_media_id', 'Imagen', 'attributes')
                        ->visible(fn (Get $get) => $get('../../type') === 'image'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('values'))
            ->defaultSort('position')
            ->reorderable('position')
            ->columns([
                TextColumn::make('name')->label('Nombre')->searchable()->description(fn (Attribute $a) => $a->code),
                TextColumn::make('type')->label('Tipo')->formatStateUsing(fn (string $state) => Attribute::TYPES[$state] ?? $state),
                TextColumn::make('values_count')->label('Valores')->alignEnd(),
                IconColumn::make('is_variant_axis')->label('Variantes')->boolean(),
                IconColumn::make('is_filterable')->label('Filtro')->boolean(),
                IconColumn::make('visible_in_catalog')->label('Visible')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription('Se borran también sus valores y se quitan de los productos y variantes que los usen. No se puede deshacer.'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAttributes::route('/'),
            'create' => CreateAttribute::route('/create'),
            'edit' => EditAttribute::route('/{record}/edit'),
        ];
    }
}
