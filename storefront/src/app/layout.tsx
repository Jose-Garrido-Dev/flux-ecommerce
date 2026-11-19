import type { Metadata, Viewport } from "next";
import "./globals.css";
import { getMenu, getStore } from "@/lib/catalog";
import { Header } from "@/components/site/Header";
import { Footer } from "@/components/site/Footer";

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
    { media: "(prefers-color-scheme: light)", color: "#faf9f7" },
    { media: "(prefers-color-scheme: dark)", color: "#0f0f10" },
  ],
};

export default async function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) {
  const [store, menu] = await Promise.all([getStore(), getMenu()]);

  return (
    <html lang="es-CL">
      <body className="flex min-h-dvh flex-col antialiased">
        <Header storeName={store?.name ?? "Tienda"} menu={menu} />
        <main id="contenido" className="flex-1">
          {children}
        </main>
        <Footer store={store} menu={menu} />
      </body>
    </html>
  );
}
