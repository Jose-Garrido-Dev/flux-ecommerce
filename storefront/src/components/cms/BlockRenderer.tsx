import Image from "next/image";
import Link from "next/link";
import type { CmsBlock } from "@/lib/types";
import { mediaUrl } from "@/lib/format";
import { Container } from "@/components/ui/Container";
import { ProductCard, ProductGrid } from "@/components/catalog/ProductCard";
import { Carousel } from "@/components/motion/Carousel";
import { Countdown } from "@/components/motion/Countdown";
import { Hero } from "@/components/home/Hero";
import { CategoryBento } from "@/components/home/CategoryBento";
import { Marquee } from "@/components/home/Marquee";
import { SectionHeading } from "@/components/home/SectionHeading";
import { CmsLink } from "./CmsLink";

/**
 * Dibuja los bloques de una página armada en el panel. Cada tipo de bloque tiene su componente;
 * el esquema compartido (src/lib/cms-blocks.json) es el mismo que valida Laravel antes de publicar.
 * Un tipo desconocido (por ejemplo, de una versión más nueva del backend) se omite.
 */
export function BlockRenderer({ blocks, headingLevel = 1 }: { blocks: CmsBlock[]; headingLevel?: 1 | 2 }) {
  // El primer titular de la página es <h1> solo si la página no tiene otro
  const firstHeroIndex = headingLevel === 1 ? blocks.findIndex((b) => b.type === "hero") : -1;

  return (
    <>
      {blocks.map((block, i) => (
        <Block key={block.id} block={block} first={i === 0} h1={i === firstHeroIndex} />
      ))}
    </>
  );
}

const btnLight = "inline-flex h-12 items-center rounded-full bg-white px-7 text-sm font-semibold text-black transition-transform hover:scale-[1.03]";
const btnAccent = "inline-flex h-12 items-center rounded-full bg-accent px-7 text-sm font-semibold text-accent-fg transition-transform hover:scale-[1.03]";

