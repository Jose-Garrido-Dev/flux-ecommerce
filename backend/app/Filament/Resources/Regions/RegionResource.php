<?php

namespace App\Filament\Resources\Regions;

use App\Domain\Shipping\Models\Region;
use App\Filament\Resources\Regions\Pages\ManageRegions;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

/** Catálogo oficial de regiones: solo se habilitan o deshabilitan para despacho. */
class RegionResource extends Resource
{
    protected static ?string $model = Region::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static string|UnitEnum|null $navigationGroup = 'Logística';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'región';

    protected static ?string $pluralModelLabel = 'Regiones';

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('position')
            ->paginated(false)
            ->columns([
                TextColumn::make('roman')->label('N°'),
                TextColumn::make('name')->label('Región')->searchable(),
                TextColumn::make('code')->label('Código'),
                TextColumn::make('communes_count')->label('Comunas')->counts('communes'),
                ToggleColumn::make('is_enabled')->label('Habilitada')
                    ->disabled(fn () => ! auth()->user()?->can('shipping.update')),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageRegions::route('/')];
    }
}
