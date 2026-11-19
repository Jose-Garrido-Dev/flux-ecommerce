# Auditoría

**Para qué sirve:** saber quién cambió qué, cuándo y desde dónde.

## Secciones
- **Listado:** fecha, acción, origen, usuario, recurso y resultado.
- **Detalle:** valores anteriores y nuevos del cambio.

## Campos
| Campo | Explicación |
| --- | --- |
| Acción | Qué ocurrió, por ejemplo `Setting.updated` |
| Origen | Usuario, sistema, proveedor externo o cliente |
| Valores anteriores / nuevos | Solo los campos que cambiaron |

## Crear, modificar y desactivar
No aplica: el sistema escribe la auditoría automáticamente y los registros **no se pueden editar ni eliminar**.

## Ejemplos
- Alguien cambió el RUT de la empresa: filtra por recurso *Setting* y revisa el detalle.

## Errores frecuentes
- *No veo un cambio:* solo se auditan los módulos ya implementados.

## Recomendaciones
- Revisa la auditoría ante cualquier diferencia de precios, stock o pagos.

## Advertencias
- Contraseñas, claves y tokens nunca se guardan: aparecen como `[oculto]`.

## Relaciones con otros módulos
- Todos los módulos críticos (precios, stock, pagos, permisos, reembolsos) escriben aquí.
