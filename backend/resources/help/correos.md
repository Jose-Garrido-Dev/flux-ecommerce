# Correos

**Para qué sirve:** editar los textos de los correos que envía la tienda, ver si cada correo salió y reenviarlo si falló. Está en *Correos → Plantillas* y *Correos → Historial*.

## Plantillas
Hay 16 correos, agrupados en Pedidos, Despachos, Devoluciones y Cuenta del cliente. En cada uno puedes cambiar:
- **Asunto**
- **Título** (la primera línea grande del correo)
- **Texto inicial** (el párrafo bajo el título)

Lo demás (detalle de productos, totales, datos para transferir, botones y enlaces) lo arma el sistema para que no se pueda romper por error.

### Variables
Escribe la variable entre llaves y el sistema la reemplaza al enviar. Cada plantilla muestra las que acepta, por ejemplo `{nombre}`, `{pedido}`, `{total}`, `{tienda}`. Una variable mal escrita queda tal cual en la vista previa, así se nota el error.

Los datos del cliente se escapan: si alguien escribe código o formato en su nombre, se muestra como texto.

En el texto inicial puedes usar **negrita** con `**texto**`.

### Botones
- **Vista previa:** muestra el correo con datos de ejemplo y el texto que tienes escrito, aunque no lo hayas guardado.
- **Enviarme una prueba:** lo envía a tu correo de usuario del panel. Si falla, muestra el motivo (normalmente la configuración `MAIL_*` del archivo `.env`).
- **Volver al texto original.**

### Desactivar un correo
Con **Enviar este correo** desactivado, ese correo no se envía y queda en el historial como *Omitido*. La confirmación de correo y la recuperación de contraseña no se pueden desactivar, porque sin ellas el cliente no puede usar su cuenta.

## Historial
Cada correo queda registrado con fecha, plantilla, destinatario, asunto, estado e intentos.

| Estado | Significado |
| --- | --- |
| En cola | Esperando que el proceso de cola lo envíe |
| Reintentando | Falló un intento; se vuelve a intentar al minuto y a los 5 minutos |
| Enviado | El servidor de correo lo aceptó |
| Falló | Fallaron los 3 intentos. Se ve el error |
| Omitido | La plantilla está desactivada |

**Reenviar** (correos enviados o fallidos) lo pone en cola de nuevo con los datos actuales del pedido. Queda como un registro nuevo; el original no cambia.

*Enviado* significa que tu servidor SMTP lo aceptó. Si el cliente no lo ve, que revise el spam; si se repite, revisa los registros SPF, DKIM y DMARC de tu dominio (sección 8.1 de la guía de instalación).

## Requisitos
- El proceso de cola (`worker`) debe estar corriendo: sin él los correos quedan *En cola*.
- La configuración SMTP está en el archivo `.env` (`MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`).

## Permisos
Plantillas e historial: permiso *settings.manage* (rol *Administrador de tienda*). El historial muestra direcciones de clientes, por eso no lo ven otros roles.
