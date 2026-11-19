#!/usr/bin/env bash
# Respaldo de base de datos y archivos subidos (producción).
# Uso: ./infra/scripts/backup.sh   (programarlo en cron, ver docs/INSTALACION.md)
set -euo pipefail

cd "$(dirname "$0")/../.."
set -a; . ./.env; set +a
COMPOSE="docker compose -f compose.prod.yml --env-file .env"
STAMP="$(date +%Y%m%d-%H%M%S)"
DEST="backups/$STAMP"
mkdir -p "$DEST"

echo "==> Base de datos"
$COMPOSE exec -T postgres pg_dump -U "$DB_USERNAME" -d "$DB_DATABASE" -Fc > "$DEST/db.dump"

echo "==> Archivos (storage)"
$COMPOSE run --rm -T app tar -czf - -C /var/www/backend storage/app > "$DEST/storage.tar.gz"

echo "==> Verificación"
test -s "$DEST/db.dump" || { echo "Respaldo de base vacío"; exit 1; }
sha256sum "$DEST"/* > "$DEST/SHA256SUMS"

# Conserva los últimos 14 respaldos
ls -1d backups/*/ 2>/dev/null | sort | head -n -14 | xargs -r rm -r --

echo "Respaldo listo en $DEST"
