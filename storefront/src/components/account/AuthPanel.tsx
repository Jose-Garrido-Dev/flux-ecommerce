"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { bff } from "@/lib/client-api";
import { buttonClass } from "@/components/ui/Button";
import { Alert, Field, fieldAria } from "@/components/ui/Field";
import { useCart } from "@/components/cart/CartProvider";

/** Ingreso y creación de cuenta. Al entrar, el carrito de invitado se une al de la cuenta. */
export function AuthPanel({ redirectTo }: { redirectTo: string }) {
  return (
    <div className="mt-12 grid gap-10 lg:grid-cols-2 lg:gap-16">
      <LoginForm redirectTo={redirectTo} />
      <RegisterForm redirectTo={redirectTo} />
    </div>
  );
}

function useAfterLogin(redirectTo: string) {
  const router = useRouter();
  const { refresh } = useCart();
  return async () => {
    await refresh();
    router.push(redirectTo);
    router.refresh();
  };
}

function LoginForm({ redirectTo }: { redirectTo: string }) {
  const done = useAfterLogin(redirectTo);
  const [form, setForm] = useState({ email: "", password: "" });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [message, setMessage] = useState<string | null>(null);
  const [sending, setSending] = useState(false);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSending(true);
    setMessage(null);
    const res = await bff("auth/login", { method: "POST", body: form });
    if (res.ok) return done();
    setSending(false);
    setErrors(res.errors);
    if (Object.keys(res.errors).length === 0) setMessage(res.message);
  };

  return (
    <section aria-labelledby="ingresar-titulo" className="rounded-[var(--radius)] border border-border bg-surface p-6 sm:p-8">
      <h2 id="ingresar-titulo" className="font-display text-4xl">
        Ingresar
      </h2>
      <form onSubmit={submit} noValidate className="mt-6 grid gap-5">
        {message && <Alert>{message}</Alert>}
        <Field id="login-correo" label="Correo electrónico" error={errors.email}>
          <input {...fieldAria("login-correo", errors.email)} className="input" type="email" autoComplete="email" required value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} />
        </Field>
        <Field id="login-clave" label="Contraseña" error={errors.password}>
          <input {...fieldAria("login-clave", errors.password)} className="input" type="password" autoComplete="current-password" required value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} />
        </Field>
        <button type="submit" disabled={sending} className={buttonClass("primary", "h-12")}>
          {sending ? "Ingresando…" : "Ingresar"}
        </button>
        <Link href="/cuenta/recuperar" className="text-center text-sm underline underline-offset-4">
          ¿Olvidaste tu contraseña?
        </Link>
      </form>
    </section>
  );
}

function RegisterForm({ redirectTo }: { redirectTo: string }) {
  const done = useAfterLogin(redirectTo);
  const [form, setForm] = useState({ first_name: "", last_name: "", email: "", password: "", password_confirmation: "", accepts_terms: false, marketing_opt_in: false });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [message, setMessage] = useState<string | null>(null);
  const [sending, setSending] = useState(false);
  const set = (key: keyof typeof form) => (e: React.ChangeEvent<HTMLInputElement>) =>
    setForm({ ...form, [key]: e.target.type === "checkbox" ? e.target.checked : e.target.value });

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSending(true);
    setMessage(null);
    const res = await bff("auth/register", { method: "POST", body: form });
    if (res.ok) return done();
    setSending(false);
    setErrors(res.errors);
    if (Object.keys(res.errors).length === 0) setMessage(res.message);
  };

  return (
    <section aria-labelledby="registro-titulo" className="p-0 sm:p-2">
      <h2 id="registro-titulo" className="font-display text-4xl">
        Crear cuenta
      </h2>
      <p className="mt-2 text-sm text-muted">Guarda tus direcciones y revisa tus pedidos. También puedes comprar sin cuenta.</p>
      <form onSubmit={submit} noValidate className="mt-6 grid gap-5 sm:grid-cols-2">
        {message && (
          <div className="sm:col-span-2">
            <Alert>{message}</Alert>
          </div>
        )}
        <Field id="reg-nombre" label="Nombre" error={errors.first_name}>
          <input {...fieldAria("reg-nombre", errors.first_name)} className="input" autoComplete="given-name" required maxLength={80} value={form.first_name} onChange={set("first_name")} />
        </Field>
        <Field id="reg-apellido" label="Apellido" error={errors.last_name}>
          <input {...fieldAria("reg-apellido", errors.last_name)} className="input" autoComplete="family-name" required maxLength={80} value={form.last_name} onChange={set("last_name")} />
        </Field>
        <Field id="reg-correo" label="Correo electrónico" error={errors.email} className="sm:col-span-2">
          <input {...fieldAria("reg-correo", errors.email)} className="input" type="email" autoComplete="email" required maxLength={190} value={form.email} onChange={set("email")} />
        </Field>
        <Field id="reg-clave" label="Contraseña" error={errors.password} hint="Mínimo 8 caracteres, con letras y números.">
          <input {...fieldAria("reg-clave", errors.password, "ayuda")} className="input" type="password" autoComplete="new-password" required value={form.password} onChange={set("password")} />
        </Field>
        <Field id="reg-clave2" label="Repite la contraseña" error={errors.password_confirmation}>
          <input {...fieldAria("reg-clave2", errors.password_confirmation)} className="input" type="password" autoComplete="new-password" required value={form.password_confirmation} onChange={set("password_confirmation")} />
        </Field>
        <label className="flex items-start gap-3 text-sm sm:col-span-2">
          <input type="checkbox" checked={form.accepts_terms} onChange={set("accepts_terms")} className="mt-0.5 h-4 w-4 accent-[var(--color-accent)]" />
          <span>Acepto los términos y condiciones y la política de privacidad.</span>
        </label>
        {errors.accepts_terms && <p className="field-error -mt-3 sm:col-span-2">{errors.accepts_terms}</p>}
        <label className="flex items-start gap-3 text-sm sm:col-span-2">
          <input type="checkbox" checked={form.marketing_opt_in} onChange={set("marketing_opt_in")} className="mt-0.5 h-4 w-4 accent-[var(--color-accent)]" />
          <span>Quiero recibir novedades y ofertas por correo (opcional).</span>
        </label>
        <button type="submit" disabled={sending} className={buttonClass("primary", "h-12 sm:col-span-2")}>
          {sending ? "Creando cuenta…" : "Crear cuenta"}
        </button>
      </form>
    </section>
  );
}
