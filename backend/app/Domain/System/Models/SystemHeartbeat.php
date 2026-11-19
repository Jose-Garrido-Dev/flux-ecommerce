<?php

namespace App\Domain\System\Models;

use Illuminate\Database\Eloquent\Model;

class SystemHeartbeat extends Model
{
    public $timestamps = false;

    protected $fillable = ['name', 'last_seen_at'];

    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime'];
    }

    public static function beat(string $name): void
    {
        static::query()->updateOrCreate(['name' => $name], ['last_seen_at' => now()]);
    }
}
