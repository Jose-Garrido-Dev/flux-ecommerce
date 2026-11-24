<?php

namespace App\Filament\Resources\MediaFolders\Pages;

use App\Filament\Resources\MediaFolders\MediaFolderResource;
use App\Filament\Resources\MediaLibrary\MediaResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;

class ManageMediaFolders extends ManageRecords
{
    protected static string $resource = MediaFolderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')->label('Volver a la biblioteca')->icon(Heroicon::OutlinedArrowLeft)->color('gray')->url(fn () => MediaResource::getUrl()),
            CreateAction::make()->label('Nueva carpeta'),
        ];
    }
}
