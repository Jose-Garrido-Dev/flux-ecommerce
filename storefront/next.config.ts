import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  // Salida autocontenida para la imagen Docker de producción
  output: "standalone",
  poweredByHeader: false,
  // Metadatos (título, descripción, canonical) siempre en el <head>, para cualquier buscador o red social
  htmlLimitedBots: /.*/,
  images: {
    // Las imágenes del catálogo se sirven desde /media (proxy a Laravel) y se optimizan aquí
    localPatterns: [{ pathname: "/media/**" }],
    formats: ["image/avif", "image/webp"],
    deviceSizes: [360, 480, 640, 828, 1080, 1280, 1600],
    minimumCacheTTL: 86400,
  },
  async headers() {
    return [
      {
        source: "/:path*",
        headers: [
          { key: "X-Content-Type-Options", value: "nosniff" },
          { key: "Referrer-Policy", value: "strict-origin-when-cross-origin" },
          { key: "X-Frame-Options", value: "SAMEORIGIN" },
        ],
      },
    ];
  },
};

export default nextConfig;
