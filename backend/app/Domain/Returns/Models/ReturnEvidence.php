<?php

namespace App\Domain\Returns\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Foto o documento de respaldo de una devolución. Se guarda en el disco local privado, nunca público. */
class ReturnEvidence extends Model
{
    protected $table = 'return_evidence';

    protected $fillable = ['return_id', 'path', 'original_name', 'uploaded_by'];

    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class, 'return_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
