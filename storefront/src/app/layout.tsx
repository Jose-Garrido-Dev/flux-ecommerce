import type { Metadata, Viewport } from "next";
import localFont from "next/font/local";
import "./globals.css";
import { getMenu, getStore } from "@/lib/catalog";
import { getMenus, themeCss } from "@/lib/content";
import { Header } from "@/components/site/Header";
import { Footer } from "@/components/site/Footer";
import { AnnouncementBar } from "@/components/site/AnnouncementBar";
import { RevealObserver } from "@/components/motion/RevealObserver";
import { CartProvider } from "@/components/cart/CartProvider";
import { CartDrawer } from "@/components/cart/CartDrawer";
import { WhatsAppButton } from "@/components/marketing/WhatsAppButton";
import { JsonLd } from "@/components/seo/JsonLd";

/*
 * Bricolage Grotesque (licencia OFL, archivo en src/app/fonts). Fuente variable de peso y ancho.
 * next/font la sirve desde el propio servidor, la precarga y ajusta la fuente de respaldo
 * para que el texto no salte al cargar.
 */
const bricolage = localFont({
  src: "./fonts/bricolage-grotesque-latin-wdth.woff2",
  weight: "200 800",
  display: "swap",
  variable: "--font-brand",
  declarations: [{ prop: "font-stretch", value: "75% 100%" }],
});

export async function generateMetadata(): Promise<Metadata> {
  const store = await getStore();
  const name = store?.name ?? "Tienda";
  return {
    metadataBase: new URL(process.env.NEXT_PUBLIC_SITE_URL ?? "http://localhost:3000"),
    title: { default: name, template: `%s | ${name}` },
    description: store?.tagline ?? `Tienda en línea ${name}`,
    openGraph: { siteName: name, locale: "es_CL", type: "website" },
  };
}

export const viewport: Viewport = {
  themeColor: [
    { media: "(prefers-color-scheme: light)", color: "#f4f5f1" },
    { media: "(prefers-color-scheme: dark)", color: "#080d22" },
  ],
};

export default async function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) {
  const [store, menu, menus] = await Promise.all([getStore(), getMenu(), getMenus()]);
  const theme = themeCss(store?.theme);

  return (
    <html lang="es-CL" className={bricolage.variable} suppressHydrationWarning>
      <head>
        {/* Marca que hay JavaScript: solo entonces se ocultan los elementos que aparecen al hacer scroll */}
        <script dangerouslySetInnerHTML={{ __html: "document.documentElement.classList.add('js')" }} />
        {/* Tema activo elegido en el panel (Contenido → Temas): solo colores #rrggbb validados y valores numéricos */}
        {theme && <style id="tema" dangerouslySetInnerHTML={{ __html: theme }} />}
      </head>
      <body className="flex min-h-dvh flex-col antialiased">
        <CartProvider>
          <AnnouncementBar messages={store?.announcements ?? []} />
          <Header storeName={store?.name ?? "Tienda"} menu={menu} links={menus.header} />
          <main id="contenido" className="flex-1">
            {children}
          </main>
          <Footer store={store} menu={menu} links={menus.footer} />
          <CartDrawer />
          {store?.whatsapp && <WhatsAppButton config={store.whatsapp} />}
        </CartProvider>
        <JsonLd data={organizationLd(store)} />
        <RevealObserver />
      </body>
    </html>
  );
}

/** Datos estructurados de la tienda (Organization) para buscadores. */
function organizationLd(store: Awaited<ReturnType<typeof getStore>>) {
  const site = process.env.NEXT_PUBLIC_SITE_URL ?? "http://localhost:3000";
  return {
    "@context": "https://schema.org",
    "@type": "Organization",
    name: store?.name ?? "Tienda",
    url: site,
    ...(store?.contact.email ? { email: store.contact.email } : {}),
    ...(store?.contact.phone ? { telephone: store.contact.phone } : {}),
    sameAs: Object.values(store?.social ?? {}),
  };
}
