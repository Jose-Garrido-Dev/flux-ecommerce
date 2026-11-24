<?php

namespace App\Filament\Resources\NewsletterSubscribers;

use App\Domain\Marketing\Models\NewsletterSubscriber;
use App\Domain\Marketing\Services\NewsletterService;
use App\Filament\Resources\NewsletterSubscribers\Pages\ManageNewsletterSubscribers;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Marketing → Boletín: suscriptores con doble confirmación. No se pueden agregar como "suscritos" desde el panel:
 * siempre reciben el correo de confirmación, así la base solo tiene consentimientos comprobables.
 */
class NewsletterSubscriberResource extends Resource
{
    protected static ?string $model = NewsletterSubscriber::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelopeOpen;

    protected static string|UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Boletín';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'suscriptor';

    protected static ?string $pluralModelLabel = 'Suscriptores del boletín';

    protected static ?string $slug = 'newsletter-subscribers';

    protected static ?string $recordTitleAttribute = 'email';

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('email')->label('Correo')->searchable()->sortable()->copyable()->copyMessage('Correo copiado'),
                TextColumn::make('name')->label('Nombre')->searchable()->placeholder('—'),
                TextColumn::make('status')->label('Estado')->badge()
                    ->formatStateUsing(fn (string $state) => NewsletterSubscriber::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'subscribed' => 'success', 'pending' => 'warning', default => 'gray',
                    }),
                TextColumn::make('source')->label('Origen')->formatStateUsing(fn (string $state) => NewsletterSubscriber::SOURCES[$state] ?? $state),
                TextColumn::make('customer_id')->label('Cliente')->formatStateUsing(fn ($state) => $state ? 'Sí' : 'No')->placeholder('No')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('confirmed_at')->label('Confirmó')->dateTime('d-m-Y H:i')->placeholder('—')->sortable(),
                TextColumn::make('unsubscribed_at')->label('Baja')->dateTime('d-m-Y H:i')->placeholder('—')->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->label('Registro')->dateTime('d-m-Y H:i')->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Estado')->options(NewsletterSubscriber::STATUSES),
                SelectFilter::make('source')->label('Origen')->options(NewsletterSubscriber::SOURCES),
            ])
            ->recordActions([
                Action::make('resend')->label('Reenviar confirmación')->icon(Heroicon::OutlinedPaperAirplane)->color('gray')
                    ->visible(fn (NewsletterSubscriber $s) => $s->status === 'pending')
                    ->authorize(fn () => auth()->user()->can('marketing.update'))
                    ->requiresConfirmation()
                    ->modalDescription('Se envía otra vez el correo con el botón para confirmar. No se reenvía si el último fue hace menos de 10 minutos.')
                    ->action(function (NewsletterSubscriber $s) {
                        app(NewsletterService::class)->subscribe($s->email, $s->name, 'admin');
                        Notification::make()->title('Correo de confirmación en cola')->success()->send();
                    }),
                Action::make('unsubscribe')->label('Dar de baja')->icon(Heroicon::OutlinedNoSymbol)->color('danger')
                    ->visible(fn (NewsletterSubscriber $s) => $s->status !== 'unsubscribed')
                    ->authorize(fn () => auth()->user()->can('marketing.update'))
                    ->requiresConfirmation()
                    ->modalHeading('Dar de baja del boletín')
                    ->modalDescription('No recibirá más campañas ni correos automáticos de marketing. Queda registrado como retiro del consentimiento.')
                    ->action(function (NewsletterSubscriber $s) {
                        app(NewsletterService::class)->unsubscribe($s, 'panel');
                        Notification::make()->title('Suscriptor dado de baja')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageNewsletterSubscribers::route('/')];
    }
}
