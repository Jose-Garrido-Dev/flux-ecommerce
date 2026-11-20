import { cookies } from "next/headers";
import { NextResponse, type NextRequest } from "next/server";
import { storeRequest } from "@/lib/store-api";
import { CART_COOKIE, CART_DAYS, SESSION_COOKIE, SESSION_DAYS, cookieOptions } from "@/lib/session";

/**
 * Puente entre el navegador y la API de Laravel para el carrito, el checkout y Mi Cuenta.
 * - Solo deja pasar las rutas de esta lista (nada del catálogo ni del panel).
 * - Guarda los tokens en cookies httpOnly y los quita de la respuesta: el JavaScript
 *   de la página nunca los ve.
 * - Las escrituras exigen que la petición venga de la propia tienda (cabecera Origin).
 */
const ALLOWED: Record<string, RegExp[]> = {
  GET: [/^cart$/, /^checkout\/options$/, /^geo\/regions$/, /^me$/, /^me\/orders$/, /^me\/orders\/[0-9a-f-]{36}$/, /^me\/addresses$/],
  POST: [
    /^cart\/items$/,
    /^checkout$/,
    /^orders\/[A-Za-z0-9_-]{20,100}\/pay$/,
    /^auth\/(register|login|logout|forgot-password|reset-password|verify-email|resend-verification)$/,
    /^me\/addresses$/,
  ],
  PATCH: [/^cart\/items\/[^/]{1,64}$/, /^me$/, /^me\/addresses\/\d+$/],
  PUT: [/^me\/password$/],
  DELETE: [/^cart\/items\/[^/]{1,64}$/, /^me\/addresses\/\d+$/],
};

type Params = { params: Promise<{ path: string[] }> };

async function handle(request: NextRequest, { params }: Params) {
  const method = request.method;
  const path = (await params).path.map(encodeURIComponent).join("/");

  if (!ALLOWED[method]?.some((re) => re.test(path))) {
    return problem(404, "No encontrado");
  }
  if (method !== "GET" && !sameOrigin(request)) {
    return problem(403, "Solicitud no permitida");
  }

  let body: unknown;
  if (method !== "GET" && method !== "DELETE") {
    body = await request.json().catch(() => ({}));
  }
  const forward: Record<string, string> = {};
  const idem = request.headers.get("idempotency-key");
  if (idem) forward["Idempotency-Key"] = idem;

  let result;
  try {
    result = await storeRequest<Record<string, unknown>>(`/${path}${request.nextUrl.search}`, { method, body, headers: forward });
  } catch {
    return problem(503, "No pudimos conectar con la tienda. Inténtalo de nuevo en unos segundos.");
  }

  const jar = await cookies();
  const payload = { ...result.body };
  const data = payload.data as Record<string, unknown> | undefined;

  // Carrito nuevo: Laravel entrega el token una sola vez
  if (path === "cart/items" && typeof payload.token === "string") {
    jar.set(CART_COOKIE, payload.token, cookieOptions(CART_DAYS));
  }
  delete payload.token;

  // Ingreso o registro: el token de la sesión queda en la cookie, no en la respuesta
  if ((path === "auth/login" || path === "auth/register") && data && typeof data.token === "string") {
    jar.set(SESSION_COOKIE, data.token, cookieOptions(SESSION_DAYS));
    const { token: _omit, ...rest } = data;
    void _omit;
    payload.data = rest;
  }

  // Al salir, o si Laravel ya no reconoce la sesión, se borra la cookie
  if (path === "auth/logout" || (result.status === 401 && path.startsWith("me"))) {
    jar.delete(SESSION_COOKIE);
  }
  // El carrito se convirtió en pedido o ya no existe
  if ((path === "checkout" && (result.status === 201 || result.status === 200)) || payload.code === "cart_not_found") {
    jar.delete(CART_COOKIE);
  }

  return NextResponse.json(payload, { status: result.status, headers: { "Cache-Control": "no-store" } });
}

function sameOrigin(request: NextRequest): boolean {
  const origin = request.headers.get("origin");
  if (!origin) return false;
  const host = request.headers.get("x-forwarded-host") ?? request.headers.get("host");
  try {
    return new URL(origin).host === host;
  } catch {
    return false;
  }
}

function problem(status: number, detail: string) {
  return NextResponse.json({ status, detail }, { status, headers: { "Cache-Control": "no-store" } });
}

export { handle as GET, handle as POST, handle as PATCH, handle as PUT, handle as DELETE };
