<?php

namespace App\Filament\Resources\BlogPosts\Pages;

use App\Filament\Concerns\HasHelpManual;
use App\Filament\Resources\BlogCategories\BlogCategoryResource;
use App\Filament\Resources\BlogPosts\BlogPostResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListBlogPosts extends ListRecords
{
    use HasHelpManual;

    protected static string $resource = BlogPostResource::class;

    protected function helpManual(): string
    {
        return 'blog';
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->helpManualAction(),
            Action::make('categories')->label('Categorías del blog')->icon(Heroicon::OutlinedFolder)->color('gray')
                ->url(BlogCategoryResource::getUrl()),
            CreateAction::make()->label('Nuevo artículo'),
        ];
    }
}
