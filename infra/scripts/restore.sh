#!/usr/bin/env bash
# Restaura un respaldo creado por backup.sh.
# Uso: ./infra/scripts/restore.sh backups/20261119-030000
# ADVERTENCIA: reemplaza la base de datos actual.
set -euo pipefail

cd "$(dirname "$0")/../.."
SRC="${1:?Indica la carpeta del respaldo, p. ej. backups/20261119-030000}"
set -a; . ./.env; set +a
COMPOSE="docker compose -f compose.prod.yml --env-file .env"

(cd "$SRC" && sha256sum -c SHA256SUMS)

read -r -p "Esto reemplaza la base de datos '$DB_DATABASE'. Escribe RESTAURAR para continuar: " ok
[ "$ok" = "RESTAURAR" ] || { echo "Cancelado."; exit 1; }

$COMPOSE stop app worker scheduler
$COMPOSE exec -T postgres pg_restore -U "$DB_USERNAME" -d "$DB_DATABASE" --clean --if-exists < "$SRC/db.dump"
$COMPOSE run --rm -T app tar -xzf - -C /var/www/backend < "$SRC/storage.tar.gz"
$COMPOSE up -d
echo "Restauración completa."
