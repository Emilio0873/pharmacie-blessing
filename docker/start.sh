#!/bin/sh
set -e

PORT="${PORT:-8080}"

mkdir -p /app/uploads/products /app/uploads/clients /app/uploads/backups
chmod -R 775 /app/uploads || true

exec php -S "0.0.0.0:${PORT}" -t /app /app/docker/router.php
