import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { apiFetch, ApiError } from "@/lib/api";
import { mediaUrl } from "@/lib/format";
import type { ProductCard, ProductDetail } from "@/lib/types";
import { Container } from "@/components/ui/Container";
import { Breadcrumbs } from "@/components/ui/Breadcrumbs";
import { ProductGrid } from "@/components/catalog/ProductCard";
import { ProductView } from "@/components/product/ProductView";

type Props = { params: Promise<{ slug: string }> };

// ISR: la ficha se genera en la primera visita y se renueva cada 5 minutos o cuando Laravel avisa
export const revalidate = 300;
export const generateStaticParams = async () => [];

async function getProduct(slug: string) {
  try {
    return await apiFetch<{ data: ProductDetail; related: ProductCard[] }>(
      `/catalog/products/${encodeURIComponent(slug)}`,
      { tags: ["catalog", `product:${slug}`] },
    );
  } catch (e) {
    if (e instanceof ApiError && e.status === 404) return null;
    throw e;
  }
}

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const res = await getProduct((await params).slug);
  if (!res) return {};
  const p = res.data;
  return {
    title: p.seo.title,
    description: p.seo.description ?? undefined,
    alternates: { canonical: `/producto/${p.slug}` },
    openGraph: { title: p.name, images: p.image ? [mediaUrl(p.image.path)] : undefined },
  };
}

export default async function ProductPage({ params }: Props) {
  const res = await getProduct((await params).slug);
  if (!res) notFound();
  const { data: product, related } = res;

  const site = process.env.NEXT_PUBLIC_SITE_URL ?? "";
  const jsonLd = [
    {
      "@context": "https://schema.org",
      "@type": "Product",
      name: product.name,
      description: product.seo.description ?? undefined,
      image: product.images.map((i) => `${site}${mediaUrl(i.path)}`),
      brand: product.brand ? { "@type": "Brand", name: product.brand.name } : undefined,
      offers: product.variants
        .filter((v) => v.price)
        .map((v) => ({
          "@type": "Offer",
          sku: v.sku,
          price: v.price!.amount,
          priceCurrency: v.price!.currency,
          url: `${site}/producto/${product.slug}`,
        })),
    },
    {
      "@context": "https://schema.org",
      "@type": "BreadcrumbList",
      itemListElement: [
        ...product.breadcrumbs.map((c, i) => ({ "@type": "ListItem", position: i + 1, name: c.name, item: `${site}/categoria/${c.slug}` })),
        { "@type": "ListItem", position: product.breadcrumbs.length + 1, name: product.name },
      ],
    },
  ];

  return (
    <>
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd).replace(/</g, "\\u003c") }} />
      <Container className="py-8">
        <Breadcrumbs
          items={[
            { name: "Inicio", href: "/" },
            ...product.breadcrumbs.map((c) => ({ name: c.name, href: `/categoria/${c.slug}` })),
            { name: product.name },
          ]}
        />
        <div className="mt-6">
          <ProductView product={product} />
        </div>
      </Container>
      {related.length > 0 && (
        <Container className="py-16">
          <h2 className="mb-8 font-display text-3xl">También te puede gustar</h2>
          <ProductGrid products={related.slice(0, 4)} />
        </Container>
      )}
    </>
  );
}
