<?php

namespace App\Domain\Content\Services;

use App\Domain\Catalog\Models\Media;
use App\Domain\Content\Models\Page;
use App\Domain\Content\Models\PageVersion;
use Illuminate\Support\Facades\DB;

/** Dónde se usa una imagen. Una imagen en uso no se puede borrar de la biblioteca. */
class MediaUsage
{
    /** @return list<string> descripciones en español */
    public function for(Media $media): array
    {
        $id = $media->id;
        $uses = [];
        foreach (DB::table('product_media')->join('products', 'products.id', '=', 'product_media.product_id')->where('media_id', $id)->pluck('products.name') as $name) {
            $uses[] = "Producto: $name";
        }
        foreach (['brands' => ['Marca', ['logo_media_id', 'banner_media_id']], 'categories' => ['Categoría', ['image_media_id', 'banner_media_id']]] as $table => [$label, $columns]) {
            $names = DB::table($table)->where(fn ($q) => collect($columns)->each(fn ($c) => $q->orWhere($c, $id)))->pluck('name');
            foreach ($names as $name) {
                $uses[] = "$label: $name";
            }
        }
        if (DB::table('attribute_values')->where('image_media_id', $id)->exists()) {
            $uses[] = 'Valor de atributo (muestra de color o imagen)';
        }
        foreach ($this->pagesUsing($id) as $title) {
            $uses[] = "Página: $title";
        }

        return $uses;
    }

    /** Páginas cuya versión publicada o última versión usan la imagen. @return list<string> */
    private function pagesUsing(int $mediaId): array
    {
        $titles = [];
        Page::query()->with(['latestVersion', 'publishedVersion'])->each(function (Page $page) use ($mediaId, &$titles) {
            foreach ([$page->publishedVersion, $page->latestVersion] as $version) {
                if ($version instanceof PageVersion && in_array($mediaId, BlockSchema::mediaIds($version->blocks), true)) {
                    $titles[] = $page->title;

                    return;
                }
            }
        });

        return $titles;
    }
}
