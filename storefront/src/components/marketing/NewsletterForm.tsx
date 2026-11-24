"use client";

import { useState } from "react";
import { bff } from "@/lib/client-api";

/** Suscripción al boletín con casilla de consentimiento. Se confirma por correo (doble confirmación). */
export function NewsletterForm() {
  const [email, setEmail] = useState("");
  const [consent, setConsent] = useState(false);
  const [busy, setBusy] = useState(false);
  const [done, setDone] = useState<string | null>(null);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [message, setMessage] = useState<string | null>(null);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (busy) return;
    setBusy(true);
    setErrors({});
    setMessage(null);
    const res = await bff<{ message: string }>("newsletter", { method: "POST", body: { email, consent, source: "footer" } });
    setBusy(false);
    if (res.ok && res.data) {
      setDone(res.data.message);
      return;
    }
    setErrors(res.errors);
    if (Object.keys(res.errors).length === 0) setMessage(res.message);
  };

  if (done) {
    return (
      <p role="status" className="max-w-sm text-sm">
        {done}
      </p>
    );
  }

  return (
    <form onSubmit={submit} noValidate className="max-w-sm">
      <label htmlFor="boletin-correo" className="text-sm font-semibold text-ink-muted">
        Recibe novedades y ofertas
      </label>
      <div className="mt-3 flex gap-2">
        <input
          id="boletin-correo"
          type="email"
          required
          maxLength={190}
          autoComplete="email"
          placeholder="tu@correo.cl"
          value={email}
          onChange={(e) => setEmail(e.target.value)}
          aria-invalid={errors.email ? true : undefined}
          aria-describedby={errors.email ? "boletin-error" : undefined}
          className="h-11 min-w-0 flex-1 rounded-[var(--radius-sm)] border border-white/20 bg-white/5 px-3 text-sm text-ink-fg placeholder:text-ink-muted focus:border-white/60 focus:outline-none"
        />
        <button type="submit" disabled={busy} className="h-11 shrink-0 rounded-[var(--radius-sm)] bg-ink-fg px-4 text-sm font-semibold text-ink disabled:opacity-60">
          {busy ? "Enviando…" : "Suscribirme"}
        </button>
      </div>
      {errors.email && (
        <p id="boletin-error" className="mt-2 text-xs text-sale">
          {errors.email}
        </p>
      )}
      <label className="mt-3 flex cursor-pointer items-start gap-2 text-xs text-ink-muted">
        <input type="checkbox" checked={consent} onChange={(e) => setConsent(e.target.checked)} className="mt-0.5 h-4 w-4 accent-[var(--color-accent)]" aria-invalid={errors.consent ? true : undefined} />
        <span>Acepto recibir correos de la tienda. Puedo darme de baja cuando quiera.</span>
      </label>
      {errors.consent && <p className="mt-1 text-xs text-sale">{errors.consent}</p>}
      {message && (
        <p role="alert" className="mt-2 text-xs text-sale">
          {message}
        </p>
      )}
    </form>
  );
}
