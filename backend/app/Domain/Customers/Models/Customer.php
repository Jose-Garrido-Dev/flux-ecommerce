<?php

namespace App\Domain\Customers\Models;

use App\Domain\Customers\Mail\CustomerMail;
use App\Domain\Orders\Models\Order;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\HasApiTokens;

/** Cliente registrado de la tienda. Los invitados no tienen fila: su pedido guarda el correo. */
class Customer extends Authenticatable implements CanResetPasswordContract
{
    use CanResetPassword, HasApiTokens, HasUuids, SoftDeletes;

    public const STATUSES = ['active' => 'Activo', 'blocked' => 'Bloqueado'];

    protected $fillable = ['first_name', 'last_name', 'email', 'phone', 'rut', 'password', 'status', 'marketing_opt_in', 'email_verified_at', 'last_login_at'];

    protected $hidden = ['password', 'remember_token'];

    protected $attributes = ['status' => 'active', 'marketing_opt_in' => false];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'marketing_opt_in' => 'boolean',
        ];
    }

    public function setEmailAttribute(string $value): void
    {
        $this->attributes['email'] = self::normalizeEmail($value);
    }

    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public function getRememberTokenName(): ?string
    {
        return null; // la tienda usa tokens de Sanctum, no "recordarme"
    }

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $url = rtrim((string) config('flux.storefront_url'), '/').'/cuenta/restablecer?'.http_build_query(['token' => $token, 'email' => $this->email]);
        Mail::to($this->email)->queue(new CustomerMail($this, 'reset', $url));
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
