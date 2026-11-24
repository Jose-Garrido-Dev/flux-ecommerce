<?php

namespace App\Domain\Content\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Avisa al storefront (POST /api/revalidate) que refresque su caché para estas etiquetas.
 * Si no hay secreto configurado no hace nada: la tienda igual se actualiza sola en pocos minutos.
 */
class RevalidateStorefront implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public function __construct(public array $tags)
    {
        $this->afterCommit();
    }

    public function backoff(): array
    {
        return [10, 60];
    }

    public function handle(): void
    {
        $secret = (string) config('flux.revalidate_secret');
        $url = rtrim((string) config('flux.storefront_internal_url'), '/');
        if ($secret === '' || $url === '' || $this->tags === []) {
            return;
        }
        $response = Http::timeout(5)->withHeaders(['x-revalidate-secret' => $secret])
            ->post($url.'/api/revalidate', ['tags' => array_values(array_unique($this->tags))]);
        if ($response->failed()) {
            Log::warning('No se pudo refrescar la caché de la tienda', ['status' => $response->status(), 'tags' => $this->tags]);
            $response->throw();
        }
    }
}
