<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductRelation;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\Rule;

/** Pestaña "Relacionados" (módulo 2, pestaña H): relacionados, complementarios, alternativos, accesorios. */
class RelatedProductsRelationManager extends RelationManager
{
    protected static string $relationship = 'relations';

    protected static ?string $title = 'Relacionados';

    protected static ?string $modelLabel = 'producto relacionado';

    protected static ?string $pluralModelLabel = 'productos relacionados';

    /** Sin permiso de edición de productos la pestaña queda solo de lectura. */
    public function isReadOnly(): bool
    {
        return ! auth()->user()?->can('products.update');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('type')->label('Tipo de relación')->options(Product::RELATION_TYPES)->required()->default('related'),
            Select::make('related_product_id')->label('Producto')->required()->searchable()
                ->getSearchResultsUsing(fn (string $search) => Product::query()
                    ->whereKeyNot($this->getOwnerRecord()->id)
                    ->where(fn ($q) => $q->where('name', 'like', "%$search%")
                        ->orWhereHas('variants', fn ($v) => $v->where('sku', 'like', "%$search%")))
                    ->limit(30)->pluck('name', 'id'))
                ->getOptionLabelUsing(fn ($value) => Product::query()->whereKey($value)->value('name'))
                ->rules(fn (?ProductRelation $record, $get) => [
                    Rule::notIn([$this->getOwnerRecord()->id]),
                    Rule::unique('product_relations', 'related_product_id')
                        ->where('product_id', $this->getOwnerRecord()->id)
                        ->where('type', $get('type'))
                        ->ignore($record?->id),
                ])
                ->validationMessages(['unique' => 'Ese producto ya está relacionado con este tipo.', 'not_in' => 'Un producto no puede relacionarse consigo mismo.']),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('relatedProduct'))
            ->reorderable('position')
            ->defaultSort('position')
            ->columns([
                TextColumn::make('relatedProduct.name')->label('Producto'),
                TextColumn::make('type')->label('Tipo')->badge()->formatStateUsing(fn ($state) => Product::RELATION_TYPES[$state] ?? $state),
                TextColumn::make('relatedProduct.status')->label('Estado')->badge()
                    ->formatStateUsing(fn ($state) => $state?->label()),
            ])
            ->filters([SelectFilter::make('type')->label('Tipo')->options(Product::RELATION_TYPES)])
            ->headerActions([
                CreateAction::make()->label('Agregar producto')
                    ->mutateDataUsing(fn (array $data) => [
                        ...$data,
                        'position' => (int) $this->getOwnerRecord()->relations()->max('position') + 1,
                    ]),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()->label('Quitar')])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()->label('Quitar seleccionados')])])
            ->emptyStateHeading('Sin productos relacionados')
            ->emptyStateDescription('Si no agregas ninguno, la tienda sugiere productos de la misma categoría.');
    }
}
