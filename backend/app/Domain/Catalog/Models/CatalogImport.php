<?php

namespace App\Domain\Catalog\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Importación masiva de productos (módulo 2.4): archivo, análisis previo, avance e informe final. */
class CatalogImport extends Model
{
    use HasUuids;

    public const MODES = [
        'upsert' => 'Crear nuevos y actualizar existentes (por SKU)',
        'create' => 'Solo crear nuevos (los SKU existentes se informan como error)',
        'update' => 'Solo actualizar existentes (los SKU nuevos se informan como error)',
    ];

    public const STATUSES = [
        'uploaded' => 'Subido', 'analyzed' => 'Analizado', 'queued' => 'En cola',
        'processing' => 'Importando', 'done' => 'Terminado', 'failed' => 'Falló',
    ];

    protected $attributes = ['mode' => 'upsert', 'create_missing' => true, 'status' => 'uploaded'];

    protected $fillable = [
        'user_id', 'original_name', 'path', 'mode', 'create_missing', 'mapping', 'status', 'total_rows',
        'processed_rows', 'created_count', 'updated_count', 'failed_count', 'summary', 'errors', 'failure',
        'started_at', 'finished_at',
    ];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected function casts(): array
    {
        return [
            'create_missing' => 'boolean',
            'mapping' => 'array',
            'summary' => 'array',
            'errors' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function extension(): string
    {
        return strtolower(pathinfo($this->path, PATHINFO_EXTENSION));
    }

    public function isRunning(): bool
    {
        return in_array($this->status, ['queued', 'processing'], true);
    }
}
