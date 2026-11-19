import type { Price as PriceT } from "@/lib/types";
import { formatMoney } from "@/lib/format";

/** Precio con IVA incluido; si hay descuento muestra el precio anterior tachado. */
export function Price({ price, size = "md" }: { price: PriceT | null; size?: "md" | "lg" }) {
  if (!price) return <span className="text-sm text-muted">Precio no disponible</span>;

  const big = size === "lg";
  return (
    <span className="inline-flex flex-wrap items-baseline gap-x-2">
      {price.varies && <span className="text-sm text-muted">Desde</span>}
      <span className={`${big ? "text-2xl" : "text-base"} font-semibold ${price.compare_at ? "text-sale" : ""}`}>
        {formatMoney(price.amount, price.currency)}
      </span>
      {price.compare_at && (
        <>
          <span className="sr-only">Precio anterior:</span>
          <s className={`${big ? "text-base" : "text-sm"} text-muted`}>{formatMoney(price.compare_at, price.currency)}</s>
        </>
      )}
    </span>
  );
}
