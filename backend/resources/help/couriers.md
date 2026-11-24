# Couriers

**Para qué sirve:** elegir con qué empresas de transporte trabajas (Chilexpress, Starken, Blue Express) y armar el enlace de seguimiento que recibe el cliente.

## Cómo funciona hoy (modo manual)
1. Marca *Trabajo con este courier*.
2. En *Enlace de seguimiento* pega la dirección de la página de seguimiento del courier, poniendo `{numero}` donde va el número de envío. Copia la dirección desde el sitio oficial del courier.
3. Al crear un despacho, escribe el número de seguimiento que te entrega el courier. El cliente recibe el enlace por correo.

Las tarifas se cargan en *Métodos de envío* con *Tarifas por zona y peso*.

## Conexión por API
La columna *Conexión por API* dice **No verificada** mientras no exista una prueba real con credenciales del courier. Hasta entonces la tienda no cotiza, no genera etiquetas ni lee el seguimiento automáticamente. *Probar conexión* lo confirma.

Para activarla se necesitan las credenciales de prueba de cada courier (contrato de empresa). Cuando las tengas, guárdalas como secretos en GitHub (no las envíes por chat) para conectar y probar la API.
