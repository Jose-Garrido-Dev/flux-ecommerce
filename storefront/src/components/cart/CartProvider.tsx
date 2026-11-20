"use client";

import { createContext, useCallback, useContext, useEffect, useMemo, useState } from "react";
import type { Cart } from "@/lib/types";
import { bff } from "@/lib/client-api";

type CartContextValue = {
  cart: Cart | null;
  loading: boolean;
  open: boolean;
  setOpen: (open: boolean) => void;
  /** Mensaje de error de la última acción (stock, producto no disponible...) */
  error: string | null;
  refresh: () => Promise<void>;
  add: (sku: string, quantity?: number) => Promise<boolean>;
  setQuantity: (sku: string, quantity: number) => Promise<boolean>;
  remove: (sku: string) => Promise<boolean>;
};

const CartContext = createContext<CartContextValue | null>(null);

/**
 * Estado del carrito en el navegador. Se carga después de pintar la página para que el
 * catálogo siga sirviéndose desde la caché (sin leer cookies en el servidor).
 */
export function CartProvider({ children }: { children: React.ReactNode }) {
  const [cart, setCart] = useState<Cart | null>(null);
  const [loading, setLoading] = useState(true);
  const [open, setOpen] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const refresh = useCallback(async () => {
    const res = await bff<Cart>("cart");
    if (res.ok && res.data) setCart(res.data);
    setLoading(false);
  }, []);

  useEffect(() => {
    void refresh();
    // Al volver a la pestaña el carrito puede haber cambiado (otra pestaña, pedido creado)
    const onFocus = () => void refresh();
    window.addEventListener("focus", onFocus);
    return () => window.removeEventListener("focus", onFocus);
  }, [refresh]);

  const run = useCallback(async (call: Promise<Awaited<ReturnType<typeof bff<Cart>>>>) => {
    setError(null);
    const res = await call;
    if (res.ok && res.data) {
      setCart(res.data);
      return true;
    }
    setError(res.errors.sku ?? res.errors.quantity ?? res.message);
    if (res.code === "cart_not_found") setCart(null);
    return false;
  }, []);

  const value = useMemo<CartContextValue>(
    () => ({
      cart,
      loading,
      open,
      setOpen,
      error,
      refresh,
      add: async (sku, quantity = 1) => {
        const ok = await run(bff<Cart>("cart/items", { method: "POST", body: { sku, quantity } }));
        setOpen(true);
        return ok;
      },
      setQuantity: (sku, quantity) => run(bff<Cart>(`cart/items/${encodeURIComponent(sku)}`, { method: "PATCH", body: { quantity } })),
      remove: (sku) => run(bff<Cart>(`cart/items/${encodeURIComponent(sku)}`, { method: "DELETE" })),
    }),
    [cart, loading, open, error, refresh, run],
  );

  return <CartContext.Provider value={value}>{children}</CartContext.Provider>;
}

export function useCart(): CartContextValue {
  const ctx = useContext(CartContext);
  if (!ctx) throw new Error("useCart debe usarse dentro de CartProvider");
  return ctx;
}
