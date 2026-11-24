<?php

namespace App\Domain\Shipping\Services;

use App\Domain\Catalog\Models\ProductVariant;

/**
 * Lo que hay que enviar: peso real y medidas de cada unidad, y restricciones de los productos.
 * Peso volumétrico por unidad (g) = largo × ancho × alto (mm) / factor (cm³ por kg).
 */
final class ShippingPackage
{
    /**
     * @param  list<array{weight_g: int, length_mm: ?int, width_mm: ?int, height_mm: ?int, quantity: int}>  $units
     * @param  list<string>  $missingWeight  SKU sin peso cargado
     */
    public function __construct(
        public readonly array $units,
        public readonly bool $allowsPickup = true,
        public readonly bool $requiresColdChain = false,
        public readonly array $missingWeight = [],
    ) {}

    /** @param  iterable<array{variant_id: int, quantity: int}>  $lines */
    public static function fromLines(iterable $lines): self
    {
        $lines = collect($lines)->filter(fn ($l) => ($l['variant_id'] ?? null) && ($l['quantity'] ?? 0) > 0);
        $variants = ProductVariant::query()->with('product')->whereIn('id', $lines->pluck('variant_id'))->get()->keyBy('id');
        $units = [];
        $pickup = true;
        $cold = false;
        $missing = [];
        foreach ($lines as $line) {
            $v = $variants[$line['variant_id']] ?? null;
            if (! $v) {
                continue;
            }
            $units[] = [
                'weight_g' => (int) $v->weight_g, 'length_mm' => $v->length_mm, 'width_mm' => $v->width_mm,
                'height_mm' => $v->height_mm, 'quantity' => (int) $line['quantity'],
            ];
            $pickup = $pickup && (bool) ($v->product?->allows_pickup ?? true);
            $cold = $cold || (bool) $v->product?->requires_cold_chain;
            if (! $v->weight_g) {
                $missing[] = $v->sku;
            }
        }

        return new self($units, $pickup, $cold, array_values(array_unique($missing)));
    }

    /** Paquete armado a mano (simulador de tarifas). Medidas en milímetros. */
    public static function manual(int $weightG, ?int $lengthMm = null, ?int $widthMm = null, ?int $heightMm = null, int $quantity = 1, bool $allowsPickup = true, bool $requiresColdChain = false): self
    {
        return new self(
            [['weight_g' => max(0, $weightG), 'length_mm' => $lengthMm, 'width_mm' => $widthMm, 'height_mm' => $heightMm, 'quantity' => max(1, $quantity)]],
            $allowsPickup,
            $requiresColdChain,
        );
    }

    /** Peso real total en gramos. */
    public function realWeight(): int
    {
        return array_sum(array_map(fn ($u) => $u['weight_g'] * $u['quantity'], $this->units));
    }

    /** Peso a cobrar: por unidad el mayor entre real y volumétrico (si el método usa factor). */
    public function chargeableWeight(?int $volumetricFactor): int
    {
        $total = 0;
        foreach ($this->units as $u) {
            $volumetric = 0;
            if ($volumetricFactor && $u['length_mm'] && $u['width_mm'] && $u['height_mm']) {
                $volumetric = (int) ceil($u['length_mm'] * $u['width_mm'] * $u['height_mm'] / $volumetricFactor);
            }
            $total += max($u['weight_g'], $volumetric) * $u['quantity'];
        }

        return $total;
    }
}
