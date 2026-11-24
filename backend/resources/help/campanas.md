# Campañas

**Para qué sirve:** enviar un correo a los suscritos confirmados del boletín. Está en *Marketing → Campañas*.

## Crear y enviar
1. **Nueva campaña:** nombre interno, destinatarios, asunto, título, texto y (opcional) un botón.
2. Guarda y usa **Enviar prueba** para verla en tu bandeja.
3. **Programar envío:** ahora o en una fecha y hora. Hasta esa hora puedes editarla o cancelarla.

## Destinatarios
| Opción | Quiénes |
| --- | --- |
| Todos los suscritos | Todos los confirmados |
| Suscritos que ya compraron | Confirmados con al menos un pedido pagado |
| Suscritos que aún no compran | Confirmados sin pedidos pagados |

La lista se arma al iniciar el envío. Cada persona recibe la campaña una sola vez.

## Cómo se envía
Los correos salen por la cola en lotes de 50 cada 30 segundos (unos 6.000 por hora), para no superar los límites de tu servidor SMTP. Necesita el *worker* de colas y el *programador de tareas* funcionando (guía de instalación).

Antes de cada correo se revisa otra vez la suscripción: quien se dio de baja queda **Omitido**.

Si un correo falla (por ejemplo, el SMTP lo rechaza), queda **Fallido** con el detalle; **Reintentar fallidos** los vuelve a poner en cola.

## Clics
El botón pasa por un enlace propio que cuenta el primer clic de cada persona y luego lleva al destino. No se mide la apertura (requiere imágenes de seguimiento que muchos programas bloquean).

## Límites
- Los rebotes (correos inexistentes) **no se procesan automáticamente**: dependen del proveedor SMTP. Estado: *No verificada*.
- Revisa el límite diario de tu proveedor SMTP antes de enviar a listas grandes.
