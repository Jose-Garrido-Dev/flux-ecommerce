<?php

namespace App\Domain\System\Models;

use App\Domain\System\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use Auditable;

    protected $fillable = ['group', 'key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'json'];
    }
}
