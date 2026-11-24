<?php

namespace App\Filament\Support;

use App\Domain\Content\Services\BlockSchema;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

/** Contenido de un correo de marketing (campañas y automatizaciones). */
final class EmailContentFields
{
    public static function section(string $variablesHelp = 'Puedes usar {nombre} y {tienda}.'): Section
    {
        return Section::make('Contenido del correo')->columnSpanFull()->columns(2)->schema([
            TextInput::make('subject')->label('Asunto')->required()->maxLength(150)->columnSpanFull()->helperText($variablesHelp),
            TextInput::make('heading')->label('Título dentro del correo')->required()->maxLength(150)->columnSpanFull(),
            MarkdownEditor::make('body')->label('Texto')->required()->maxLength(20000)->columnSpanFull()
                ->toolbarButtons([['bold', 'italic', 'link'], ['heading', 'bulletList', 'orderedList', 'blockquote'], ['undo', 'redo']])
                ->helperText('Formato simple: negrita, listas y enlaces. No se permite HTML.'),
            TextInput::make('button_label')->label('Texto del botón')->maxLength(60)->requiredWith('button_url')
                ->validationMessages(['required_with' => 'Escribe el texto del botón o deja vacío el enlace.']),
            TextInput::make('button_url')->label('Enlace del botón')->maxLength(500)->placeholder('/catalogo o https://…')
                ->requiredWith('button_label')
                ->validationMessages(['required_with' => 'Escribe el enlace del botón o deja vacío el texto.'])
                ->rule(fn () => function (string $attribute, $value, \Closure $fail) {
                    if (filled($value) && ! BlockSchema::validLink((string) $value)) {
                        $fail('El enlace debe ser una ruta de la tienda que empiece con / (por ejemplo /catalogo) o una dirección https://.');
                    }
                }),
        ]);
    }
}
