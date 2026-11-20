"use client";

import type { GeoRegion } from "@/lib/types";
import { Field, fieldAria } from "@/components/ui/Field";

export type AddressInput = {
  recipient: string;
  phone: string;
  street: string;
  number: string;
  apartment: string;
  region_code: string;
  commune: string;
  reference: string;
};

export const emptyAddress: AddressInput = { recipient: "", phone: "", street: "", number: "", apartment: "", region_code: "", commune: "", reference: "" };

/**
 * Campos de una dirección chilena. Si ya se cargó el listado oficial de comunas, la comuna
 * se elige de una lista; si no, se escribe (el servidor la valida cuando existe el listado).
 */
export function AddressFields({
  prefix,
  value,
  onChange,
  regions,
  errors,
  errorPrefix = "",
}: {
  prefix: string;
  value: AddressInput;
  onChange: (next: AddressInput) => void;
  regions: GeoRegion[];
  errors: Record<string, string>;
  /** Prefijo de las claves de error de la API (p. ej. "address.") */
  errorPrefix?: string;
}) {
  const set = (key: keyof AddressInput) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => {
    const next = { ...value, [key]: e.target.value };
    if (key === "region_code") next.commune = "";
    onChange(next);
  };
  const err = (key: string) => errors[`${errorPrefix}${key}`];
  const communes = regions.find((r) => r.code === value.region_code)?.communes ?? [];
  const id = (k: string) => `${prefix}-${k}`;

  return (
    <div className="grid gap-5 sm:grid-cols-6">
      <Field id={id("destinatario")} label="Nombre de quien recibe" error={err("recipient")} className="sm:col-span-3">
        <input {...fieldAria(id("destinatario"), err("recipient"))} className="input" value={value.recipient} onChange={set("recipient")} autoComplete="shipping name" required maxLength={120} />
      </Field>
      <Field id={id("telefono")} label="Teléfono de contacto" error={err("phone")} className="sm:col-span-3">
        <input {...fieldAria(id("telefono"), err("phone"))} className="input" value={value.phone} onChange={set("phone")} type="tel" autoComplete="shipping tel" required maxLength={30} placeholder="+56 9 1234 5678" />
      </Field>
      <Field id={id("calle")} label="Calle" error={err("street")} className="sm:col-span-4">
        <input {...fieldAria(id("calle"), err("street"))} className="input" value={value.street} onChange={set("street")} autoComplete="shipping address-line1" required maxLength={150} />
      </Field>
      <Field id={id("numero")} label="Número" error={err("number")} className="sm:col-span-2">
        <input {...fieldAria(id("numero"), err("number"))} className="input" value={value.number} onChange={set("number")} required maxLength={20} />
      </Field>
      <Field id={id("depto")} label="Departamento, casa u oficina (opcional)" error={err("apartment")} className="sm:col-span-6">
        <input {...fieldAria(id("depto"), err("apartment"))} className="input" value={value.apartment} onChange={set("apartment")} autoComplete="shipping address-line2" maxLength={40} />
      </Field>
      <Field id={id("region")} label="Región" error={err("region_code")} className="sm:col-span-3">
        <select {...fieldAria(id("region"), err("region_code"))} className="input" value={value.region_code} onChange={set("region_code")} required>
          <option value="">Elige una región</option>
          {regions.map((r) => (
            <option key={r.code} value={r.code}>
              {r.name}
            </option>
          ))}
        </select>
      </Field>
      <Field id={id("comuna")} label="Comuna" error={err("commune")} className="sm:col-span-3">
        {communes.length > 0 ? (
          <select {...fieldAria(id("comuna"), err("commune"))} className="input" value={value.commune} onChange={set("commune")} required>
            <option value="">Elige una comuna</option>
            {communes.map((c) => (
              <option key={c} value={c}>
                {c}
              </option>
            ))}
          </select>
        ) : (
          <input {...fieldAria(id("comuna"), err("commune"))} className="input" value={value.commune} onChange={set("commune")} autoComplete="shipping address-level2" required maxLength={80} />
        )}
      </Field>
      <Field id={id("referencia")} label="Referencia para el repartidor (opcional)" error={err("reference")} className="sm:col-span-6">
        <input {...fieldAria(id("referencia"), err("reference"))} className="input" value={value.reference} onChange={set("reference")} maxLength={200} placeholder="Ej.: portón verde, dejar en conserjería" />
      </Field>
    </div>
  );
}
