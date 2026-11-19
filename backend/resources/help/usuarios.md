# Usuarios y roles

**Para qué sirve:** administrar quién entra al panel y qué puede hacer cada persona.

## Secciones
- **Listado:** nombre, correo, roles, estado y último ingreso. Filtra por estado o rol.
- **Formulario:** datos de la persona, contraseña, estado y roles.

## Campos
| Campo | Explicación |
| --- | --- |
| Nombre | Nombre visible en la auditoría |
| Correo | Con él se ingresa al panel; debe ser único |
| Contraseña | Mínimo 12 caracteres. Al editar, déjala vacía para no cambiarla |
| Estado | *Activo* puede entrar; *Desactivado* no |
| Roles | Definen los permisos. Sin rol no se puede entrar |

## Crear
1. Pulsa **Nuevo usuario**.
2. Completa nombre, correo y contraseña.
3. Elige uno o más roles y guarda.

## Modificar
Pulsa **Editar** en la fila. Para cambiar la contraseña escribe la nueva; si no, déjala vacía.

## Desactivar
Edita al usuario y cambia el estado a **Desactivado**. Los usuarios no se eliminan para conservar la auditoría.

## Ejemplos
- Una persona de bodega: rol **Inventario**.
- Contabilidad: rol **Finanzas** (puede confirmar pagos y procesar reembolsos).

## Errores frecuentes
- *El correo ya está en uso:* ya existe un usuario con ese correo; edítalo en vez de crear otro.
- *No puede entrar al panel:* revisa que esté **Activo** y tenga al menos un rol.

## Recomendaciones
- Da a cada persona solo los roles que necesita.
- Usa un usuario por persona; nunca compartas cuentas.

## Advertencias
- El rol **Superadministrador técnico** tiene todos los permisos, incluidos sistema y roles. Asígnalo solo a quien administra la instalación.

## Relaciones con otros módulos
- **Auditoría:** cada cambio de usuario queda registrado, sin contraseñas.
