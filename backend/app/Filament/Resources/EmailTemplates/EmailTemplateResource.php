<?php

namespace App\Filament\Resources\EmailTemplates;

use App\Domain\Notifications\Models\EmailTemplate;
use App\Domain\Notifications\Services\EmailTemplateRegistry;
use App\Filament\Resources\EmailTemplates\Pages\EditEmailTemplate;
use App\Filament\Resources\EmailTemplates\Pages\ListEmailTemplates;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use UnitEnum;

/** Correos → Plantillas: asunto, título y texto de cada correo que envía la tienda. */
class EmailTemplateResource extends Resource
{
    protected static ?string $model = EmailTemplate::class;

    protected static ?string $slug = 'email-templates';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static string|UnitEnum|null $navigationGroup = 'Correos';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Plantillas';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'plantilla de correo';

    protected static ?string $pluralModelLabel = 'Plantillas de correo';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function variablesHelp(string $key): HtmlString
    {
        $vars = ['tienda' => 'Nombre de la tienda'] + EmailTemplateRegistry::get($key)['variables'];

        return new HtmlString(collect($vars)->map(fn ($label, $var) => '<code>{'.e($var).'}</code> '.e($label))->implode('<br>'));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make('Texto del correo')->columnSpan(2)->schema([
                TextInput::make('subject')->label('Asunto')->required()->maxLength(200),
                TextInput::make('heading')->label('Título')->required()->maxLength(200),
                Textarea::make('intro')->label('Texto principal')->required()->rows(8)->maxLength(5000)
                    ->helperText('Admite formato Markdown: **negrita**, *cursiva*, listas con guion y párrafos separados por una línea en blanco.'),
                Toggle::make('enabled')->label('Enviar este correo')
                    ->disabled(fn (?EmailTemplate $record) => $record && $record->definition()['required'])
                    ->helperText(fn (?EmailTemplate $record) => $record && $record->definition()['required']
                        ? 'Este correo es necesario para el cliente y no se puede desactivar.'
                        : 'Si lo desactivas no se envía y queda registrado en el historial como no enviado.'),
            ]),
            Section::make('Cuándo se envía')->schema([
                TextEntry::make('description')->hiddenLabel()->label('Cuándo se envía')
                    ->state(fn (EmailTemplate $record) => $record->definition()['description']),
                TextEntry::make('variables')->label('Variables disponibles')
                    ->state(fn (EmailTemplate $record) => self::variablesHelp($record->key)),
                TextEntry::make('version')->label('Versión')
                    ->belowContent(fn (EmailTemplate $record) => $record->updated_at ? 'Modificada el '.$record->updated_at->format('d-m-Y H:i').($record->editor ? ' por '.$record->editor->name : '') : null),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->orderBy('id'))
            ->paginated(false)
            ->groups([Group::make('group')->label('Grupo')->getKeyFromRecordUsing(fn (EmailTemplate $r) => $r->definition()['group'])
                ->getTitleFromRecordUsing(fn (EmailTemplate $r) => $r->definition()['group'])])
            ->columns([
                TextColumn::make('name')->label('Correo')->weight('bold')
                    ->state(fn (EmailTemplate $r) => $r->definition()['name'])
                    ->description(fn (EmailTemplate $r) => $r->definition()['description'])->wrap(),
                TextColumn::make('subject')->label('Asunto')->wrap(),
                IconColumn::make('enabled')->label('Se envía')->boolean(),
                TextColumn::make('updated_at')->label('Modificada')->dateTime('d-m-Y H:i'),
            ])
            ->recordActions([EditAction::make()->label('Editar')]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmailTemplates::route('/'),
            'edit' => EditEmailTemplate::route('/{record}/edit'),
        ];
    }
}
