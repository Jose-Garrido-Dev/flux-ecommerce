# Medios de pago

**Para qué sirve:** configurar cómo te pagan los clientes: Webpay Plus (tarjetas de crédito, débito y prepago) y transferencia bancaria.

## Webpay Plus
| Campo | Explicación |
| --- | --- |
| Estado | *No verificada* hasta que la prueba de conexión resulte bien. Mientras no esté verificada, el checkout no ofrece Webpay aunque esté activada |
| Ofrecer Webpay en el checkout | Muestra Webpay como medio de pago (solo si está verificada) |
| Ambiente | *Integración* para pruebas (no cobra) o *Producción* para cobrar de verdad |
| Código de comercio | Lo entrega Transbank. En integración es 597055555532 |
| API Key secret | La entrega Transbank. Se guarda cifrada y nunca se muestra; si dejas el campo vacío se conserva la guardada |

**Botones arriba de la página:**
- **Cargar credenciales de prueba:** llena el código de comercio y la llave pública del ambiente de integración de Transbank.
- **Probar conexión con Webpay:** guarda y crea una transacción de prueba de $50 en Transbank, sin cobrar. Si responde bien, Webpay queda verificada para ese ambiente y esas credenciales.

Si cambias el ambiente o las credenciales, la verificación anterior deja de valer y hay que probar de nuevo.

### Pasar a producción
1. Completa la afiliación con Transbank (Webpay Plus REST). Te entregan el código de comercio de producción.
2. Transbank pide una validación: hacer compras de prueba en integración y enviar la evidencia. Al aprobarla, te entregan la API Key secret de producción.
3. Elige *Producción*, ingresa el código y la llave, y pulsa **Probar conexión con Webpay**.
4. La dirección pública del backend (`APP_URL`) debe usar HTTPS y ser accesible desde internet: Transbank devuelve al cliente a `APP_URL/api/pagos/webpay_plus/retorno`.

### Cómo se confirma un pago
- Cuando el cliente vuelve de Webpay, el sistema confirma el pago **directamente con Transbank** y compara el monto y la orden de compra. Volver dos veces o recargar la página no cobra ni descuenta stock dos veces.
- Si el cliente cierra el navegador después de pagar, el sistema consulta a Transbank cada minuto y confirma el pago igual.
- Si el pago es rechazado o el cliente lo anula, el pedido sigue pendiente y puede intentarlo de nuevo. El stock queda reservado 30 minutos por intento.
- Nunca se guarda el número completo de la tarjeta: solo los 4 últimos dígitos, el tipo de pago, las cuotas y el código de autorización.

## Transferencia bancaria
| Campo | Explicación |
| --- | --- |
| Ofrecer transferencia bancaria | Si está apagado o faltan datos de la cuenta, el checkout no ofrece este medio |
| Banco, tipo y número de cuenta, titular, RUT | Lo que ve el cliente para transferir |
| Correo para el comprobante | Opcional |
| Plazo para pagar | Horas que el stock queda reservado. Si no confirmas el pago en ese plazo, el pedido se anula y el stock se libera (48 por defecto) |
| Instrucciones adicionales | Texto libre que se muestra junto a los datos |

### Cómo funciona
1. El cliente compra y ve los datos de la cuenta en pantalla y en el correo del pedido.
2. Transfiere indicando el número de pedido.
3. Tú revisas el banco y confirmas el pago en *Ventas → Pedidos*.

## Otras pasarelas
Mercado Pago, Flow y Khipu se agregan con el mismo esquema y se activan solo después de probarlas con credenciales de prueba de una cuenta tuya en cada servicio.

## Permisos
Solo el rol *Administrador de tienda* (o superior) puede cambiar esta configuración. Cada cambio queda en Auditoría; las credenciales aparecen como "[oculto]".
