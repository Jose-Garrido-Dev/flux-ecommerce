<?php

namespace App\Domain\Notifications\Services;

use App\Domain\Notifications\Models\EmailLog;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

/** Reenvía un correo del historial con los datos actuales del pedido. Queda como un envío nuevo en el historial. */
final class EmailResender
{
    public static function resend(EmailLog $log): void
    {
        try {
            $mailable = $log->mailable ? unserialize(base64_decode($log->mailable)) : null;
        } catch (Throwable) {
            $mailable = null;
        }
        if (! $mailable instanceof Mailable) {
            throw ValidationException::withMessages(['log' => 'Este correo no se puede reenviar (falta su contenido o el pedido ya no existe).']);
        }
        $mailable->emailLogId = null;
        Mail::mailer()->queue($mailable);
    }
}
