import type { Metadata, Viewport } from "next";
import localFont from "next/font/local";
import "./globals.css";
import { getMenu, getStore } from "@/lib/catalog";
import { Header } from "@/components/site/Header";
import { Footer } from "@/components/site/Footer";
import { AnnouncementBar } from "@/components/site/AnnouncementBar";
import { RevealObserver } from "@/components/motion/RevealObserver";

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
  const [store, menu] = await Promise.all([getStore(), getMenu()]);

  return (
    <html lang="es-CL" className={bricolage.variable} suppressHydrationWarning>
      <head>
        {/* Marca que hay JavaScript: solo entonces se ocultan los elementos que aparecen al hacer scroll */}
        <script dangerouslySetInnerHTML={{ __html: "document.documentElement.classList.add('js')" }} />
      </head>
      <body className="flex min-h-dvh flex-col antialiased">
        <AnnouncementBar messages={store?.announcements ?? []} />
        <Header storeName={store?.name ?? "Tienda"} menu={menu} />
        <main id="contenido" className="flex-1">
          {children}
        </main>
        <Footer store={store} menu={menu} />
        <RevealObserver />
      </body>
    </html>
  );
}
