#!/usr/bin/env bash
# Redeploy after git push. Run as the deploy user:  bash deploy/deploy.sh [--skip-frontend]
# NEVER add `php artisan optimize` / config:cache - routes use env('APP_DOMAIN') directly.
set -euo pipefail
APP_DIR="${APP_DIR:-/var/www/marketplace_platforms}"
cd "$APP_DIR"
git pull origin main

cd backend
composer install --no-dev --optimize-autoloader --no-interaction
[ -L public/storage ] || php artisan storage:link
php artisan down --retry=30 || true
trap 'php artisan up' EXIT
php artisan migrate --force
php artisan seed:run --permissions-only
yarn install
yarn build
sudo chown -R "$(whoami)":www-data storage bootstrap/cache
sudo find storage bootstrap/cache -type d -exec chmod 2775 {} \;
sudo find storage bootstrap/cache -type f -exec chmod 664 {} \;
php artisan optimize:clear
php artisan queue:restart

if [ "${1:-}" != "--skip-frontend" ]; then
  cd ../frontend
  npm ci
  NODE_OPTIONS=--max-old-space-size=3072 npm run build
fi

if pm2 describe mp-frontend >/dev/null 2>&1; then
  pm2 restart mp-frontend mp-reverb mp-queue
else
  pm2 start "$APP_DIR/ecosystem.config.cjs" && pm2 save
fi
echo "Done ------------"
