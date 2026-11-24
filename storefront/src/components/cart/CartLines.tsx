"use client";

import Image from "next/image";
import Link from "next/link";
import { useState } from "react";
import type { CartLine } from "@/lib/types";
import { formatMoney, mediaUrl } from "@/lib/format";
import { useCart } from "./CartProvider";

/** Líneas del carrito con cantidad editable. Se usa en el mini carrito y en /carrito. */
export function CartLines({ items, compact = false }: { items: CartLine[]; compact?: boolean }) {
  return (
    <ul className="divide-y divide-border">
      {items.map((line) => (
        <Line key={line.sku} line={line} compact={compact} />
      ))}
    </ul>
  );
}

function Line({ line, compact }: { line: CartLine; compact: boolean }) {
  const { setQuantity, remove, setOpen } = useCart();
  const [busy, setBusy] = useState(false);
  const max = line.max_quantity ?? 99;

  const change = async (q: number) => {
    setBusy(true);
    await (q <= 0 ? remove(line.sku) : setQuantity(line.sku, Math.min(q, max)));
    setBusy(false);
  };

  const size = compact ? "h-24 w-20" : "h-32 w-28 sm:h-36 sm:w-32";

  return (
    <li className={`flex gap-4 py-5 ${busy ? "opacity-60" : ""}`} aria-busy={busy}>
      <Link href={`/producto/${line.product_slug}`} onClick={() => setOpen(false)} className={`relative ${size} shrink-0 overflow-hidden rounded-[var(--radius)] bg-subtle`}>
        {line.image && <Image src={mediaUrl(line.image.path)} alt={line.image.alt} fill sizes="8rem" className="object-cover" />}
      </Link>
      <div className="flex min-w-0 flex-1 flex-col">
        <div className="flex items-start justify-between gap-3">
          <div className="min-w-0">
            <Link href={`/producto/${line.product_slug}`} onClick={() => setOpen(false)} className="line-clamp-2 font-medium hover:underline">
              {line.name}
            </Link>
            {line.variant_label && <p className="mt-0.5 text-sm text-muted">{line.variant_label}</p>}
          </div>
          <p className="shrink-0 text-right font-semibold tabular-nums">{formatMoney(line.line_total)}</p>
        </div>

        <p className="mt-1 text-xs text-muted tabular-nums">
          {formatMoney(line.unit_price)} c/u
          {line.compare_at && <s className="ml-2">{formatMoney(line.compare_at)}</s>}
        </p>
        {(line.discount ?? 0) > 0 && line.purchasable && (
          <p className="mt-1 text-xs font-medium text-accent tabular-nums">Descuento −{formatMoney(line.discount ?? 0)}</p>
        )}
        {line.previous_unit_price !== null && line.purchasable && (
          <p className="mt-1 text-xs font-medium text-sale">Antes {formatMoney(line.previous_unit_price)}: el precio cambió desde que lo agregaste.</p>
        )}
        {!line.purchasable && <p className="mt-1 text-xs font-medium text-sale">Ya no está disponible. Quítalo para continuar.</p>}
        {line.purchasable && line.max_quantity !== null && line.quantity > line.max_quantity && (
          <p className="mt-1 text-xs font-medium text-sale">Solo quedan {line.max_quantity} unidades.</p>
        )}

        <div className="mt-auto flex items-center justify-between pt-3">
          {line.purchasable ? (
            <div className="inline-flex items-center rounded-full border border-border">
              <button type="button" disabled={busy} onClick={() => change(line.quantity - 1)} className="flex h-9 w-9 items-center justify-center rounded-full hover:bg-subtle" aria-label={`Quitar una unidad de ${line.name}`}>
                −
              </button>
              <span className="min-w-8 text-center text-sm tabular-nums" aria-live="polite">
                <span className="sr-only">Cantidad: </span>
                {line.quantity}
              </span>
              <button
                type="button"
                disabled={busy || line.quantity >= max}
                onClick={() => change(line.quantity + 1)}
                className="flex h-9 w-9 items-center justify-center rounded-full hover:bg-subtle disabled:opacity-40"
                aria-label={`Agregar una unidad de ${line.name}`}
              >
                +
              </button>
            </div>
          ) : (
            <span />
          )}
          <button type="button" disabled={busy} onClick={() => change(0)} className="text-sm text-muted underline-offset-4 hover:text-fg hover:underline">
            Quitar
          </button>
        </div>
      </div>
    </li>
  );
}
