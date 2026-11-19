<?php

namespace App\Filament\Resources\ModuleStatuses;

use App\Domain\System\Enums\ModuleState;
use App\Domain\System\Models\ModuleStatus;
use App\Filament\Resources\ModuleStatuses\Pages\ManageModuleStatuses;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/** Tablero de avance (módulo 33): no se marca "Validado" sin evidencia. */
class ModuleStatusResource extends Resource
{
    protected static ?string $model = ModuleStatus::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Sistema';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'módulo';

    protected static ?string $pluralModelLabel = 'Tablero de avance';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Módulo')->disabled(),
            Select::make('state')->label('Estado')->options(ModuleState::options())->required()->live(),
            TextInput::make('evidence_url')->label('Evidencia (URL)')->url()->maxLength(255)
                ->required(fn (Get $get) => in_array($get('state'), ['validated', 'testing'], true))
                ->helperText('Obligatoria para "En pruebas" y "Validado": PR, ejecución de CI o informe.'),
            Textarea::make('note')->label('Nota')->rows(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('number')
            ->columns([
                TextColumn::make('number')->label('#')->sortable(),
                TextColumn::make('name')->label('Módulo')->searchable(),
                TextColumn::make('phase')->label('Fase')->sortable(),
                TextColumn::make('state')->label('Estado')->badge()
                    ->formatStateUsing(fn (ModuleState $state) => $state->label())
                    ->color(fn (ModuleState $state) => match ($state) {
                        ModuleState::Validated => 'success',
                        ModuleState::Testing, ModuleState::Implemented => 'info',
                        ModuleState::InDevelopment => 'warning',
                        ModuleState::Blocked => 'danger',
                        ModuleState::Pending => 'gray',
                    }),
                TextColumn::make('evidence_url')->label('Evidencia')->url(fn ($state) => $state)->openUrlInNewTab()
                    ->limit(30),
                TextColumn::make('updated_at')->label('Actualizado')->dateTime('d-m-Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('state')->label('Estado')->options(ModuleState::options()),
            ])
            ->recordActions([
                EditAction::make()->mutateDataUsing(function (array $data) {
                    $data['updated_by'] = auth()->id();

                    return $data;
                }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageModuleStatuses::route('/')];
    }
}
