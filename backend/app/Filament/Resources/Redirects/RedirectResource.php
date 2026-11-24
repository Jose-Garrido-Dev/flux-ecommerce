<?php

namespace App\Filament\Resources\Redirects;

use App\Domain\Content\Models\Redirect;
use App\Domain\Content\Services\RedirectService;
use App\Filament\Resources\Redirects\Pages\ManageRedirects;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;
use UnitEnum;

/** Contenido → SEO y redirecciones. Toda escritura pasa por RedirectService (valida ciclos y aplana cadenas). */
class RedirectResource extends Resource
{
    protected static ?string $model = Redirect::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Contenido';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'SEO y redirecciones';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'redirección';

    protected static ?string $pluralModelLabel = 'Redirecciones';

    protected static ?string $recordTitleAttribute = 'from_path';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            TextInput::make('from_path')->label('Dirección antigua')->required()->maxLength(300)->placeholder('/tienda/polera-negra')
                ->helperText('Ruta dentro de tu dominio. Si pegas la dirección completa, se toma solo la ruta.'),
            TextInput::make('to_path')->label('Redirigir a')->required()->maxLength(500)->placeholder('/producto/polera-negra o https://…'),
            Select::make('code')->label('Tipo')->options(Redirect::CODES)->default(301)->required()
                ->helperText('Permanente (301) traspasa el posicionamiento en buscadores. Temporal (302) solo para cambios por pocos días.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('from_path')->label('Desde')->searchable()->sortable()->copyable()->copyMessage('Copiado')->wrap(),
                TextColumn::make('to_path')->label('Hacia')->searchable()->wrap(),
                TextColumn::make('code')->label('Tipo')->badge()->formatStateUsing(fn (int $state) => Redirect::CODES[$state] ?? $state)
                    ->color(fn (int $state) => $state === 301 ? 'success' : 'warning'),
                TextColumn::make('is_automatic')->label('Origen')->formatStateUsing(fn (bool $state) => $state ? 'Automática' : 'Manual'),
                TextColumn::make('hits')->label('Visitas')->alignEnd()->sortable(),
                TextColumn::make('last_hit_at')->label('Última visita')->dateTime('d-m-Y H:i')->placeholder('—')->sortable()->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('is_automatic')->label('Origen')->trueLabel('Automáticas')->falseLabel('Manuales')->placeholder('Todas'),
            ])
            ->recordActions([
                EditAction::make()->using(fn (Redirect $record, array $data) => self::save($record, $data)),
                Action::make('delete')->label('Eliminar')->icon(Heroicon::OutlinedTrash)->color('danger')
                    ->authorize(fn (Redirect $r) => auth()->user()->can('delete', $r))
                    ->requiresConfirmation()
                    ->modalHeading('Eliminar redirección')
                    ->modalDescription('La dirección antigua dejará de redirigir y mostrará "Página no encontrada".')
                    ->action(function (Redirect $r) {
                        app(RedirectService::class)->delete($r);
                        Notification::make()->success()->title('Redirección eliminada')->send();
                    }),
            ]);
    }

    /** Guarda con el servicio y muestra sus errores en los campos del modal. */
    public static function save(?Redirect $redirect, array $data): Redirect
    {
        try {
            return app(RedirectService::class)->save($redirect, $data['from_path'], $data['to_path'], (int) $data['code'], $redirect?->is_automatic ?? false);
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(collect($e->errors())->mapWithKeys(fn ($m, $k) => ["mountedActions.0.data.$k" => $m])->all());
        }
    }

    public static function getPages(): array
    {
        return ['index' => ManageRedirects::route('/')];
    }
}
