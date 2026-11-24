import Image from "next/image";
import Link from "next/link";
import type { BlogPostSummary } from "@/lib/types";
import { mediaUrl } from "@/lib/format";

export const blogDate = new Intl.DateTimeFormat("es-CL", { dateStyle: "long", timeZone: "America/Santiago" });

export function PostCard({ post }: { post: BlogPostSummary }) {
  return (
    <article className="group flex flex-col">
      <Link href={`/blog/${post.slug}`} className="relative block aspect-[1200/630] overflow-hidden rounded-[var(--radius)] bg-subtle">
        {post.image ? (
          <Image src={mediaUrl(post.image.path)} alt={post.image.alt} fill sizes="(min-width: 1024px) 33vw, (min-width: 640px) 50vw, 100vw" className="object-cover transition-transform duration-500 group-hover:scale-105" />
        ) : (
          <span aria-hidden className="absolute inset-0 flex items-end bg-gradient-to-br from-accent/25 via-subtle to-subtle p-5 font-display text-3xl leading-none text-fg/70">
            {post.title}
          </span>
        )}
      </Link>
      <p className="mt-4 text-xs text-muted">
        {post.category && <span className="font-semibold text-accent">{post.category.name} · </span>}
        <time dateTime={post.published_at}>{blogDate.format(new Date(post.published_at))}</time> · {post.reading_minutes} min de lectura
      </p>
      <h2 className="mt-2 font-display text-2xl leading-tight">
        <Link href={`/blog/${post.slug}`} className="hover:underline underline-offset-4">
          {post.title}
        </Link>
      </h2>
      {post.excerpt && <p className="mt-2 line-clamp-3 text-sm text-muted">{post.excerpt}</p>}
    </article>
  );
}
