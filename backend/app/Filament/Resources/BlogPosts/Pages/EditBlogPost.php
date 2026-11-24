<?php

namespace App\Filament\Resources\BlogPosts\Pages;

use App\Domain\Content\Models\BlogPost;
use App\Filament\Concerns\HasHelpManual;
use App\Filament\Resources\BlogPosts\BlogPostResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

/** @property BlogPost $record */
class EditBlogPost extends EditRecord
{
    use HasHelpManual;

    protected static string $resource = BlogPostResource::class;

    public function getTitle(): string
    {
        return $this->record->title;
    }

    public function getSubheading(): ?string
    {
        return match (BlogPostResource::state($this->record)) {
            'Publicado' => 'Publicado en '.$this->record->storefrontPath(),
            'Programado' => 'Programado para el '.$this->record->published_at?->format('d-m-Y H:i'),
            default => 'Borrador: no se ve en la tienda.',
        };
    }

    protected function helpManual(): string
    {
        return 'blog';
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return BlogPostResource::prepare($data);
    }

    protected function getHeaderActions(): array
    {
        return [$this->helpManualAction(), DeleteAction::make()];
    }
}
