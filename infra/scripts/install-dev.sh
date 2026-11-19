#!/usr/bin/env bash
# Instalación del entorno de DESARROLLO con Docker.
# Uso: ./infra/scripts/install-dev.sh
set -euo pipefail

cd "$(dirname "$0")/../.."

echo "==> Verificando Docker"
command -v docker >/dev/null || { echo "Falta Docker. Ver docs/INSTALACION.md sección 2."; exit 1; }
docker compose version >/dev/null || { echo "Falta Docker Compose v2."; exit 1; }

echo "==> Creando backend/.env (si no existe)"
if [ ! -f backend/.env ]; then
  cp backend/.env.docker.example backend/.env
fi

echo "==> Construyendo imágenes y levantando servicios base"
export UID GID="$(id -g)"
docker compose build app
docker compose up -d postgres redis meilisearch mailpit

echo "==> Instalando dependencias PHP"
docker compose run --rm app composer install --no-interaction

if ! grep -q '^APP_KEY=base64' backend/.env; then
  docker compose run --rm app php artisan key:generate
fi

echo "==> Migraciones y datos base (regiones, roles, módulos)"
docker compose run --rm app php artisan migrate --seed --force
docker compose run --rm app php artisan storage:link || true

echo "==> Levantando todo"
docker compose up -d

cat <<'MSG'

Listo.
  Storefront:      http://localhost:3000
  API / health:    http://localhost:8000/api/health
  Panel admin:     http://localhost:8000/admin
  Correos (dev):   http://localhost:8025

Crea tu usuario administrador con:
  docker compose exec app php artisan flux:admin
MSG
