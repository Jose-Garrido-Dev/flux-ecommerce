import { API_BASE } from "@/lib/api";

/**
 * Sirve las imágenes subidas en Laravel (storage/app/public) bajo el dominio de la tienda.
 * Así next/image las optimiza sin depender de la URL pública del backend.
 */
const ALLOWED = /\.(jpe?g|png|webp|avif|gif|svg)$/i;

export async function GET(_req: Request, { params }: { params: Promise<{ path: string[] }> }) {
  const { path } = await params;
  if (path.some((p) => p === ".." || p === "." || p.includes("\\")) || !ALLOWED.test(path.at(-1) ?? "")) {
    return new Response("Not found", { status: 404 });
  }

  const upstream = await fetch(`${API_BASE}/storage/${path.map(encodeURIComponent).join("/")}`, {
    cache: "no-store",
    signal: AbortSignal.timeout(10000),
  }).catch(() => null);

  if (!upstream?.ok || !upstream.body) {
    return new Response("Not found", { status: 404 });
  }

  const type = upstream.headers.get("content-type") ?? "application/octet-stream";
  return new Response(upstream.body, {
    headers: {
      "Content-Type": type,
      "Cache-Control": "public, max-age=86400, stale-while-revalidate=604800",
      "X-Content-Type-Options": "nosniff",
      // Un SVG nunca debe ejecutar scripts
      ...(type.includes("svg") ? { "Content-Security-Policy": "default-src 'none'; style-src 'unsafe-inline'" } : {}),
    },
  });
}
