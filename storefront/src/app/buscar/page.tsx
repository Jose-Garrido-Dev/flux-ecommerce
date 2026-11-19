import type { Metadata } from "next";
import { listProducts, toApiQuery, type SearchParams } from "@/lib/catalog";
import { Container } from "@/components/ui/Container";
import { CatalogView } from "@/components/catalog/CatalogView";
import { SearchForm } from "@/components/site/SearchForm";

export const metadata: Metadata = { title: "Buscar", robots: { index: false } };

export default async function SearchPage({ searchParams }: { searchParams: Promise<SearchParams> }) {
  const params = await searchParams;
  const q = typeof params.q === "string" ? params.q.slice(0, 100) : "";

  return (
    <Container className="py-10">
      <h1 className="font-display text-5xl sm:text-7xl">{q ? `Resultados para “${q}”` : "Buscar"}</h1>
      <SearchForm id="q-pagina" defaultValue={q} className="mb-10 mt-6 max-w-xl" />
      {q && <CatalogView list={await listProducts(toApiQuery(params))} pathname="/buscar" params={params} />}
    </Container>
  );
}
