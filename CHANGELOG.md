# Changelog

Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/) y versionado semántico.

## [Sin publicar]

### Corregido

- Textos en inglés que se veían en el panel:
  - La etiqueta "Attribute values" en la pestaña Especificaciones.
  - Los nombres de modelos, acciones y resultados en Auditoría.
  - Los nombres internos de los roles en Usuarios.
  - "Dashboard" y "Open Graph".
- Correos del sistema y páginas de error de Laravel traducidos (`lang/es.json`).
- Revisiones automáticas para que no vuelva a aparecer texto en inglés en el panel, la tienda ni los correos (sección 10.1 de la guía).

### Agregado

- Fase 4 (inventario y bodegas):
  - Bodegas con dirección, región, comuna, responsable, prioridad y bodega principal. Viene creada la *Bodega principal*.
  - Existencias por producto, variante y bodega: físico, reservado, disponible, mínimo y estado. Filtros por bodega, estado y control de stock.
  - Movimientos: entrada por compra, entrada manual, devolución, salida manual, merma, ajustes, ventas, transferencias, reservas y liberaciones. Cada uno guarda antes, después, motivo, referencia, usuario y fecha. No se editan ni se borran (la base de datos lo impide): se corrigen con **Revertir**, que crea un contramovimiento.
  - Conteo físico: se escribe lo contado y la diferencia queda como ajuste.
  - Transferencias entre bodegas en una sola operación.
  - Reservas de stock con duración configurable. El programador de tareas libera las vencidas cada minuto. Un aviso de pago repetido no descuenta dos veces.
  - Control de concurrencia: 20 compras simultáneas por la última unidad venden exactamente una (prueba en PostgreSQL).
  - Alertas: sin stock, stock bajo, reservas vencidas sin liberar, sin movimientos en 90 días, diferencias de inventario, mayor rotación y sobreventa permitida. Se ven en el inicio del panel y en Existencias.
  - Productos: pestaña *Inventario* (controlar stock, sobreventa, stock mínimo, stock inicial y stock por bodega), columna *Stock* y filtro *Disponibilidad*. Botón *Inventario* en cada variante.
  - Configuración → Inventario: duración de la reserva y valores por defecto para productos nuevos.
  - Importación y exportación con las columnas `stock`, `stock_minimo` y `controlar_stock`.
  - Tienda: "Disponible", "¡Quedan N unidades!", "Agotado por ahora" o "Disponible por encargo" en la ficha, variantes agotadas tachadas, sello "Agotado" en las tarjetas y filtro "Solo productos disponibles".
  - API 0.5.0: filtro `in_stock`, campo `in_stock` en las tarjetas y objeto `stock` en cada variante.
  - Rol *Inventario* con acceso a estas pantallas. Manuales de bodegas, existencias, movimientos, transferencias, reservas y configuración.

- Fase 3 (catálogo en el panel):
  - Productos: formulario con pestañas (General, Precios con margen, Especificaciones, Envío, SEO con vista previa de Google), variantes, imágenes y productos relacionados.
  - Acciones sobre productos: duplicar, publicar, despublicar y archivar, individuales o masivas. Un producto con pedidos no se puede eliminar.
  - Búsqueda por nombre, SKU o código de barras, con filtros por estado, marca, categoría, precio, destacados, variantes y productos sin imágenes.
  - Generador de combinaciones de variantes, cambio masivo de precio (monto fijo o porcentaje) y elección de la variante principal.
  - Subida de varias imágenes a la vez: se valida el tipo real del archivo, se corrige la orientación, se reducen a 2400 px y se genera una miniatura. Se ordenan arrastrando y se puede elegir la principal y asociarlas a una variante.
  - Panel de categorías (árbol, orden, banner, SEO, mover productos), marcas (logo, banner, SEO, archivado) y atributos (6 tipos, valores con color o imagen).
  - Importación de CSV/Excel (Catálogo → Importar productos):
    - Plantilla con instrucciones, mapeo de columnas, análisis previo sin escribir nada y vista previa.
    - Errores por fila, con detección de SKU y códigos de barras repetidos.
    - Modos crear, actualizar o ambos. Crea marcas, categorías y opciones que falten.
    - Procesa en segundo plano mostrando el avance y entrega un informe descargable.
  - Exportación a CSV o Excel con el mismo formato de la importación.
  - Manuales de uso de productos, categorías, marcas, atributos e importación.
  - API: la ficha usa el título, la descripción y la URL canónica de SEO definidos en el panel. Los productos relacionados elegidos a mano van primero.

- Fase 2 (design system y tienda base):
  - Esquema del catálogo: multimedia, clases de impuesto (IVA 19% y exento), marcas, categorías en árbol, atributos, productos, variantes, precios con ofertas programadas.
  - API pública del catálogo: listado con filtros facetados, orden, rango de precio y búsqueda; ficha de producto; categorías; marcas; portada. Errores en formato `application/problem+json`.
  - Tienda: design system con tokens y tema Minimalista, encabezado con menú de categorías, portada, catálogo, categoría, marca, búsqueda y ficha con galería y selector de variantes. Datos estructurados de producto y migas de pan.
  - Imágenes del catálogo servidas desde el propio servidor y optimizadas por Next.js (sin S3 ni CDN).
  - Comando `flux:demo` con catálogo de demostración, solo para desarrollo.
  - CI: el job de desarrollo carga el catálogo de demostración y verifica páginas e imágenes.
  - Rediseño visual de la tienda (tema "Lapislázuli"): portada con hero animado y productos reales con parallax, mosaico de categorías, carrusel de destacados, bloque de ofertas con cuenta regresiva, cintas en movimiento, barra de anuncios, menú desplegable con imagen, ficha con zoom y barra fija en móvil, pie de página nuevo. Tipografía Bricolage Grotesque alojada en el propio servidor.
  - API: `on_sale` en la portada y como filtro del listado; `is_new` y `swatches` en las tarjetas; `announcements` y `social` en `/store`.
  - Panel: campo "Barra de anuncios" en Datos del negocio.
  - Traducciones al español de todos los mensajes de validación (antes se veían claves como `validation.min.string`).
  - Imágenes de demostración dibujadas por código (`infra/demo-art`), incluidas en el repositorio.
- Fase 1 (fundaciones): monorepo con backend Laravel 13 + Filament 5 y storefront Next.js 15.
- Docker Compose para desarrollo y producción, CI en GitHub Actions, scripts de instalación, despliegue, respaldo y restauración.
- Catálogo de las 16 regiones de Chile, roles iniciales y permisos, configuración del negocio, registro de auditoría, tablero de avance de módulos.
- Endpoints de salud `/api/health` y `/api/health/ready` y endpoint público `/api/storefront/v1/store`.
- Sin servicios externos obligatorios: colas, caché, sesiones y búsqueda en PostgreSQL; archivos en el disco del servidor. Redis, Meilisearch y S3 quedan como perfiles opcionales.
- Latido del worker de colas en `/api/health/ready`.
- Guía de instalación en VPS con Webuzo (proxy del dominio y HTTPS desde Webuzo).
- Guía completa de instalación y configuración (`docs/INSTALACION.md`).
