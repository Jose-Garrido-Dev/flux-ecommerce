<?php

namespace App\Domain\Notifications\Concerns;

use App\Domain\Notifications\Models\EmailLog;
use App\Domain\Notifications\Services\EmailTemplates;
use Illuminate\Contracts\Queue\Factory as Queue;
use Throwable;

/**
 * Historial y reintentos de los correos de la tienda.
 * - Al ponerlo en cola se crea el registro (o se omite si la plantilla está desactivada).
 * - Cada intento suma 1; si falla queda el error y la cola lo reintenta (3 intentos: ahora, al minuto y a los 5 minutos).
 * - Si se agotan los intentos queda "Falló" y se puede reenviar desde el panel.
 */
trait TracksDelivery
{
    public ?int $emailLogId = null;

    public int $tries = 3;

    /** Clave de la plantilla (EmailTemplateRegistry). */
    abstract public function templateKey(): string;

    /** @return array{order_id?: ?int, customer_id?: ?int} */
    protected function logContext(): array
    {
        return [];
    }

    public function backoff(): array
    {
        return [60, 300];
    }

    public function queue(Queue $queue)
    {
        $to = collect($this->to)->pluck('address')->implode(', ');
        if (! EmailTemplates::enabled($this->templateKey())) {
            EmailLog::query()->create(['template_key' => $this->templateKey(), 'to' => $to, 'status' => 'skipped', ...$this->logContext()]);

            return null;
        }
        if ($this->emailLogId === null) {
            $snapshot = base64_encode(serialize($this));
            $this->emailLogId = EmailLog::query()->create([
                'template_key' => $this->templateKey(), 'to' => $to, 'status' => 'queued', 'mailable' => $snapshot, ...$this->logContext(),
            ])->id;
        } else {
            EmailLog::query()->whereKey($this->emailLogId)->update(['status' => 'queued', 'failed_at' => null]);
        }

        return parent::queue($queue);
    }

    public function send($mailer)
    {
        $log = $this->emailLogId ? EmailLog::query()->find($this->emailLogId) : null;
        $log?->increment('attempts');
        try {
            $sent = parent::send($mailer);
        } catch (Throwable $e) {
            $log?->update(['status' => 'retrying', 'last_error' => mb_substr($e->getMessage(), 0, 1000)]);
            throw $e;
        }
        $log?->update(['status' => 'sent', 'sent_at' => now(), 'subject' => $this->subject, 'failed_at' => null]);

        return $sent;
    }

    /** La cola agotó los intentos. */
    public function failed(Throwable $e): void
    {
        if ($this->emailLogId) {
            EmailLog::query()->whereKey($this->emailLogId)->update([
                'status' => 'failed', 'failed_at' => now(), 'last_error' => mb_substr($e->getMessage(), 0, 1000),
            ]);
        }
    }
}
