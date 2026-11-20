import "server-only";

/**
 * Cookies de la tienda. Son httpOnly: el navegador nunca ve los tokens, solo el
 * servidor de Next.js, que los reenvía a Laravel (X-Cart-Token y Authorization).
 */
export const CART_COOKIE = "flux_carrito";
export const SESSION_COOKIE = "flux_sesion";

const secure = (process.env.NEXT_PUBLIC_SITE_URL ?? "").startsWith("https://");

export const cookieOptions = (days: number) => ({
  httpOnly: true,
  secure,
  sameSite: "lax" as const,
  path: "/",
  maxAge: days * 24 * 60 * 60,
});

/** El carrito se recuerda 60 días; la sesión del cliente vence a los 30 (igual que su token en Laravel). */
export const CART_DAYS = 60;
export const SESSION_DAYS = 30;
