"use client";

import Link from "next/link";
import { useEffect, useRef, useState } from "react";
import { bff } from "@/lib/client-api";
import { buttonClass } from "@/components/ui/Button";
import { Alert, Field, fieldAria } from "@/components/ui/Field";

/** Pide el enlace para elegir una nueva contraseña. La respuesta es la misma exista o no la cuenta. */
export function ForgotForm() {
  const [email, setEmail] = useState("");
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [done, setDone] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);
  const [sending, setSending] = useState(false);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSending(true);
    setMessage(null);
    const res = await bff<{ message: string }>("auth/forgot-password", { method: "POST", body: { email } });
    setSending(false);
    setErrors(res.errors);
    if (res.ok) setDone(res.data?.message ?? "Revisa tu correo.");
    else if (Object.keys(res.errors).length === 0) setMessage(res.message);
  };

  if (done) return <Alert tone="ok">{done}</Alert>;

  return (
    <form onSubmit={submit} noValidate className="grid gap-5">
      {message && <Alert>{message}</Alert>}
      <Field id="recuperar-correo" label="Correo electrónico" error={errors.email}>
        <input {...fieldAria("recuperar-correo", errors.email)} className="input" type="email" autoComplete="email" required value={email} onChange={(e) => setEmail(e.target.value)} />
      </Field>
      <button type="submit" disabled={sending} className={buttonClass("primary", "h-12")}>
        {sending ? "Enviando…" : "Enviar enlace"}
      </button>
    </form>
  );
}

export function ResetForm({ token, email }: { token: string; email: string }) {
  const [form, setForm] = useState({ password: "", password_confirmation: "" });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [done, setDone] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);
  const [sending, setSending] = useState(false);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSending(true);
    setMessage(null);
    const res = await bff<{ message: string }>("auth/reset-password", { method: "POST", body: { token, email, ...form } });
    setSending(false);
    setErrors(res.errors);
    if (res.ok) setDone(res.data?.message ?? "Tu contraseña se cambió.");
    else if (res.errors.token || res.errors.email) setMessage(res.errors.token ?? res.errors.email);
    else if (Object.keys(res.errors).length === 0) setMessage(res.message);
  };

  if (done) {
    return (
      <div className="grid gap-6">
        <Alert tone="ok">{done}</Alert>
        <Link href="/cuenta" className={buttonClass("primary", "justify-self-start")}>
          Ingresar
        </Link>
      </div>
    );
  }

  return (
    <form onSubmit={submit} noValidate className="grid gap-5">
      {message && (
        <Alert>
          {message}{" "}
          <Link href="/cuenta/recuperar" className="underline">
            Pedir un enlace nuevo
          </Link>
        </Alert>
      )}
      <p className="text-sm text-muted">Cuenta: {email}</p>
      <Field id="nueva-clave" label="Nueva contraseña" error={errors.password} hint="Mínimo 8 caracteres, con letras y números.">
        <input {...fieldAria("nueva-clave", errors.password, "ayuda")} className="input" type="password" autoComplete="new-password" value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} />
      </Field>
      <Field id="nueva-clave2" label="Repite la nueva contraseña" error={errors.password_confirmation}>
        <input {...fieldAria("nueva-clave2", errors.password_confirmation)} className="input" type="password" autoComplete="new-password" value={form.password_confirmation} onChange={(e) => setForm({ ...form, password_confirmation: e.target.value })} />
      </Field>
      <button type="submit" disabled={sending} className={buttonClass("primary", "h-12")}>
        {sending ? "Guardando…" : "Guardar contraseña"}
      </button>
    </form>
  );
}

/** Confirma el correo apenas se abre el enlace (una sola vez, aunque React monte dos veces en desarrollo). */
export function VerifyEmail({ token }: { token: string }) {
  const [state, setState] = useState<{ ok: boolean; text: string } | null>(null);
  const started = useRef(false);

  useEffect(() => {
    if (started.current) return;
    started.current = true;
    bff<{ message: string }>("auth/verify-email", { method: "POST", body: { token } }).then((res) =>
      setState(res.ok ? { ok: true, text: res.data?.message ?? "Tu correo quedó confirmado." } : { ok: false, text: res.errors.token ?? res.message ?? "No se pudo confirmar." }),
    );
  }, [token]);

  if (!state) return <p className="text-muted">Confirmando tu correo…</p>;

  return (
    <div className="grid gap-6">
      <Alert tone={state.ok ? "ok" : "error"}>{state.text}</Alert>
      <Link href="/cuenta" className={buttonClass("primary", "justify-self-start")}>
        Ir a Mi cuenta
      </Link>
    </div>
  );
}
