<?php

namespace App\Filament\Concerns;

use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Botón "Manual de uso" (módulo 25). Lee resources/help/{slug}.md.
 * Cada página que lo use define helpManual(): string con el slug.
 */
trait HasHelpManual
{
    abstract protected function helpManual(): string;

    protected function helpManualAction(): Action
    {
        return Action::make('manual')
            ->label('Manual de uso')
            ->icon(Heroicon::OutlinedQuestionMarkCircle)
            ->color('gray')
            ->slideOver()
            ->modalHeading('Manual de uso')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cerrar')
            ->modalContent(function () {
                $path = resource_path('help/'.$this->helpManual().'.md');
                $markdown = File::exists($path)
                    ? File::get($path)
                    : "# Manual en preparación\n\nEste módulo aún no tiene manual. Ningún módulo se marca *Validado* sin su manual.";

                return view('filament.help.manual', [
                    'html' => Str::markdown($markdown, ['html_input' => 'strip', 'allow_unsafe_links' => false]),
                ]);
            });
    }
}
