<?php

namespace App\Filament\Resources\EmailLogs;

use App\Domain\Notifications\Models\EmailLog;
use App\Domain\Notifications\Services\EmailResender;
use App\Domain\Notifications\Services\EmailTemplateRegistry;
use App\Filament\Resources\EmailLogs\Pages\ManageEmailLogs;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Support\InventoryActions;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Correos → Historial: cada correo enviado, con intentos, errores y reenvío. */
class EmailLogResource extends Resource
{
    protected static ?string $model = EmailLog::class;

    protected static ?string $slug = 'email-logs';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxStack;

    protected static string|UnitEnum|null $navigationGroup = 'Correos';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Historial';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'correo enviado';

    protected static ?string $pluralModelLabel = 'Historial de correos';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $failed = EmailLog::query()->where('status', 'failed')->where('created_at', '>=', now()->subDays(7))->count();

        return $failed > 0 ? (string) $failed : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Correos que fallaron en los últimos 7 días';
    }

    public static function statusColor(string $state): string
    {
        return match ($state) {
            'sent' => 'success',
            'failed' => 'danger',
            'retrying' => 'warning',
            default => 'gray',
        };
    }

    public static function resendAction(): Action
    {
        return Action::make('resend')->label('Reenviar')->icon(Heroicon::OutlinedArrowPath)
            ->visible(fn (EmailLog $record) => $record->mailable !== null && in_array($record->status, ['sent', 'failed'], true))
            ->authorize(fn () => auth()->user()?->can('settings.manage'))
            ->requiresConfirmation()
            ->modalHeading('Reenviar el correo')
            ->modalDescription(fn (EmailLog $record) => 'Se envía de nuevo a '.$record->to.' con los datos actuales del pedido.')
            ->modalSubmitActionLabel('Reenviar')
            ->action(function (EmailLog $record) {
                InventoryActions::run(fn () => EmailResender::resend($record));
                Notification::make()->success()->title('Correo en cola para reenviar')->send();
            });
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('order'))
            ->defaultSort('id', 'desc')
            ->searchPlaceholder('Correo o asunto')
            ->columns([
                TextColumn::make('created_at')->label('Fecha')->dateTime('d-m-Y H:i')->sortable(),
                TextColumn::make('template_key')->label('Correo')->formatStateUsing(fn (EmailLog $record) => $record->templateName())
                    ->description(fn (EmailLog $record) => $record->subject),
                TextColumn::make('to')->label('Para')->searchable(query: fn (Builder $query, string $search) => $query
                    ->where(fn ($q) => $q->where('to', 'like', '%'.mb_strtolower($search).'%')->orWhere('subject', 'like', "%{$search}%"))),
                TextColumn::make('order.number')->label('Pedido')->placeholder('—')
                    ->url(fn (EmailLog $record) => $record->order ? OrderResource::getUrl('view', ['record' => $record->order]) : null),
                TextColumn::make('status')->label('Estado')->badge()
                    ->formatStateUsing(fn ($state) => EmailLog::STATUSES[$state] ?? $state)->color(fn ($state) => self::statusColor($state))
                    ->description(fn (EmailLog $record) => $record->attempts > 1 ? $record->attempts.' intentos' : null),
                TextColumn::make('last_error')->label('Último error')->placeholder('—')->limit(60)->wrap()
                    ->tooltip(fn (EmailLog $record) => $record->last_error),
            ])
            ->filters([
                SelectFilter::make('status')->label('Estado')->options(EmailLog::STATUSES),
                SelectFilter::make('template_key')->label('Correo')
                    ->options(collect(EmailTemplateRegistry::all())->map(fn ($d) => $d['name'])->all()),
            ])
            ->recordActions([self::resendAction()])
            ->emptyStateHeading('Sin correos todavía')
            ->emptyStateDescription('Aquí aparece cada correo que envía la tienda.');
    }

    public static function getPages(): array
    {
        return ['index' => ManageEmailLogs::route('/')];
    }
}
