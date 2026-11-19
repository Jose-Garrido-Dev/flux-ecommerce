import "server-only";
import { apiFetch, apiGet } from "./api";
import type { CategoryNode, ProductList, PublicStore } from "./types";

export type SearchParams = Record<string, string | string[] | undefined>;

const ALLOWED = ["q", "sort", "page", "min_price", "max_price", "on_sale", "in_stock"] as const;

/** Traduce los parámetros de la URL de la tienda a la consulta de la API. */
export function toApiQuery(params: SearchParams, fixed: Record<string, string> = {}): string {
  const q = new URLSearchParams(fixed);
  for (const key of ALLOWED) {
    const v = params[key];
    if (typeof v === "string" && v !== "") q.set(key, v);
  }
  // Filtros por atributo: ?color=negro,blanco&talla=m → filter[color]=negro,blanco
  for (const [key, v] of Object.entries(params)) {
    if ((ALLOWED as readonly string[]).includes(key) || typeof v !== "string" || v === "") continue;
    if (/^[a-z0-9_-]{1,64}$/.test(key)) q.set(`filter[${key}]`, v);
  }
  q.set("per_page", "24");
  return q.toString();
}

export function listProducts(query: string) {
  return apiFetch<ProductList>(`/catalog/products?${query}`, { tags: ["catalog"], revalidate: 120 });
}

export async function getStore(): Promise<PublicStore | null> {
  try {
    return await apiGet<PublicStore>("/store", { tags: ["store"] });
  } catch {
    return null;
  }
}

export async function getMenu(): Promise<CategoryNode[]> {
  try {
    return await apiGet<CategoryNode[]>("/catalog/categories", { tags: ["catalog"] });
  } catch {
    return [];
  }
}
