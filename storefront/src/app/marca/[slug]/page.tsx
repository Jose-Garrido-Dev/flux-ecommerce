import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { apiGetOrNull } from "@/lib/api";
import { listProducts, toApiQuery, type SearchParams } from "@/lib/catalog";
import type { BrandDetail } from "@/lib/types";
import { Container } from "@/components/ui/Container";
import { Breadcrumbs } from "@/components/ui/Breadcrumbs";
import { CatalogView } from "@/components/catalog/CatalogView";

type Props = { params: Promise<{ slug: string }>; searchParams: Promise<SearchParams> };

const getBrand = (slug: string) => apiGetOrNull<BrandDetail>(`/catalog/brands/${encodeURIComponent(slug)}`, { tags: ["catalog"] });

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const brand = await getBrand((await params).slug);
  return brand ? { title: brand.name, description: brand.description ?? undefined } : {};
}

export default async function BrandPage({ params, searchParams }: Props) {
  const { slug } = await params;
  const query = await searchParams;
  const brand = await getBrand(slug);
  if (!brand) notFound();

  const list = await listProducts(toApiQuery(query, { brand: slug }));

  return (
    <Container className="py-10">
      <Breadcrumbs items={[{ name: "Inicio", href: "/" }, { name: "Marcas" }, { name: brand.name }]} />
      <header className="mb-10 mt-4">
        <h1 className="font-display text-4xl">{brand.name}</h1>
        {brand.description && <p className="mt-3 max-w-2xl text-muted">{brand.description}</p>}
      </header>
      <CatalogView list={list} pathname={`/marca/${slug}`} params={query} />
    </Container>
  );
}
