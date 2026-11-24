<?php

namespace App\Domain\Content\Concerns;

use App\Domain\Content\Services\RedirectService;

/**
 * Cuando cambia la dirección (slug) de un producto, categoría, marca, página o artículo,
 * la dirección antigua queda redirigiendo (301) a la nueva: no se pierden visitas ni posicionamiento.
 */
trait RedirectsOnSlugChange
{
    public static function bootRedirectsOnSlugChange(): void
    {
        static::updated(function ($model) {
            if (! $model->wasChanged('slug') || ! $model->redirectsEnabled()) {
                return;
            }
            $old = $model->getOriginal('slug');
            if ($old) {
                app(RedirectService::class)->slugChanged($model->pathForSlug($old), $model->pathForSlug($model->slug));
            }
        });
    }

    protected function redirectsEnabled(): bool
    {
        return true;
    }

    public function pathForSlug(string $slug): string
    {
        $clone = clone $this;
        $clone->slug = $slug;

        return $clone->storefrontPath();
    }
}
