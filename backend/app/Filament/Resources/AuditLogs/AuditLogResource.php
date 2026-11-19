<?php

namespace App\Filament\Resources\AuditLogs;

use App\Domain\System\Models\AuditLog;
use App\Filament\Resources\AuditLogs\Pages\ManageAuditLogs;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/** Consulta de auditoría (módulo 23). Solo lectura. */
class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Sistema';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'registro de auditoría';

    protected static ?string $pluralModelLabel = 'Auditoría';

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('created_at')->label('Fecha')->dateTime('d-m-Y H:i:s'),
            TextEntry::make('action')->label('Acción'),
            TextEntry::make('actor_type')->label('Origen'),
            TextEntry::make('actor_id')->label('Usuario (ID)')->placeholder('—'),
            TextEntry::make('auditable_type')->label('Recurso')->placeholder('—'),
            TextEntry::make('auditable_id')->label('ID del recurso')->placeholder('—'),
            TextEntry::make('result')->label('Resultado'),
            TextEntry::make('ip')->label('IP')->placeholder('—'),
            KeyValueEntry::make('old_values')->label('Valores anteriores')->columnSpanFull(),
            KeyValueEntry::make('new_values')->label('Valores nuevos')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Fecha')->dateTime('d-m-Y H:i:s')->sortable(),
                TextColumn::make('action')->label('Acción')->searchable(),
                TextColumn::make('actor_type')->label('Origen')->badge(),
                TextColumn::make('actor_id')->label('Usuario')->placeholder('—'),
                TextColumn::make('auditable_type')->label('Recurso')
                    ->formatStateUsing(fn (?string $state) => $state ? class_basename($state) : '—'),
                TextColumn::make('auditable_id')->label('ID'),
                TextColumn::make('result')->label('Resultado')->badge(),
            ])
            ->filters([
                SelectFilter::make('actor_type')->label('Origen')
                    ->options(['user' => 'Usuario', 'system' => 'Sistema', 'provider' => 'Proveedor', 'customer' => 'Cliente']),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageAuditLogs::route('/')];
    }
}
