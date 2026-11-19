import { revalidateTag } from "next/cache";
import { NextResponse, type NextRequest } from "next/server";
import { timingSafeEqual } from "node:crypto";

/**
 * Laravel llama a este endpoint cuando cambia contenido publicado
 * (productos, páginas, configuración) para refrescar la caché de Next.js.
 * Cuerpo: { "tags": ["store", "product:12"] }  Cabecera: x-revalidate-secret
 */
export async function POST(req: NextRequest) {
  const secret = process.env.REVALIDATE_SECRET ?? "";
  const given = req.headers.get("x-revalidate-secret") ?? "";

  const valid =
    secret.length > 0 &&
    given.length === secret.length &&
    timingSafeEqual(Buffer.from(given), Buffer.from(secret));

  if (!valid) {
    return NextResponse.json({ error: "unauthorized" }, { status: 401 });
  }

  const body = (await req.json().catch(() => null)) as { tags?: unknown } | null;
  const tags = Array.isArray(body?.tags)
    ? body.tags.filter((t): t is string => typeof t === "string" && t.length <= 100).slice(0, 50)
    : [];

  tags.forEach((tag) => revalidateTag(tag));

  return NextResponse.json({ revalidated: tags });
}
