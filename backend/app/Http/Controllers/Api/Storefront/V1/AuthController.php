<?php

namespace App\Http\Controllers\Api\Storefront\V1;

use App\Domain\Cart\Services\CartService;
use App\Domain\Customers\Mail\CustomerMail;
use App\Domain\Customers\Models\Consent;
use App\Domain\Customers\Models\Customer;
use App\Domain\Orders\Models\Order;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Laravel\Sanctum\PersonalAccessToken;

/** Cuentas de clientes: registro, ingreso, salida, verificación de correo y recuperación de contraseña. */
class AuthController extends StorefrontController
{
    public function register(Request $request, CartService $carts): JsonResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
            'accepts_terms' => ['accepted'],
            'marketing_opt_in' => ['nullable', 'boolean'],
        ], ['accepts_terms.accepted' => 'Debes aceptar los términos y condiciones.']);

        if (Customer::withTrashed()->where('email', Customer::normalizeEmail($data['email']))->exists()) {
            return $this->validationProblem('email', 'Ya existe una cuenta con este correo. Ingresa o recupera tu contraseña.');
        }

        $customer = DB::transaction(function () use ($data, $request) {
            $customer = Customer::query()->create([
                'first_name' => $data['first_name'], 'last_name' => $data['last_name'], 'email' => $data['email'],
                'password' => $data['password'], 'marketing_opt_in' => (bool) ($data['marketing_opt_in'] ?? false),
            ]);
            $ip = self::clientIp($request);
            Consent::query()->create(['customer_id' => $customer->id, 'email' => $customer->email, 'purpose' => 'terms', 'granted' => true, 'source' => 'register', 'ip' => $ip]);
            Consent::query()->create(['customer_id' => $customer->id, 'email' => $customer->email, 'purpose' => 'marketing', 'granted' => $customer->marketing_opt_in, 'source' => 'register', 'ip' => $ip]);

            return $customer;
        });
        $this->sendVerification($customer);

        return $this->loggedIn($request, $customer, $carts, 201);
    }

    public function login(Request $request, CartService $carts): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:190'], 'password' => ['required', 'string', 'max:200']]);
        $key = 'customer-login:'.Customer::normalizeEmail($data['email']).'|'.self::clientIp($request);
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return $this->problem(429, 'too_many_attempts', 'Demasiados intentos. Espera '.RateLimiter::availableIn($key).' segundos y vuelve a intentarlo.');
        }

        $customer = Customer::query()->where('email', Customer::normalizeEmail($data['email']))->first();
        if (! $customer || ! Hash::check($data['password'], $customer->password) || $customer->status !== 'active') {
            RateLimiter::hit($key, 60);

            return $this->validationProblem('email', 'El correo o la contraseña no son correctos.');
        }
        RateLimiter::clear($key);
        $customer->forceFill(['last_login_at' => now()])->save();

        return $this->loggedIn($request, $customer, $carts);
    }

    public function logout(Request $request): JsonResponse
    {
        if ($bearer = $request->bearerToken()) {
            PersonalAccessToken::findToken($bearer)?->delete();
        }

        return response()->json(['data' => ['ok' => true]]);
    }

    /** Siempre responde lo mismo, exista o no la cuenta (no revela qué correos están registrados). */
    public function forgot(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:190']]);
        $key = 'customer-forgot:'.self::clientIp($request);
        if (! RateLimiter::tooManyAttempts($key, 5)) {
            RateLimiter::hit($key, 300);
            Password::broker('customers')->sendResetLink(['email' => Customer::normalizeEmail($data['email'])]);
        }

        return response()->json(['data' => ['message' => 'Si el correo tiene una cuenta, te enviamos un enlace para elegir una nueva contraseña.']]);
    }

    public function reset(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:200'],
            'email' => ['required', 'email', 'max:190'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ]);
        $status = Password::broker('customers')->reset(
            ['email' => Customer::normalizeEmail($data['email']), 'token' => $data['token'], 'password' => $data['password'], 'password_confirmation' => $request->input('password_confirmation')],
            function (Customer $customer, string $password) {
                $customer->forceFill(['password' => $password])->save();
                $customer->tokens()->delete(); // cierra las sesiones abiertas
            },
        );

        return $status === Password::PASSWORD_RESET
            ? response()->json(['data' => ['message' => 'Tu contraseña se cambió. Ya puedes ingresar.']])
            : $this->validationProblem('token', 'El enlace no es válido o ya venció. Pide uno nuevo.');
    }

    /** Confirma el correo y vincula a la cuenta los pedidos hechos antes como invitado con ese correo. */
    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:1000']]);
        try {
            $payload = Crypt::decrypt($data['token']);
        } catch (DecryptException) {
            $payload = null;
        }
        $customer = is_array($payload) && ($payload['exp'] ?? 0) > now()->timestamp
            ? Customer::query()->where('uuid', $payload['id'] ?? '')->where('email', $payload['email'] ?? '')->first()
            : null;
        if (! $customer) {
            return $this->validationProblem('token', 'El enlace no es válido o ya venció. Pide uno nuevo desde tu cuenta.');
        }
        if (! $customer->email_verified_at) {
            DB::transaction(function () use ($customer) {
                $customer->forceFill(['email_verified_at' => now()])->save();
                Order::query()->whereNull('customer_id')->where('email', $customer->email)->update(['customer_id' => $customer->id]);
            });
        }

        return response()->json(['data' => ['message' => 'Tu correo quedó confirmado.']]);
    }

    public function resendVerification(Request $request): JsonResponse
    {
        $customer = $request->user();
        $key = 'customer-verify:'.$customer->id;
        if (! $customer->email_verified_at && ! RateLimiter::tooManyAttempts($key, 3)) {
            RateLimiter::hit($key, 600);
            $this->sendVerification($customer);
        }

        return response()->json(['data' => ['message' => 'Te enviamos un nuevo enlace de confirmación.']]);
    }

    private function sendVerification(Customer $customer): void
    {
        $token = Crypt::encrypt(['id' => $customer->uuid, 'email' => $customer->email, 'exp' => now()->addHours(48)->timestamp]);
        $url = rtrim((string) config('flux.storefront_url'), '/').'/cuenta/verificar?'.http_build_query(['token' => $token]);
        Mail::to($customer->email)->queue(new CustomerMail($customer, 'verify', $url));
    }

    private function loggedIn(Request $request, Customer $customer, CartService $carts, int $status = 200): JsonResponse
    {
        if ($guest = $carts->findByToken($request->header('X-Cart-Token'))) {
            if ($guest->customer_id === null) {
                $carts->attachToCustomer($guest, $customer);
            }
        }
        $token = $customer->createToken('tienda', ['customer'], now()->addDays(30))->plainTextToken;

        return response()->json(['data' => ['token' => $token, 'customer' => MeController::profile($customer)]], $status);
    }

    private function validationProblem(string $field, string $message): JsonResponse
    {
        return response()->json(['message' => $message, 'errors' => [$field => [$message]]], 422);
    }
}
