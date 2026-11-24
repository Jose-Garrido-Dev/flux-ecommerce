<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Domain\Content\Models\Page;
use App\Domain\Content\Services\PageService;
use App\Domain\System\Services\SettingsRepository;
use App\Filament\Concerns\HasHelpManual;
use App\Filament\Resources\Pages\PageResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListPages extends ListRecords
{
    use HasHelpManual;

    protected static string $resource = PageResource::class;

    protected function helpManual(): string
    {
        return 'paginas';
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->helpManualAction(),
            Action::make('homeFromCurrent')->label('Crear portada editable')->icon(Heroicon::OutlinedHome)->color('gray')
                ->visible(fn () => ! Page::query()->where('type', 'home')->exists())
                ->authorize(fn () => auth()->user()?->can('content.create'))
                ->requiresConfirmation()
                ->modalHeading('Crear la portada con bloques')
                ->modalDescription('Se crea un borrador con los mismos bloques que tiene hoy la portada de la tienda (titular, categorías, destacados, ofertas y novedades). La tienda no cambia hasta que la publiques.')
                ->modalSubmitActionLabel('Crear borrador')
                ->action(function () {
                    $tagline = app(SettingsRepository::class)->get('business', 'tagline') ?: app(SettingsRepository::class)->get('business', 'store_name', config('app.name'));
                    $page = app(PageService::class)->create(['title' => 'Portada', 'type' => 'home'], [
                        ['type' => 'hero', 'data' => ['title' => $tagline, 'style' => 'field', 'button_label' => 'Ver catálogo', 'button_link' => '/catalogo']],
                        ['type' => 'categories', 'data' => ['title' => 'Categorías']],
                        ['type' => 'products', 'data' => ['title' => 'Destacados', 'source' => 'featured', 'layout' => 'carousel', 'limit' => 8]],
                        ['type' => 'products', 'data' => ['title' => 'Ofertas', 'source' => 'on_sale', 'layout' => 'grid', 'limit' => 4, 'link_label' => 'Ver todas', 'link' => '/catalogo?on_sale=1']],
                        ['type' => 'products', 'data' => ['title' => 'Novedades', 'source' => 'newest', 'layout' => 'grid', 'limit' => 8, 'link_label' => 'Ver catálogo', 'link' => '/catalogo']],
                        ['type' => 'brands', 'data' => ['title' => 'Marcas']],
                    ]);
                    $this->redirect(PageResource::getUrl('edit', ['record' => $page]));
                }),
            CreateAction::make()->label('Nueva página'),
        ];
    }
}
