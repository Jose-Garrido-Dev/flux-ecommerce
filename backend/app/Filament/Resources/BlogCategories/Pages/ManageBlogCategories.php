<?php

namespace App\Filament\Resources\BlogCategories\Pages;

use App\Filament\Concerns\HasHelpManual;
use App\Filament\Resources\BlogCategories\BlogCategoryResource;
use App\Filament\Resources\BlogPosts\BlogPostResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;

class ManageBlogCategories extends ManageRecords
{
    use HasHelpManual;

    protected static string $resource = BlogCategoryResource::class;

    protected function helpManual(): string
    {
        return 'blog';
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->helpManualAction(),
            Action::make('posts')->label('Volver a los artículos')->icon(Heroicon::OutlinedArrowLeft)->color('gray')->url(BlogPostResource::getUrl()),
            CreateAction::make(),
        ];
    }
}
