<?php

namespace App\Filament\Resources\Campaigns\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Destinatarios de la campaña: se generan al iniciar el envío. */
class RecipientsRelationManager extends RelationManager
{
    protected static string $relationship = 'recipients';

    protected static ?string $title = 'Destinatarios';

    protected static ?string $modelLabel = 'destinatario';

    protected static ?string $pluralModelLabel = 'destinatarios';

    public const STATUSES = ['pending' => 'En cola', 'sent' => 'Enviado', 'failed' => 'Fallido', 'skipped' => 'Omitido'];

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('email')
            ->defaultSort('id')
            ->emptyStateHeading('Sin destinatarios todavía')
            ->emptyStateDescription('La lista se arma con los suscritos confirmados al momento de enviar.')
            ->columns([
                TextColumn::make('email')->label('Correo')->searchable(),
                TextColumn::make('status')->label('Estado')->badge()
                    ->formatStateUsing(fn (string $state) => self::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'sent' => 'success', 'failed' => 'danger', 'pending' => 'warning', default => 'gray',
                    }),
                TextColumn::make('attempts')->label('Intentos')->alignEnd(),
                TextColumn::make('error')->label('Detalle')->placeholder('—')->limit(80)->wrap(),
                TextColumn::make('sent_at')->label('Enviado')->dateTime('d-m-Y H:i')->placeholder('—'),
                TextColumn::make('clicked_at')->label('Clic')->dateTime('d-m-Y H:i')->placeholder('—'),
            ])
            ->filters([SelectFilter::make('status')->label('Estado')->options(self::STATUSES)]);
    }
}
