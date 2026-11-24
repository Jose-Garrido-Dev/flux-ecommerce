"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import type { OrderDetail, ReturnSummary } from "@/lib/types";
import { bff } from "@/lib/client-api";
import { formatMoney } from "@/lib/format";
import { buttonClass } from "@/components/ui/Button";
import { Alert, Field, fieldAria } from "@/components/ui/Field";

const dateFormat = new Intl.DateTimeFormat("es-CL", { dateStyle: "long", timeZone: "America/Santiago" });

/**
 * Lo que el cliente puede hacer con su pedido: cancelarlo mientras no pague, pedir una devolución
 * o un cambio después de recibirlo, y seguir (o anular) sus solicitudes.
 * `apiBase` es "orders/{token}" (enlace del correo) o "me/orders/{uuid}" (con sesión).
 */
export function OrderActions({ order, apiBase }: { order: OrderDetail; apiBase: string }) {
  const showReturns = order.returns.length > 0 || order.return_policy.can_request || order.status === "completed";
  if (!order.can_cancel && !showReturns) return null;

  return (
    <div className="space-y-10">
      {order.can_cancel && <CancelOrder apiBase={apiBase} />}
      {showReturns && <Returns order={order} apiBase={apiBase} />}
    </div>
  );
}

function CancelOrder({ apiBase }: { apiBase: string }) {
  const router = useRouter();
  const [confirming, setConfirming] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const cancel = async () => {
    setBusy(true);
    setError(null);
    const res = await bff(`${apiBase}/cancel`, { method: "POST", body: {} });
    setBusy(false);
    if (!res.ok) {
      setError(Object.values(res.errors)[0] ?? res.message);
      return;
    }
    setConfirming(false);
    router.refresh();
  };

  return (
    <section aria-labelledby="cancelar-titulo" className="rounded-[var(--radius)] border border-border p-5">
      <h2 id="cancelar-titulo" className="label">
        ¿Ya no lo quieres?
      </h2>
      <p className="mt-2 text-sm text-muted">Mientras no pagues puedes cancelar el pedido. Liberamos los productos y no se hace ningún cargo.</p>
      {confirming ? (
        <div className="mt-4 flex flex-wrap gap-3">
          <button type="button" onClick={cancel} disabled={busy} className={buttonClass("primary", "h-11 px-6")}>
            {busy ? "Cancelando…" : "Sí, cancelar el pedido"}
          </button>
          <button type="button" onClick={() => setConfirming(false)} className={buttonClass("secondary", "h-11 px-6")}>
            No, mantenerlo
          </button>
        </div>
      ) : (
        <button type="button" onClick={() => setConfirming(true)} className="mt-4 text-sm font-medium underline underline-offset-4 hover:text-sale">
          Cancelar el pedido
        </button>
      )}
      {error && (
        <p role="alert" className="mt-3 text-sm text-sale">
          {error}
        </p>
      )}
    </section>
  );
}

function Returns({ order, apiBase }: { order: OrderDetail; apiBase: string }) {
  const [open, setOpen] = useState(false);
  const policy = order.return_policy;

  return (
    <section aria-labelledby="devoluciones-titulo">
      <h2 id="devoluciones-titulo" className="label">
        Devoluciones y cambios
      </h2>
      {order.returns.length > 0 && (
        <div className="mt-3 space-y-4">
          {order.returns.map((r) => (
            <ReturnCard key={r.uuid} item={r} apiBase={apiBase} />
          ))}
        </div>
      )}
      {policy.can_request ? (
        open ? (
          <ReturnForm order={order} apiBase={apiBase} onDone={() => setOpen(false)} />
        ) : (
          <div className="mt-4">
            <p className="text-sm text-muted">
              {policy.deadline ? `Puedes pedir una devolución o un cambio hasta el ${dateFormat.format(new Date(policy.deadline))}.` : `Tienes ${policy.days} días desde la entrega para pedir una devolución o un cambio.`}
            </p>
            <button type="button" onClick={() => setOpen(true)} className={buttonClass("secondary", "mt-4 h-11 px-6")}>
              Pedir una devolución o cambio
            </button>
          </div>
        )
      ) : (
        policy.blocked_reason && order.returns.length === 0 && <p className="mt-3 text-sm text-muted">{policy.blocked_reason}</p>
      )}
    </section>
  );
}

function ReturnCard({ item, apiBase }: { item: ReturnSummary; apiBase: string }) {
  const router = useRouter();
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const tone =
    item.status === "rejected" ? "bg-sale/12 text-sale" : ["processed", "closed"].includes(item.status) ? "bg-ok/15 text-ok" : item.status === "cancelled" ? "bg-subtle text-muted" : "bg-accent/12 text-accent";

  const cancel = async () => {
    setBusy(true);
    setError(null);
    const res = await bff(`${apiBase}/returns/${item.uuid}/cancel`, { method: "POST", body: {} });
    setBusy(false);
    if (!res.ok) setError(Object.values(res.errors)[0] ?? res.message);
    else router.refresh();
  };

  return (
    <article className="rounded-[var(--radius)] border border-border p-5 text-sm">
      <header className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <p className="font-medium">
            {item.type_label} {item.label}
          </p>
          <p className="text-muted">{item.reason_label}</p>
        </div>
        <span className={`rounded-full px-3 py-1 text-xs font-semibold ${tone}`}>{item.status_label}</span>
      </header>
      <ul className="mt-3 space-y-1">
        {item.items.map((i, n) => (
          <li key={n} className="flex justify-between gap-4">
            <span>
              {i.name}
              {i.variant_label ? <span className="text-muted"> · {i.variant_label}</span> : null}
            </span>
            <span className="tabular-nums text-muted">× {i.quantity}</span>
          </li>
        ))}
      </ul>
      {item.instructions && (
        <div className="mt-4 rounded-[var(--radius-sm)] bg-subtle p-4">
          <p className="font-medium">Cómo enviarnos el producto</p>
          <p className="mt-1 whitespace-pre-line">{item.instructions}</p>
        </div>
      )}
      {item.rejection_reason && <p className="mt-4">Motivo: {item.rejection_reason}</p>}
      {item.resolution && <p className="mt-4">{item.resolution}</p>}
      {item.refund_amount ? <p className="mt-1 text-muted">Reembolso: {formatMoney(item.refund_amount)}</p> : null}
      {item.can_cancel && (
        <button type="button" onClick={cancel} disabled={busy} className="mt-4 font-medium underline underline-offset-4 hover:text-sale">
          {busy ? "Anulando…" : "Anular esta solicitud"}
        </button>
      )}
      {error && (
        <p role="alert" className="mt-3 text-sale">
          {error}
        </p>
      )}
    </article>
  );
}

