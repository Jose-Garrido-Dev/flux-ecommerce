<?php

namespace App\Filament\Resources\Products\Pages;

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Services\ProductWriter;
use App\Domain\Pricing\Models\TaxClass;
use App\Filament\Concerns\HasHelpManual;
use App\Filament\Resources\Products\Concerns\SavesProduct;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Support\InventoryActions;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/** @property Product $record */
class EditProduct extends EditRecord
{
    use HasHelpManual, SavesProduct;

    protected static string $resource = ProductResource::class;

    protected function helpManual(): string
    {
        return 'productos';
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function getContentTabLabel(): ?string
    {
        return 'Producto';
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['default_variant'] = $this->defaultVariantFormData($this->record);
        $data['inventory'] = $this->inventoryFormData($this->record);
        // Productos creados antes de tener impuesto asignado (p. ej. importados): se propone IVA
        $data['tax_class_id'] ??= TaxClass::query()->where('is_default', true)->value('id');

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return $this->saveProduct($record, $data);
    }

    protected function afterSave(): void
    {
        $this->keepPrimaryCategoryAttached();
    }

    protected function getHeaderActions(): array
    {
        $writer = app(ProductWriter::class);

        return [
            $this->helpManualAction(),
            InventoryActions::movement('stockMovement', fn (Product $record, array $data) => [
                $record->variants()->where('is_default', true)->value('id'), $data['warehouse_id'],
            ])->visible(fn () => ! $this->record->has_variants)->color('gray'),
            Action::make('viewInStore')->label('Ver en la tienda')->icon(Heroicon::OutlinedArrowTopRightOnSquare)->color('gray')
                ->url(fn () => rtrim(config('flux.storefront_url'), '/').'/producto/'.$this->record->slug, shouldOpenInNewTab: true)
                ->visible(fn () => $this->record->isVisible()),
            Action::make('publish')->label('Publicar')->icon(Heroicon::OutlinedEye)
                ->visible(fn () => $this->record->status !== ProductStatus::Active)
                ->authorize(fn () => auth()->user()->can('products.update'))
                ->requiresConfirmation()
                ->action(fn () => $this->runAndRefresh(fn () => $writer->publish($this->record), 'Producto publicado')),
            ActionGroup::make([
                Action::make('unpublish')->label('Despublicar')->icon(Heroicon::OutlinedEyeSlash)
                    ->visible(fn () => $this->record->status === ProductStatus::Active)
                    ->authorize(fn () => auth()->user()->can('products.update'))
                    ->requiresConfirmation()
                    ->action(fn () => $this->runAndRefresh(fn () => $writer->unpublish($this->record), 'Producto despublicado')),
                Action::make('duplicate')->label('Duplicar')->icon(Heroicon::OutlinedDocumentDuplicate)
                    ->authorize(fn () => auth()->user()->can('products.create'))
                    ->requiresConfirmation()
                    ->modalDescription('Se crea una copia en borrador con SKU nuevos (sufijo -COPIA).')
                    ->action(function () use ($writer) {
                        $copy = $writer->duplicate($this->record);
                        Notification::make()->success()->title('Producto duplicado')->send();
                        $this->redirect(ProductResource::getUrl('edit', ['record' => $copy]));
                    }),
                Action::make('archive')->label('Archivar')->icon(Heroicon::OutlinedArchiveBox)
                    ->visible(fn () => $this->record->status !== ProductStatus::Archived)
                    ->authorize(fn () => auth()->user()->can('products.archive'))
                    ->requiresConfirmation()
                    ->modalDescription('Deja de mostrarse en la tienda y se conserva con todo su historial.')
                    ->action(fn () => $this->runAndRefresh(fn () => $writer->archive($this->record), 'Producto archivado')),
                DeleteAction::make()
                    ->modalDescription(fn () => $writer->deletionBlocker($this->record)
                        ?? 'Se elimina el producto con sus variantes y precios. Esta acción no se puede deshacer.')
                    ->using(function () use ($writer) {
                        try {
                            $writer->delete($this->record);
                        } catch (ValidationException $e) {
                            Notification::make()->danger()->title('No se puede eliminar')
                                ->body(collect($e->errors())->flatten()->first())->send();
                            $this->halt();
                        }

                        return true;
                    }),
            ]),
        ];
    }

    private function runAndRefresh(callable $action, string $message): void
    {
        try {
            $action();
        } catch (ValidationException $e) {
            Notification::make()->danger()->title(collect($e->errors())->flatten()->first())->send();

            return;
        }
        $this->record->refresh();
        $this->refreshFormData(['status', 'published_at']);
        Notification::make()->success()->title($message)->send();
    }
}
