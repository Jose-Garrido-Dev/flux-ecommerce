# Changelog

Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/) y versionado semántico.

## [Sin publicar]

### Corregido

- El resumen de errores que Laravel agrega a las respuestas de la API salía en inglés ("(and 10 more errors)"). Ahora dice "(y 10 errores más)".
- Textos en inglés que se veían en el panel:
  - La etiqueta "Attribute values" en la pestaña Especificaciones.
  - Los nombres de modelos, acciones y resultados en Auditoría.
  - Los nombres internos de los roles en Usuarios.
  - "Dashboard" y "Open Graph".
- Correos del sistema y páginas de error de Laravel traducidos (`lang/es.json`).
- Revisiones automáticas para que no vuelva a aparecer texto en inglés en el panel, la tienda ni los correos (sección 10.1 de la guía).

### Agregado

- Fase 6 (pagos con Webpay Plus):
  - Contrato común para pasarelas de pago y Webpay Plus (API REST v1.2 de Transbank).
  - El pago se confirma siempre con Transbank desde el servidor, comparando monto y orden de compra. Volver dos veces de Webpay o recargar no cobra ni descuenta stock dos veces.
  - Si el pago es rechazado o anulado, el cliente puede intentarlo de nuevo desde la página del pedido (cada intento usa una orden de compra nueva). El stock queda reservado 30 minutos por intento.
  - Conciliación automática cada minuto de pagos sin regreso del cliente, y botón *Consultar en Webpay* en el pedido.
  - Reembolsos totales y parciales desde el panel (Webpay y transferencia), con devolución de stock opcional en el reembolso total y correo al cliente.
  - *Configuración → Pagos*: ambiente, credenciales cifradas, credenciales de prueba y **Probar conexión**. Webpay solo aparece en el checkout después de una prueba real exitosa. Comando `payments:verify`.
  - Bitácora inmutable de cada llamada a la pasarela (sin número de tarjeta ni llaves).
  - Prueba real automática contra el ambiente de integración de Transbank en GitHub Actions, con evidencia.
  - API 0.7.0: `POST /orders/{token}/pay`, campos del pago en el pedido y retorno `/api/pagos/{pasarela}/retorno`.

- Fase 5 (carrito, cuentas y checkout con transferencia):
  - Carrito de invitado y de cliente. Al ingresar, el carrito de invitado se une al de la cuenta. El navegador solo envía SKU y cantidad: los precios los calcula siempre el servidor, y el carrito avisa si un precio cambió o si un producto ya no está disponible.
  - Checkout en una página: contacto, dirección, despacho, pago, boleta o factura y comentarios. El total que ve el cliente solo sirve para avisarle si algo cambió; el pedido se cobra con el total recalculado. Una clave de idempotencia evita pedidos duplicados al reintentar.
  - Pago por transferencia bancaria: el stock se reserva por el plazo configurado (48 horas por defecto) y el pedido sin pagar se anula solo. La confirmación del pago en el panel descuenta el stock y avisa al cliente.
  - Página del pedido con los datos de la transferencia y enlace privado de seguimiento (sin sesión no muestra la dirección completa).
  - Mi cuenta: registro, ingreso, confirmación de correo, recuperación de contraseña, datos personales, libreta de direcciones y pedidos.
  - Panel: *Ventas → Pedidos* (confirmar pago, anular), *Clientes* (bloquear y desbloquear), *Logística → Métodos de envío* y *Configuración → Pagos*, con sus manuales.
  - Correos: pedido recibido, pago confirmado, pedido nuevo para la tienda, confirmación de correo y recuperación de contraseña. Los correos muestran el nombre de la tienda en vez de "Laravel".
  - Comando `geo:import-communes` para cargar las comunas desde el listado oficial CUT.
  - Seguridad: los tokens del carrito y de la sesión viven en cookies httpOnly del dominio de la tienda; el navegador no los ve.
  - API 0.6.0: carrito, checkout, seguimiento, cuentas, Mi cuenta y regiones.

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
