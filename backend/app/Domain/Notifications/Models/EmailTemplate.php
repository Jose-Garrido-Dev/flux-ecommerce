<?php

namespace App\Domain\Notifications\Models;

use App\Domain\Notifications\Services\EmailTemplateRegistry;
use App\Domain\System\Concerns\Auditable;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Texto editable de un correo (asunto, título y texto principal). Ver EmailTemplateRegistry. */
class EmailTemplate extends Model
{
    use Auditable;

    protected $fillable = ['key', 'subject', 'heading', 'intro', 'enabled', 'version', 'updated_by'];

    protected $attributes = ['enabled' => true, 'version' => 1];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function definition(): array
    {
        return EmailTemplateRegistry::get($this->key);
    }

    /** Crea las plantillas que falten con su texto por defecto. */
    public static function syncDefaults(): void
    {
        $existing = static::query()->pluck('key')->all();
        foreach (EmailTemplateRegistry::all() as $key => $def) {
            if (! in_array($key, $existing, true)) {
                static::query()->create(['key' => $key, 'subject' => $def['subject'], 'heading' => $def['heading'], 'intro' => $def['intro']]);
            }
        }
    }

    /** Vuelve al texto original. */
    public function restoreDefault(): void
    {
        $def = $this->definition();
        $this->update(['subject' => $def['subject'], 'heading' => $def['heading'], 'intro' => $def['intro'], 'version' => $this->version + 1, 'updated_by' => auth()->id()]);
    }
}
