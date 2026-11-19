import type { Metadata } from "next";
import { listProducts, toApiQuery, type SearchParams } from "@/lib/catalog";
import { Container } from "@/components/ui/Container";
import { CatalogView } from "@/components/catalog/CatalogView";
import { Breadcrumbs } from "@/components/ui/Breadcrumbs";

export const metadata: Metadata = { title: "Catálogo" };

export default async function CatalogPage({ searchParams }: { searchParams: Promise<SearchParams> }) {
  const params = await searchParams;
  const list = await listProducts(toApiQuery(params));

  return (
    <Container className="py-10">
      <Breadcrumbs items={[{ name: "Inicio", href: "/" }, { name: "Catálogo" }]} />
      <h1 className="mb-12 mt-5 font-display text-6xl sm:text-8xl">Catálogo</h1>
      <CatalogView list={list} pathname="/catalogo" params={params} />
    </Container>
  );
}
