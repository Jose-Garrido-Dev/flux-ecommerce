import "server-only";
import { apiFetch, apiGetOrNull, ApiError } from "./api";
import type { BlogIndex, BlogPost } from "./types";

const SLUG = /^[a-z0-9-]{1,160}$/;

/** Listado del blog. Se revalida cada 5 minutos para que aparezcan los artículos programados. */
export async function getBlogIndex(page = 1, category?: string): Promise<BlogIndex | null> {
  const params = new URLSearchParams();
  if (page > 1) params.set("page", String(page));
  if (category && SLUG.test(category)) params.set("category", category);
  const qs = params.toString();
  try {
    return await apiFetch<BlogIndex>(`/blog/posts${qs ? `?${qs}` : ""}`, { tags: ["blog"], revalidate: 300 });
  } catch (e) {
    if (e instanceof ApiError && e.status === 422) return null;
    throw e;
  }
}

export function getBlogPost(slug: string): Promise<BlogPost | null> {
  if (!SLUG.test(slug)) return Promise.resolve(null);
  return apiGetOrNull<BlogPost>(`/blog/posts/${slug}`, { tags: ["blog"], revalidate: 300 });
}
