"use client";

import { useState } from "react";
import type { CartDiscounts } from "@/lib/types";
import { formatMoney } from "@/lib/format";
import { useCart } from "./CartProvider";

/**
 * Campo de cupón. El servidor decide si aplica (vigencia, mínimo, productos, límites)
 * y explica el motivo en español cuando no.
 */
export function CouponForm({ discounts, onChange }: { discounts?: CartDiscounts; onChange?: () => void }) {
  const { applyCoupon, removeCoupon } = useCart();
  const [open, setOpen] = useState(false);
  const [code, setCode] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const coupon = discounts?.coupon ?? null;

  const apply = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!code.trim() || busy) return;
    setBusy(true);
    setError(null);
    const problem = await applyCoupon(code.trim());
    setBusy(false);
    if (problem) {
      setError(problem);
      return;
    }
    setCode("");
    setOpen(false);
    onChange?.();
  };

  const remove = async () => {
    setBusy(true);
    await removeCoupon();
    setBusy(false);
    onChange?.();
  };

  if (coupon) {
    return (
      <div className="rounded-[var(--radius-sm)] border border-border bg-subtle px-3 py-2.5 text-sm">
        <div className="flex items-center justify-between gap-3">
          <span>
            Cupón <span className="font-semibold tracking-wide">{coupon.code}</span>
          </span>
          <button type="button" onClick={remove} disabled={busy} className="text-xs text-muted underline hover:text-fg">
            Quitar
          </button>
        </div>
        {discounts?.coupon_error && (
          <p role="alert" className="mt-1.5 text-xs text-sale">
            {discounts.coupon_error}
          </p>
        )}
      </div>
    );
  }

  if (!open) {
    return (
      <button type="button" onClick={() => setOpen(true)} className="text-sm underline underline-offset-4 hover:text-accent">
        ¿Tienes un cupón de descuento?
      </button>
    );
  }

  // No es un <form> anidado: el checkout ya es un formulario
  return (
    <div>
      <label htmlFor="cupon" className="field-label text-sm font-medium">
        Cupón de descuento
      </label>
      <div className="mt-1.5 flex gap-2">
        <input
          id="cupon"
          className="input uppercase"
          value={code}
          onChange={(e) => setCode(e.target.value)}
          onKeyDown={(e) => {
            if (e.key === "Enter") void apply(e);
          }}
          maxLength={40}
          autoComplete="off"
          autoCapitalize="characters"
          aria-invalid={error ? true : undefined}
          aria-describedby={error ? "cupon-error" : undefined}
        />
        <button type="button" onClick={apply} disabled={busy || !code.trim()} className="shrink-0 rounded-[var(--radius-sm)] border border-fg px-4 text-sm font-medium hover:bg-fg hover:text-bg disabled:opacity-50">
          {busy ? "Aplicando…" : "Aplicar"}
        </button>
      </div>
      {error && (
        <p id="cupon-error" role="alert" className="field-error mt-1.5">
          {error}
        </p>
      )}
    </div>
  );
}

/** Filas de descuento para un resumen (promociones y cupón). */
export function DiscountRows({ discounts }: { discounts?: CartDiscounts }) {
  if (!discounts) return null;
  const rows = [
    ...discounts.promotions.map((p) => ({ label: p.name, amount: p.amount })),
    ...(discounts.coupon && discounts.coupon.amount > 0 ? [{ label: `Cupón ${discounts.coupon.code}`, amount: discounts.coupon.amount }] : []),
  ];
  return (
    <>
      {rows.map((r) => (
        <div key={r.label} className="flex justify-between text-accent">
          <dt>{r.label}</dt>
          <dd className="tabular-nums">−{formatMoney(r.amount)}</dd>
        </div>
      ))}
    </>
  );
}
