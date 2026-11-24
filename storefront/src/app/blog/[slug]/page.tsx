import type { Metadata } from "next";
import Image from "next/image";
import Link from "next/link";
import { notFound } from "next/navigation";
import { getBlogPost } from "@/lib/blog";
import { getStore } from "@/lib/catalog";
import { mediaUrl } from "@/lib/format";
import { Container } from "@/components/ui/Container";
import { JsonLd } from "@/components/seo/JsonLd";
import { PostCard, blogDate } from "@/components/blog/PostCard";

type Props = { params: Promise<{ slug: string }> };

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const post = await getBlogPost((await params).slug);
  if (!post) return {};
  const image = post.image ? [{ url: mediaUrl(post.image.path), alt: post.image.alt }] : undefined;
  return {
    title: post.seo.title,
    description: post.seo.description ?? undefined,
    alternates: { canonical: `/blog/${post.slug}` },
    openGraph: { type: "article", title: post.seo.title, description: post.seo.description ?? undefined, publishedTime: post.published_at, images: image },
  };
}

export default async function BlogPostPage({ params }: Props) {
  const [post, store] = await Promise.all([getBlogPost((await params).slug), getStore()]);
  if (!post) notFound();
  const site = process.env.NEXT_PUBLIC_SITE_URL ?? "http://localhost:3000";
  const author = post.author ?? store?.name ?? "Tienda";

  const article = {
    "@context": "https://schema.org",
    "@type": "Article",
    headline: post.title,
    description: post.seo.description ?? undefined,
    datePublished: post.published_at,
    dateModified: post.updated_at ?? post.published_at,
    mainEntityOfPage: `${site}/blog/${post.slug}`,
    ...(post.image ? { image: [`${site}${mediaUrl(post.image.path)}`] } : {}),
    author: post.author ? { "@type": "Person", name: post.author } : { "@type": "Organization", name: author },
    publisher: { "@type": "Organization", name: store?.name ?? "Tienda" },
  };
  const breadcrumbs = {
    "@context": "https://schema.org",
    "@type": "BreadcrumbList",
    itemListElement: [
      { "@type": "ListItem", position: 1, name: "Inicio", item: `${site}/` },
      { "@type": "ListItem", position: 2, name: "Blog", item: `${site}/blog` },
      { "@type": "ListItem", position: 3, name: post.title, item: `${site}/blog/${post.slug}` },
    ],
  };

  return (
    <>
      <JsonLd data={article} />
      <JsonLd data={breadcrumbs} />
      <Container className="py-12">
        <article className="mx-auto max-w-3xl">
          <nav aria-label="Ruta" className="text-sm text-muted">
            <Link href="/blog" className="hover:underline underline-offset-4">
              Blog
            </Link>
            {post.category && (
              <>
                {" / "}
                <Link href={`/blog?categoria=${post.category.slug}`} className="hover:underline underline-offset-4">
                  {post.category.name}
                </Link>
              </>
            )}
          </nav>
          <h1 className="mt-4 font-display text-5xl leading-[1.02] sm:text-6xl">{post.title}</h1>
          <p className="mt-5 text-sm text-muted">
            {author} · <time dateTime={post.published_at}>{blogDate.format(new Date(post.published_at))}</time> · {post.reading_minutes} min de lectura
          </p>
          {post.image && (
            <div className="relative mt-10 aspect-[1200/630] overflow-hidden rounded-[var(--radius)] bg-subtle">
              <Image src={mediaUrl(post.image.path)} alt={post.image.alt} fill priority sizes="(min-width: 768px) 48rem, 100vw" className="object-cover" />
            </div>
          )}
          {/* HTML generado por Laravel desde Markdown con el HTML del autor escapado */}
          <div className="prose-flux mt-10 text-lg leading-relaxed" dangerouslySetInnerHTML={{ __html: post.html }} />
        </article>

        {post.related.length > 0 && (
          <section aria-labelledby="relacionados" className="mx-auto mt-24 max-w-6xl">
            <h2 id="relacionados" className="font-display text-4xl">
              Sigue leyendo
            </h2>
            <div className="mt-10 grid gap-x-8 gap-y-14 sm:grid-cols-2 lg:grid-cols-3">
              {post.related.map((p) => (
                <PostCard key={p.slug} post={p} />
              ))}
            </div>
          </section>
        )}
      </Container>
    </>
  );
}
