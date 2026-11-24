<?php

namespace App\Filament\Resources\Redirects\Pages;

use App\Filament\Concerns\HasHelpManual;
use App\Filament\Resources\Redirects\RedirectResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageRedirects extends ManageRecords
{
    use HasHelpManual;

    protected static string $resource = RedirectResource::class;

    public function getSubheading(): ?string
    {
        return 'Cuando cambias la URL de un producto, categoría, marca, página o artículo, la redirección se crea sola. El mapa del sitio está en /sitemap.xml.';
    }

    protected function helpManual(): string
    {
        return 'redirecciones';
    }

    protected function getHeaderActions(): array
    {
        return [$this->helpManualAction(), CreateAction::make()->using(fn (array $data) => RedirectResource::save(null, $data))];
    }
}
