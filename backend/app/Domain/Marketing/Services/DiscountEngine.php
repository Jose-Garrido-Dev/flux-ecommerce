<?php

namespace App\Domain\Marketing\Services;

use App\Domain\Catalog\Models\Category;
use App\Domain\Marketing\Models\Coupon;
use App\Domain\Marketing\Models\Promotion;
use App\Domain\Orders\Models\Order;

/**
 * Calcula los descuentos de un carrito: primero las promociones automáticas vigentes (por prioridad)
 * y después el cupón. Cada descuento se reparte entre las líneas a las que aplica, para que el IVA
 * se calcule sobre el precio que realmente paga el cliente y las devoluciones reembolsen lo correcto.
 *
 * Reglas de combinación:
 * - Una promoción "no combinable" se aplica sola (gana la de mayor prioridad que corresponda).
 * - Si el cupón o una promoción aplicada no se combinan, queda la opción que más le conviene al cliente.
 * - El cupón de envío gratis se combina siempre.
 *
 * Línea esperada: variant_id, product_id, brand_id, category_ids, quantity, unit_price, line_total.
 */
class DiscountEngine
{
    /** @var array<int, list<int>> */
    private array $categoryTree = [];

    /**
     * @param  list<array>  $lines
     * @return array{line_discounts: array<int, int>, items_discount: int, free_shipping: bool, coupon: ?array, coupon_error: ?string, promotions: list<array>}
     */
    public function calculate(array $lines, ?string $couponCode, ?string $email = null, ?int $customerId = null, bool $checkLimits = true): array
    {
        $lines = array_values($lines);
        $subtotal = array_sum(array_column($lines, 'line_total'));
        $empty = ['line_discounts' => array_fill(0, count($lines), 0), 'items_discount' => 0, 'free_shipping' => false, 'coupon' => null, 'coupon_error' => null, 'promotions' => []];
        if ($lines === []) {
            return $empty;
        }

        // Promociones automáticas
        $promoNet = array_column($lines, 'line_total');
        $promoLineDiscounts = array_fill(0, count($lines), 0);
        $applied = [];
        $exclusive = false;
        foreach (Promotion::query()->running()->orderByDesc('priority')->orderBy('id')->get() as $promotion) {
            if ($applied !== [] && ! $promotion->stackable) {
                continue;
            }
            $perLine = $this->promotion($promotion, $lines, $promoNet, $subtotal);
            $amount = array_sum($perLine);
            if ($amount <= 0) {
                continue;
            }
            foreach ($perLine as $i => $d) {
                $promoNet[$i] -= $d;
                $promoLineDiscounts[$i] += $d;
            }
            $applied[] = ['id' => $promotion->id, 'name' => $promotion->name, 'amount' => $amount, 'stackable' => $promotion->stackable];
            if (! $promotion->stackable) {
                $exclusive = true;
                break;
            }
        }
        $promoTotal = array_sum($promoLineDiscounts);

        $result = [
            ...$empty,
            'line_discounts' => $promoLineDiscounts,
            'items_discount' => $promoTotal,
            'promotions' => array_map(fn ($p) => ['id' => $p['id'], 'name' => $p['name'], 'amount' => $p['amount']], $applied),
        ];

        $code = Coupon::normalize($couponCode);
        if ($code === '') {
            return $result;
        }
        $coupon = Coupon::query()->with('targets')->where('code', $code)->first();
        if ($error = $this->couponError($coupon, $lines, $subtotal, $email, $customerId, $checkLimits)) {
            return [...$result, 'coupon' => $coupon ? $this->couponInfo($coupon, 0) : ['code' => $code, 'name' => $code, 'amount' => 0, 'free_shipping' => false], 'coupon_error' => $error];
        }

        if ($coupon->type === 'free_shipping') {
            return [...$result, 'free_shipping' => true, 'coupon' => $this->couponInfo($coupon, 0, true)];
        }

        $conflict = ! $coupon->stackable || $exclusive;
        $base = $conflict ? array_column($lines, 'line_total') : $promoNet;
        $couponLines = $this->couponDiscount($coupon, $lines, $base);
        $couponTotal = array_sum($couponLines);

        if ($conflict && $applied !== []) {
            if ($couponTotal <= $promoTotal) {
                return [...$result, 'coupon' => $this->couponInfo($coupon, 0), 'coupon_error' => 'Este cupón no se combina con la promoción vigente, que te conviene más.'];
            }

            // El cupón conviene más: reemplaza a las promociones
            return [...$empty, 'line_discounts' => $couponLines, 'items_discount' => $couponTotal, 'coupon' => $this->couponInfo($coupon, $couponTotal)];
        }

        $lineDiscounts = array_map(fn ($a, $b) => $a + $b, $promoLineDiscounts, $couponLines);

        return [...$result, 'line_discounts' => $lineDiscounts, 'items_discount' => array_sum($lineDiscounts), 'coupon' => $this->couponInfo($coupon, $couponTotal)];
    }

