import Link from "next/link";
import type { Shipment } from "@/lib/types";

const dateFormat = new Intl.DateTimeFormat("es-CL", { dateStyle: "long", timeStyle: "short", timeZone: "America/Santiago" });
const date = (iso: string | null) => (iso ? dateFormat.format(new Date(iso)) : "—");

const DONE = ["delivered", "picked_up"];
const PROBLEM = ["failed_attempt", "returned"];

/** Un despacho: estado, courier, número de seguimiento, productos y línea de tiempo. */
export function ShipmentCard({ shipment, index, total, showFollowLink = false }: { shipment: Shipment; index?: number; total?: number; showFollowLink?: boolean }) {
  const tone = DONE.includes(shipment.status) ? "bg-ok/15 text-ok" : PROBLEM.includes(shipment.status) ? "bg-sale/12 text-sale" : "bg-accent/12 text-accent";
  const events = [...shipment.events].reverse();

  return (
    <article className="rounded-[var(--radius)] border border-border p-5">
      <header className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <p className="font-medium">
            {total && total > 1 ? `Despacho ${index} de ${total}` : "Despacho"} · {shipment.carrier}
          </p>
          {shipment.tracking_number && (
            <p className="mt-1 text-sm text-muted">
              N.º de seguimiento: <span className="select-all font-semibold text-fg">{shipment.tracking_number}</span>
            </p>
          )}
        </div>
        <span className={`rounded-full px-3 py-1 text-xs font-semibold ${tone}`}>{shipment.status_label}</span>
      </header>

      {shipment.items.length > 0 && (
        <ul className="mt-4 space-y-1 text-sm">
          {shipment.items.map((item, i) => (
            <li key={i} className="flex justify-between gap-4">
              <span>
                {item.name}
                {item.variant_label ? <span className="text-muted"> · {item.variant_label}</span> : null}
              </span>
              <span className="tabular-nums text-muted">× {item.quantity}</span>
            </li>
          ))}
        </ul>
      )}

      {events.length > 0 && (
        <ol className="mt-5 space-y-3 border-l border-border pl-5 text-sm">
          {events.map((e, i) => (
            <li key={i} className="relative">
              <span aria-hidden className={`absolute -left-[1.6rem] top-1.5 h-2.5 w-2.5 rounded-full ${i === 0 ? "bg-accent" : "bg-border"}`} />
              <p className="font-medium">{e.label}</p>
              {e.description && <p className="text-muted">{e.description}</p>}
              <p className="text-xs text-muted">
                {date(e.at)}
                {e.location ? ` · ${e.location}` : ""}
              </p>
            </li>
          ))}
        </ol>
      )}

      {(shipment.tracking_url || (showFollowLink && shipment.follow_token)) && (
        <div className="mt-5 flex flex-wrap gap-x-6 gap-y-2 text-sm font-medium">
          {shipment.tracking_url && (
            <a href={shipment.tracking_url} target="_blank" rel="noopener noreferrer" className="underline underline-offset-4 hover:text-accent">
              Ver en la página de {shipment.carrier}
            </a>
          )}
          {showFollowLink && shipment.follow_token && (
            <Link href={`/seguimiento/${shipment.follow_token}`} className="underline underline-offset-4 hover:text-accent">
              Enlace para compartir el seguimiento
            </Link>
          )}
        </div>
      )}
    </article>
  );
}
