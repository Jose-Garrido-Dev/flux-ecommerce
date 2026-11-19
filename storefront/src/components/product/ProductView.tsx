"use client";

import Image from "next/image";
import { useMemo, useState } from "react";
import type { ProductDetail, Variant } from "@/lib/types";
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
    <div className="grid gap-10 lg:grid-cols-[1.2fr_1fr] lg:gap-16">
      <div className="space-y-3">
        <div className="relative aspect-[4/5] overflow-hidden rounded-[var(--radius)] bg-subtle">
          {current ? (
            <Image
              key={current.path}
              src={mediaUrl(current.path)}
              alt={current.alt}
              fill
              priority
              sizes="(min-width: 1024px) 55vw, 100vw"
              className="object-cover"
            />
          ) : (
            <div className="flex h-full items-center justify-center text-muted">Sin imagen</div>
          )}
        </div>
        {images.length > 1 && (
          <ul className="grid grid-cols-5 gap-2 sm:grid-cols-6" aria-label="Imágenes del producto">
            {images.map((img, i) => (
              <li key={img.path}>
                <button
                  type="button"
                  onClick={() => setImageIndex(i)}
                  aria-label={`Ver imagen ${i + 1} de ${images.length}`}
                  aria-current={i === imageIndex}
                  className={`relative block aspect-square w-full overflow-hidden rounded-[var(--radius-sm)] bg-subtle ring-offset-2 ring-offset-bg ${i === imageIndex ? "ring-2 ring-fg" : "opacity-80 hover:opacity-100"}`}
                >
                  <Image src={mediaUrl(img.path)} alt="" fill sizes="96px" className="object-cover" />
                </button>
              </li>
            ))}
          </ul>
        )}
      </div>

      <div className="lg:sticky lg:top-24 lg:self-start">
        {product.brand && <p className="label">{product.brand.name}</p>}
        <h1 className="mt-2 font-display text-3xl leading-tight sm:text-4xl">{product.name}</h1>
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
                  const common = `transition-colors duration-[var(--motion-fast)] ${available ? "" : "opacity-40"}`;
                  return axis.display === "swatch" && v.color_hex ? (
                    <button
                      key={v.value}
                      type="button"
                      onClick={() => choose(axis.code, v.value)}
                      aria-pressed={active}
                      aria-label={`${v.label}${available ? "" : " (otra combinación)"}`}
                      title={v.label}
                      className={`flex h-10 w-10 items-center justify-center rounded-full border ${active ? "border-fg ring-2 ring-fg ring-offset-2 ring-offset-bg" : "border-border"} ${common}`}
                    >
                      <span className="h-7 w-7 rounded-full border border-black/10" style={{ background: v.color_hex }} />
                    </button>
                  ) : (
                    <button
                      key={v.value}
                      type="button"
                      onClick={() => choose(axis.code, v.value)}
                      aria-pressed={active}
                      className={`h-10 min-w-12 rounded-full border px-4 text-sm ${active ? "border-fg bg-fg text-bg" : "border-border hover:border-fg"} ${common}`}
                    >
                      {v.label}
                    </button>
                  );
                })}
              </div>
            </fieldset>
          ))}
        </div>

        <div className="mt-8">
          <button type="button" disabled className={buttonClass("primary", "w-full")}>
            Agregar al carrito
          </button>
          <p className="mt-2 text-center text-xs text-muted">La compra en línea se habilita próximamente.</p>
          {variant && <p className="mt-6 text-xs text-muted">SKU: {variant.sku}</p>}
        </div>

        {product.description_html && (
          <section className="mt-10 border-t border-border pt-8">
            <h2 className="label">Descripción</h2>
            <div className="prose-flux mt-4 text-sm leading-relaxed" dangerouslySetInnerHTML={{ __html: product.description_html }} />
          </section>
        )}

        {product.specifications.length > 0 && (
          <section className="mt-8 border-t border-border pt-8">
            <h2 className="label">Características</h2>
            <dl className="mt-4 divide-y divide-border text-sm">
              {product.specifications.map((s) => (
                <div key={s.name} className="flex justify-between gap-4 py-2">
                  <dt className="text-muted">{s.name}</dt>
                  <dd>{s.value}</dd>
                </div>
              ))}
            </dl>
          </section>
        )}
      </div>
    </div>
  );
}
