import Image from "next/image";
import Link from "next/link";
import type { ProductCard as Card } from "@/lib/types";
import { mediaUrl } from "@/lib/format";
import { Price } from "@/components/ui/Price";

const SIZES = "(min-width: 1280px) 22vw, (min-width: 768px) 30vw, 46vw";

export function ProductCard({
  product,
  priority = false,
  headingLevel = 3,
  sizes = SIZES,
}: {
  product: Card;
  priority?: boolean;
  headingLevel?: 2 | 3;
  sizes?: string;
}) {
  const Heading = headingLevel === 2 ? "h2" : "h3";
  const discount = product.price?.discount_percent;
  const swatches = (product.swatches ?? []).slice(0, 5);

  return (
    <article className="group relative">
      <div className="card-media relative aspect-[4/5] overflow-hidden rounded-[var(--radius)] bg-subtle">
        {product.image ? (
          <Image src={mediaUrl(product.image.path)} alt={product.image.alt} fill sizes={sizes} priority={priority} className="main-img object-cover" />
        ) : (
          <div className="flex h-full items-center justify-center text-sm text-muted">Sin imagen</div>
        )}
        {product.hover_image && (
          <Image src={mediaUrl(product.hover_image.path)} alt="" fill sizes={sizes} className="hover-img object-cover" />
        )}
        <div className="absolute left-3 top-3 flex flex-col items-start gap-1.5">
          {discount ? (
            <span className="rounded-full bg-sale px-2.5 py-1 text-[0.7rem] font-semibold text-bg">-{discount}%</span>
          ) : null}
          {product.is_new && (
            <span className="rounded-full bg-surface/90 px-2.5 py-1 text-[0.7rem] font-semibold text-fg backdrop-blur">Nuevo</span>
          )}
        </div>
        <div aria-hidden className="quick absolute inset-x-3 bottom-3 hidden md:block">
          <span className="flex h-11 items-center justify-center gap-2 rounded-full bg-surface/95 text-sm font-medium text-fg backdrop-blur">
            Ver producto
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
          </span>
        </div>
      </div>
      <div className="mt-4 space-y-1.5">
        <div className="flex items-center justify-between gap-3">
          {product.brand ? <p className="label !text-[0.62rem]">{product.brand.name}</p> : <span />}
          {swatches.length > 1 && (
            <ul className="flex -space-x-1" aria-label={`${(product.swatches ?? []).length} colores`}>
              {swatches.map((s) => (
                <li key={s.label} title={s.label} className="h-3.5 w-3.5 rounded-full border-2 border-bg" style={{ background: s.color_hex }}>
                  <span className="sr-only">{s.label}</span>
                </li>
              ))}
            </ul>
          )}
        </div>
        <Heading className="text-[0.95rem] font-medium leading-snug">
          <Link href={`/producto/${product.slug}`} className="after:absolute after:inset-0 after:rounded-[var(--radius)]">
            {product.name}
          </Link>
        </Heading>
        <Price price={product.price} />
      </div>
    </article>
  );
}

export function ProductGrid({
  products,
  priorityCount = 0,
  headingLevel = 3,
}: {
  products: Card[];
  priorityCount?: number;
  headingLevel?: 2 | 3;
}) {
  return (
    <ul className="grid grid-cols-2 gap-x-4 gap-y-12 md:grid-cols-3 md:gap-x-6 xl:grid-cols-4">
      {products.map((p, i) => (
        // Las primeras tarjetas (las de arriba) se pintan de inmediato: no esperan al JavaScript (LCP)
        <li
          key={p.id}
          data-reveal={i < priorityCount ? undefined : ""}
          style={{ "--reveal-delay": `${(i % 4) * 70}ms` } as React.CSSProperties}
        >
          <ProductCard product={p} priority={i < priorityCount} headingLevel={headingLevel} />
        </li>
      ))}
    </ul>
  );
}
