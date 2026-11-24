<?php

namespace App\Filament\Resources\Menus\Pages;

use App\Domain\Content\Jobs\RevalidateStorefront;
use App\Domain\Content\Models\Menu;
use App\Domain\Content\Models\MenuItem;
use App\Domain\System\Services\AuditLogger;
use App\Filament\Concerns\HasHelpManual;
use App\Filament\Resources\Menus\MenuResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/** @property Menu $record */
class EditMenu extends EditRecord
{
    use HasHelpManual;

    protected static string $resource = MenuResource::class;

    public function getTitle(): string
    {
        return $this->record->name;
    }

    public function getSubheading(): ?string
    {
        return $this->record->location === 'header'
            ? 'Si no tiene enlaces, la tienda muestra las categorías principales.'
            : 'Si no tiene enlaces, el pie muestra las categorías y las páginas de la cuenta.';
    }

    protected function helpManual(): string
    {
        return 'menus';
    }

    protected function getHeaderActions(): array
    {
        return [$this->helpManualAction()];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $items = $this->record->items()->get();
        $row = fn (MenuItem $i) => ['label' => $i->label, 'link_type' => $i->link_type,
            'link_target' => $i->link_type === 'url' ? null : $i->link_target, 'url' => $i->link_type === 'url' ? $i->link_target : null];
        $data['items'] = $items->whereNull('parent_id')->values()->map(fn (MenuItem $i) => [
            ...$row($i), 'children' => $items->where('parent_id', $i->id)->values()->map($row)->all(),
        ])->all();

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Menu $record */
        $before = $record->items()->count();
        DB::transaction(function () use ($record, $data) {
            $record->items()->delete();
            $save = function (array $item, int $position, ?int $parentId) use ($record) {
                return $record->items()->create([
                    'parent_id' => $parentId, 'label' => trim($item['label']), 'link_type' => $item['link_type'], 'position' => $position,
                    'link_target' => $item['link_type'] === 'url' ? trim((string) ($item['url'] ?? '')) : ($item['link_target'] ?? null),
                ]);
            };
            foreach (array_values($data['items'] ?? []) as $i => $item) {
                $parent = $save($item, $i, null);
                if ($record->location === 'header') {
                    foreach (array_values($item['children'] ?? []) as $j => $child) {
                        $save($child, $j, $parent->id);
                    }
                }
            }
            $record->touch();
        });
        app(AuditLogger::class)->record('Menu.updated', $record, ['items' => $before], ['items' => $record->items()->count()]);
        RevalidateStorefront::dispatch(['menus']);

        return $record;
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Menú guardado. La tienda se actualiza en unos segundos.';
    }
}
