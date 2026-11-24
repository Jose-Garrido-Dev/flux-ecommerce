import type { MetadataRoute } from "next";

/** /robots.txt: los buscadores no indexan carrito, pago, cuenta, pedidos ni vistas previas. */
export default function robots(): MetadataRoute.Robots {
  const site = (process.env.NEXT_PUBLIC_SITE_URL ?? "http://localhost:3000").replace(/\/+$/, "");
  return {
    rules: [
      {
        userAgent: "*",
        allow: "/",
        disallow: ["/carrito", "/checkout", "/cuenta", "/pedido/", "/pago/", "/seguimiento/", "/vista-previa/", "/newsletter/", "/api/", "/buscar"],
      },
    ],
    sitemap: `${site}/sitemap.xml`,
  };
}
