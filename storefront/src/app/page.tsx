import { connection } from "next/server";
import { apiGet } from "@/lib/api";
import { getStore } from "@/lib/catalog";
import type { HomeData } from "@/lib/types";
import { Container } from "@/components/ui/Container";
import { ProductCard, ProductGrid } from "@/components/catalog/ProductCard";
import { Carousel } from "@/components/motion/Carousel";
import { Hero } from "@/components/home/Hero";
import { CategoryBento } from "@/components/home/CategoryBento";
import { SaleSection } from "@/components/home/SaleSection";
import { SectionHeading } from "@/components/home/SectionHeading";
import { Marquee } from "@/components/home/Marquee";
import { BlockRenderer } from "@/components/cms/BlockRenderer";

async function getHome(): Promise<HomeData | null> {
  try {
    const home = await apiGet<HomeData>("/home", { tags: ["catalog", "home", "pages"], revalidate: 120 });
    // Tolera un backend sin actualizar (o una respuesta en caché anterior) que aún no envía on_sale
    return { ...home, on_sale: home.on_sale ?? [] };
  } catch {
    return null;
  }
}

/**
 * Portada. Si en el panel hay una portada publicada (Contenido → Páginas), se dibujan sus bloques;
 * si no, la portada fija con destacados, ofertas, novedades y marcas.
 */
export default async function Home() {
  // Se arma en cada visita con datos en caché: así nunca queda "congelada" una portada vacía del build
  await connection();
  const [store, home] = await Promise.all([getStore(), getHome()]);

  if (!store) {
    return (
      <Container className="py-32">
        <section role="status" className="max-w-xl">
          <h1 className="font-display text-5xl">Tienda en mantenimiento</h1>
          <p className="mt-4 text-muted">No pudimos conectar con el servidor de la tienda. Intenta nuevamente en unos minutos.</p>
        </section>
      </Container>
    );
  }

  if (home?.page && home.page.blocks.length > 0) {
    return <BlockRenderer blocks={home.page.blocks} />;
  }

  const empty = !home || (home.featured.length === 0 && home.newest.length === 0);
  const heroProducts = home ? [...home.featured, ...home.newest] : [];

  return (
    <>
      <Hero
        title={store.tagline ?? store.name}
        products={heroProducts}
        firstCategory={home?.categories[0] ?? null}
      />

      {empty ? (
        <Container className="py-24">
          <p className="text-lg text-muted">Estamos preparando el catálogo. Vuelve pronto.</p>
        </Container>
      ) : (
        <>
          {home!.categories.length > 0 && (
            <Marquee items={home!.categories.map((c) => ({ label: c.name, href: `/categoria/${c.slug}` }))} />
          )}

          {home!.categories.length > 0 && (
            <Container className="pt-24">
              <SectionHeading title="Categorías" href="/catalogo" />
              <CategoryBento categories={home!.categories} />
            </Container>
          )}

          {home!.featured.length > 0 && (
            <Container className="pt-28">
              <SectionHeading title="Destacados" />
              <Carousel label="Productos destacados">
                {home!.featured.map((p) => (
                  <li key={p.id} className="w-[72%] shrink-0 snap-start sm:w-[44%] md:w-[31%] xl:w-[23.5%]">
                    <ProductCard product={p} sizes="(min-width: 1280px) 23vw, (min-width: 768px) 31vw, 72vw" />
                  </li>
                ))}
              </Carousel>
            </Container>
          )}

          {home!.on_sale.length > 0 && (
            <div className="pt-28">
              <SaleSection products={home!.on_sale} />
            </div>
          )}

          {home!.newest.length > 0 && (
            <Container className="pt-28">
              <SectionHeading title="Recién llegados" href="/catalogo?sort=newest" />
              <ProductGrid products={home!.newest} />
            </Container>
          )}

          {home!.brands.length > 0 && (
            <section className="pt-28">
              <Container>
                <p data-reveal className="label mb-6 text-center">Nuestras marcas</p>
              </Container>
              <Marquee items={home!.brands.map((b) => ({ label: b.name, href: `/marca/${b.slug}` }))} />
            </section>
          )}
        </>
      )}
    </>
  );
}
