import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { apiGetOrNull } from "@/lib/api";
import { listProducts, toApiQuery, type SearchParams } from "@/lib/catalog";
import type { CategoryDetail } from "@/lib/types";
import { Container } from "@/components/ui/Container";
import { Breadcrumbs } from "@/components/ui/Breadcrumbs";
import { CatalogView } from "@/components/catalog/CatalogView";

type Props = { params: Promise<{ slug: string }>; searchParams: Promise<SearchParams> };

const getCategory = (slug: string) =>
  apiGetOrNull<CategoryDetail>(`/catalog/categories/${encodeURIComponent(slug)}`, { tags: ["catalog"] });

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const category = await getCategory((await params).slug);
  return category
    ? { title: category.name, description: category.description ?? undefined, alternates: { canonical: `/categoria/${category.slug}` } }
    : {};
}

export default async function CategoryPage({ params, searchParams }: Props) {
  const { slug } = await params;
  const query = await searchParams;
  const category = await getCategory(slug);
  if (!category) notFound();

  const list = await listProducts(toApiQuery(query, { category: slug }));

  return (
    <Container className="py-10">
      <Breadcrumbs
        items={[
          { name: "Inicio", href: "/" },
          ...category.breadcrumbs.slice(0, -1).map((c) => ({ name: c.name, href: `/categoria/${c.slug}` })),
          { name: category.name },
        ]}
      />
      <header className="mb-10 mt-4">
        <h1 className="font-display text-4xl">{category.name}</h1>
        {category.description && <p className="mt-3 max-w-2xl text-muted">{category.description}</p>}
        {category.children.length > 0 && (
          <ul className="mt-6 flex flex-wrap gap-2">
            {category.children.map((c) => (
              <li key={c.slug}>
                <Link href={`/categoria/${c.slug}`} className="inline-block rounded-full border border-border px-4 py-2 text-sm hover:border-fg">
                  {c.name}
                </Link>
              </li>
            ))}
          </ul>
        )}
      </header>
      <CatalogView list={list} pathname={`/categoria/${slug}`} params={query} />
    </Container>
  );
}
