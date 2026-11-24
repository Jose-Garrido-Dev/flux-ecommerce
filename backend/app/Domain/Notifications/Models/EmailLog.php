<?php

namespace App\Domain\Notifications\Models;

use App\Domain\Customers\Models\Customer;
use App\Domain\Notifications\Services\EmailTemplateRegistry;
use App\Domain\Orders\Models\Order;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Historial de cada correo: en cola, reintentando, enviado, fallido u omitido (plantilla desactivada). */
class EmailLog extends Model
{
    use HasUuids;

    public const STATUSES = [
        'queued' => 'En cola', 'retrying' => 'Reintentando', 'sent' => 'Enviado', 'failed' => 'Falló', 'skipped' => 'No enviado (plantilla desactivada)',
    ];

    protected $fillable = ['template_key', 'to', 'subject', 'status', 'attempts', 'last_error', 'order_id', 'customer_id', 'mailable', 'sent_at', 'failed_at'];

    protected $hidden = ['mailable'];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected function casts(): array
    {
        return ['sent_at' => 'datetime', 'failed_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function templateName(): string
    {
        return EmailTemplateRegistry::all()[$this->template_key]['name'] ?? (string) $this->template_key;
    }
}
