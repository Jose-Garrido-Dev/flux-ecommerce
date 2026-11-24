import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { getBlogIndex } from "@/lib/blog";
import { Container } from "@/components/ui/Container";
import { Pagination } from "@/components/catalog/Pagination";
import { PostCard } from "@/components/blog/PostCard";

type Props = { searchParams: Promise<{ page?: string; categoria?: string }> };

export async function generateMetadata({ searchParams }: Props): Promise<Metadata> {
  const { categoria, page } = await searchParams;
  return {
    title: "Blog",
    description: "Novedades, guías y consejos de la tienda.",
    alternates: { canonical: categoria ? `/blog?categoria=${encodeURIComponent(categoria)}` : "/blog" },
    // Las páginas 2 en adelante no se indexan para no duplicar contenido
    ...(page && page !== "1" ? { robots: { index: false, follow: true } } : {}),
  };
}

export default async function BlogPage({ searchParams }: Props) {
  const { page: rawPage, categoria } = await searchParams;
  const page = Math.max(1, Math.min(1000, Number.parseInt(rawPage ?? "1", 10) || 1));
  const blog = await getBlogIndex(page, categoria);
  if (!blog || (categoria && !blog.category)) notFound();

  const chip = (active: boolean) =>
    `rounded-full border px-4 py-2 text-sm ${active ? "border-fg bg-fg text-bg" : "border-border hover:border-fg/60"}`;

  return (
    <Container className="py-12">
      <h1 className="font-display text-6xl sm:text-7xl">{blog.category ? blog.category.name : "Blog"}</h1>
      {blog.categories.length > 0 && (
        <nav aria-label="Categorías del blog" className="mt-8 flex flex-wrap gap-2">
          <Link href="/blog" className={chip(!blog.category)}>
            Todo
          </Link>
          {blog.categories.map((c) => (
            <Link key={c.slug} href={`/blog?categoria=${c.slug}`} className={chip(blog.category?.slug === c.slug)}>
              {c.name}
            </Link>
          ))}
        </nav>
      )}
      {blog.data.length === 0 ? (
        <p className="mt-12 text-muted">Todavía no hay artículos publicados.</p>
      ) : (
        <div className="mt-12 grid gap-x-8 gap-y-14 sm:grid-cols-2 lg:grid-cols-3">
          {blog.data.map((post) => (
            <PostCard key={post.slug} post={post} />
          ))}
        </div>
      )}
      <div className="mt-14">
        <Pagination page={blog.meta.current_page} lastPage={blog.meta.last_page} pathname="/blog" params={categoria ? { categoria } : {}} />
      </div>
    </Container>
  );
}
