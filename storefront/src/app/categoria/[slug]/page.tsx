import type { Metadata } from "next";
import Image from "next/image";
import Link from "next/link";
import { mediaUrl } from "@/lib/format";
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
      <header className="relative mb-12 mt-5 overflow-hidden rounded-[var(--radius)] bg-ink text-ink-fg">
        {category.image && (
          <Image src={mediaUrl(category.image.path)} alt="" fill priority sizes="100vw" className="object-cover opacity-60" />
        )}
        <div className="absolute inset-0 bg-gradient-to-r from-black/70 via-black/30 to-transparent" />
        <div className="relative px-6 py-14 sm:px-12 sm:py-20">
          <h1 className="font-display text-6xl sm:text-8xl">{category.name}</h1>
          {category.description && <p className="mt-4 max-w-xl text-ink-fg/80">{category.description}</p>}
          {category.children.length > 0 && (
            <ul className="mt-8 flex flex-wrap gap-2">
              {category.children.map((c) => (
                <li key={c.slug}>
                  <Link
                    href={`/categoria/${c.slug}`}
                    className="inline-block rounded-full border border-white/30 bg-white/5 px-4 py-2 text-sm backdrop-blur transition-colors hover:bg-white hover:text-black"
                  >
                    {c.name}
                  </Link>
                </li>
              ))}
            </ul>
          )}
        </div>
      </header>
      <CatalogView list={list} pathname={`/categoria/${slug}`} params={query} />
    </Container>
  );
}
