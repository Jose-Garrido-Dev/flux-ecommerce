#!/usr/bin/env bash
# Despliegue / actualización en PRODUCCIÓN.
# Uso: FLUX_VERSION=0.1.0 ./infra/scripts/deploy.sh
set -euo pipefail

cd "$(dirname "$0")/../.."
COMPOSE="docker compose -f compose.prod.yml --env-file .env"

[ -f .env ] || { echo "Falta .env en la raíz (copiar desde .env.example)."; exit 1; }
[ -f backend/.env ] || { echo "Falta backend/.env (copiar desde backend/.env.production.example)."; exit 1; }
set -a; . ./.env; set +a

echo "==> Respaldo previo"
if [ -n "$($COMPOSE ps -q postgres 2>/dev/null)" ]; then
  ./infra/scripts/backup.sh || { echo "El respaldo falló; se detiene el despliegue."; exit 1; }
else
  echo "Primera instalación: la base aún no existe, no hay nada que respaldar."
fi

echo "==> Construyendo imágenes ${FLUX_VERSION:-latest}"
$COMPOSE build

echo "==> Levantando base de datos"
$COMPOSE up -d postgres

echo "==> Migraciones"
$COMPOSE run --rm app php artisan migrate --force

echo "==> Reiniciando servicios"
$COMPOSE up -d --remove-orphans

echo "==> Cachés de configuración y rutas (en cada contenedor PHP)"
for svc in app worker scheduler; do
  $COMPOSE exec -T "$svc" php artisan optimize
done
$COMPOSE exec -T app php artisan queue:restart

echo "==> Verificando salud"
for i in $(seq 1 30); do
  if curl -fsS "http://127.0.0.1:${BACKEND_PORT:-8000}/api/health/ready"; then
    echo " OK"; exit 0
  fi
  sleep 2
done
echo "El backend no respondió sano en 60 s. Revisa: $COMPOSE logs app web"
exit 1
