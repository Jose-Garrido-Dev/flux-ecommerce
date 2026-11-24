<?php

namespace App\Http\Controllers\Api\Storefront\V1;

use App\Domain\Content\Models\Page;
use App\Domain\Content\Services\MenuPresenter;
use App\Domain\Content\Services\PagePresenter;
use App\Domain\Content\Services\PageService;
use Illuminate\Http\JsonResponse;

/** Páginas publicadas, vista previa de borradores y menús. */
class ContentController extends StorefrontController
{
    public function page(string $slug, PagePresenter $presenter): JsonResponse
    {
        $page = Page::query()->published()->where('type', '!=', 'home')->where('slug', $slug)->with('publishedVersion')->first();
        if (! $page) {
            return $this->problem(404, 'not_found', 'La página no existe.');
        }

        return response()->json(['data' => $presenter->present($page, $page->publishedVersion)]);
    }

    /** Vista previa con el enlace firmado del panel: muestra la versión guardada aunque no esté publicada. */
    public function preview(string $token, PageService $pages, PagePresenter $presenter): JsonResponse
    {
        $found = $pages->fromPreviewToken($token);
        if (! $found) {
            return $this->problem(404, 'not_found', 'El enlace de vista previa no es válido o venció. Genera uno nuevo desde el panel.');
        }
        [$page, $version] = $found;

        return response()->json(['data' => [...$presenter->present($page, $version), 'preview' => true]], 200, ['Cache-Control' => 'no-store']);
    }

    public function menus(MenuPresenter $menus): JsonResponse
    {
        return response()->json(['data' => $menus->all()]);
    }
}
