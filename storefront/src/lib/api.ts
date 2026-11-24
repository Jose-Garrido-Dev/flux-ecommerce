import "server-only";

/**
 * Cliente de la API storefront de Laravel.
 * Solo se ejecuta en el servidor de Next.js: la URL interna y el secreto
 * compartido nunca llegan al navegador.
 */
export const API_BASE = process.env.FLUX_API_INTERNAL_URL ?? "http://localhost:8000";

export class ApiError extends Error {
  constructor(
    public status: number,
    public code: string,
    message: string,
  ) {
    super(message);
  }
}

type FetchOptions = {
  /** Etiquetas para revalidación bajo demanda (p. ej. "store", "catalog") */
  tags?: string[];
  /** Segundos de caché; false = sin caché (stock, precios, checkout) */
  revalidate?: number | false;
  /** Nunca guardar en caché (vista previa de borradores) */
  noStore?: boolean;
};

/** Devuelve el cuerpo completo de la respuesta (data, meta, facets...). */
export async function apiFetch<T>(path: string, opts: FetchOptions = {}): Promise<T> {
  const res = await fetch(`${API_BASE}/api/storefront/v1${path}`, {
    headers: {
      Accept: "application/json",
      // Identifica al servidor de la tienda para que Laravel no lo limite como a un visitante
      "X-Flux-Storefront-Key": process.env.REVALIDATE_SECRET ?? "",
    },
    ...(opts.noStore ? { cache: "no-store" as const } : { next: { tags: opts.tags, revalidate: opts.revalidate ?? 300 } }),
    signal: AbortSignal.timeout(8000),
  });

  if (!res.ok) {
    const body = await res.json().catch(() => ({}));
    throw new ApiError(res.status, body.code ?? "api_error", body.detail ?? body.title ?? res.statusText);
  }

  return (await res.json()) as T;
}

export async function apiGet<T>(path: string, opts: FetchOptions = {}): Promise<T> {
  return (await apiFetch<{ data: T }>(path, opts)).data;
}

/** Igual que apiGet, pero devuelve null si el recurso no existe (404). */
export async function apiGetOrNull<T>(path: string, opts: FetchOptions = {}): Promise<T | null> {
  try {
    return await apiGet<T>(path, opts);
  } catch (e) {
    if (e instanceof ApiError && e.status === 404) return null;
    throw e;
  }
}
