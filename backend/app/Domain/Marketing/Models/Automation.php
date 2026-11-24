<?php

namespace App\Domain\Marketing\Models;

use App\Domain\System\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Automation extends Model
{
    use Auditable;

    public const TRIGGERS = [
        'welcome' => 'Bienvenida al confirmar la suscripción',
        'abandoned_cart' => 'Carrito abandonado',
        'post_purchase' => 'Después de la compra',
    ];

    protected $fillable = ['trigger', 'name', 'delay_hours', 'subject', 'heading', 'body', 'button_label', 'button_url', 'is_active', 'active_since'];

    protected function casts(): array
    {
        return ['delay_hours' => 'integer', 'is_active' => 'boolean', 'active_since' => 'datetime'];
    }

    public function runs(): HasMany
    {
        return $this->hasMany(AutomationRun::class);
    }
}
