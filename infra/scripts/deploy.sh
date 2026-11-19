#!/usr/bin/env bash
# Despliegue / actualización en PRODUCCIÓN.
# Uso: FLUX_VERSION=0.1.0 ./infra/scripts/deploy.sh
set -euo pipefail

cd "$(dirname "$0")/../.."
COMPOSE="docker compose -f compose.prod.yml --env-file .env"

[ -f .env ] || { echo "Falta .env en la raíz (copiar desde .env.example)."; exit 1; }
[ -f backend/.env ] || { echo "Falta backend/.env (copiar desde backend/.env.production.example)."; exit 1; }

echo "==> Respaldo previo"
./infra/scripts/backup.sh || { echo "El respaldo falló; se detiene el despliegue."; exit 1; }

echo "==> Construyendo imágenes ${FLUX_VERSION:-latest}"
$COMPOSE build

echo "==> Levantando base de datos y Redis"
$COMPOSE up -d postgres redis meilisearch

echo "==> Migraciones"
$COMPOSE run --rm app php artisan migrate --force

echo "==> Cachés de configuración"
$COMPOSE run --rm app php artisan optimize

echo "==> Reiniciando servicios"
$COMPOSE up -d --remove-orphans
$COMPOSE exec -T app php artisan queue:restart

echo "==> Verificando salud"
sleep 5
curl -fsS "http://127.0.0.1:${BACKEND_PORT:-8000}/api/health/ready" && echo " OK"
