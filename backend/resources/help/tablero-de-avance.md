# Tablero de avance

**Para qué sirve:** ver en qué estado está cada uno de los 34 módulos del sistema, con evidencia.

## Secciones
- **Listado:** número, módulo, fase del plan, estado, evidencia y fecha de actualización.

## Campos
| Campo | Explicación |
| --- | --- |
| Estado | Pendiente, En desarrollo, Implementado, En pruebas, Validado o Bloqueado |
| Evidencia | Enlace a un PR, una ejecución de CI o un informe de prueba |
| Nota | Contexto, por ejemplo qué bloquea el módulo |

## Crear
Los 34 módulos se cargan automáticamente; no se crean a mano.

## Modificar
Pulsa **Editar**, cambia el estado y, si corresponde, agrega la evidencia.

## Desactivar
No aplica. Un módulo que no se usará se marca **Bloqueado** con una nota.

## Ejemplos
- Pagos con Webpay probados en sandbox: **En pruebas** con el enlace al informe.

## Errores frecuentes
- *La evidencia es obligatoria:* los estados **En pruebas** y **Validado** exigen un enlace.

## Recomendaciones
- Actualiza el tablero cada vez que se fusiona un PR relevante.

## Advertencias
- No marques un módulo como **Validado** sin evidencia ni manual de uso.

## Relaciones con otros módulos
- Se refleja también en `docs/module-status.md` del repositorio.