    /** Mensaje de por qué el cupón no aplica, o null si aplica. */
    public function couponError(?Coupon $coupon, array $lines, int $subtotal, ?string $email, ?int $customerId, bool $checkLimits = true): ?string
    {
        if (! $coupon || $coupon->status !== 'active') {
            return 'El cupón no existe o ya no está vigente.';
        }
        if ($coupon->starts_at && $coupon->starts_at->isFuture()) {
            return 'Este cupón todavía no está vigente.';
        }
        if ($coupon->ends_at && $coupon->ends_at->isPast()) {
            return 'Este cupón venció.';
        }
        if ($coupon->min_subtotal && $subtotal < $coupon->min_subtotal) {
            return 'Este cupón requiere una compra mínima de $'.number_format($coupon->min_subtotal, 0, ',', '.').'.';
        }
        if ($coupon->type !== 'free_shipping' && $this->eligible($coupon, $lines) === []) {
            return 'Ningún producto de tu carrito participa de este cupón.';
        }
        if (! $checkLimits) {
            return null;
        }
        if ($coupon->usage_limit !== null && $coupon->activeRedemptions()->count() >= $coupon->usage_limit) {
            return 'Este cupón ya se usó el máximo de veces.';
        }
        if ($email !== null || $customerId !== null) {
            $mine = fn ($query) => $query->where(fn ($w) => $w->when($email, fn ($q) => $q->orWhere('email', $email))->when($customerId, fn ($q) => $q->orWhere('customer_id', $customerId)));
            if ($coupon->per_customer_limit !== null && $mine($coupon->activeRedemptions())->count() >= $coupon->per_customer_limit) {
                return 'Ya usaste este cupón el máximo de veces permitido.';
            }
            if ($coupon->first_purchase_only && $mine(Order::query()->where('status', '!=', 'cancelled'))->exists()) {
                return 'Este cupón es solo para la primera compra.';
            }
        }

        return null;
    }

    /** @return array<int, int> */
    private function couponDiscount(Coupon $coupon, array $lines, array $base): array
    {
        $eligible = $this->eligible($coupon, $lines);
        $weights = array_fill(0, count($lines), 0);
        foreach ($eligible as $i) {
            $weights[$i] = max(0, $base[$i]);
        }
        $pool = array_sum($weights);
        $amount = $coupon->type === 'percent' ? intdiv($pool * $coupon->value, 100) : min($coupon->value, $pool);
        if ($coupon->max_discount) {
            $amount = min($amount, $coupon->max_discount);
        }

        return self::allocate($amount, $weights);
    }

    /** Índices de las líneas que participan del cupón (incluidas y no excluidas). */
    private function eligible(Coupon $coupon, array $lines): array
    {
        $include = $coupon->targets->where('mode', 'include');
        $exclude = $coupon->targets->where('mode', 'exclude');
        $matches = fn ($targets, array $line) => $this->matches(
            $targets->where('target_type', 'product')->pluck('target_id')->all(),
            $targets->where('target_type', 'category')->pluck('target_id')->all(),
            $targets->where('target_type', 'brand')->pluck('target_id')->all(),
            $line,
        );

        $out = [];
        foreach ($lines as $i => $line) {
            if (($include->isEmpty() || $matches($include, $line)) && ! ($exclude->isNotEmpty() && $matches($exclude, $line))) {
                $out[] = $i;
            }
        }

        return $out;
    }

