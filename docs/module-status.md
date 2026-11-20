# Tablero de avance

Estados: **Pendiente** · **En desarrollo** · **Implementado** · **En pruebas** · **Validado** · **Bloqueado**.
Un módulo pasa a "Validado" solo con evidencia enlazada (PR, ejecución de CI, captura o informe de sandbox). El mismo tablero se carga en la tabla `module_statuses` y se ve en el panel (Sistema → Módulos).

Última actualización: 2026-11-19.

| # | Módulo | Fase | Estado | Evidencia / nota |
| --- | --- | --- | --- | --- |
| 1 | Tablero de inicio | 11 | Pendiente | |
| 2 | Gestión de productos | 3 | En desarrollo | Fase 3: panel con pestañas General, Precios, Especificaciones, Envío, SEO, Variantes, Imágenes y Relacionados. Duplicar, publicar, archivar, acciones masivas, exportar e importar CSV/Excel. Importación de 1.000 productos con informe de errores y bloqueo de borrado con pedidos probados. Pestaña Inventario (Fase 4) con stock inicial, stock mínimo y sobreventa. Pestaña de IA en la Fase 12 |
| 3 | Categorías | 3 | En desarrollo | Panel: árbol, orden arrastrando, imagen, banner, SEO, mover productos y borrado protegido. Los ciclos se rechazan en el formulario (prueba) |
| 4 | Marcas | 3 | En desarrollo | Panel con logo, banner, SEO, archivado y borrado protegido. Nombre único sin importar mayúsculas ni tildes (prueba) |
| 5 | Atributos y variantes | 3 | En desarrollo | Panel de atributos (6 tipos) y valores. Generador de combinaciones (máx. 300 por vez), cambio de precio masivo y variante principal (pruebas) |
| 6 | Inventario y bodegas | 4 | En desarrollo | Fase 4: bodegas, existencias por bodega, movimientos inmutables con contramovimientos (bloqueados también en la base de datos), conteos, transferencias, reservas con vencimiento automático, alertas y configuración. Prueba de 20 compras simultáneas por la última unidad en PostgreSQL: vende exactamente 1. Reserva vencida liberada por el comando programado y aviso de pago repetido sin doble descuento (pruebas). Las reservas desde el carrito llegan en la Fase 5 |
| 7 | Pedidos | 5 | En desarrollo | Fase 5: checkout invitado y con cuenta crea el pedido pendiente con copia de precios, impuestos y dirección; el total que envía el navegador se ignora (prueba de total manipulado). Clave de idempotencia: reintentar no duplica. 20 checkouts simultáneos por la última unidad: un solo pedido (PostgreSQL). Panel: listado, detalle, confirmar pago (idempotente) y anular con devolución de stock; vencimiento automático de pedidos sin pagar. Compra completa probada en navegador (producto → carrito → checkout → página del pedido). Fase 6: pago con Webpay, reintentos y reembolsos (panel y correo al cliente). Devoluciones, notas y estados de despacho en la Fase 8 |
| 8 | Pasarelas de pago | 6 | En desarrollo | Contrato común de pasarelas. Webpay Plus REST v1.2: pago, confirmación en el servidor (el regreso del navegador nunca marca pagado por sí solo; se comparan monto y orden de compra), regreso duplicado sin efecto doble, reintento con orden de compra nueva, conciliación cada minuto de pagos sin regreso, reembolsos totales y parciales, bitácora de cada llamada sin datos de tarjeta, credenciales cifradas. Prueba real contra el ambiente de integración de Transbank en GitHub Actions (aprobado, rechazado, anulado, duplicado y reembolso). Mercado Pago, Flow y Khipu: no verificadas, requieren cuentas de prueba propias. Transferencia bancaria manual disponible |
| 9 | Envíos y logística | 7 | En desarrollo | Regiones cargadas. Comunas: comando `geo:import-communes` para el CSV oficial CUT de la SUBDERE (no se pudo descargar desde este entorno, se carga en la instalación); mientras tanto la comuna se escribe a mano. Fase 5: métodos de envío con tarifa fija por región, gratis desde un monto y retiro en tienda. Zonas, pesos y couriers en la Fase 7 |
| 10 | Clientes y cuentas | 5 | En desarrollo | Registro, ingreso (límite de 5 intentos), confirmación de correo (vincula pedidos de invitado con ese correo), recuperación de contraseña sin revelar si la cuenta existe, perfil, direcciones y pedidos. Al ingresar, el carrito de invitado se une al de la cuenta. Tokens en cookies httpOnly de la tienda. Panel: listado, detalle, bloquear y desbloquear (cierra sesiones). Pruebas de API y navegador |
| 11 | Cupones y promociones | 10 | Pendiente | |
| 12 | Marketing y newsletter | 10 | Pendiente | |
| 13 | Correos y notificaciones | 8 | En desarrollo | SMTP configurable por `.env`. Fase 5: confirmación del pedido, pago recibido, aviso de pedido nuevo a la tienda, confirmación de correo y recuperación de contraseña, en español y con el nombre de la tienda (prueba). Plantillas editables en la Fase 8 |
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
| 26 | Storefront público | 2 | Implementado | Portada con hero animado, mosaico de categorías, carrusel de destacados, bloque de ofertas con cuenta regresiva real y cintas de categorías y marcas; menú desplegable con imagen; catálogo con filtros, categoría, marca, búsqueda y ficha con zoom. Lighthouse móvil 90–96 en rendimiento y 100 en accesibilidad, buenas prácticas y SEO; axe sin errores (claro y oscuro, escritorio y móvil). Fase 5: mini carrito, carrito, checkout en una página, página del pedido con datos de transferencia y Mi cuenta (ingreso, registro, recuperación, datos, direcciones y pedidos) |
| 27 | Experiencia visual y animaciones | 2 | En desarrollo | Tema "Lapislázuli" con tokens y modo oscuro; titular animado por palabras, parallax, aparición al hacer scroll, transición entre páginas, zoom de imagen, cintas en movimiento. Todo en CSS (sin librerías de animación) y desactivado con movimiento reducido. Editor de temas en la Fase 9 |
| 28 | Devoluciones y reembolsos | 8 | Pendiente | |
| 29 | Configuración técnica y operaciones | 1 | En desarrollo | Docker, health checks, respaldos, CI |
| 30 | Reutilización comercial Flux | 15 | Pendiente | |
| 31 | Base de datos y reglas críticas | 1 | En desarrollo | Migraciones base |
| 32 | Testing y calidad | 1 | En desarrollo | CI: backend en PostgreSQL, storefront, instalación Docker de producción y de desarrollo con catálogo de demostración |
| 33 | Criterios de finalización | 1 | En desarrollo | Este tablero |
| 34 | Plan de implementación | — | Validado | Diseño técnico v0.1 |
