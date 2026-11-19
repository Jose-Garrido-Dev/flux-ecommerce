import Image from "next/image";
import Link from "next/link";
import type { ProductCard } from "@/lib/types";
import { formatMoney, mediaUrl } from "@/lib/format";
import { Container } from "@/components/ui/Container";
import { Parallax } from "@/components/motion/Parallax";

/** Divide el titular en palabras para animarlas una a una (solo transform, sin ocultar el texto). */
function AnimatedHeadline({ text }: { text: string }) {
  const words = text.split(/\s+/);
  return (
    <>
      {words.map((w, i) => (
        <span key={i}>
          <span className="word-mask">
            <span className="word" style={{ animationDelay: `${120 + i * 90}ms` }}>
              {w}
            </span>
          </span>
          {i < words.length - 1 && " "}
        </span>
      ))}
    </>
  );
}

/** Tarjetas flotantes con productos reales del catálogo (destacados), con parallax. */
const SLOTS = [
  { cls: "left-[4%] top-[6%] w-[46%]", depth: 0.12, tilt: "-4deg" },
  { cls: "right-[2%] top-[0%] w-[40%]", depth: 0.28, tilt: "5deg" },
  { cls: "left-[30%] bottom-[0%] w-[42%]", depth: 0.2, tilt: "2deg" },
];

export function Hero({
  title,
  products,
  firstCategory,
}: {
  title: string;
  products: ProductCard[];
  firstCategory: { name: string; slug: string } | null;
}) {
  const showcase = products.filter((p) => p.image).slice(0, 3);

  return (
    <section className="relative isolate overflow-hidden bg-field text-field-fg">

      <Container className="grid min-h-[calc(100svh-6.5rem)] items-center gap-12 py-16 lg:grid-cols-[1.15fr_1fr] lg:py-20">
        <div>
          <h1 className="font-display text-[clamp(3.4rem,10vw,9rem)]">
            <AnimatedHeadline text={title} />
          </h1>
          <div className="fade-up mt-10 flex flex-wrap items-center gap-3" style={{ animationDelay: "500ms" }}>
            <Link
              href="/catalogo"
              className="inline-flex h-14 items-center rounded-full bg-field-fg px-8 text-[0.95rem] font-semibold text-field transition-transform hover:scale-[1.03]"
            >
              Ver catálogo
            </Link>
            {firstCategory && (
              <Link
                href={`/categoria/${firstCategory.slug}`}
                className="inline-flex h-14 items-center rounded-full border-2 border-field-fg/40 px-8 text-[0.95rem] font-semibold transition-colors hover:border-field-fg"
              >
                {firstCategory.name}
              </Link>
            )}
          </div>
        </div>

        {showcase.length > 0 && (
          <Parallax className="relative mx-auto aspect-square w-full max-w-[34rem]">
            {showcase.map((p, i) => {
              const slot = SLOTS[i];
              return (
                <Link
                  key={p.id}
                  href={`/producto/${p.slug}`}
                  className={`float-card absolute ${slot.cls} ${i === 0 ? "z-10" : ""}`}
                  style={{ "--depth": slot.depth, "--tilt": slot.tilt } as React.CSSProperties}
                >
                  <div className="float-inner">
                    <div className="rise" style={{ animationDelay: `${200 + i * 140}ms` }}>
                      <div className="rounded-[var(--radius)] bg-white p-2 shadow-[var(--shadow-float)]">
                        <div className="relative aspect-[4/5] overflow-hidden rounded-[var(--radius-sm)]">
                        <Image
                          src={mediaUrl(p.image!.path)}
                          alt={p.image!.alt}
                          fill
                          priority={i === 0}
                          sizes="(min-width: 1024px) 18vw, 40vw"
                          className="object-cover"
                        />
                        </div>
                      </div>
                      {i === 0 && p.price && (
                        <span className="absolute -bottom-5 left-4 flex flex-col rounded-[var(--radius)] bg-ink px-4 py-2.5 text-ink-fg shadow-lg">
                          <span className="text-xs text-ink-muted">{p.name}</span>
                          <span className="text-lg font-semibold tabular-nums">{formatMoney(p.price.amount)}</span>
                        </span>
                      )}
                    </div>
                  </div>
                </Link>
              );
            })}
          </Parallax>
        )}
      </Container>

    </section>
  );
}
