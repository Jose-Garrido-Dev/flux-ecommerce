import "server-only";

/**
 * Cliente de la API storefront de Laravel.
 * Solo se ejecuta en el servidor de Next.js: la URL interna y cualquier
 * credencial nunca llegan al navegador.
 */
const API_BASE = process.env.FLUX_API_INTERNAL_URL ?? "http://localhost:8000";

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
  /** Etiquetas para revalidación bajo demanda (p. ej. "store", "product:12") */
  tags?: string[];
  /** Segundos de caché; false = sin caché (stock, precios, checkout) */
  revalidate?: number | false;
};

export async function apiGet<T>(path: string, opts: FetchOptions = {}): Promise<T> {
  const res = await fetch(`${API_BASE}/api/storefront/v1${path}`, {
    headers: { Accept: "application/json" },
    next: {
      tags: opts.tags,
      revalidate: opts.revalidate ?? 300,
    },
    signal: AbortSignal.timeout(8000),
  });

  if (!res.ok) {
    const body = await res.json().catch(() => ({}));
    throw new ApiError(res.status, body.code ?? "api_error", body.title ?? res.statusText);
  }

  return (await res.json()).data as T;
}

export type PublicStore = {
  name: string;
  tagline: string | null;
  currency: string;
  locale: string;
  contact: { email: string | null; phone: string | null };
};
