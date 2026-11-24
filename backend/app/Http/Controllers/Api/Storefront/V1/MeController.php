<?php

namespace App\Http\Controllers\Api\Storefront\V1;

use App\Domain\Customers\Models\Address;
use App\Domain\Customers\Models\Consent;
use App\Domain\Customers\Models\Customer;
use App\Domain\Marketing\Services\NewsletterService;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Services\OrderPayments;
use App\Domain\Orders\Services\OrderPresenter;
use App\Domain\Returns\Services\ReturnService;
use App\Domain\Shipping\Models\Region;
use App\Support\Rut;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Laravel\Sanctum\PersonalAccessToken;

/** Mi cuenta: perfil, contraseña, direcciones y pedidos del cliente autenticado. */
class MeController extends StorefrontController
{
    public static function profile(Customer $c): array
    {
        return [
            'first_name' => $c->first_name, 'last_name' => $c->last_name, 'email' => $c->email, 'phone' => $c->phone,
            'rut' => $c->rut, 'marketing_opt_in' => $c->marketing_opt_in, 'email_verified' => $c->email_verified_at !== null,
        ];
    }

    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => self::profile($request->user())]);
    }

    public function update(Request $request): JsonResponse
    {
        $customer = $request->user();
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'phone' => ['nullable', 'string', 'min:8', 'max:30', 'regex:/^[0-9+()\s-]+$/'],
            'rut' => ['nullable', 'string', 'max:12', function (string $a, mixed $v, Closure $fail) {
                if (filled($v) && ! Rut::isValid((string) $v)) {
                    $fail('El RUT no es válido.');
                }
            }],
            'marketing_opt_in' => ['boolean'],
        ]);
        $data['rut'] = filled($data['rut'] ?? null) ? Rut::format($data['rut']) : null;
        DB::transaction(function () use ($customer, $data, $request) {
            $optIn = (bool) ($data['marketing_opt_in'] ?? $customer->marketing_opt_in);
            if ($optIn !== $customer->marketing_opt_in) {
                Consent::query()->create(['customer_id' => $customer->id, 'email' => $customer->email, 'purpose' => 'marketing', 'granted' => $optIn, 'source' => 'account', 'ip' => self::clientIp($request)]);
            }
            $changed = $optIn !== $customer->marketing_opt_in;
            $customer->update([...$data, 'marketing_opt_in' => $optIn]);
            if ($changed) {
                app(NewsletterService::class)->syncCustomer($customer->fresh(), $optIn, 'account', self::clientIp($request));
            }
        });

        return response()->json(['data' => self::profile($customer->fresh())]);
    }

    public function password(Request $request): JsonResponse
    {
        $customer = $request->user();
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ]);
        if (! Hash::check($data['current_password'], $customer->password)) {
            return response()->json(['message' => 'La contraseña actual no es correcta.', 'errors' => ['current_password' => ['La contraseña actual no es correcta.']]], 422);
        }
        $customer->update(['password' => $data['password']]);
        // Cierra las otras sesiones
        $currentId = PersonalAccessToken::findToken((string) $request->bearerToken())?->id;
        $customer->tokens()->whereKeyNot($currentId ?? 0)->delete();

        return response()->json(['data' => ['message' => 'Contraseña actualizada.']]);
    }

    public function orders(Request $request): JsonResponse
    {
        $orders = Order::query()->where('customer_id', $request->user()->id)->latest('id')->paginate(20);

        return response()->json([
            'data' => collect($orders->items())->map(fn (Order $o) => [
                'uuid' => $o->uuid, 'number' => $o->number, 'placed_at' => $o->placed_at?->toIso8601String(),
                'status' => $o->status, 'status_label' => Order::STATUSES[$o->status] ?? $o->status,
                'payment_status_label' => Order::PAYMENT_STATUSES[$o->payment_status] ?? $o->payment_status,
                'grand_total' => $o->grand_total,
            ])->all(),
            'meta' => ['page' => $orders->currentPage(), 'last_page' => $orders->lastPage(), 'total' => $orders->total()],
        ]);
    }

    public function order(Request $request, string $uuid, OrderPresenter $presenter): JsonResponse
    {
        $order = Order::query()->where('customer_id', $request->user()->id)->where('uuid', $uuid)->first();
        if (! $order) {
            return $this->problem(404, 'not_found', 'El pedido no existe.');
        }

        return response()->json(['data' => $presenter->present($order, full: true)]);
    }

    public function cancelOrder(Request $request, string $uuid, OrderPayments $payments, OrderPresenter $presenter): JsonResponse
    {
        $order = Order::query()->where('customer_id', $request->user()->id)->where('uuid', $uuid)->first();
        if (! $order) {
            return $this->problem(404, 'not_found', 'El pedido no existe.');
        }
        $payments->cancel($order, 'Lo cancelaste desde tu cuenta.', 'customer');

        return response()->json(['data' => $presenter->present($order->fresh(), full: true)]);
    }

    public function requestReturn(Request $request, string $uuid, ReturnService $returns, OrderPresenter $presenter): JsonResponse
    {
        $order = Order::query()->where('customer_id', $request->user()->id)->where('uuid', $uuid)->first();
        if (! $order) {
            return $this->problem(404, 'not_found', 'El pedido no existe.');
        }

        return OrderTrackingController::storeReturn($order, $request, $returns, $presenter, full: true);
    }

    public function cancelReturn(Request $request, string $uuid, string $returnUuid, ReturnService $returns, OrderPresenter $presenter): JsonResponse
    {
        $order = Order::query()->where('customer_id', $request->user()->id)->where('uuid', $uuid)->first();
        $return = $order?->returns()->where('uuid', $returnUuid)->first();
        if (! $return) {
            return $this->problem(404, 'not_found', 'La solicitud no existe.');
        }
        $returns->cancel($return);

        return response()->json(['data' => $presenter->present($order->fresh(), full: true)]);
    }

    public function addresses(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()->addresses()->with('region')->orderByDesc('is_default')->orderBy('id')->get()
            ->map->toPublicArray()->all()]);
    }

    public function storeAddress(Request $request): JsonResponse
    {
        $customer = $request->user();
        if ($customer->addresses()->count() >= 20) {
            return response()->json(['message' => 'Puedes guardar hasta 20 direcciones.', 'errors' => ['street' => ['Puedes guardar hasta 20 direcciones.']]], 422);
        }
        $address = $this->saveAddress(new Address(['customer_id' => $customer->id]), $this->validateAddress($request), $customer);

        return response()->json(['data' => $address->toPublicArray()], 201);
    }

    public function updateAddress(Request $request, int $id): JsonResponse
    {
        $address = $request->user()->addresses()->find($id);
        if (! $address) {
            return $this->problem(404, 'not_found', 'La dirección no existe.');
        }

        return response()->json(['data' => $this->saveAddress($address, $this->validateAddress($request), $request->user())->toPublicArray()]);
    }

    public function destroyAddress(Request $request, int $id): JsonResponse
    {
        $request->user()->addresses()->whereKey($id)->delete();

        return response()->json(['data' => ['ok' => true]]);
    }

    private function validateAddress(Request $request): array
    {
        return $request->validate([
            'label' => ['nullable', 'string', 'max:40'],
            'recipient' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'min:8', 'max:30', 'regex:/^[0-9+()\s-]+$/'],
            'street' => ['required', 'string', 'max:150'],
            'number' => ['required', 'string', 'max:20'],
            'apartment' => ['nullable', 'string', 'max:40'],
            'region_code' => ['required', 'string', Rule::exists('regions', 'code')->where('is_enabled', true)],
            'commune' => ['required', 'string', 'max:80'],
            'reference' => ['nullable', 'string', 'max:200'],
            'is_default' => ['boolean'],
        ]);
    }

    private function saveAddress(Address $address, array $data, Customer $customer): Address
    {
        $region = Region::query()->where('code', $data['region_code'])->firstOrFail();
        $commune = $region->communes()->whereRaw('lower(name) = ?', [mb_strtolower(trim($data['commune']))])->first();

        return DB::transaction(function () use ($address, $data, $customer, $region, $commune) {
            $default = ($data['is_default'] ?? false) || ! $customer->addresses()->whereKeyNot($address->id ?? 0)->exists();
            if ($default) {
                $customer->addresses()->whereKeyNot($address->id ?? 0)->update(['is_default' => false]);
            }
            $address->fill([
                ...collect($data)->except(['region_code', 'is_default'])->all(),
                'region_id' => $region->id, 'commune_id' => $commune?->id, 'commune' => $commune?->name ?? trim($data['commune']),
                'is_default' => $default,
            ])->save();

            return $address->load('region');
        });
    }
}