function Block({ block, first, h1 }: { block: CmsBlock; first: boolean; h1: boolean }) {
  const space = first ? "" : "pt-24 sm:pt-28";

  switch (block.type) {
    case "hero": {
      const d = block.data;
      const cta = d.button_label && d.button_link ? { label: d.button_label, href: d.button_link } : null;
      const Title = h1 ? "h1" : "h2";
      if ((d.style ?? "field") === "field" || !d.image) {
        return (
          <div className={first ? "" : space}>
            <Hero title={d.title} subtitle={d.subtitle} products={d.products} firstCategory={null} cta={cta} />
          </div>
        );
      }
      if (d.style === "image") {
        return (
          <section className={`relative isolate flex min-h-[min(86svh,56rem)] items-end overflow-hidden text-white ${first ? "" : "mt-24 sm:mt-28"}`}>
            <Image src={mediaUrl(d.image.path)} alt={d.image.alt} fill priority={first} sizes="100vw" className="-z-10 object-cover" />
            <div className="absolute inset-0 -z-10 bg-gradient-to-t from-black/75 via-black/25 to-black/5" />
            <Container className="pb-16 pt-40 sm:pb-24">
              <Title className="max-w-4xl font-display text-[clamp(3rem,8vw,7.5rem)]">{d.title}</Title>
              {d.subtitle && <p className="mt-5 max-w-xl text-lg text-white/85 sm:text-xl">{d.subtitle}</p>}
              {cta && (
                <CmsLink href={cta.href} className={`${btnLight} mt-9`}>
                  {cta.label}
                </CmsLink>
              )}
            </Container>
          </section>
        );
      }
      return (
        <Container className={`grid items-center gap-10 lg:grid-cols-2 ${first ? "py-12 lg:py-16" : space}`}>
          <div>
            <Title className="font-display text-[clamp(2.8rem,7vw,6.5rem)]">{d.title}</Title>
            {d.subtitle && <p className="mt-5 max-w-xl text-lg text-muted">{d.subtitle}</p>}
            {cta && (
              <CmsLink href={cta.href} className={`${btnAccent} mt-9`}>
                {cta.label}
              </CmsLink>
            )}
          </div>
          <div className="relative aspect-[4/5] overflow-hidden rounded-[var(--radius)] bg-subtle sm:aspect-[5/4] lg:aspect-[4/5]">
            <Image src={mediaUrl(d.image.path)} alt={d.image.alt} fill priority={first} sizes="(min-width: 1024px) 50vw, 100vw" className="object-cover" />
          </div>
        </Container>
      );
    }

    case "banner": {
      const d = block.data;
      const inner = (
        <div className="group relative isolate flex min-h-[22rem] items-end overflow-hidden rounded-[var(--radius)] p-8 text-white sm:min-h-[28rem] sm:p-12">
          <Image src={mediaUrl(d.image.path)} alt={d.image.alt} fill sizes="(min-width: 1280px) 1216px, 100vw" className="-z-10 object-cover transition-transform duration-[1.4s] ease-[var(--ease-out)] group-hover:scale-[1.04]" />
          {(d.title || d.text) && <div className="absolute inset-0 -z-10 bg-gradient-to-t from-black/70 via-black/15 to-transparent" />}
          <div className="max-w-2xl">
            {d.title && <h2 className="font-display text-5xl sm:text-7xl">{d.title}</h2>}
            {d.text && <p className="mt-4 text-lg text-white/85">{d.text}</p>}
            {d.button_label && d.link && <span className={`${btnLight} mt-7`}>{d.button_label}</span>}
          </div>
        </div>
      );
      return (
        <Container className={space}>
          <div data-reveal>{d.link ? <CmsLink href={d.link} className="block">{inner}</CmsLink> : inner}</div>
        </Container>
      );
    }

    case "image_text": {
      const d = block.data;
      return (
        <Container className={`grid items-center gap-10 md:grid-cols-2 lg:gap-16 ${space}`}>
          {d.image && (
            <div data-reveal className={`relative aspect-[4/5] overflow-hidden rounded-[var(--radius)] bg-subtle ${d.image_side === "right" ? "md:order-2" : ""}`}>
              <Image src={mediaUrl(d.image.path)} alt={d.image.alt} fill sizes="(min-width: 768px) 50vw, 100vw" className="object-cover" />
            </div>
          )}
          <div data-reveal className={d.image ? "" : "md:col-span-2 max-w-3xl"}>
            {d.title && <h2 className="font-display text-5xl sm:text-6xl">{d.title}</h2>}
            {d.html && <div className="prose-flux mt-6 text-lg leading-relaxed text-muted" dangerouslySetInnerHTML={{ __html: d.html }} />}
            {d.button_label && d.link && (
              <CmsLink href={d.link} className={`${btnAccent} mt-8`}>
                {d.button_label}
              </CmsLink>
            )}
          </div>
        </Container>
      );
    }

    case "rich_text": {
      const d = block.data;
      return (
        <Container className={space}>
          <div className="mx-auto max-w-3xl">
            {d.title && <h2 className="mb-6 font-display text-5xl sm:text-6xl">{d.title}</h2>}
            {/* HTML generado por Laravel desde Markdown: el HTML escrito a mano llega escapado */}
            <div className="prose-flux text-lg leading-relaxed" dangerouslySetInnerHTML={{ __html: d.html }} />
          </div>
        </Container>
      );
    }

    case "products": {
      const d = block.data;
      const title = d.title ?? "Productos";
      return (
        <Container className={space}>
          <SectionHeading title={title} href={d.link ?? undefined} linkLabel={d.link_label ?? "Ver todo"} />
          {d.layout === "grid" ? (
            <ProductGrid products={d.products} />
          ) : (
            <Carousel label={title}>
              {d.products.map((p) => (
                <li key={p.id} className="w-[72%] shrink-0 snap-start sm:w-[44%] md:w-[31%] xl:w-[23.5%]">
                  <ProductCard product={p} sizes="(min-width: 1280px) 23vw, (min-width: 768px) 31vw, 72vw" />
                </li>
              ))}
            </Carousel>
          )}
        </Container>
      );
    }

    case "categories":
      return (
        <Container className={space}>
          <SectionHeading title={block.data.title ?? "Categorías"} href="/catalogo" />
          <CategoryBento categories={block.data.categories} />
        </Container>
      );

    case "brands": {
      const d = block.data;
      const withLogos = d.brands.filter((b) => b.logo);
      return (
        <section className={space}>
          <Container>
            <p data-reveal className="label mb-6 text-center">
              {d.title ?? "Nuestras marcas"}
            </p>
          </Container>
          {withLogos.length >= 4 && withLogos.length === d.brands.length ? (
            <Container>
              <ul className="grid grid-cols-2 gap-4 sm:grid-cols-4 lg:grid-cols-6">
                {d.brands.map((b) => (
                  <li key={b.slug}>
                    <Link href={`/marca/${b.slug}`} className="relative flex aspect-[3/2] items-center justify-center rounded-[var(--radius)] border border-border bg-surface p-6 transition-colors hover:border-fg">
                      <Image src={mediaUrl(b.logo!.path)} alt={b.name} fill sizes="12rem" className="object-contain p-6" />
                    </Link>
                  </li>
                ))}
              </ul>
            </Container>
          ) : (
            <Marquee items={d.brands.map((b) => ({ label: b.name, href: `/marca/${b.slug}` }))} />
          )}
        </section>
      );
    }

    case "marquee":
      return (
        <div className={space}>
          <Marquee items={block.data.items.map((it) => ({ label: it.text, href: it.link ?? "/catalogo" }))} />
        </div>
      );

    case "countdown": {
      const d = block.data;
      return (
        <div className={space}>
          <section className="relative isolate overflow-hidden bg-ink py-20 text-ink-fg sm:py-24">
            <Container className="grid items-center gap-10 lg:grid-cols-[1.2fr_1fr]">
              <div data-reveal>
                <h2 className="font-display text-6xl sm:text-8xl">{d.title}</h2>
                {d.text && <p className="mt-5 max-w-xl text-lg text-ink-muted">{d.text}</p>}
                {d.button_label && d.link && (
                  <CmsLink href={d.link} className={`${btnLight} mt-8`}>
                    {d.button_label}
                  </CmsLink>
                )}
              </div>
              <div data-reveal className="lg:justify-self-end">
                <Countdown until={d.ends_at} label={`Tiempo restante: ${d.title}`} />
              </div>
            </Container>
          </section>
        </div>
      );
    }

    case "testimonials": {
      const d = block.data;
      return (
        <Container className={space}>
          {d.title && <SectionHeading title={d.title} />}
          <ul className="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
            {d.items.map((t, i) => (
              <li key={i} data-reveal style={{ "--reveal-delay": `${(i % 3) * 90}ms` } as React.CSSProperties}>
                <figure className="flex h-full flex-col rounded-[var(--radius)] border border-border bg-surface p-7">
                  {t.rating ? (
                    <p className="text-accent" aria-label={`${t.rating} de 5 estrellas`}>
                      <span aria-hidden>{"★".repeat(t.rating)}{"☆".repeat(5 - t.rating)}</span>
                    </p>
                  ) : null}
                  <blockquote className="mt-4 flex-1 text-lg leading-relaxed">“{t.text}”</blockquote>
                  <figcaption className="mt-6 text-sm font-semibold text-muted">{t.name}</figcaption>
                </figure>
              </li>
            ))}
          </ul>
        </Container>
      );
    }

    case "faq": {
      const d = block.data;
      return (
        <Container className={space}>
          <div className="mx-auto max-w-3xl">
            <h2 className="mb-8 font-display text-5xl sm:text-6xl">{d.title ?? "Preguntas frecuentes"}</h2>
            <div className="divide-y divide-border border-y border-border">
              {d.items.map((q, i) => (
                <details key={i} className="group py-5">
                  <summary className="flex cursor-pointer list-none items-center justify-between gap-6 text-lg font-medium [&::-webkit-details-marker]:hidden">
                    {q.question}
                    <span aria-hidden className="text-2xl leading-none text-muted transition-transform group-open:rotate-45">+</span>
                  </summary>
                  <div className="prose-flux mt-3 text-muted" dangerouslySetInnerHTML={{ __html: q.html }} />
                </details>
              ))}
            </div>
          </div>
        </Container>
      );
    }

    case "video": {
      const d = block.data;
      if (!/^https:\/\/(www\.youtube-nocookie\.com|player\.vimeo\.com)\//.test(d.embed)) return null;
      return (
        <Container className={space}>
          {d.title && <SectionHeading title={d.title} />}
          <div className="relative aspect-video overflow-hidden rounded-[var(--radius)] bg-ink">
            <iframe
              src={d.embed}
              title={d.title ?? "Video"}
              loading="lazy"
              allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; fullscreen"
              allowFullScreen
              referrerPolicy="strict-origin-when-cross-origin"
              className="absolute inset-0 h-full w-full"
            />
          </div>
        </Container>
      );
    }

    default:
      return null;
  }
}
