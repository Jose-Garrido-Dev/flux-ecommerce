"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import type { CustomerProfile } from "@/lib/types";
import { bff } from "@/lib/client-api";
import { buttonClass } from "@/components/ui/Button";
import { Alert, Field, fieldAria } from "@/components/ui/Field";
import { useCart } from "@/components/cart/CartProvider";

export function ProfileForm({ profile }: { profile: CustomerProfile }) {
  const router = useRouter();
  const [form, setForm] = useState({
    first_name: profile.first_name,
    last_name: profile.last_name,
    phone: profile.phone ?? "",
    rut: profile.rut ?? "",
    marketing_opt_in: profile.marketing_opt_in,
  });
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [status, setStatus] = useState<{ ok: boolean; text: string } | null>(null);
  const [sending, setSending] = useState(false);
  const set = (key: keyof typeof form) => (e: React.ChangeEvent<HTMLInputElement>) =>
    setForm({ ...form, [key]: e.target.type === "checkbox" ? e.target.checked : e.target.value });

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSending(true);
    setStatus(null);
    const res = await bff("me", { method: "PATCH", body: { ...form, phone: form.phone || null, rut: form.rut || null } });
    setSending(false);
    setErrors(res.errors);
    if (res.ok) {
      setStatus({ ok: true, text: "Tus datos se guardaron." });
      router.refresh();
    } else if (Object.keys(res.errors).length === 0) {
      setStatus({ ok: false, text: res.message ?? "No se pudo guardar." });
    }
  };

  return (
    <form onSubmit={submit} noValidate className="mt-6 grid gap-5 sm:grid-cols-2">
      {status && (
        <div className="sm:col-span-2">
          <Alert tone={status.ok ? "ok" : "error"}>{status.text}</Alert>
        </div>
      )}
      <Field id="perfil-correo" label="Correo electrónico" className="sm:col-span-2" hint="Para cambiar el correo, escríbenos.">
        <input {...fieldAria("perfil-correo", undefined, "ayuda")} className="input opacity-70" value={profile.email} readOnly />
      </Field>
      <Field id="perfil-nombre" label="Nombre" error={errors.first_name}>
        <input {...fieldAria("perfil-nombre", errors.first_name)} className="input" value={form.first_name} onChange={set("first_name")} autoComplete="given-name" maxLength={80} />
      </Field>
      <Field id="perfil-apellido" label="Apellido" error={errors.last_name}>
        <input {...fieldAria("perfil-apellido", errors.last_name)} className="input" value={form.last_name} onChange={set("last_name")} autoComplete="family-name" maxLength={80} />
      </Field>
      <Field id="perfil-telefono" label="Teléfono" error={errors.phone}>
        <input {...fieldAria("perfil-telefono", errors.phone)} className="input" type="tel" value={form.phone} onChange={set("phone")} autoComplete="tel" maxLength={30} />
      </Field>
      <Field id="perfil-rut" label="RUT (opcional)" error={errors.rut}>
        <input {...fieldAria("perfil-rut", errors.rut)} className="input" value={form.rut} onChange={set("rut")} maxLength={12} placeholder="12.345.678-5" />
      </Field>
      <label className="flex items-start gap-3 text-sm sm:col-span-2">
        <input type="checkbox" checked={form.marketing_opt_in} onChange={set("marketing_opt_in")} className="mt-0.5 h-4 w-4 accent-[var(--color-accent)]" />
        <span>Quiero recibir novedades y ofertas por correo.</span>
      </label>
      <button type="submit" disabled={sending} className={buttonClass("primary", "sm:col-span-2 sm:justify-self-start")}>
        {sending ? "Guardando…" : "Guardar cambios"}
      </button>
    </form>
  );
}

export function PasswordForm() {
  const empty = { current_password: "", password: "", password_confirmation: "" };
  const [form, setForm] = useState(empty);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [status, setStatus] = useState<{ ok: boolean; text: string } | null>(null);
  const [sending, setSending] = useState(false);
  const set = (key: keyof typeof form) => (e: React.ChangeEvent<HTMLInputElement>) => setForm({ ...form, [key]: e.target.value });

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSending(true);
    setStatus(null);
    const res = await bff<{ message: string }>("me/password", { method: "PUT", body: form });
    setSending(false);
    setErrors(res.errors);
    if (res.ok) {
      setForm(empty);
      setStatus({ ok: true, text: res.data?.message ?? "Contraseña actualizada." });
    } else if (Object.keys(res.errors).length === 0) {
      setStatus({ ok: false, text: res.message ?? "No se pudo cambiar la contraseña." });
    }
  };

  return (
    <form onSubmit={submit} noValidate className="mt-6 grid gap-5">
      {status && <Alert tone={status.ok ? "ok" : "error"}>{status.text}</Alert>}
      <Field id="clave-actual" label="Contraseña actual" error={errors.current_password}>
        <input {...fieldAria("clave-actual", errors.current_password)} className="input" type="password" autoComplete="current-password" value={form.current_password} onChange={set("current_password")} />
      </Field>
      <Field id="clave-nueva" label="Nueva contraseña" error={errors.password} hint="Mínimo 8 caracteres, con letras y números.">
        <input {...fieldAria("clave-nueva", errors.password, "ayuda")} className="input" type="password" autoComplete="new-password" value={form.password} onChange={set("password")} />
      </Field>
      <Field id="clave-repetir" label="Repite la nueva contraseña" error={errors.password_confirmation}>
        <input {...fieldAria("clave-repetir", errors.password_confirmation)} className="input" type="password" autoComplete="new-password" value={form.password_confirmation} onChange={set("password_confirmation")} />
      </Field>
      <button type="submit" disabled={sending} className={buttonClass("secondary", "justify-self-start")}>
        {sending ? "Guardando…" : "Cambiar contraseña"}
      </button>
    </form>
  );
}

export function LogoutButton() {
  const router = useRouter();
  const { refresh } = useCart();
  const [sending, setSending] = useState(false);

  return (
    <button
      type="button"
      disabled={sending}
      onClick={async () => {
        setSending(true);
        await bff("auth/logout", { method: "POST", body: {} });
        await refresh();
        router.push("/cuenta");
        router.refresh();
      }}
      className={buttonClass("secondary")}
    >
      {sending ? "Saliendo…" : "Cerrar sesión"}
    </button>
  );
}

export function VerifyBanner({ email }: { email: string }) {
  const [text, setText] = useState<string | null>(null);
  const [sending, setSending] = useState(false);

  return (
    <Alert tone="info">
      <p>
        Confirma tu correo <strong>{email}</strong> con el enlace que te enviamos. Al confirmarlo, verás aquí también los pedidos que hiciste como invitado con ese correo.
      </p>
      {text ? (
        <p className="mt-2 font-medium">{text}</p>
      ) : (
        <button
          type="button"
          disabled={sending}
          className="mt-2 font-medium underline underline-offset-4"
          onClick={async () => {
            setSending(true);
            const res = await bff<{ message: string }>("auth/resend-verification", { method: "POST", body: {} });
            setSending(false);
            setText(res.ok ? (res.data?.message ?? "Te enviamos un nuevo enlace.") : (res.message ?? "No se pudo enviar."));
          }}
        >
          {sending ? "Enviando…" : "Enviar el enlace otra vez"}
        </button>
      )}
    </Alert>
  );
}
