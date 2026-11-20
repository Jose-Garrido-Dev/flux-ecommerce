"use client";

import Link from "next/link";
import { formatMoney } from "@/lib/format";
import { buttonClass } from "@/components/ui/Button";
import { useCart } from "./CartProvider";
import { CartLines } from "./CartLines";

export function CartPageView() {
  const { cart, loading, error } = useCart();
  const items = cart?.items ?? [];
  const blocked = items.some((l) => !l.purchasable || (l.max_quantity !== null && l.quantity > l.max_quantity));

  if (loading) return <p className="mt-10 text-muted">Cargando tu carrito…</p>;

  if (items.length === 0) {
    return (
      <div className="mt-10 rounded-[var(--radius)] border border-border bg-surface px-6 py-16 text-center">
        <p className="font-display text-3xl">Tu carrito está vacío</p>
        <p className="mt-3 text-muted">Explora el catálogo y agrega lo que te guste.</p>
        <Link href="/catalogo" className={buttonClass("primary", "mt-8")}>
          Ver el catálogo
        </Link>
      </div>
    );
  }

  return (
    <div className="mt-10 grid gap-10 lg:grid-cols-[1fr_24rem] lg:gap-16">
      <section aria-label="Productos">
        {error && (
          <p role="alert" className="mb-4 rounded-[var(--radius)] border border-sale/40 bg-sale/10 px-4 py-3 text-sm text-sale">
            {error}
          </p>
        )}
        {cart && cart.warnings.length > 0 && (
          <ul className="mb-4 space-y-1 rounded-[var(--radius)] border border-border bg-subtle px-4 py-3 text-sm">
            {cart.warnings.map((w) => (
              <li key={w}>{w}</li>
            ))}
          </ul>
        )}
        <div className="border-y border-border">
          <CartLines items={items} />
        </div>
      </section>

      <aside className="h-fit rounded-[var(--radius)] border border-border bg-surface p-6 lg:sticky lg:top-24">
        <h2 className="font-display text-3xl">Resumen</h2>
        <dl className="mt-6 space-y-3 text-sm">
          <div className="flex justify-between">
            <dt className="text-muted">Productos ({cart?.count})</dt>
            <dd className="tabular-nums">{formatMoney(cart?.subtotal ?? 0)}</dd>
          </div>
          <div className="flex justify-between">
            <dt className="text-muted">Despacho</dt>
            <dd>Se calcula al pagar</dd>
          </div>
          <div className="flex justify-between border-t border-border pt-3 text-base font-semibold">
            <dt>Subtotal</dt>
            <dd className="tabular-nums">{formatMoney(cart?.subtotal ?? 0)}</dd>
          </div>
          <div className="flex justify-between text-xs text-muted">
            <dt>IVA incluido</dt>
            <dd className="tabular-nums">{formatMoney(cart?.tax_total ?? 0)}</dd>
          </div>
        </dl>
        {blocked ? (
          <p className="mt-6 text-sm text-sale">Revisa los productos marcados antes de continuar.</p>
        ) : (
          <Link href="/checkout" className={buttonClass("primary", "mt-6 h-12 w-full text-base")}>
            Ir a pagar
          </Link>
        )}
        <Link href="/catalogo" className={buttonClass("ghost", "mt-4 w-full")}>
          Seguir comprando
        </Link>
      </aside>
    </div>
  );
}
