"use client";

import Image from "next/image";
import { useMemo, useState } from "react";
import type { StockStatus, ProductDetail, Variant } from "@/lib/types";
import { mediaUrl } from "@/lib/format";
import { Price } from "@/components/ui/Price";
import { buttonClass } from "@/components/ui/Button";

/**
 * Galería + selector de variantes. El precio mostrado corresponde siempre a la
 * variante elegida. El carrito llega en la Fase 5: el botón queda deshabilitado
 * y lo dice, en vez de simular una compra.
 */
export function ProductView({ product }: { product: ProductDetail }) {
  const initial = product.variants.find((v) => v.is_default) ?? product.variants[0];
  const [selected, setSelected] = useState<Record<string, string>>(initial?.options ?? {});
  const [imageIndex, setImageIndex] = useState(0);

  const variant: Variant | undefined = useMemo(
    () =>
      product.variants.find((v) => product.options.every((axis) => v.options[axis.code] === selected[axis.code])),
    [product, selected],
  );

  /** ¿Existe alguna variante con este valor, manteniendo lo ya elegido en los otros ejes? */
  const isAvailable = (code: string, value: string) =>
    product.variants.some((v) =>
      product.options.every((axis) => (axis.code === code ? v.options[code] === value : v.options[axis.code] === selected[axis.code])),
    );

  /** La combinación con este valor (y lo ya elegido en los otros ejes) existe pero está agotada. */
  const isSoldOut = (code: string, value: string) => {
    const match = product.variants.find((v) =>
      product.options.every((axis) => (axis.code === code ? v.options[code] === value : v.options[axis.code] === selected[axis.code])),
    );
    return match?.stock?.availability === "out_of_stock";
  };

  const choose = (code: string, value: string) => {
    let next = { ...selected, [code]: value };
    // Si la combinación no existe, toma la primera variante que tenga el valor elegido
    if (!product.variants.some((v) => product.options.every((a) => v.options[a.code] === next[a.code]))) {
      const fallback = product.variants.find((v) => v.options[code] === value);
      if (fallback) next = { ...fallback.options };
    }
    setSelected(next);
    // Muestra la foto de la variante si tiene una propia
    const match = product.variants.find((v) => product.options.every((a) => v.options[a.code] === next[a.code]));
    const idx = match?.image ? product.images.findIndex((img) => img.path === match.image?.path) : -1;
    if (idx >= 0) setImageIndex(idx);
  };

  const images = product.images.length > 0 ? product.images : [];
  const current = images[imageIndex] ?? images[0];

  return (
    <div className="grid gap-10 lg:grid-cols-[1.25fr_1fr] lg:gap-20">
      <div className="flex flex-col-reverse gap-3 lg:flex-row">
        {images.length > 1 && (
          <ul className="no-scrollbar flex gap-2 overflow-x-auto lg:max-h-[calc(100svh-8rem)] lg:w-20 lg:flex-col lg:overflow-y-auto" aria-label="Imágenes del producto">
            {images.map((img, i) => (
              <li key={img.path} className="w-16 shrink-0 lg:w-full">
                <button
                  type="button"
                  onClick={() => setImageIndex(i)}
                  aria-label={`Ver imagen ${i + 1} de ${images.length}`}
                  aria-current={i === imageIndex}
                  className={`relative block aspect-[4/5] w-full overflow-hidden rounded-[var(--radius-sm)] bg-subtle transition ${i === imageIndex ? "ring-2 ring-fg ring-offset-2 ring-offset-bg" : "opacity-70 hover:opacity-100"}`}
                >
                  <Image src={mediaUrl(img.path)} alt="" fill sizes="80px" className="object-cover" />
                </button>
              </li>
            ))}
          </ul>
        )}
        <div
          className="group/zoom relative aspect-[4/5] flex-1 cursor-zoom-in overflow-hidden rounded-[var(--radius)] bg-subtle"
          onMouseMove={(e) => {
            const r = e.currentTarget.getBoundingClientRect();
            e.currentTarget.style.setProperty("--zx", `${((e.clientX - r.left) / r.width) * 100}%`);
            e.currentTarget.style.setProperty("--zy", `${((e.clientY - r.top) / r.height) * 100}%`);
          }}
        >
          {current ? (
            <Image
              key={current.path}
              src={mediaUrl(current.path)}
              alt={current.alt}
              fill
              priority
              sizes="(min-width: 1024px) 50vw, 100vw"
              className="object-cover transition-transform duration-[var(--motion)] [transform-origin:var(--zx,50%)_var(--zy,50%)] group-hover/zoom:scale-[1.6] motion-reduce:group-hover/zoom:scale-100"
            />
          ) : (
            <div className="flex h-full items-center justify-center text-muted">Sin imagen</div>
          )}
          {product.price?.discount_percent ? (
            <span className="absolute left-4 top-4 rounded-full bg-sale px-3 py-1.5 text-xs font-semibold text-bg">
              -{product.price.discount_percent}%
            </span>
          ) : null}
        </div>
      </div>

      <div className="pb-20 lg:sticky lg:top-28 lg:self-start lg:pb-0">
        {product.brand && <p className="label">{product.brand.name}</p>}
        <h1 className="mt-2 font-display text-5xl sm:text-6xl">{product.name}</h1>
        <div className="mt-4">
          <Price price={variant?.price ?? null} size="lg" />
          <p className="mt-1 text-xs text-muted">{product.tax.label}</p>
          {variant?.price?.sale_ends_at && (
            <p className="mt-2 text-sm text-sale">
              Oferta válida hasta el{" "}
              {new Date(variant.price.sale_ends_at).toLocaleDateString("es-CL", {
                day: "numeric",
                month: "long",
                timeZone: "America/Santiago",
              })}
            </p>
          )}
        </div>

        {product.short_description && <p className="mt-6 text-muted">{product.short_description}</p>}

        <div className="mt-8 space-y-6">
          {product.options.map((axis) => (
            <fieldset key={axis.code}>
              <legend className="text-sm">
                {axis.name}:{" "}
                <span className="font-medium">{axis.values.find((v) => v.value === selected[axis.code])?.label}</span>
              </legend>
              <div className="mt-3 flex flex-wrap gap-2">
                {axis.values.map((v) => {
                  const active = selected[axis.code] === v.value;
                  const available = isAvailable(axis.code, v.value);
                  const soldOut = isSoldOut(axis.code, v.value);
                  const common = `transition-colors duration-[var(--motion-fast)] ${available ? "" : "opacity-40"}`;
                  return axis.display === "swatch" && v.color_hex ? (
                    <button
                      key={v.value}
                      type="button"
                      onClick={() => choose(axis.code, v.value)}
                      aria-pressed={active}
                      aria-label={`${v.label}${soldOut ? " (agotado)" : available ? "" : " (otra combinación)"}`}
                      title={v.label}
                      className={`relative flex h-10 w-10 items-center justify-center rounded-full border ${active ? "border-fg ring-2 ring-fg ring-offset-2 ring-offset-bg" : "border-border"} ${common}`}
                    >
                      {soldOut && <span aria-hidden className="sold-out-slash" />}
                      <span className="h-7 w-7 rounded-full border border-black/10" style={{ background: v.color_hex }} />
                    </button>
                  ) : (
                    <button
                      key={v.value}
                      type="button"
                      onClick={() => choose(axis.code, v.value)}
                      aria-pressed={active}
                      className={`h-10 min-w-12 rounded-full border px-4 text-sm ${active ? "border-fg bg-fg text-bg" : "border-border hover:border-fg"} ${soldOut ? "line-through" : ""} ${common}`}
                    >
                      {v.label}
                      {soldOut && <span className="sr-only"> (agotado)</span>}
                    </button>
                  );
                })}
              </div>
            </fieldset>
          ))}
        </div>

        <div className="mt-10">
          <StockNote stock={variant?.stock} />
          <button type="button" disabled className={buttonClass("primary", "h-14 w-full text-base")}>
            {variant?.stock?.availability === "out_of_stock" ? "Agotado" : "Agregar al carrito"}
          </button>
          <p className="mt-3 text-center text-xs text-muted">La compra en línea se habilita próximamente.</p>
        </div>

        {/* Barra fija en móvil con precio y acción */}
        <div className="fixed inset-x-0 bottom-0 z-30 flex items-center justify-between gap-4 border-t border-border bg-bg/95 px-4 py-3 backdrop-blur lg:hidden">
          <Price price={variant?.price ?? null} />
          <button type="button" disabled className={buttonClass("primary", "h-11 px-5")}>
            {variant?.stock?.availability === "out_of_stock" ? "Agotado" : "Agregar al carrito"}
          </button>
        </div>

        <div className="mt-10 divide-y divide-border border-y border-border">
          {product.description_html && (
            <details className="group/acc" open>
              <summary className="flex cursor-pointer list-none items-center justify-between py-5 text-sm font-semibold [&::-webkit-details-marker]:hidden">
                Descripción
                <span aria-hidden className="text-lg transition-transform group-open/acc:rotate-45">+</span>
              </summary>
              <div className="prose-flux pb-6 text-sm leading-relaxed text-muted" dangerouslySetInnerHTML={{ __html: product.description_html }} />
            </details>
          )}
          {product.specifications.length > 0 && (
            <details className="group/acc">
              <summary className="flex cursor-pointer list-none items-center justify-between py-5 text-sm font-semibold [&::-webkit-details-marker]:hidden">
                Características
                <span aria-hidden className="text-lg transition-transform group-open/acc:rotate-45">+</span>
              </summary>
              <dl className="divide-y divide-border pb-4 text-sm">
                {product.specifications.map((s) => (
                  <div key={s.name} className="flex justify-between gap-4 py-2.5">
                    <dt className="text-muted">{s.name}</dt>
                    <dd>{s.value}</dd>
                  </div>
                ))}
                {variant && (
                  <div className="flex justify-between gap-4 py-2.5">
                    <dt className="text-muted">SKU</dt>
                    <dd className="tabular-nums">{variant.sku}</dd>
                  </div>
                )}
              </dl>
            </details>
          )}
        </div>
      </div>
    </div>
  );
}

/** Aviso de disponibilidad de la variante elegida. */
function StockNote({ stock }: { stock?: StockStatus }) {
  if (!stock) return null;
  const text = {
    in_stock: "Disponible",
    low_stock: stock.quantity === 1 ? "¡Última unidad!" : `¡Quedan ${stock.quantity} unidades!`,
    out_of_stock: "Agotado por ahora",
    backorder: "Disponible por encargo",
  }[stock.availability];
  const tone = {
    in_stock: "bg-ok",
    low_stock: "bg-sale",
    out_of_stock: "bg-muted",
    backorder: "bg-accent",
  }[stock.availability];
  return (
    <p className="mb-4 flex items-center gap-2 text-sm font-medium" role="status">
      <span aria-hidden className={`h-2 w-2 rounded-full ${tone}`} />
      {text}
    </p>
  );
}
