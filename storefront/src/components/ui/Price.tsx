import type { Price as PriceT } from "@/lib/types";
import { formatMoney } from "@/lib/format";

/** Precio con IVA incluido; si hay descuento muestra el precio anterior tachado. */
export function Price({ price, size = "md", tone = "default" }: { price: PriceT | null; size?: "md" | "lg"; tone?: "default" | "ink" }) {
  if (!price) return <span className="text-sm text-muted">Precio no disponible</span>;

  const big = size === "lg";
  const muted = tone === "ink" ? "text-ink-muted" : "text-muted";
  return (
    <span className="inline-flex flex-wrap items-baseline gap-x-2">
      {price.varies && <span className={`text-xs ${muted}`}>Desde</span>}
      <span className={`${big ? "text-3xl font-display" : "text-[0.95rem] font-semibold"} tabular-nums ${price.compare_at ? (tone === "ink" ? "text-hot" : "text-sale") : ""}`}>
        {formatMoney(price.amount, price.currency)}
      </span>
      {price.compare_at && (
        <>
          <span className="sr-only">Precio anterior:</span>
          <s className={`${big ? "text-base" : "text-sm"} ${muted} tabular-nums`}>{formatMoney(price.compare_at, price.currency)}</s>
        </>
      )}
    </span>
  );
}
