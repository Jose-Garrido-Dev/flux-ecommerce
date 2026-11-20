"use client";

import { useCart } from "./CartProvider";

export function CartButton({ className = "" }: { className?: string }) {
  const { cart, setOpen } = useCart();
  const count = cart?.count ?? 0;

  return (
    <button type="button" onClick={() => setOpen(true)} className={`relative ${className}`}>
      <span className="sr-only">{count > 0 ? `Abrir carrito, ${count} ${count === 1 ? "producto" : "productos"}` : "Abrir carrito"}</span>
      <svg aria-hidden width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6">
        <path d="M5 8h14l-1.2 11.2a2 2 0 0 1-2 1.8H8.2a2 2 0 0 1-2-1.8L5 8Z" />
        <path d="M9 8V6.5a3 3 0 0 1 6 0V8" />
      </svg>
      {count > 0 && (
        <span aria-hidden className="absolute -right-0.5 -top-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-accent px-1 text-[0.7rem] font-semibold text-accent-fg tabular-nums">
          {count > 99 ? "99+" : count}
        </span>
      )}
    </button>
  );
}
