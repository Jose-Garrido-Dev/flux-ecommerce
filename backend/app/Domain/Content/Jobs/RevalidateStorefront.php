<?php

namespace App\Domain\Content\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Avisa al storefront (POST /api/revalidate) que refresque su caché para estas etiquetas.
 * Si no hay secreto configurado no hace nada: la tienda igual se actualiza sola en pocos minutos.
 * Si la tienda no responde, el worker reintenta; sin worker (cola "sync") solo se registra en el log,
 * para que publicar en el panel nunca falle porque la tienda esté apagada.
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
        try {
            Http::timeout(5)->withHeaders(['x-revalidate-secret' => $secret])
                ->post($url.'/api/revalidate', ['tags' => array_values(array_unique($this->tags))])
                ->throw();
        } catch (ConnectionException|RequestException $e) {
            Log::warning('No se pudo refrescar la caché de la tienda', ['error' => $e->getMessage(), 'tags' => $this->tags]);
            if ($this->job !== null && ! $this->job instanceof SyncJob) {
                throw $e;
            }
        }
    }
}
