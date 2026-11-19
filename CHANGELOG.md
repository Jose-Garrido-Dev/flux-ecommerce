# Changelog

Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/) y versionado semántico.

## [Sin publicar]

### Agregado

- Fase 2 (design system y tienda base):
  - Esquema del catálogo: multimedia, clases de impuesto (IVA 19% y exento), marcas, categorías en árbol, atributos, productos, variantes, precios con ofertas programadas.
  - API pública del catálogo: listado con filtros facetados, orden, rango de precio y búsqueda; ficha de producto; categorías; marcas; portada. Errores en formato `application/problem+json`.
  - Tienda: design system con tokens y tema Minimalista, encabezado con menú de categorías, portada, catálogo, categoría, marca, búsqueda y ficha con galería y selector de variantes. Datos estructurados de producto y migas de pan.
  - Imágenes del catálogo servidas desde el propio servidor y optimizadas por Next.js (sin S3 ni CDN).
  - Comando `flux:demo` con catálogo de demostración, solo para desarrollo.
  - CI: el job de desarrollo carga el catálogo de demostración y verifica páginas e imágenes.
- Fase 1 (fundaciones): monorepo con backend Laravel 13 + Filament 5 y storefront Next.js 15.
- Docker Compose para desarrollo y producción, CI en GitHub Actions, scripts de instalación, despliegue, respaldo y restauración.
- Catálogo de las 16 regiones de Chile, roles iniciales y permisos, configuración del negocio, registro de auditoría, tablero de avance de módulos.
- Endpoints de salud `/api/health` y `/api/health/ready` y endpoint público `/api/storefront/v1/store`.
- Sin servicios externos obligatorios: colas, caché, sesiones y búsqueda en PostgreSQL; archivos en el disco del servidor. Redis, Meilisearch y S3 quedan como perfiles opcionales.
- Latido del worker de colas en `/api/health/ready`.
- Guía de instalación en VPS con Webuzo (proxy del dominio y HTTPS desde Webuzo).
- Guía completa de instalación y configuración (`docs/INSTALACION.md`).
