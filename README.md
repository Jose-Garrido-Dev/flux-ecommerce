# Flux E-commerce Premium Intelligence

Tienda e-commerce independiente para el mercado chileno, construida con **Laravel** (backend, reglas de negocio y panel administrativo con Filament) y **Next.js** (storefront público), con inteligencia artificial integrada. Es el producto base que Flux Soluciones reutiliza para cada cliente: **una instalación = una tienda**. No es un SaaS ni un marketplace multiempresa.

## Estado

**Fase 2 de 15: design system y tienda base** (la Fase 1, fundaciones, está terminada). El avance real, módulo por módulo y con evidencia, está en [docs/module-status.md](docs/module-status.md). Ninguna integración externa (pagos, couriers, IA) se declara operativa hasta probarla con credenciales reales.

## Inicio rápido (desarrollo)

Requisitos: Git y Docker con Compose v2.

```bash
git clone https://github.com/Jose-Garrido-Dev/flux-ecommerce.git
cd flux-ecommerce
./infra/scripts/install-dev.sh
docker compose exec app php artisan flux:admin
docker compose exec app php artisan flux:demo   # opcional: catálogo de demostración (solo desarrollo)
```

- Tienda: <http://localhost:3000>
- Panel: <http://localhost:8000/admin>
- Correos de prueba: <http://localhost:8025>

## Documentación

| Documento | Contenido |
| --- | --- |
| [docs/INSTALACION.md](docs/INSTALACION.md) | Guía completa: requisitos, instalación local y en producción, variables de entorno, respaldos, actualizaciones, servicios externos, problemas frecuentes |
| [docs/arquitectura.md](docs/arquitectura.md) | Resumen de la arquitectura y enlace al diseño técnico completo |
| [docs/module-status.md](docs/module-status.md) | Tablero de avance por módulo |
| [CHANGELOG.md](CHANGELOG.md) | Cambios por versión |

## Estructura

```text
backend/      Laravel 13 + Filament 5 (API, panel /admin, colas, scheduler)
storefront/   Next.js 15 (tienda pública)
infra/        Docker, Nginx y scripts de instalación, despliegue y respaldo
packages/     Contrato OpenAPI compartido entre backend y storefront
custom/       Personalizaciones por cliente, fuera del núcleo
docs/         Documentación
```

## Licencia

Software propietario de Flux Soluciones. Todos los derechos reservados.
