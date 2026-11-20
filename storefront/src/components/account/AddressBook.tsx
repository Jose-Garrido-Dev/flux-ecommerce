"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import type { CustomerAddress, GeoRegion } from "@/lib/types";
import { bff } from "@/lib/client-api";
import { buttonClass } from "@/components/ui/Button";
import { Alert, Field, fieldAria } from "@/components/ui/Field";
import { AddressFields, emptyAddress, type AddressInput } from "@/components/checkout/AddressFields";

type Editing = { id: number | null; label: string; is_default: boolean; address: AddressInput };

/** Libreta de direcciones: agregar, editar, eliminar y elegir la principal. */
export function AddressBook({ addresses, regions }: { addresses: CustomerAddress[]; regions: GeoRegion[] }) {
  const router = useRouter();
  const [editing, setEditing] = useState<Editing | null>(null);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [message, setMessage] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const startNew = () => {
    setErrors({});
    setEditing({ id: null, label: "", is_default: addresses.length === 0, address: emptyAddress });
  };
  const startEdit = (a: CustomerAddress) => {
    setErrors({});
    setEditing({
      id: a.id,
      label: a.label ?? "",
      is_default: a.is_default,
      address: {
        recipient: a.recipient,
        phone: a.phone,
        street: a.street,
        number: a.number,
        apartment: a.apartment ?? "",
        region_code: a.region_code ?? "",
        commune: a.commune,
        reference: a.reference ?? "",
      },
    });
  };

  const save = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!editing) return;
    setBusy(true);
    setMessage(null);
    const body = { ...editing.address, label: editing.label || null, is_default: editing.is_default };
    const res = editing.id ? await bff(`me/addresses/${editing.id}`, { method: "PATCH", body }) : await bff("me/addresses", { method: "POST", body });
    setBusy(false);
    setErrors(res.errors);
    if (res.ok) {
      setEditing(null);
      router.refresh();
    } else if (Object.keys(res.errors).length === 0) {
      setMessage(res.message);
    }
  };

  const destroy = async (a: CustomerAddress) => {
    setBusy(true);
    setMessage(null);
    const res = await bff(`me/addresses/${a.id}`, { method: "DELETE" });
    setBusy(false);
    if (res.ok) router.refresh();
    else setMessage(res.message);
  };

  const makeDefault = async (a: CustomerAddress) => {
    setBusy(true);
    const res = await bff(`me/addresses/${a.id}`, {
      method: "PATCH",
      body: { label: a.label, recipient: a.recipient, phone: a.phone, street: a.street, number: a.number, apartment: a.apartment, region_code: a.region_code, commune: a.commune, reference: a.reference, is_default: true },
    });
    setBusy(false);
    if (res.ok) router.refresh();
    else setMessage(res.message);
  };

  return (
    <div className="mt-6">
      {message && <Alert>{message}</Alert>}
      {addresses.length === 0 && !editing && <p className="text-muted">No tienes direcciones guardadas.</p>}
      <ul className="grid gap-4 sm:grid-cols-2">
        {addresses.map((a) => (
          <li key={a.id} className="rounded-[var(--radius)] border border-border bg-surface p-5 text-sm">
            <p className="flex items-center gap-2 font-semibold">
              {a.label ?? a.recipient}
              {a.is_default && <span className="rounded-full bg-subtle px-2 py-0.5 text-xs font-medium">Principal</span>}
            </p>
            <p className="mt-2 text-muted">
              {a.recipient} · {a.phone}
              <br />
              {a.street} {a.number}
              {a.apartment ? `, ${a.apartment}` : ""}
              <br />
              {a.commune}, {a.region}
            </p>
            <div className="mt-4 flex flex-wrap gap-4">
              <button type="button" disabled={busy} onClick={() => startEdit(a)} className="underline underline-offset-4">
                Editar
              </button>
              {!a.is_default && (
                <button type="button" disabled={busy} onClick={() => makeDefault(a)} className="underline underline-offset-4">
                  Usar como principal
                </button>
              )}
              <button type="button" disabled={busy} onClick={() => destroy(a)} className="text-sale underline underline-offset-4">
                Eliminar
              </button>
            </div>
          </li>
        ))}
      </ul>

      {editing ? (
        <form onSubmit={save} noValidate className="mt-6 rounded-[var(--radius)] border border-border bg-surface p-6">
          <h3 className="mb-5 font-display text-2xl">{editing.id ? "Editar dirección" : "Nueva dirección"}</h3>
          <Field id="dir-nombre" label="Nombre de la dirección (opcional)" error={errors.label} className="mb-5">
            <input {...fieldAria("dir-nombre", errors.label)} className="input" value={editing.label} onChange={(e) => setEditing({ ...editing, label: e.target.value })} maxLength={40} placeholder="Ej.: Casa, Oficina" />
          </Field>
          <AddressFields prefix="dir" value={editing.address} onChange={(address) => setEditing({ ...editing, address })} regions={regions} errors={errors} />
          <label className="mt-5 flex items-center gap-3 text-sm">
            <input type="checkbox" checked={editing.is_default} onChange={(e) => setEditing({ ...editing, is_default: e.target.checked })} className="h-4 w-4 accent-[var(--color-accent)]" />
            Usar como dirección principal
          </label>
          <div className="mt-6 flex gap-3">
            <button type="submit" disabled={busy} className={buttonClass("primary")}>
              {busy ? "Guardando…" : "Guardar dirección"}
            </button>
            <button type="button" onClick={() => setEditing(null)} className={buttonClass("secondary")}>
              Cancelar
            </button>
          </div>
        </form>
      ) : (
        <button type="button" onClick={startNew} className={buttonClass("secondary", "mt-6")}>
          Agregar dirección
        </button>
      )}
    </div>
  );
}
