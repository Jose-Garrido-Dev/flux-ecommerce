import Image from "next/image";
import Link from "next/link";
import { connection } from "next/server";
import { apiGet } from "@/lib/api";
import { getStore } from "@/lib/catalog";
import { mediaUrl } from "@/lib/format";
import type { HomeData } from "@/lib/types";
import { Container } from "@/components/ui/Container";
import { ButtonLink } from "@/components/ui/Button";
import { ProductGrid } from "@/components/catalog/ProductCard";

async function getHome(): Promise<HomeData | null> {
  try {
    return await apiGet<HomeData>("/home", { tags: ["catalog", "home"], revalidate: 120 });
  } catch {
    return null;
  }
}

/** Portada con bloques fijos (Fase 2). En la Fase 9 los bloques vienen del constructor de páginas. */
export default async function Home() {
  // Se arma en cada visita con datos en caché: así nunca queda "congelada" una portada vacía del build
  await connection();
  const [store, home] = await Promise.all([getStore(), getHome()]);

  if (!store) {
    return (
      <Container className="py-32">
        <section role="status" className="reveal max-w-xl">
          <h1 className="font-display text-4xl">Tienda en mantenimiento</h1>
          <p className="mt-4 text-muted">No pudimos conectar con el servidor de la tienda. Intenta nuevamente en unos minutos.</p>
        </section>
      </Container>
    );
  }

  const empty = !home || (home.featured.length === 0 && home.newest.length === 0);

  return (
    <>
      <section className="border-b border-border">
        <Container className="grid items-end gap-10 py-16 sm:py-24 lg:grid-cols-[1.4fr_1fr] lg:py-32">
          <div className="reveal">
            <p className="label">Nueva temporada</p>
            <h1 className="mt-5 font-display text-5xl leading-[1.05] tracking-tight sm:text-7xl">{store.name}</h1>
            {store.tagline && <p className="mt-6 max-w-xl text-lg text-muted">{store.tagline}</p>}
            {!empty && (
              <div className="mt-10 flex flex-wrap gap-3">
                <ButtonLink href="/catalogo">Ver catálogo</ButtonLink>
                {home!.categories[0] && (
                  <ButtonLink href={`/categoria/${home!.categories[0].slug}`} variant="secondary">
                    {home!.categories[0].name}
                  </ButtonLink>
                )}
              </div>
            )}
          </div>
          <p className="reveal text-sm text-muted lg:text-right">
            Precios en pesos chilenos, IVA incluido.
            <br />
            Despacho a todo Chile.
          </p>
        </Container>
      </section>

      {empty ? (
        <Container className="py-20">
          <p className="text-muted">Estamos preparando el catálogo. Vuelve pronto.</p>
        </Container>
      ) : (
        <>
          {home!.categories.length > 0 && (
            <Container className="py-16">
              <h2 className="font-display text-3xl">Categorías</h2>
              <ul className="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {home!.categories.map((c) => (
                  <li key={c.slug}>
                    <Link href={`/categoria/${c.slug}`} className="group relative block aspect-[4/3] overflow-hidden rounded-[var(--radius)] bg-subtle">
                      {c.image && (
                        <Image
                          src={mediaUrl(c.image.path)}
                          alt=""
                          fill
                          sizes="(min-width: 1024px) 33vw, (min-width: 640px) 50vw, 100vw"
                          className="object-cover transition-transform duration-[var(--motion-slow)] group-hover:scale-[1.03]"
                        />
                      )}
                      <span className="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/55 to-transparent p-5 pt-16 font-display text-2xl text-white">
                        {c.name}
                      </span>
                    </Link>
                  </li>
                ))}
              </ul>
            </Container>
          )}

          {home!.featured.length > 0 && (
            <Container className="py-16">
              <div className="mb-8 flex items-end justify-between gap-4">
                <h2 className="font-display text-3xl">Destacados</h2>
                <Link href="/catalogo" className="text-sm underline underline-offset-4">
                  Ver todo
                </Link>
              </div>
              <ProductGrid products={home!.featured} />
            </Container>
          )}

          {home!.newest.length > 0 && (
            <Container className="py-16">
              <div className="mb-8 flex items-end justify-between gap-4">
                <h2 className="font-display text-3xl">Recién llegados</h2>
                <Link href="/catalogo?sort=newest" className="text-sm underline underline-offset-4">
                  Ver todo
                </Link>
              </div>
              <ProductGrid products={home!.newest} />
            </Container>
          )}

          {home!.brands.length > 0 && (
            <Container className="py-16">
              <h2 className="label text-center">Nuestras marcas</h2>
              <ul className="mt-8 flex flex-wrap items-center justify-center gap-x-12 gap-y-6">
                {home!.brands.map((b) => (
                  <li key={b.slug}>
                    <Link href={`/marca/${b.slug}`} className="font-display text-2xl text-muted transition-colors hover:text-fg">
                      {b.name}
                    </Link>
                  </li>
                ))}
              </ul>
            </Container>
          )}
        </>
      )}
    </>
  );
}
