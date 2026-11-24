"use client";

import Image from "next/image";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useMemo, useRef, useState } from "react";
import type { Cart, CheckoutOptions, CustomerAddress, CustomerProfile, GeoRegion } from "@/lib/types";
import { bff } from "@/lib/client-api";
import { formatMoney, mediaUrl } from "@/lib/format";
import { buttonClass } from "@/components/ui/Button";
import { Alert, Field, fieldAria } from "@/components/ui/Field";
import { useCart } from "@/components/cart/CartProvider";
import { CouponForm, DiscountRows } from "@/components/cart/CouponForm";
import { startPayment } from "@/lib/pay";
import { AddressFields, emptyAddress, type AddressInput } from "./AddressFields";

type Contact = { email: string; first_name: string; last_name: string; phone: string };
type Billing = { document_type: "boleta" | "factura"; billing_rut: string; billing_name: string; billing_activity: string };

/**
 * Checkout en una sola página: contacto, entrega, despacho, pago y revisión.
 * El navegador solo envía datos y el total que vio; Laravel recalcula todo y, si el
 * total cambió, responde price_changed para que el cliente lo revise antes de pagar.
 */
export function CheckoutForm({
  cart: initialCart,
  regions,
  profile,
  addresses,
}: {
  cart: Cart;
  regions: GeoRegion[];
  profile: CustomerProfile | null;
  addresses: CustomerAddress[];
}) {
  const router = useRouter();
  const { refresh: refreshCart, cart: liveCart } = useCart();
  // El carrito del servidor sirve para pintar; después manda el del navegador (cambia al aplicar un cupón)
  const cart = liveCart ?? initialCart;
  // Una clave por intento de compra: si la respuesta se pierde y el cliente reintenta, no se duplica el pedido
  const idempotencyKey = useRef<string>("");
  if (!idempotencyKey.current) idempotencyKey.current = newKey();

  const defaultAddress = addresses.find((a) => a.is_default) ?? addresses[0];
  const [contact, setContact] = useState<Contact>({
    email: profile?.email ?? "",
    first_name: profile?.first_name ?? "",
    last_name: profile?.last_name ?? "",
    phone: profile?.phone ?? "",
  });
  const [savedId, setSavedId] = useState<number | "nueva">(defaultAddress?.id ?? "nueva");
  const [address, setAddress] = useState<AddressInput>(
    profile ? { ...emptyAddress, recipient: `${profile.first_name} ${profile.last_name}`.trim(), phone: profile.phone ?? "" } : emptyAddress,
  );
  const [options, setOptions] = useState<CheckoutOptions | null>(null);
  const [loadingOptions, setLoadingOptions] = useState(true);
  const [shippingCode, setShippingCode] = useState("");
  const [paymentCode, setPaymentCode] = useState("");
  const [billing, setBilling] = useState<Billing>({ document_type: "boleta", billing_rut: "", billing_name: "", billing_activity: "" });
  const [notes, setNotes] = useState("");
  const [terms, setTerms] = useState(false);
  const [newsletter, setNewsletter] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [message, setMessage] = useState<string | null>(null);
  const [sending, setSending] = useState(false);

  const saved = typeof savedId === "number" ? addresses.find((a) => a.id === savedId) : undefined;
  const effectiveAddress: AddressInput = saved
    ? {
        recipient: saved.recipient,
        phone: saved.phone,
        street: saved.street,
        number: saved.number,
        apartment: saved.apartment ?? "",
        region_code: saved.region_code ?? "",
        commune: saved.commune,
        reference: saved.reference ?? "",
      }
    : address;
  const regionCode = effectiveAddress.region_code;
  const email = contact.email.trim().toLowerCase();
  const commune = effectiveAddress.commune.trim();

  // Los métodos de despacho dependen de la región, la comuna, el peso y el subtotal (el servidor los conoce)
  useEffect(() => {
    let cancelled = false;
    setLoadingOptions(true);
    const params = new URLSearchParams();
    if (regionCode) params.set("region_code", regionCode);
    if (regionCode && commune) params.set("commune", commune);
    // Con el correo el servidor revisa los límites del cupón por cliente y "solo primera compra"
    if (EMAIL.test(email)) params.set("email", email);
    const query = params.toString();
    // Espera un momento mientras se escribe la comuna a mano
    const timer = setTimeout(() => bff<CheckoutOptions>(`checkout/options${query ? `?${query}` : ""}`).then((res) => {
      if (cancelled) return;
      setLoadingOptions(false);
      if (!res.ok || !res.data) {
        setMessage(res.message);
        return;
      }
      const data = res.data;
      setOptions(data);
      setShippingCode((current) => (data.shipping_methods.some((m) => m.code === current) ? current : (data.shipping_methods.find((m) => m.type !== "pickup") ?? data.shipping_methods[0])?.code ?? ""));
      setPaymentCode((current) => (data.payment_methods.some((m) => m.code === current) ? current : data.payment_methods[0]?.code ?? ""));
    }), 300);
    return () => {
      cancelled = true;
      clearTimeout(timer);
    };
  }, [regionCode, commune, email, cart.subtotal, cart.count, cart.discounts?.coupon?.code]);

  const shipping = options?.shipping_methods.find((m) => m.code === shippingCode);
  const payment = options?.payment_methods.find((m) => m.code === paymentCode);
  const isPickup = shipping?.type === "pickup";
  const discounts = options?.discounts ?? cart.discounts;
  const itemsTotal = options?.total ?? cart.total ?? cart.subtotal;
  const total = itemsTotal + (shipping?.amount ?? 0);
  const blocked = cart.items.some((l) => !l.purchasable || (l.max_quantity !== null && l.quantity > l.max_quantity));
  const noPayment = options !== null && options.payment_methods.length === 0;

  const regionName = useMemo(() => regions.find((r) => r.code === regionCode)?.name, [regions, regionCode]);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (sending) return;
    setSending(true);
    setErrors({});
    setMessage(null);

    const res = await bff<{ number: number; token: string; next: "pay" | "instructions" }>("checkout", {
      method: "POST",
      headers: { "Idempotency-Key": idempotencyKey.current },
      body: {
        ...contact,
        shipping_method: shippingCode,
        payment_method: paymentCode,
        address: isPickup ? undefined : effectiveAddress,
        ...(billing.document_type === "factura" ? billing : { document_type: "boleta" }),
        notes: notes || undefined,
        accepts_terms: terms,
        subscribe_newsletter: newsletter || undefined,
        expected_total: total,
      },
    });

    if (res.ok && res.data) {
      await refreshCart();
      if (res.data.next === "pay") {
        // Pago con pasarela: el pedido ya existe y el stock está apartado; ahora vamos a pagar
        const pay = await startPayment(res.data.token);
        if (pay.ok && !pay.paid) return; // el navegador se va al sitio de la pasarela
        router.push(`/pedido/${res.data.token}${pay.ok ? "?pago=aprobado" : "?pago=error"}`);
        return;
      }
      router.push(`/pedido/${res.data.token}?nuevo=1`);
      return;
    }

    setSending(false);
    setErrors(res.errors);
    if (res.code === "price_changed" || res.code === "cart_changed" || res.code === "stock_insufficient" || res.code === "coupon_invalid") {
      // Recarga el carrito y los totales para que el cliente vea lo nuevo antes de confirmar
      router.refresh();
      await refreshCart();
    }
    // Con errores por campo basta el aviso general; el detalle va junto a cada campo
    setMessage(Object.keys(res.errors).length > 0 ? null : res.message);
    window.scrollTo({ top: 0, behavior: "smooth" });
  };

  const setC = (key: keyof Contact) => (e: React.ChangeEvent<HTMLInputElement>) => setContact({ ...contact, [key]: e.target.value });
  const setB = (key: keyof Billing) => (e: React.ChangeEvent<HTMLInputElement>) => setBilling({ ...billing, [key]: e.target.value });

  return (
    <form onSubmit={submit} noValidate className="mt-10 grid gap-10 lg:grid-cols-[1fr_26rem] lg:gap-16">
      <div className="space-y-12">
        {message && <Alert>{message}</Alert>}
        {Object.keys(errors).length > 0 && !message && <Alert>Revisa los campos marcados.</Alert>}

        <Step n={1} title="Contacto">
          <div className="grid gap-5 sm:grid-cols-2">
            <Field id="correo" label="Correo electrónico" error={errors.email} hint="Te enviaremos aquí la confirmación y el seguimiento." className="sm:col-span-2">
              <input {...fieldAria("correo", errors.email, "ayuda")} className="input" type="email" value={contact.email} onChange={setC("email")} autoComplete="email" required maxLength={190} readOnly={Boolean(profile)} />
            </Field>
            <Field id="nombre" label="Nombre" error={errors.first_name}>
              <input {...fieldAria("nombre", errors.first_name)} className="input" value={contact.first_name} onChange={setC("first_name")} autoComplete="given-name" required maxLength={80} />
            </Field>
            <Field id="apellido" label="Apellido" error={errors.last_name}>
              <input {...fieldAria("apellido", errors.last_name)} className="input" value={contact.last_name} onChange={setC("last_name")} autoComplete="family-name" required maxLength={80} />
            </Field>
            <Field id="telefono" label="Teléfono" error={errors.phone} className="sm:col-span-2">
              <input {...fieldAria("telefono", errors.phone)} className="input" type="tel" value={contact.phone} onChange={setC("phone")} autoComplete="tel" required maxLength={30} placeholder="+56 9 1234 5678" />
            </Field>
          </div>
        </Step>

        <Step n={2} title="Dirección de entrega">
          {isPickup ? (
            <p className="text-sm text-muted">Elegiste retiro en tienda: no necesitas indicar una dirección.</p>
          ) : (
            <>
            {addresses.length > 0 && (
              <fieldset className="mb-6">
                <legend className="sr-only">Dirección guardada</legend>
                <div className="grid gap-3 sm:grid-cols-2">
                  {addresses.map((a) => (
                    <label key={a.id} className={`flex cursor-pointer gap-3 rounded-[var(--radius)] border p-4 text-sm ${savedId === a.id ? "border-fg bg-surface" : "border-border hover:border-fg/50"}`}>
                      <input type="radio" name="direccion" checked={savedId === a.id} onChange={() => setSavedId(a.id)} className="mt-1 accent-[var(--color-accent)]" />
                      <span>
                        <span className="font-medium">{a.label ?? a.recipient}</span>
                        <span className="mt-1 block text-muted">
                          {a.street} {a.number}
                          {a.apartment ? `, ${a.apartment}` : ""}, {a.commune}, {a.region}
                        </span>
                      </span>
                    </label>
                  ))}
                  <label className={`flex cursor-pointer items-center gap-3 rounded-[var(--radius)] border p-4 text-sm ${savedId === "nueva" ? "border-fg bg-surface" : "border-border hover:border-fg/50"}`}>
                    <input type="radio" name="direccion" checked={savedId === "nueva"} onChange={() => setSavedId("nueva")} className="accent-[var(--color-accent)]" />
                    <span className="font-medium">Usar otra dirección</span>
                  </label>
                </div>
              </fieldset>
            )}
            {savedId === "nueva" && <AddressFields prefix="envio" value={address} onChange={setAddress} regions={regions} errors={errors} errorPrefix="address." />}
            {saved && Object.keys(errors).some((k) => k.startsWith("address.")) && (
              <p className="field-error mt-2">La dirección guardada tiene datos incompletos. Elige &quot;Usar otra dirección&quot; o edítala en Mi cuenta.</p>
            )}
            </>
          )}
        </Step>

        <Step n={3} title="Despacho">
          {loadingOptions && !options ? (
            <p className="text-sm text-muted">Cargando opciones de despacho…</p>
          ) : options && options.shipping_methods.length === 0 ? (
            <Alert tone="info">
              {!regionCode
                ? "Elige la región de entrega para ver las opciones de despacho."
                : !commune
                  ? "Elige la comuna para ver las opciones de despacho."
                  : `Por ahora no despachamos a ${commune}, ${regionName ?? "esta región"}.`}
            </Alert>
          ) : (
            <fieldset>
              <legend className="sr-only">Método de despacho</legend>
              <div className="grid gap-3">
                {options?.shipping_methods.map((m) => (
                  <label key={m.code} className={`flex cursor-pointer items-start gap-4 rounded-[var(--radius)] border p-4 transition-colors ${shippingCode === m.code ? "border-fg bg-surface" : "border-border hover:border-fg/50"}`}>
                    <input type="radio" name="despacho" value={m.code} checked={shippingCode === m.code} onChange={() => setShippingCode(m.code)} className="mt-1 accent-[var(--color-accent)]" />
                    <span className="flex-1">
                      <span className="flex justify-between gap-4 font-medium">
                        {m.name}
                        <span className="tabular-nums">
                          {m.original_amount ? <s className="mr-2 font-normal text-muted">{formatMoney(m.original_amount)}</s> : null}
                          {m.amount === 0 ? "Gratis" : formatMoney(m.amount)}
                        </span>
                      </span>
                      {m.carrier_name && <span className="mt-1 block text-sm text-muted">Con {m.carrier_name}</span>}
                      {m.description && <span className="mt-1 block text-sm text-muted">{m.description}</span>}
                      {m.estimated_days && <span className="mt-1 block text-sm text-muted">Plazo estimado: {m.estimated_days}</span>}
                      {m.pickup_address && <span className="mt-1 block text-sm text-muted">Retiras en: {m.pickup_address}</span>}
                      {m.pickup_hours && <span className="mt-1 block text-sm text-muted">Horario: {m.pickup_hours}</span>}
                      {m.free_over !== null && m.amount > 0 && <span className="mt-1 block text-xs text-muted">Gratis en compras desde {formatMoney(m.free_over)}.</span>}
                    </span>
                  </label>
                ))}
              </div>
              {errors.shipping_method && <p className="field-error mt-2">{errors.shipping_method}</p>}
            </fieldset>
          )}
        </Step>

        <Step n={4} title="Pago">
          {noPayment ? (
            <Alert>Por ahora la tienda no tiene medios de pago activos. Escríbenos para completar tu compra.</Alert>
          ) : (
            <fieldset>
              <legend className="sr-only">Medio de pago</legend>
              <div className="grid gap-3">
                {options?.payment_methods.map((m) => (
                  <label key={m.code} className={`flex cursor-pointer items-start gap-4 rounded-[var(--radius)] border p-4 ${paymentCode === m.code ? "border-fg bg-surface" : "border-border"}`}>
                    <input type="radio" name="pago" value={m.code} checked={paymentCode === m.code} onChange={() => setPaymentCode(m.code)} className="mt-1 accent-[var(--color-accent)]" />
                    <span>
                      <span className="flex flex-wrap items-center gap-2 font-medium">
                        {m.name}
                        {m.test_mode && <span className="rounded-full bg-sale/12 px-2 py-0.5 text-xs font-semibold text-sale">Modo de prueba: no se cobra</span>}
                      </span>
                      <span className="mt-1 block text-sm text-muted">{m.description}</span>
                    </span>
                  </label>
                ))}
              </div>
              {errors.payment_method && <p className="field-error mt-2">{errors.payment_method}</p>}
            </fieldset>
          )}

          <fieldset className="mt-8">
            <legend className="field-label text-sm font-semibold">Documento tributario</legend>
            <div className="mt-3 flex gap-3">
              {(["boleta", "factura"] as const).map((d) => (
                <label key={d} className={`flex cursor-pointer items-center gap-2 rounded-full border px-5 py-2.5 text-sm ${billing.document_type === d ? "border-fg bg-fg text-bg" : "border-border"}`}>
                  <input type="radio" name="documento" className="sr-only" checked={billing.document_type === d} onChange={() => setBilling({ ...billing, document_type: d })} />
                  {d === "boleta" ? "Boleta" : "Factura"}
                </label>
              ))}
            </div>
            {billing.document_type === "factura" && (
              <div className="mt-5 grid gap-5 sm:grid-cols-2">
                <Field id="rut-empresa" label="RUT de la empresa" error={errors.billing_rut}>
                  <input {...fieldAria("rut-empresa", errors.billing_rut)} className="input" value={billing.billing_rut} onChange={setB("billing_rut")} placeholder="76.123.456-7" maxLength={12} />
                </Field>
                <Field id="razon-social" label="Razón social" error={errors.billing_name}>
                  <input {...fieldAria("razon-social", errors.billing_name)} className="input" value={billing.billing_name} onChange={setB("billing_name")} maxLength={150} />
                </Field>
                <Field id="giro" label="Giro" error={errors.billing_activity} className="sm:col-span-2">
                  <input {...fieldAria("giro", errors.billing_activity)} className="input" value={billing.billing_activity} onChange={setB("billing_activity")} maxLength={150} />
                </Field>
              </div>
            )}
          </fieldset>

          <Field id="notas" label="Comentarios del pedido (opcional)" error={errors.notes} className="mt-8">
            <textarea {...fieldAria("notas", errors.notes)} className="input" value={notes} onChange={(e) => setNotes(e.target.value)} maxLength={500} />
          </Field>
        </Step>
      </div>

      <aside className="h-fit rounded-[var(--radius)] border border-border bg-surface p-6 lg:sticky lg:top-24">
        <h2 className="font-display text-3xl">Tu pedido</h2>
        <ul className="mt-5 max-h-80 divide-y divide-border overflow-y-auto">
          {cart.items.map((l) => (
            <li key={l.sku} className="flex gap-3 py-3 text-sm">
              <div className="relative h-16 w-14 shrink-0 overflow-hidden rounded-[var(--radius-sm)] bg-subtle">
                {l.image && <Image src={mediaUrl(l.image.path)} alt={l.image.alt} fill sizes="3.5rem" className="object-cover" />}
                <span className="absolute right-0.5 top-0.5 rounded-full bg-fg px-1.5 text-[0.7rem] font-semibold text-bg">{l.quantity}</span>
              </div>
              <div className="min-w-0 flex-1">
                <p className="line-clamp-2 font-medium">{l.name}</p>
                {l.variant_label && <p className="text-muted">{l.variant_label}</p>}
                {!l.purchasable && <p className="text-sale">No disponible</p>}
              </div>
              <p className="tabular-nums">{formatMoney(l.line_total)}</p>
            </li>
          ))}
        </ul>
        <dl className="mt-5 space-y-3 border-t border-border pt-5 text-sm">
          <div className="flex justify-between">
            <dt className="text-muted">Productos</dt>
            <dd className="tabular-nums">{formatMoney(cart.subtotal)}</dd>
          </div>
          <DiscountRows discounts={discounts} />
          <div className="flex justify-between">
            <dt className="text-muted">Despacho</dt>
            <dd className="tabular-nums">{!shipping ? "—" : shipping.amount === 0 ? "Gratis" : formatMoney(shipping.amount)}</dd>
          </div>
          <div className="flex items-baseline justify-between border-t border-border pt-3">
            <dt className="font-semibold">Total</dt>
            <dd className="font-display text-4xl tabular-nums">{formatMoney(total)}</dd>
          </div>
          <div className="flex justify-between text-xs text-muted">
            <dt>Incluye IVA</dt>
            <dd className="tabular-nums">{formatMoney(cart.tax_total + includedTax(shipping?.amount ?? 0))}</dd>
          </div>
        </dl>

        <div className="mt-5">
          <CouponForm discounts={discounts} />
        </div>
        {errors.coupon && <p className="field-error mt-1">{errors.coupon}</p>}

        <label className="mt-6 flex cursor-pointer items-start gap-3 text-sm">
          <input type="checkbox" checked={terms} onChange={(e) => setTerms(e.target.checked)} className="mt-0.5 h-4 w-4 accent-[var(--color-accent)]" aria-invalid={errors.accepts_terms ? true : undefined} />
          <span>Acepto los términos y condiciones y la política de privacidad de la tienda.</span>
        </label>
        {errors.accepts_terms && <p className="field-error mt-1">{errors.accepts_terms}</p>}
        <label className="mt-3 flex cursor-pointer items-start gap-3 text-sm">
          <input type="checkbox" checked={newsletter} onChange={(e) => setNewsletter(e.target.checked)} className="mt-0.5 h-4 w-4 accent-[var(--color-accent)]" />
          <span>Quiero recibir novedades y ofertas por correo (opcional). Te pediremos confirmarlo.</span>
        </label>

        {blocked ? (
          <p className="mt-6 text-sm text-sale">
            Hay productos sin stock o no disponibles.{" "}
            <Link href="/carrito" className="underline">
              Revisa el carrito
            </Link>
            .
          </p>
        ) : (
          <button type="submit" disabled={sending || !shipping || noPayment || !paymentCode} className={buttonClass("primary", "mt-6 h-14 w-full text-base")}>
            {sending ? (payment?.type === "redirect" ? "Te llevamos a pagar…" : "Confirmando…") : payment?.type === "redirect" ? `Pagar ${formatMoney(total)}` : `Confirmar pedido por ${formatMoney(total)}`}
          </button>
        )}
        <p className="mt-3 text-center text-xs text-muted">
          {payment?.type === "redirect"
            ? `Pagas en el sitio seguro de ${payment.name}. Tus productos quedan reservados mientras pagas.`
            : "Al confirmar reservamos tus productos mientras haces la transferencia."}
        </p>
      </aside>
    </form>
  );
}

function Step({ n, title, children }: { n: number; title: string; children: React.ReactNode }) {
  return (
    <section aria-labelledby={`paso-${n}`}>
      <h2 id={`paso-${n}`} className="mb-6 flex items-center gap-3 font-display text-3xl">
        <span aria-hidden className="flex h-9 w-9 items-center justify-center rounded-full bg-fg font-sans text-sm font-semibold text-bg">
          {n}
        </span>
        {title}
      </h2>
      {children}
    </section>
  );
}

/** IVA incluido en un monto bruto, igual que Tax::included en Laravel (19 %). */
function includedTax(gross: number): number {
  return gross - Math.round(gross / 1.19);
}

const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

function newKey(): string {
  if (typeof crypto !== "undefined" && "randomUUID" in crypto) return crypto.randomUUID();
  return `${Date.now()}-${Math.random().toString(36).slice(2)}`;
}
