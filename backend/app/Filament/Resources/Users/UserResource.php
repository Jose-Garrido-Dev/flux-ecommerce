<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use App\Support\Labels;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'usuario';

    protected static ?string $pluralModelLabel = 'Usuarios y roles';

    protected static ?string $recordTitleAttribute = 'name';

    public const STATUSES = ['active' => 'Activo', 'disabled' => 'Desactivado'];

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nombre')->required()->maxLength(120),
            TextInput::make('email')->label('Correo')->email()->required()->maxLength(190)
                ->unique(ignoreRecord: true),
            TextInput::make('password')->label('Contraseña')->password()->revealable()
                ->minLength(12)
                ->required(fn (string $operation) => $operation === 'create')
                ->dehydrated(fn (?string $state) => filled($state))
                ->dehydrateStateUsing(fn (string $state) => Hash::make($state))
                ->helperText('Mínimo 12 caracteres. Déjala vacía para no cambiarla.'),
            Select::make('status')->label('Estado')->options(self::STATUSES)->default('active')->required(),
            Select::make('roles')->label('Roles')->relationship('roles', 'name')
                ->getOptionLabelFromRecordUsing(fn ($record) => Labels::role($record->name))
                ->multiple()->preload()->required()
                ->helperText('Los permisos se definen por rol. Un usuario sin rol no puede entrar al panel.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nombre')->searchable()->sortable(),
                TextColumn::make('email')->label('Correo')->searchable(),
                TextColumn::make('roles.name')->label('Roles')->badge()->formatStateUsing(fn (string $state) => Labels::role($state)),
                TextColumn::make('status')->label('Estado')->badge()
                    ->formatStateUsing(fn (string $state) => self::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => $state === 'active' ? 'success' : 'gray'),
                TextColumn::make('last_login_at')->label('Último ingreso')->dateTime('d-m-Y H:i')->sortable(),
                TextColumn::make('created_at')->label('Creado')->dateTime('d-m-Y H:i')->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->label('Estado')->options(self::STATUSES),
                SelectFilter::make('roles')->label('Rol')->relationship('roles', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => Labels::role($record->name)),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageUsers::route('/')];
    }
}
