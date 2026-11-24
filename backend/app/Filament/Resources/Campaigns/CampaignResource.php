<?php

namespace App\Filament\Resources\Campaigns;

use App\Domain\Marketing\Models\Campaign;
use App\Domain\Marketing\Services\CampaignService;
use App\Filament\Resources\Campaigns\Pages\CreateCampaign;
use App\Filament\Resources\Campaigns\Pages\EditCampaign;
use App\Filament\Resources\Campaigns\Pages\ListCampaigns;
use App\Filament\Resources\Campaigns\RelationManagers\RecipientsRelationManager;
use App\Filament\Support\EmailContentFields;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/** Marketing → Campañas: correos a los suscritos confirmados, en lotes por la cola. */
class CampaignResource extends Resource
{
    protected static ?string $model = Campaign::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 4;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'campaña';

    protected static ?string $pluralModelLabel = 'Campañas';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Section::make('Campaña')->columnSpanFull()->columns(2)->schema([
                TextInput::make('name')->label('Nombre interno')->required()->maxLength(120)
                    ->helperText('Solo para el panel, por ejemplo "Cyber noviembre".'),
                Select::make('audience')->label('Destinatarios')->options(Campaign::AUDIENCES)->default('all')->required()
                    ->helperText(fn () => 'Suscritos confirmados hoy: '.number_format(app(CampaignService::class)->audienceQuery('all')->count(), 0, ',', '.').'. La lista se arma al momento del envío.'),
                TextInput::make('preheader')->label('Texto de vista previa')->maxLength(150)->columnSpanFull()
                    ->helperText('Lo que muestran algunos programas de correo junto al asunto.'),
            ]),
            EmailContentFields::section('Puedes usar {nombre} (si el suscriptor lo indicó) y {tienda}.'),
        ])->disabled(fn (?Campaign $record) => $record !== null && ! $record->isEditable());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->label('Nombre')->searchable()->sortable()->description(fn (Campaign $c) => $c->subject),
                TextColumn::make('audience')->label('Destinatarios')->formatStateUsing(fn (string $state) => Campaign::AUDIENCES[$state] ?? $state)
                    ->toggleable(),
                TextColumn::make('status')->label('Estado')->badge()
                    ->formatStateUsing(fn (string $state) => Campaign::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'sent' => 'success', 'sending' => 'warning', 'scheduled' => 'info', 'cancelled' => 'danger', default => 'gray',
                    }),
                TextColumn::make('scheduled_at')->label('Envío')->dateTime('d-m-Y H:i')->placeholder('—')->sortable(),
                TextColumn::make('sent_count')->label('Enviados')->alignEnd()
                    ->formatStateUsing(fn ($state, Campaign $c) => $c->recipients_count ? "$state de {$c->recipients_count}" : '—'),
                TextColumn::make('failed_count')->label('Fallidos')->alignEnd()->toggleable(),
                TextColumn::make('clicks_count')->label('Clics')->alignEnd(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Estado')->options(Campaign::STATUSES),
            ])
            ->recordActions([
                EditAction::make()->label(fn (Campaign $c) => $c->isEditable() ? 'Editar' : 'Ver'),
            ]);
    }

    public static function getRelations(): array
    {
        return [RecipientsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCampaigns::route('/'),
            'create' => CreateCampaign::route('/create'),
            'edit' => EditCampaign::route('/{record}/edit'),
        ];
    }
}
