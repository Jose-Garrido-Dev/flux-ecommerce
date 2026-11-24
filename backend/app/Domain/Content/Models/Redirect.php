<?php

namespace App\Domain\Content\Models;

use App\Domain\System\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Redirect extends Model
{
    use Auditable;

    public const CODES = [301 => 'Permanente (301)', 302 => 'Temporal (302)'];

    protected $fillable = ['from_path', 'to_path', 'code', 'is_automatic', 'hits', 'last_hit_at'];

    protected $attributes = ['code' => 301, 'is_automatic' => false, 'hits' => 0];

    protected function casts(): array
    {
        return ['code' => 'integer', 'is_automatic' => 'boolean', 'hits' => 'integer', 'last_hit_at' => 'datetime'];
    }
}
