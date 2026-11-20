import "server-only";
import { cookies, headers } from "next/headers";
import { API_BASE } from "./api";
import { CART_COOKIE, SESSION_COOKIE } from "./session";

export type StoreResponse<T = unknown> = { status: number; body: T };

/**
 * Llamada sin caché a la API de Laravel en nombre del comprador: agrega el token del
 * carrito, el de la sesión del cliente y su IP real. Solo en el servidor.
 */
export async function storeRequest<T = unknown>(
  path: string,
  init: { method?: string; body?: unknown; headers?: Record<string, string> } = {},
): Promise<StoreResponse<T>> {
  const jar = await cookies();
  const cart = jar.get(CART_COOKIE)?.value;
  const session = jar.get(SESSION_COOKIE)?.value;

  const res = await fetch(`${API_BASE}/api/storefront/v1${path}`, {
    method: init.method ?? "GET",
    headers: {
      Accept: "application/json",
      ...(init.body !== undefined ? { "Content-Type": "application/json" } : {}),
      "X-Flux-Storefront-Key": process.env.REVALIDATE_SECRET ?? "",
      "X-Flux-Client-IP": await clientIp(),
      ...(cart ? { "X-Cart-Token": cart } : {}),
      ...(session ? { Authorization: `Bearer ${session}` } : {}),
      ...init.headers,
    },
    body: init.body !== undefined ? JSON.stringify(init.body) : undefined,
    cache: "no-store",
    signal: AbortSignal.timeout(15000),
  });
  const body = (await res.json().catch(() => ({}))) as T;

  return { status: res.status, body };
}

/** Datos de una respuesta correcta, o null (404, 401 u otro error). */
export async function storeData<T>(path: string): Promise<T | null> {
  try {
    const { status, body } = await storeRequest<{ data: T }>(path);
    return status >= 200 && status < 300 ? body.data : null;
  } catch {
    return null;
  }
}

export async function hasSession(): Promise<boolean> {
  return Boolean((await cookies()).get(SESSION_COOKIE)?.value);
}

/** IP del comprador según el proxy de entrada (Apache de Webuzo, Caddy o Nginx). */
async function clientIp(): Promise<string> {
  const h = await headers();
  return (h.get("x-forwarded-for")?.split(",")[0] ?? h.get("x-real-ip") ?? "").trim();
}
