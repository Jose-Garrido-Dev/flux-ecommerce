# Tablero de avance

Estados: **Pendiente** · **En desarrollo** · **Implementado** · **En pruebas** · **Validado** · **Bloqueado**.
Un módulo pasa a "Validado" solo con evidencia enlazada (PR, ejecución de CI, captura o informe de sandbox). El mismo tablero se carga en la tabla `module_statuses` y se ve en el panel (Sistema → Módulos).

Última actualización: 2026-11-19.

| # | Módulo | Fase | Estado | Evidencia / nota |
| --- | --- | --- | --- | --- |
| 1 | Dashboard administrativo | 11 | Pendiente | |
| 2 | Gestión de productos | 3 | En desarrollo | Fase 2: tablas de productos, variantes, precios e imágenes, y API pública. El formulario del panel llega en la Fase 3 |
| 3 | Categorías | 3 | En desarrollo | Árbol con rutas materializadas, ciclos y más de 4 niveles rechazados (pruebas) |
| 4 | Marcas | 3 | En desarrollo | Nombre único sin importar mayúsculas ni tildes |
| 5 | Atributos y variantes | 3 | En desarrollo | Ejes de variante y filtros facetados en la API |
| 6 | Inventario y bodegas | 4 | Pendiente | |
| 7 | Pedidos | 8 | Pendiente | |
| 8 | Pasarelas de pago | 6 | Pendiente | Webpay Plus será la primera. Ninguna verificada |
| 9 | Envíos y logística | 7 | En desarrollo | Regiones cargadas. Comunas pendientes de cargar desde fuente oficial verificada |
| 10 | Clientes y cuentas | 5 | Pendiente | |
| 11 | Cupones y promociones | 10 | Pendiente | |
| 12 | Marketing y newsletter | 10 | Pendiente | |
| 13 | Correos y notificaciones | 8 | Pendiente | SMTP configurable por `.env` |
| 14 | CMS y editor visual | 9 | Pendiente | |
| 15 | Biblioteca multimedia | 9 | Pendiente | |
| 16 | Inteligencia artificial | 12 | Pendiente | |
| 17 | Finanzas y rentabilidad | 11 | Pendiente | |
| 18 | IVA, moneda y configuración comercial | 1 | En desarrollo | Datos del negocio editables en el panel. Clases de impuesto IVA 19% y exento |
| 19 | SEO y blog | 10 | Pendiente | |
| 20 | WhatsApp y atención | 10 | Pendiente | |
| 21 | Reseñas | 8 | Pendiente | |
| 22 | Usuarios y permisos | 1 | En desarrollo | 9 roles iniciales y permisos `modulo.accion` |
| 23 | Auditoría | 1 | En desarrollo | Tabla y servicio de auditoría |
| 24 | Configuraciones generales | 1 | En desarrollo | Pestaña "Negocio" |
| 25 | Manuales de uso | 1 | Pendiente | |
| 26 | Storefront público | 2 | Implementado | Portada con hero animado, mosaico de categorías, carrusel de destacados, bloque de ofertas con cuenta regresiva real y cintas de categorías y marcas; menú desplegable con imagen; catálogo con filtros, categoría, marca, búsqueda y ficha con zoom. Lighthouse móvil 90–96 en rendimiento y 100 en accesibilidad, buenas prácticas y SEO; axe sin errores (claro y oscuro, escritorio y móvil). Carrito en la Fase 5 |
| 27 | Experiencia visual y animaciones | 2 | En desarrollo | Tema "Lapislázuli" con tokens y modo oscuro; titular animado por palabras, parallax, aparición al hacer scroll, transición entre páginas, zoom de imagen, cintas en movimiento. Todo en CSS (sin librerías de animación) y desactivado con movimiento reducido. Editor de temas en la Fase 9 |
| 28 | Devoluciones y reembolsos | 8 | Pendiente | |
| 29 | Configuración técnica y operaciones | 1 | En desarrollo | Docker, health checks, respaldos, CI |
| 30 | Reutilización comercial Flux | 15 | Pendiente | |
| 31 | Base de datos y reglas críticas | 1 | En desarrollo | Migraciones base |
| 32 | Testing y calidad | 1 | En desarrollo | CI: backend en PostgreSQL, storefront, instalación Docker de producción y de desarrollo con catálogo de demostración |
| 33 | Criterios de finalización | 1 | En desarrollo | Este tablero |
| 34 | Plan de implementación | — | Validado | Diseño técnico v0.1 |