    /** @return array<int, int> descuento por línea */
    private function promotion(Promotion $promotion, array $lines, array $net, int $subtotal): array
    {
        $c = $promotion->conditions ?? [];
        $a = $promotion->actions ?? [];
        $none = array_fill(0, count($lines), 0);
        $hasTargets = ! empty($c['product_ids']) || ! empty($c['category_ids']) || ! empty($c['brand_ids']);
        $eligible = [];
        foreach ($lines as $i => $line) {
            if (! $hasTargets || $this->matches($c['product_ids'] ?? [], $c['category_ids'] ?? [], $c['brand_ids'] ?? [], $line)) {
                $eligible[] = $i;
            }
        }
        if ($eligible === []) {
            return $none;
        }
        $weights = $none;
        foreach ($eligible as $i) {
            $weights[$i] = max(0, $net[$i]);
        }
        $pool = array_sum($weights);
        $value = (int) ($a['value'] ?? 0);
        $percent = ($a['discount_type'] ?? 'percent') === 'percent';
        $quantity = array_sum(array_map(fn ($i) => $lines[$i]['quantity'], $eligible));

        switch ($promotion->type) {
            case 'cart_amount':
                if ($subtotal < (int) ($c['min_subtotal'] ?? 0)) {
                    return $none;
                }
                break;
            case 'qty_discount':
                if ($quantity < max(1, (int) ($c['min_quantity'] ?? 1))) {
                    return $none;
                }
                break;
            case 'collection':
                break;
            case 'buy_x_get_y':
                return $this->buyXGetY($lines, $net, $eligible, max(1, (int) ($c['buy_quantity'] ?? 2)), max(1, (int) ($a['get_quantity'] ?? 1)), $value ?: 100);
            default:
                return $none;
        }

        $amount = $percent ? intdiv($pool * min($value, 100), 100) : min($value, $pool);

        return self::allocate($amount, $weights);
    }

    /** Por cada X+Y unidades que participan, las Y más baratas tienen el descuento (100 % = gratis). */
    private function buyXGetY(array $lines, array $net, array $eligible, int $buy, int $get, int $percent): array
    {
        $units = [];
        foreach ($eligible as $i) {
            $unitNet = $lines[$i]['quantity'] > 0 ? intdiv(max(0, $net[$i]), $lines[$i]['quantity']) : 0;
            for ($u = 0; $u < $lines[$i]['quantity']; $u++) {
                $units[] = [$i, $unitNet];
            }
        }
        usort($units, fn ($x, $y) => $x[1] <=> $y[1]);
        $free = intdiv(count($units), $buy + $get) * $get;
        $out = array_fill(0, count($lines), 0);
        foreach (array_slice($units, 0, $free) as [$i, $price]) {
            $out[$i] += intdiv($price * min($percent, 100), 100);
        }

        return $out;
    }

    private function matches(array $productIds, array $categoryIds, array $brandIds, array $line): bool
    {
        if (in_array((int) $line['product_id'], array_map('intval', $productIds), true)) {
            return true;
        }
        if ($line['brand_id'] && in_array((int) $line['brand_id'], array_map('intval', $brandIds), true)) {
            return true;
        }
        foreach ($categoryIds as $id) {
            if (array_intersect($this->descendants((int) $id), $line['category_ids'] ?? []) !== []) {
                return true;
            }
        }

        return false;
    }

    /** Una categoría incluye a sus subcategorías. */
    private function descendants(int $id): array
    {
        return $this->categoryTree[$id] ??= Category::query()->find($id)?->selfAndDescendantIds() ?? [$id];
    }

    private function couponInfo(Coupon $coupon, int $amount, bool $freeShipping = false): array
    {
        return ['id' => $coupon->id, 'code' => $coupon->code, 'name' => $coupon->name, 'amount' => $amount, 'free_shipping' => $freeShipping];
    }

    /**
     * Reparte un monto en proporción a los pesos, en pesos enteros, sin pasarse de ningún peso.
     * Los pesos sobrantes por redondeo van a las líneas con mayor resto.
     *
     * @return array<int, int>
     */
    public static function allocate(int $amount, array $weights): array
    {
        $total = array_sum($weights);
        $out = array_fill(0, count($weights), 0);
        if ($amount <= 0 || $total <= 0) {
            return $out;
        }
        $amount = min($amount, $total);
        $remainders = [];
        foreach ($weights as $i => $w) {
            $exact = $amount * $w / $total;
            $out[$i] = (int) floor($exact);
            $remainders[$i] = $exact - $out[$i];
        }
        $left = $amount - array_sum($out);
        arsort($remainders);
        foreach (array_keys($remainders) as $i) {
            if ($left <= 0) {
                break;
            }
            if ($out[$i] < $weights[$i]) {
                $out[$i]++;
                $left--;
            }
        }

        return $out;
    }
}
