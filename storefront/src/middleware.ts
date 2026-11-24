import { NextResponse, type NextFetchEvent, type NextRequest } from "next/server";

/**
 * Redirecciones administradas en el panel (Contenido → SEO y redirecciones) y las que se crean
 * solas al cambiar una URL. La lista se guarda en memoria 60 segundos para no consultar Laravel
 * en cada visita; si la API no responde, la tienda sigue funcionando sin redirigir.
 */
type Rule = { from: string; to: string; code: 301 | 302 };

const API = process.env.FLUX_API_INTERNAL_URL ?? "http://localhost:8000";
const TTL_MS = 60_000;

let cache: { at: number; rules: Map<string, Rule> } | null = null;
let loading: Promise<Map<string, Rule>> | null = null;

async function rules(): Promise<Map<string, Rule>> {
  if (cache && Date.now() - cache.at < TTL_MS) return cache.rules;
  loading ??= (async () => {
    try {
      const res = await fetch(`${API}/api/storefront/v1/seo/redirects`, {
        headers: { Accept: "application/json", "X-Flux-Storefront-Key": process.env.REVALIDATE_SECRET ?? "" },
        cache: "no-store",
        signal: AbortSignal.timeout(3000),
      });
      if (!res.ok) throw new Error(String(res.status));
      const body = (await res.json()) as { data: Rule[] };
      const map = new Map(body.data.map((r) => [r.from, r]));
      cache = { at: Date.now(), rules: map };
      return map;
    } catch {
      // Sin API: se reintenta en 10 segundos con lo que había
      const previous = cache?.rules ?? new Map<string, Rule>();
      cache = { at: Date.now() - TTL_MS + 10_000, rules: previous };
      return previous;
    } finally {
      loading = null;
    }
  })();
  return loading;
}

export async function middleware(request: NextRequest, event: NextFetchEvent) {
  const { pathname, search } = request.nextUrl;
  const map = await rules();
  if (map.size === 0) return NextResponse.next();

  const path = pathname !== "/" ? pathname.replace(/\/+$/, "") : pathname;
  const rule = (search ? map.get(path + search) : undefined) ?? map.get(path);
  if (!rule) return NextResponse.next();

  // Cuenta la visita sin demorar la redirección
  event.waitUntil(
    fetch(`${API}/api/storefront/v1/seo/redirects/hit`, {
      method: "POST",
      headers: { Accept: "application/json", "Content-Type": "application/json", "X-Flux-Storefront-Key": process.env.REVALIDATE_SECRET ?? "" },
      body: JSON.stringify({ path: rule.from }),
      signal: AbortSignal.timeout(3000),
    }).catch(() => undefined),
  );

  const destination = rule.to.startsWith("/") ? new URL(rule.to, request.url) : new URL(rule.to);
  return NextResponse.redirect(destination, rule.code);
}

export const config = {
  // Nunca en archivos internos, el puente de la API, imágenes ni archivos con extensión
  matcher: ["/((?!_next/|api/|media/|vista-previa/|favicon.ico|sitemap.xml|robots.txt|.*\\.[a-z0-9]{2,5}$).*)"],
};
