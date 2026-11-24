import type { MetadataRoute } from "next";
import { apiGet } from "@/lib/api";

type Entry = { path: string; lastmod: string | null; priority: number };

/** /sitemap.xml: contenido visible según Laravel (productos, categorías, marcas, páginas y blog). */
export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  const site = (process.env.NEXT_PUBLIC_SITE_URL ?? "http://localhost:3000").replace(/\/+$/, "");
  let entries: Entry[] = [];
  try {
    entries = await apiGet<Entry[]>("/seo/sitemap", { tags: ["sitemap", "catalog", "pages", "blog"], revalidate: 3600 });
  } catch {
    // Sin API se publica al menos la portada y el catálogo
    entries = [
      { path: "/", lastmod: null, priority: 1 },
      { path: "/catalogo", lastmod: null, priority: 0.8 },
    ];
  }
  return entries.map((e) => ({
    url: `${site}${e.path.split("/").map((s) => encodeURIComponent(decodeURIComponent(s))).join("/")}`,
    ...(e.lastmod ? { lastModified: e.lastmod } : {}),
    priority: e.priority,
  }));
}

// Se genera en cada solicitud (la respuesta de la API queda en caché una hora): así no queda fijo lo que había al compilar
export const dynamic = "force-dynamic";
