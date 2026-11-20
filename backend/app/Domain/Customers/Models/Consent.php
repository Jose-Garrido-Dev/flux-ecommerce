<?php

namespace App\Domain\Customers\Models;

use Illuminate\Database\Eloquent\Model;

/** Consentimiento (términos, privacidad, marketing). Solo se agregan filas: un retiro es una fila nueva. */
class Consent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['customer_id', 'email', 'purpose', 'granted', 'source', 'ip', 'text_version'];

    protected function casts(): array
    {
        return ['granted' => 'boolean'];
    }
}
