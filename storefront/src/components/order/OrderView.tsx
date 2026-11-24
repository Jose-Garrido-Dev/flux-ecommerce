import Image from "next/image";
import type { OrderDetail } from "@/lib/types";
import { formatMoney, mediaUrl } from "@/lib/format";
import { PayButton } from "./PayButton";
import { ShipmentCard } from "./ShipmentCard";
import { OrderActions } from "./OrderActions";

const dateFormat = new Intl.DateTimeFormat("es-CL", { dateStyle: "long", timeStyle: "short", timeZone: "America/Santiago" });

export function formatDate(iso: string | null): string {
  return iso ? dateFormat.format(new Date(iso)) : "—";
}

/** Detalle de un pedido: estado, datos de transferencia (si falta pagar), productos y totales. */
export function OrderView({ order, orderToken, apiBase }: { order: OrderDetail; orderToken?: string; apiBase?: string }) {
  const transfer = order.payment.transfer;
  const pay = order.payment;
  const address = order.shipping.address;

  return (
    <div className="grid gap-10 lg:grid-cols-[1fr_24rem] lg:gap-16">
      <div className="space-y-10">
        <div className="flex flex-wrap gap-3">
          <Badge>{order.status_label}</Badge>
          <Badge tone={order.payment_status === "paid" ? "ok" : order.payment_status === "pending" ? "warn" : "muted"}>Pago: {order.payment_status_label}</Badge>
          {pay.status === "failed" || pay.status === "aborted" ? <Badge tone="warn">Último intento: {pay.status_label}</Badge> : null}
          {order.fulfillment_status !== "unfulfilled" && order.status !== "cancelled" ? (
            <Badge tone={order.fulfillment_status === "delivered" ? "ok" : "muted"}>Despacho: {order.fulfillment_status_label}</Badge>
          ) : null}
        </div>

        {pay.can_pay && orderToken && (
          <section aria-labelledby="pagar-titulo" className="rounded-[var(--radius)] border-2 border-accent bg-surface p-6">
            <h2 id="pagar-titulo" className="font-display text-3xl">
              {pay.status === "pending" ? `Paga ${formatMoney(order.grand_total)} con ${pay.method}` : "Tu pago no se completó"}
            </h2>
            <p className="mt-2 text-sm text-muted">
              {pay.status !== "pending" && pay.failure_reason ? `${pay.failure_reason} ` : ""}
              Tus productos siguen reservados hasta el {formatDate(pay.expires_at)} (hora de Chile). Puedes intentarlo de nuevo con la misma u otra tarjeta.
            </p>
            <div className="mt-5">
              <PayButton orderToken={orderToken} label={pay.status === "pending" ? `Pagar con ${pay.method}` : "Intentar pagar de nuevo"} />
            </div>
          </section>
        )}

        {transfer && (
          <section aria-labelledby="pago-titulo" className="rounded-[var(--radius)] border-2 border-accent bg-surface p-6">
            <h2 id="pago-titulo" className="font-display text-3xl">
              Transfiere {formatMoney(order.grand_total)}
            </h2>
            <p className="mt-2 text-sm text-muted">
              Reservamos tus productos hasta el {formatDate(order.payment.expires_at)} (hora de Chile). Si no recibimos el pago antes, el pedido se anula y los productos vuelven a la venta.
            </p>
            <dl className="mt-6 grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
              <Row label="Banco" value={transfer.bank_name} />
              <Row label="Tipo de cuenta" value={transfer.account_type} />
              <Row label="Número de cuenta" value={transfer.account_number} />
              <Row label="Titular" value={transfer.account_holder} />
              <Row label="RUT" value={transfer.account_rut} />
              <Row label="Correo para el comprobante" value={transfer.account_email} />
              <Row label="Monto" value={formatMoney(order.grand_total)} />
              <Row label="Asunto o comentario" value={`Pedido ${order.number}`} />
            </dl>
            {transfer.instructions && <p className="mt-6 whitespace-pre-line text-sm">{transfer.instructions}</p>}
          </section>
        )}

        {order.shipments.length > 0 && (
          <section aria-labelledby="despachos-titulo">
            <h2 id="despachos-titulo" className="label">
              {order.shipping.type === "pickup" ? "Retiro" : "Seguimiento del despacho"}
            </h2>
            <div className="mt-3 space-y-4">
              {order.shipments.map((s, i) => (
                <ShipmentCard key={i} shipment={s} index={i + 1} total={order.shipments.length} showFollowLink />
              ))}
            </div>
          </section>
        )}

        <section aria-labelledby="productos-titulo">
          <h2 id="productos-titulo" className="label">
            Productos
          </h2>
          <ul className="mt-3 divide-y divide-border border-y border-border">
            {order.items.map((item) => (
              <li key={item.sku} className="flex gap-4 py-4">
                <div className="relative h-20 w-16 shrink-0 overflow-hidden rounded-[var(--radius-sm)] bg-subtle">
                  {item.image && <Image src={mediaUrl(item.image.path)} alt={item.image.alt} fill sizes="4rem" className="object-cover" />}
                </div>
                <div className="min-w-0 flex-1">
                  <p className="font-medium">{item.name}</p>
                  {item.variant_label && <p className="text-sm text-muted">{item.variant_label}</p>}
                  <p className="mt-1 text-xs text-muted tabular-nums">
                    {item.quantity} × {formatMoney(item.unit_price)}
                  </p>
                </div>
                <p className="font-semibold tabular-nums">{formatMoney(item.line_total)}</p>
              </li>
            ))}
          </ul>
        </section>

        {apiBase && <OrderActions order={order} apiBase={apiBase} />}

        {order.events.length > 0 && (
          <section aria-labelledby="historial-titulo">
            <h2 id="historial-titulo" className="label">
              Historial
            </h2>
            <ol className="mt-3 space-y-3 border-l border-border pl-5 text-sm">
              {order.events.map((e, i) => (
                <li key={i} className="relative">
                  <span aria-hidden className="absolute -left-[1.6rem] top-1.5 h-2.5 w-2.5 rounded-full bg-accent" />
                  <p className="font-medium">{e.label}</p>
                  <p className="text-xs text-muted">{formatDate(e.at)}</p>
                </li>
              ))}
            </ol>
          </section>
        )}
      </div>

      <aside className="h-fit space-y-6 rounded-[var(--radius)] border border-border bg-surface p-6">
        <dl className="space-y-3 text-sm">
          <div className="flex justify-between">
            <dt className="text-muted">Productos</dt>
            <dd className="tabular-nums">{formatMoney(order.subtotal)}</dd>
          </div>
          {(order.discounts ?? []).map((d) => (
            <div key={`${d.label}-${d.target}`} className="flex justify-between text-accent">
              <dt>{d.target === "shipping" ? `${d.label} (despacho)` : d.label}</dt>
              <dd className="tabular-nums">−{formatMoney(d.amount)}</dd>
            </div>
          ))}
          <div className="flex justify-between">
            <dt className="text-muted">Despacho</dt>
            <dd className="tabular-nums">{order.shipping_total === 0 ? "Gratis" : formatMoney(order.shipping_total)}</dd>
          </div>
          <div className="flex justify-between border-t border-border pt-3 text-base font-semibold">
            <dt>Total</dt>
            <dd className="tabular-nums">{formatMoney(order.grand_total)}</dd>
          </div>
          <div className="flex justify-between text-xs text-muted">
            <dt>IVA incluido</dt>
            <dd className="tabular-nums">{formatMoney(order.tax_total)}</dd>
          </div>
        </dl>
        <div className="border-t border-border pt-5 text-sm">
          <p className="label">Entrega</p>
          <p className="mt-2 font-medium">{order.shipping.method ?? "—"}</p>
          {order.shipping.estimated_days && <p className="text-muted">Plazo estimado: {order.shipping.estimated_days}</p>}
          {order.shipping.pickup_address && <p className="mt-2 text-muted">Retiras en: {order.shipping.pickup_address}</p>}
          {address && (
            <p className="mt-2 text-muted">
              {address.recipient && (
                <>
                  {address.recipient}
                  <br />
                </>
              )}
              {address.street && (
                <>
                  {address.street} {address.number}
                  {address.apartment ? `, ${address.apartment}` : ""}
                  <br />
                </>
              )}
              {[address.commune, address.region].filter(Boolean).join(", ")}
            </p>
          )}
        </div>
        <div className="border-t border-border pt-5 text-sm">
          <p className="label">Pago</p>
          <p className="mt-2">{order.payment.method}</p>
          {pay.card_last4 && (
            <p className="text-muted">
              {pay.payment_type ?? "Tarjeta"} terminada en {pay.card_last4}
              {pay.installments ? `, ${pay.installments} cuotas` : ""}
            </p>
          )}
          {pay.authorization_code && <p className="text-muted">Código de autorización: {pay.authorization_code}</p>}
          {pay.refunded_amount > 0 && <p className="text-muted">Reembolsado: {formatMoney(pay.refunded_amount)}</p>}
          {order.document_type && <p className="text-muted">Documento: {order.document_type === "factura" ? "Factura" : "Boleta"}</p>}
        </div>
      </aside>
    </div>
  );
}

function Row({ label, value }: { label: string; value: string | null }) {
  if (!value) return null;
  return (
    <div>
      <dt className="text-muted">{label}</dt>
      <dd className="mt-0.5 select-all font-semibold">{value}</dd>
    </div>
  );
}

function Badge({ children, tone = "muted" }: { children: React.ReactNode; tone?: "ok" | "warn" | "muted" }) {
  const cls = tone === "ok" ? "bg-ok/15 text-ok" : tone === "warn" ? "bg-sale/12 text-sale" : "bg-subtle text-fg";
  return <span className={`rounded-full px-3 py-1 text-xs font-semibold ${cls}`}>{children}</span>;
}
