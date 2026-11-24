import "server-only";
import { apiGet, apiGetOrNull } from "./api";
import type { CmsPage, Menus, PublicTheme } from "./types";

const SLUG = /^[a-z0-9-]{1,160}$/;
const TOKEN = /^[A-Za-z0-9_-]{10,400}\.[A-Za-z0-9_-]{20,100}$/;

/** Menús del encabezado y pie armados en el panel. Vacíos = la tienda usa las categorías. */
export async function getMenus(): Promise<Menus> {
  try {
    return await apiGet<Menus>("/menus", { tags: ["menus"] });
  } catch {
    return { header: [], footer: [] };
  }
}

/** Página publicada (institucional o landing). */
export function getPage(slug: string): Promise<CmsPage | null> {
  if (!SLUG.test(slug)) return Promise.resolve(null);
  return apiGetOrNull<CmsPage>(`/pages/${slug}`, { tags: ["pages", `page:${slug}`], revalidate: 600 });
}

/** Borrador con enlace firmado. Nunca se guarda en caché. */
export function getPreview(token: string): Promise<CmsPage | null> {
  if (!TOKEN.test(token)) return Promise.resolve(null);
  return apiGetOrNull<CmsPage>(`/preview/${token}`, { noStore: true });
}

const HEX = /^#[0-9a-fA-F]{6}$/;
const KEY = /^[a-z0-9-]{1,20}$/;
const FONTS: Record<PublicTheme["font_display"], string> = {
  brand: "var(--font-sans)",
  serif: 'ui-serif, "Iowan Old Style", "Palatino Linotype", Palatino, Georgia, "Times New Roman", serif',
  system: 'ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif',
};

/**
 * Variables CSS del tema activo. Laravel ya filtra claves y colores; aquí se vuelve a validar
 * porque el resultado se inserta en una etiqueta <style>.
 */
export function themeCss(theme: PublicTheme | null | undefined): string {
  if (!theme) return "";
  const colors = (set: Record<string, string>) =>
    Object.entries(set ?? {})
      .filter(([k, v]) => KEY.test(k) && HEX.test(v))
      .map(([k, v]) => `--color-${k}:${v};`)
      .join("");
  const brand = theme.font_display === "brand";
  const clamp = (n: number, max: number) => Math.max(0, Math.min(max, Math.round(Number(n) || 0)));
  const base =
    colors(theme.light) +
    `--font-display:${FONTS[theme.font_display] ?? FONTS.brand};` +
    `--display-stretch:${brand ? "78%" : "100%"};--display-weight:${brand ? 750 : theme.font_display === "serif" ? 500 : 700};` +
    `--display-transform:${theme.uppercase_headings ? "uppercase" : "none"};` +
    `--radius-sm:${clamp(theme.radius_sm, 24)}px;--radius:${clamp(theme.radius, 32)}px;`;
  const dark = colors(theme.dark);

  // html:root gana a :root de globals.css sin importar el orden en que el navegador cargue los estilos
  return `html:root{${base}}` + (dark ? `@media (prefers-color-scheme: dark){html:root{${dark}}}` : "");
}
