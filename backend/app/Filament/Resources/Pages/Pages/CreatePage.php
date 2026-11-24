<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Domain\Content\Services\PageService;
use App\Filament\Resources\Pages\PageResource;
use App\Filament\Support\FormErrors;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePage extends CreateRecord
{
    protected static string $resource = PageResource::class;

    protected static bool $canCreateAnother = false;

    protected function handleRecordCreation(array $data): Model
    {
        return FormErrors::run(fn () => app(PageService::class)->create($data));
    }

    protected function getRedirectUrl(): string
    {
        return PageResource::getUrl('edit', ['record' => $this->record]);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Página creada. Agrega bloques y publícala cuando esté lista.';
    }
}