function ReturnForm({ order, apiBase, onDone }: { order: OrderDetail; apiBase: string; onDone: () => void }) {
  const router = useRouter();
  const policy = order.return_policy;
  const items = order.items.filter((i) => i.returnable_quantity > 0);
  const [quantities, setQuantities] = useState<Record<number, number>>({});
  const [type, setType] = useState<"return" | "exchange">("return");
  const [reason, setReason] = useState("");
  const [notes, setNotes] = useState("");
  const [busy, setBusy] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [message, setMessage] = useState<string | null>(null);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setErrors({});
    setMessage(null);
    const chosen = Object.entries(quantities)
      .filter(([, q]) => q > 0)
      .map(([id, quantity]) => ({ id: Number(id), quantity }));
    if (chosen.length === 0) {
      setErrors({ items: "Elige al menos un producto." });
      return;
    }
    setBusy(true);
    const res = await bff(`${apiBase}/returns`, { method: "POST", body: { type, reason, notes: notes || undefined, items: chosen } });
    setBusy(false);
    if (!res.ok) {
      setErrors(res.errors);
      setMessage(Object.keys(res.errors).length ? null : res.message);
      return;
    }
    onDone();
    router.refresh();
  };

  return (
    <form onSubmit={submit} className="mt-4 space-y-6 rounded-[var(--radius)] border border-border p-5" noValidate>
      {policy.policy && <p className="whitespace-pre-line text-sm text-muted">{policy.policy}</p>}

      <fieldset>
        <legend className="text-sm font-medium">¿Qué productos quieres devolver?</legend>
        <ul className="mt-3 divide-y divide-border border-y border-border">
          {items.map((i) => (
            <li key={i.id} className="flex items-center justify-between gap-4 py-3 text-sm">
              <label htmlFor={`dev-${i.id}`} className="flex-1">
                {i.name}
                {i.variant_label ? <span className="text-muted"> · {i.variant_label}</span> : null}
              </label>
              <select
                id={`dev-${i.id}`}
                className="input w-24"
                value={quantities[i.id] ?? 0}
                onChange={(e) => setQuantities({ ...quantities, [i.id]: Number(e.target.value) })}
              >
                {Array.from({ length: i.returnable_quantity + 1 }, (_, n) => (
                  <option key={n} value={n}>
                    {n}
                  </option>
                ))}
              </select>
            </li>
          ))}
        </ul>
        {errors.items && <p className="field-error mt-2">{errors.items}</p>}
      </fieldset>

      {policy.allow_exchange && (
        <fieldset>
          <legend className="text-sm font-medium">¿Qué prefieres?</legend>
          <div className="mt-2 flex flex-wrap gap-6 text-sm">
            <label className="flex items-center gap-2">
              <input type="radio" name="tipo" checked={type === "return"} onChange={() => setType("return")} className="accent-[var(--color-accent)]" />
              Devolución del dinero
            </label>
            <label className="flex items-center gap-2">
              <input type="radio" name="tipo" checked={type === "exchange"} onChange={() => setType("exchange")} className="accent-[var(--color-accent)]" />
              Cambio por otro producto
            </label>
          </div>
        </fieldset>
      )}

      <Field id="dev-motivo" label="Motivo" error={errors.reason}>
        <select {...fieldAria("dev-motivo", errors.reason)} className="input" value={reason} onChange={(e) => setReason(e.target.value)} required>
          <option value="">Elige un motivo</option>
          {Object.entries(policy.reasons).map(([code, label]) => (
            <option key={code} value={code}>
              {label}
            </option>
          ))}
        </select>
      </Field>

      <Field id="dev-comentario" label="Cuéntanos más (opcional)" error={errors.notes} hint={type === "exchange" ? "Indica qué talla, color o producto quieres a cambio." : undefined}>
        <textarea {...fieldAria("dev-comentario", errors.notes)} className="input min-h-24" value={notes} onChange={(e) => setNotes(e.target.value)} maxLength={2000} />
      </Field>

      {message && <Alert>{message}</Alert>}

      <div className="flex flex-wrap gap-3">
        <button type="submit" disabled={busy} className={buttonClass("primary", "h-11 px-6")}>
          {busy ? "Enviando…" : "Enviar solicitud"}
        </button>
        <button type="button" onClick={onDone} className={buttonClass("secondary", "h-11 px-6")}>
          Volver
        </button>
      </div>
      <p className="text-xs text-muted">Revisaremos tu solicitud y te responderemos por correo con los pasos para enviarnos el producto.</p>
    </form>
  );
}
