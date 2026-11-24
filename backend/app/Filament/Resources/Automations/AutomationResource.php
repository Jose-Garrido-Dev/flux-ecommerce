<?php

namespace App\Filament\Resources\Automations;

use App\Domain\Marketing\Models\Automation;
use App\Filament\Resources\Automations\Pages\ManageAutomations;
use App\Filament\Support\EmailContentFields;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Marketing → Automatizaciones: tres correos fijos (bienvenida, carrito abandonado, después de la compra).
 * Al activarlas solo consideran lo que ocurra desde ese momento: no se envían correos por hechos antiguos.
 */
class AutomationResource extends Resource
{
    protected static ?string $model = Automation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static string|UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 5;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'automatización';

    protected static ?string $pluralModelLabel = 'Automatizaciones';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('delay_hours')->label('Esperar')->suffix('horas')->numeric()->integer()->minValue(0)->maxValue(720)->required()
                ->helperText(fn (?Automation $record) => match ($record?->trigger) {
                    'welcome' => 'Desde que confirma la suscripción. 0 = de inmediato.',
                    'abandoned_cart' => 'Desde la última actividad del carrito. Mínimo recomendado: 1 hora.',
                    'post_purchase' => 'Desde que se confirma el pago del pedido. 168 horas = 7 días.',
                    default => null,
                }),
            EmailContentFields::section('Puedes usar {nombre} y {tienda}.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id')
            ->paginated(false)
            ->modifyQueryUsing(fn ($query) => $query->withCount(['runs as sent_count' => fn ($query) => $query->where('status', 'sent')]))
            ->columns([
                TextColumn::make('name')->label('Correo')->description(fn (Automation $a) => Automation::TRIGGERS[$a->trigger] ?? $a->trigger),
                TextColumn::make('delay_hours')->label('Espera')->formatStateUsing(fn (int $state) => $state === 0 ? 'Inmediato' : ($state % 24 === 0 ? ($state / 24).' días' : "$state horas")),
                IconColumn::make('is_active')->label('Activa')->boolean(),
                TextColumn::make('active_since')->label('Activa desde')->dateTime('d-m-Y H:i')->placeholder('—'),
                TextColumn::make('sent_count')->label('Enviados')->alignEnd(),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('toggle')
                    ->label(fn (Automation $a) => $a->is_active ? 'Desactivar' : 'Activar')
                    ->icon(fn (Automation $a) => $a->is_active ? Heroicon::OutlinedPause : Heroicon::OutlinedPlay)
                    ->color(fn (Automation $a) => $a->is_active ? 'gray' : 'success')
                    ->authorize(fn (Automation $a) => auth()->user()->can('update', $a))
                    ->requiresConfirmation()
                    ->modalDescription(fn (Automation $a) => $a->is_active
                        ? 'Deja de enviar este correo. Lo ya enviado no cambia.'
                        : 'Solo se enviará por lo que ocurra desde ahora (no por suscripciones, carritos o compras anteriores). Solo a suscritos confirmados.')
                    ->action(function (Automation $a) {
                        $a->update($a->is_active ? ['is_active' => false] : ['is_active' => true, 'active_since' => now()]);
                        Notification::make()->success()->title($a->is_active ? 'Automatización activada' : 'Automatización desactivada')->send();
                    }),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => ManageAutomations::route('/')];
    }
}
