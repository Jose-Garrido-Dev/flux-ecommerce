/**
 * Llamadas desde el navegador al puente /api/tienda (ver app/api/tienda/[...path]/route.ts).
 * Nunca llevan tokens: viajan en cookies httpOnly que solo lee el servidor de la tienda.
 */
export type BffResult<T = unknown> = {
  ok: boolean;
  status: number;
  data: T | null;
  /** Mensaje general en español listo para mostrar */
  message: string | null;
  /** Errores por campo (validación de Laravel) */
  errors: Record<string, string>;
  /** Código de error de la API (p. ej. price_changed) */
  code: string | null;
  raw: Record<string, unknown>;
};

export async function bff<T = unknown>(
  path: string,
  init: { method?: string; body?: unknown; headers?: Record<string, string> } = {},
): Promise<BffResult<T>> {
  let res: Response;
  try {
    res = await fetch(`/api/tienda/${path}`, {
      method: init.method ?? "GET",
      headers: { Accept: "application/json", ...(init.body !== undefined ? { "Content-Type": "application/json" } : {}), ...init.headers },
      body: init.body !== undefined ? JSON.stringify(init.body) : undefined,
      cache: "no-store",
      credentials: "same-origin",
    });
  } catch {
    return fail(0, "No pudimos conectar. Revisa tu conexión e inténtalo de nuevo.");
  }
  const raw = ((await res.json().catch(() => ({}))) ?? {}) as Record<string, unknown>;
  const errors: Record<string, string> = {};
  if (raw.errors && typeof raw.errors === "object") {
    for (const [field, list] of Object.entries(raw.errors as Record<string, string[]>)) {
      errors[field] = Array.isArray(list) ? list[0] : String(list);
    }
  }
  const message = res.ok
    ? null
    : String(raw.detail ?? raw.message ?? (res.status === 429 ? "Demasiados intentos. Espera un momento." : "Algo salió mal. Inténtalo de nuevo."));

  return {
    ok: res.ok,
    status: res.status,
    data: (raw.data as T) ?? null,
    message,
    errors,
    code: typeof raw.code === "string" ? raw.code : null,
    raw,
  };
}

function fail<T>(status: number, message: string): BffResult<T> {
  return { ok: false, status, data: null, message, errors: {}, code: null, raw: {} };
}
