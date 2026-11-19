<?php

namespace App\Domain\Catalog\Jobs;

use App\Domain\Catalog\Import\CatalogImporter;
use App\Domain\Catalog\Models\CatalogImport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/** Ejecuta una importación en segundo plano (worker de colas), por lotes. */
class RunCatalogImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;

    public int $tries = 1;

    public function __construct(public CatalogImport $import) {}

    public function handle(CatalogImporter $importer): void
    {
        $importer->run($this->import);
    }

    public function failed(Throwable $e): void
    {
        $this->import->update([
            'status' => 'failed',
            'failure' => 'La importación se detuvo: '.$e->getMessage(),
            'finished_at' => now(),
        ]);
    }
}
