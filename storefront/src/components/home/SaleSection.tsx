import Image from "next/image";
import Link from "next/link";
import type { ProductCard } from "@/lib/types";
import { mediaUrl } from "@/lib/format";
import { Container } from "@/components/ui/Container";
import { Price } from "@/components/ui/Price";
import { Countdown } from "@/components/motion/Countdown";

/** Bloque de ofertas vigentes. El temporizador usa la fecha real de término más próxima. */
export function SaleSection({ products }: { products: ProductCard[] }) {
  const ends = products
    .map((p) => p.price?.sale_ends_at)
    .filter((d): d is string => !!d)
    .sort()[0];
  const maxDiscount = Math.max(...products.map((p) => p.price?.discount_percent ?? 0));

  return (
    <section className="relative isolate overflow-hidden bg-ink py-24 text-ink-fg">
      <Container className="grid items-center gap-14 lg:grid-cols-[0.9fr_1.4fr]">
        <div data-reveal>
          <p className="text-base font-medium text-ink-muted">Ofertas vigentes</p>
          <h2 className="mt-4 font-display text-6xl sm:text-8xl">
            {maxDiscount > 0 ? (
              <>
                <span className="block text-hot">-{maxDiscount}%</span>
                <span className="block">en piezas seleccionadas</span>
              </>
            ) : (
              "Precios especiales"
            )}
          </h2>
          {ends && (
            <div className="mt-10">
              <p className="mb-3 text-sm text-ink-muted">La oferta más próxima termina en</p>
              <Countdown until={ends} />
            </div>
          )}
          <Link
            href="/catalogo?on_sale=1"
            className="mt-10 inline-flex h-14 items-center gap-3 rounded-full bg-hot px-8 text-[0.95rem] font-semibold text-ink transition-transform hover:scale-[1.03]"
          >
            Ver todas las ofertas
          </Link>
        </div>
        <ul className="grid grid-cols-2 gap-4">
          {products.slice(0, 4).map((p, i) => (
            <li key={p.id} data-reveal style={{ "--reveal-delay": `${i * 90}ms` } as React.CSSProperties}>
              <Link href={`/producto/${p.slug}`} className="group block rounded-[var(--radius)] bg-ink-2 p-3 ring-1 ring-white/5 transition-colors hover:ring-white/20">
                <div className="relative aspect-square overflow-hidden rounded-[calc(var(--radius)-6px)]">
                  {p.image && (
                    <Image
                      src={mediaUrl(p.image.path)}
                      alt={p.image.alt}
                      fill
                      sizes="(min-width: 1024px) 25vw, 45vw"
                      className="object-cover transition-transform duration-[1.2s] group-hover:scale-105"
                    />
                  )}
                  {p.price?.discount_percent ? (
                    <span className="absolute left-2 top-2 rounded-full bg-hot px-2.5 py-1 text-[0.7rem] font-bold text-ink">
                      -{p.price.discount_percent}%
                    </span>
                  ) : null}
                </div>
                <div className="px-1 pb-1 pt-3">
                  <h3 className="truncate text-sm font-medium">{p.name}</h3>
                  <Price price={p.price} tone="ink" />
                </div>
              </Link>
            </li>
          ))}
        </ul>
      </Container>
    </section>
  );
}
