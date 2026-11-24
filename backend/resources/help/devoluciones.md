# Devoluciones y cambios

**Para qué sirve:** recibir y resolver las solicitudes de devolución o cambio de los clientes, desde que las piden hasta que se reembolsa o se envía el cambio. Está en *Ventas → Devoluciones*. La configuración está en *Configuración → Devoluciones*.

## Cómo llega una solicitud
- **El cliente la pide** desde su pedido (*Mi cuenta → Pedidos* o el enlace del correo), después de recibirlo y dentro del plazo. Elige los productos y la cantidad, el motivo y, si la tienda lo permite, si quiere el dinero o un cambio.
- **La tienda la registra** desde el pedido con **Registrar devolución** (por ejemplo, el cliente llamó o llegó a la tienda). Desde el panel no se aplica el plazo.

Al crearse, el cliente recibe un correo de confirmación y la tienda un aviso al correo de contacto (*Configuración → Datos del negocio*).

## Estados
| Estado | Qué significa | Qué hacer |
| --- | --- | --- |
| Solicitada | El cliente la pidió | Tomarla para revisar, aprobarla o rechazarla |
| En revisión | Alguien de la tienda la está viendo | Aprobarla o rechazarla |
| Aprobada: esperando el producto | Se aceptó y el cliente recibió las instrucciones de envío | Registrar la recepción cuando llegue |
| Rechazada | No se acepta. El cliente recibió el motivo | Cerrarla |
| Producto recibido | Llegó a la tienda y se revisó | Resolver (reembolso o cambio) |
| Resuelta | Se reembolsó o se envió el cambio | Cerrarla |
| Cerrada | Terminada | — |
| Anulada | El cliente o la tienda la anularon antes de recibir el producto | — |

Un paso que no corresponde (por ejemplo, resolver antes de recibir) se rechaza con un mensaje que explica por qué.

## Paso a paso
1. **Tomar para revisar:** asigna un responsable.
2. **Aprobar:** escribe las instrucciones para el cliente: dónde y cómo enviar el producto. Se le envían por correo y las ve en su pedido. Si en la configuración escribiste instrucciones generales, aparecen ya escritas.
3. **Rechazar:** el motivo es obligatorio porque el cliente lo recibe por correo.
4. **Registrar recepción:** por cada producto indica cómo llegó (*Nuevo, sin uso*, *Abierto o usado*, *Dañado*) y si vuelve al stock. Lo que vuelve al stock entra a la bodega principal como *Entrada por devolución* (Inventario → Movimientos). Un producto dañado **no** vuelve al stock.
5. **Resolver:**
   - **Devolución:** el monto propuesto es lo que el cliente pagó por esas unidades; puedes cambiarlo. El reembolso se hace igual que desde el pedido: con Webpay se pide a Transbank en el momento; con transferencia se registra y tú haces la devolución desde el banco. Queda ligado a la devolución.
   - **Cambio:** describe qué se envía en cambio. No se reembolsa nada.
   El cliente recibe un correo con la resolución.
6. **Cerrar.**

Con **Subir respaldo** puedes guardar fotos o documentos (por ejemplo, la foto del daño). Se guardan en el servidor y solo los ve quien tiene acceso a devoluciones.

## Configuración (Configuración → Devoluciones)
- **Los clientes pueden pedir devoluciones en línea:** si lo desactivas, el cliente ve que debe escribir a la tienda.
- **Plazo:** días desde la entrega (por defecto 10, el derecho a retracto en Chile cuando aplica). Cuenta desde la fecha en que el último despacho quedó *Entregado* o *Retirado*.
- **Ofrecer cambio por otro producto.**
- **Instrucciones** para enviar el producto y **política** que el cliente lee antes de pedir.

## Lo que el cliente ve
En su pedido: el estado de cada solicitud, las instrucciones cuando está aprobada, el motivo si se rechazó y la resolución con el monto reembolsado. Puede anular su solicitud mientras no envíe el producto. Nunca ve las notas internas ni quién la atendió.

## Permisos
- Ver: permiso *returns.view* (roles *Ventas*, *Logística*, *Atención al cliente* y *Administrador de tienda*).
- Registrar desde el pedido: *returns.create* (*Atención al cliente*, *Administrador de tienda*).
- Revisar, recibir, subir respaldos, cerrar y anular: *returns.update*.
- Aprobar y rechazar: *returns.approve* (*Ventas*, *Administrador de tienda*).
- Resolver con reembolso: *refunds.process* (*Finanzas*, *Administrador de tienda*).

## Relaciones con otros módulos
- **Pedidos:** la sección *Devoluciones* del pedido y su historial muestran cada cambio de estado.
- **Inventario → Movimientos:** las entradas por devolución tienen como referencia la devolución.
- **Correos:** las plantillas *Devolución: …* se editan en *Correos → Plantillas*.
