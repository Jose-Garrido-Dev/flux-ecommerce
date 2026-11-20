"use client";

import Link from "next/link";
import { useEffect, useRef } from "react";
import { usePathname } from "next/navigation";
import { formatMoney } from "@/lib/format";
import { buttonClass } from "@/components/ui/Button";
import { useCart } from "./CartProvider";
import { CartLines } from "./CartLines";

/** Mini carrito lateral. Usa <dialog> nativo: atrapa el foco y se cierra con Escape. */
export function CartDrawer() {
  const { cart, open, setOpen, error } = useCart();
  const ref = useRef<HTMLDialogElement>(null);
  const pathname = usePathname();

  useEffect(() => {
    const dialog = ref.current;
    if (!dialog) return;
    if (open && !dialog.open) dialog.showModal();
    if (!open && dialog.open) dialog.close();
  }, [open]);

  // Al navegar se cierra
  useEffect(() => setOpen(false), [pathname, setOpen]);

  const items = cart?.items ?? [];
  const blocked = items.some((l) => !l.purchasable || (l.max_quantity !== null && l.quantity > l.max_quantity));

  return (
    <dialog
      ref={ref}
      onClose={() => setOpen(false)}
      onClick={(e) => e.target === ref.current && setOpen(false)}
      aria-labelledby="mini-carrito-titulo"
      className="drawer fixed inset-y-0 right-0 left-auto m-0 h-dvh max-h-dvh w-full max-w-md bg-bg p-0 text-fg backdrop:bg-black/40 backdrop:backdrop-blur-sm"
    >
      <div className="flex h-full flex-col">
        <div className="flex items-center justify-between border-b border-border px-6 py-5">
          <h2 id="mini-carrito-titulo" className="font-display text-3xl">
            Tu carrito {cart && cart.count > 0 && <span className="text-muted">({cart.count})</span>}
          </h2>
          <button type="button" onClick={() => setOpen(false)} className="flex h-10 w-10 items-center justify-center rounded-full hover:bg-subtle">
            <span className="sr-only">Cerrar carrito</span>
            <svg aria-hidden width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6">
              <path d="M6 6l12 12M18 6L6 18" />
            </svg>
          </button>
        </div>

        {error && (
          <p role="alert" className="mx-6 mt-4 rounded-[var(--radius)] border border-sale/40 bg-sale/10 px-4 py-3 text-sm text-sale">
            {error}
          </p>
        )}

        {items.length === 0 ? (
          <div className="flex flex-1 flex-col items-center justify-center gap-6 px-6 text-center">
            <p className="font-display text-2xl">Tu carrito está vacío</p>
            <Link href="/catalogo" onClick={() => setOpen(false)} className={buttonClass("primary")}>
              Ver el catálogo
            </Link>
          </div>
        ) : (
          <>
            <div className="flex-1 overflow-y-auto px-6">
              <CartLines items={items} compact />
            </div>
            <div className="border-t border-border px-6 py-5">
              <div className="flex items-baseline justify-between">
                <span className="text-sm text-muted">Subtotal (IVA incluido)</span>
                <span className="font-display text-3xl tabular-nums">{formatMoney(cart?.subtotal ?? 0)}</span>
              </div>
              <p className="mt-1 text-xs text-muted">El despacho se calcula en el siguiente paso.</p>
              <div className="mt-5 grid gap-3">
                {blocked ? (
                  <p className="text-sm text-sale">Revisa los productos marcados antes de continuar.</p>
                ) : (
                  <Link href="/checkout" onClick={() => setOpen(false)} className={buttonClass("primary", "h-12 w-full text-base")}>
                    Ir a pagar
                  </Link>
                )}
                <Link href="/carrito" onClick={() => setOpen(false)} className={buttonClass("secondary", "w-full")}>
                  Ver carrito
                </Link>
              </div>
            </div>
          </>
        )}
      </div>
    </dialog>
  );
}
