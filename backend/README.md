# Backend Flux (Laravel 13 + Filament 5)

API, panel administrativo (`/admin`), colas y tareas programadas de la tienda.

- Instalación y configuración: [../docs/INSTALACION.md](../docs/INSTALACION.md)
- Arquitectura y reglas: [../docs/arquitectura.md](../docs/arquitectura.md)

## Comandos útiles

```bash
php artisan migrate --seed          # migraciones + datos base reales (regiones, roles, módulos)
php artisan flux:admin              # crear Superadministrador técnico
php artisan test                    # pruebas
vendor/bin/pint                     # formato de código
```

## Dónde está cada cosa

| Carpeta | Contenido |
| --- | --- |
| `app/Domain/` | Lógica de negocio por dominio (Pricing, Shipping, System, ...) |
| `app/Support/` | Utilidades: `Money`, `Rut` |
| `app/Filament/` | Panel administrativo |
| `app/Http/Controllers/Api/` | API de salud y storefront |
| `app/Policies/` | Permisos `modulo.accion` por recurso |
| `resources/help/` | Manuales de uso de cada pantalla (Markdown) |
| `database/seeders/` | Solo datos reales; nunca datos de ejemplo en producción |
