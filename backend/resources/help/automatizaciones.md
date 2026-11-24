# Automatizaciones

**Para qué sirve:** correos que se envían solos cuando ocurre algo. Está en *Marketing → Automatizaciones*. Vienen desactivadas.

| Correo | Cuándo | Espera inicial |
| --- | --- | --- |
| Bienvenida | Al confirmar la suscripción al boletín | Inmediato |
| Carrito abandonado | Carrito con productos sin actividad, de un cliente con sesión iniciada | 4 horas |
| Después de la compra | Desde que se confirma el pago del pedido | 7 días |

## Reglas
- Solo llegan a **suscritos confirmados** del boletín. Quien no aceptó recibir novedades nunca recibe estos correos.
- Al **activar** una automatización, solo cuenta lo que ocurra desde ese momento: no se envía por suscripciones, carritos o compras anteriores.
- Cada persona recibe cada correo una sola vez por suscripción, carrito o pedido.
- El carrito abandonado lista los productos y lleva al carrito. Los carritos de invitados no se consideran, porque no hay un correo confirmado.

## Funcionamiento
Se revisan cada 10 minutos con el *programador de tareas*. Puedes editar la espera, el asunto, el texto y el botón; usa {nombre} y {tienda}.
