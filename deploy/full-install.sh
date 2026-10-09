#!/usr/bin/env bash
# =============================================================================
# FULL ONE-COMMAND INSTALL - Marketplace (Laravel backend + Next.js frontend)
# Fresh Ubuntu 24.04 VPS. Run as a NORMAL sudo user (not root):
#     bash full-install.sh
# or non-interactive:
#     DOMAIN=example.com SERVER_IP=1.2.3.4 EMAIL=me@mail.com DB_MODE=fresh bash full-install.sh
#     DB_MODE=import DUMP=/home/deploy/marketplace_platform_live.sql ...
# PREREQUISITE: DNS A records for @, www, api, admin, portal, partner, delivery,
#               carrier, marketer, travel-agency, ws, pma -> SERVER_IP (SSL step needs them).
# =============================================================================
set -euo pipefail
[ "$(whoami)" = root ] && { echo "Run as a normal sudo user (adduser deploy; usermod -aG sudo deploy)."; exit 1; }
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

ask() { # ask VAR "Question" [default]
  local v="$1" q="$2" d="${3:-}"
  [ -n "${!v:-}" ] && return
  read -rp "$q${d:+ [$d]}: " x; printf -v "$v" '%s' "${x:-$d}"
}
ask DOMAIN    "Domain (e.g. example.com)"
ask SERVER_IP "Server public IP" "$(curl -s4 ifconfig.me || true)"
ask EMAIL     "Email for Let's Encrypt"
ask DB_MODE   "Database mode: fresh (migrate+seed) or import (SQL dump)" "fresh"
[ "$DB_MODE" = import ] && ask DUMP "Path to SQL dump" "$HOME/marketplace_platform_live.sql"
ask MAIL_HOST "SMTP host (blank = log only)" ""
[ -n "$MAIL_HOST" ] && { ask MAIL_USERNAME "SMTP user"; ask MAIL_PASSWORD "SMTP password"; ask MAIL_PORT "SMTP port" "587"; }
ask GOOGLE_MAPS_API_KEY "Google Maps API key (blank to skip)" ""
export DOMAIN SERVER_IP EMAIL

APP_DIR="${APP_DIR:-/var/www/marketplace}"; export APP_DIR
DB_NAME="${DB_NAME:-marketplace_platform}"; DB_USER="${DB_USER:-marketplace}"
export PMA_PASS="${PMA_PASS:-$(openssl rand -base64 18 | tr -d '/+=')}"
export TURN_PASS="${TURN_PASS:-$(openssl rand -base64 24 | tr -d '/+=' | cut -c1-24)}"
REVERB_ID="$(shuf -i 100000-999999 -n1)"; REVERB_KEY="$(openssl rand -hex 16)"; REVERB_SECRET="$(openssl rand -hex 16)"

setenv() { # setenv FILE KEY VALUE  (replace or append)
  local f="$1" k="$2" v="$3"
  v="${v//\\/\\\\}"; v="${v//&/\\&}"; v="${v//|/\\|}"
  if grep -q "^${k}=" "$f"; then sed -i "s|^${k}=.*|${k}=\"${v}\"|" "$f"; else printf '%s="%s"\n' "$k" "${v//\\/}" >> "$f"; fi
}

echo "################ 1/9  Server provisioning ################"
bash "$HERE/server-setup.sh"
DB_PASS="$(cat "$HOME/.mp_db_pass")"
cd "$APP_DIR"

echo "################ 2/9  Backend .env ################"
cd "$APP_DIR/backend"
[ -f .env ] || cp .env.example .env
E=.env
setenv $E APP_NAME "Marketplace";          setenv $E APP_ENV production;  setenv $E APP_DEBUG false
setenv $E APP_URL "https://admin.$DOMAIN"; setenv $E APP_DOMAIN "$DOMAIN"; setenv $E FRONTEND_URL "https://$DOMAIN"
for p in ADMIN:admin PORTAL:portal PARTNER:partner DELIVERY:delivery MARKETER:marketer TRAVEL:travel-agency CARRIER:carrier; do
  setenv $E "APP_${p%%:*}_SUBDOMAIN" "${p##*:}"; done
setenv $E LOG_STACK daily; setenv $E LOG_LEVEL warning
setenv $E DB_CONNECTION mysql; setenv $E DB_HOST 127.0.0.1; setenv $E DB_PORT 3306
setenv $E DB_DATABASE "$DB_NAME"; setenv $E DB_USERNAME "$DB_USER"; setenv $E DB_PASSWORD "$DB_PASS"
setenv $E SESSION_DRIVER database; setenv $E QUEUE_CONNECTION database; setenv $E CACHE_STORE database
setenv $E BROADCAST_CONNECTION reverb
setenv $E REVERB_APP_ID "$REVERB_ID"; setenv $E REVERB_APP_KEY "$REVERB_KEY"; setenv $E REVERB_APP_SECRET "$REVERB_SECRET"
setenv $E REVERB_HOST "ws.$DOMAIN"; setenv $E REVERB_PORT 443; setenv $E REVERB_SCHEME https
setenv $E REVERB_SERVER_HOST 127.0.0.1; setenv $E REVERB_SERVER_PORT 8080
setenv $E VITE_REVERB_APP_KEY "$REVERB_KEY"; setenv $E VITE_REVERB_HOST "ws.$DOMAIN"
setenv $E VITE_REVERB_PORT 443; setenv $E VITE_REVERB_SCHEME https
setenv $E TURN_URL "turn:ws.$DOMAIN:3478"; setenv $E TURN_USERNAME streamuser; setenv $E TURN_CREDENTIAL "$TURN_PASS"
if [ -n "$MAIL_HOST" ]; then
  setenv $E MAIL_MAILER smtp; setenv $E MAIL_HOST "$MAIL_HOST"; setenv $E MAIL_PORT "$MAIL_PORT"
  setenv $E MAIL_USERNAME "$MAIL_USERNAME"; setenv $E MAIL_PASSWORD "$MAIL_PASSWORD"
  setenv $E MAIL_FROM_ADDRESS "no-reply@$DOMAIN"
fi
setenv $E GOOGLE_MAPS_API_KEY "$GOOGLE_MAPS_API_KEY"
chmod 640 .env

echo "################ 3/9  Composer + keys ################"
composer install --no-dev --optimize-autoloader --no-interaction
php artisan key:generate --force
php artisan jwt:secret --force

echo "################ 4/9  CORS for your domain ################"
sed -i "s/noon\.codefanz\.com/$DOMAIN/g" config/cors.php

echo "################ 5/9  Storage, permissions ################"
php artisan storage:link || true
sudo chown -R "$(whoami)":www-data storage bootstrap/cache
sudo find storage bootstrap/cache -type d -exec chmod 2775 {} \;
sudo find storage bootstrap/cache -type f -exec chmod 664 {} \;

echo "################ 6/9  Database ($DB_MODE) ################"
if [ "$DB_MODE" = import ]; then
  mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$DUMP"
  php artisan migrate --force
  php artisan seed:run --permissions-only
else
  php artisan migrate --force
  php artisan seed:run
fi
php artisan qr:generate-missing || true
php artisan optimize:clear      # never "optimize"/config:cache - panel routes use env()

echo "################ 7/9  Frontend/asset builds ################"
yarn install && yarn build
cd "$APP_DIR/frontend"
cat > .env.production <<ENV
NODE_ENV=production
NEXT_PUBLIC_APP_URL=https://$DOMAIN
NEXT_PUBLIC_BASE_API_URL=https://api.$DOMAIN/api/customer/v1
NEXT_PUBLIC_API_PUBLIC_URL=https://api.$DOMAIN/api/public/v1
NEXT_PUBLIC_STORAGE_URL=https://$DOMAIN
NEXT_PUBLIC_REVERB_APP_KEY=$REVERB_KEY
NEXT_PUBLIC_REVERB_HOST=ws.$DOMAIN
NEXT_PUBLIC_REVERB_PORT=443
NEXT_PUBLIC_REVERB_SCHEME=https
NEXT_PUBLIC_TURN_URL=turn:ws.$DOMAIN:3478
NEXT_PUBLIC_TURN_USERNAME=streamuser
NEXT_PUBLIC_TURN_CREDENTIAL=$TURN_PASS
NEXT_PUBLIC_GOOGLE_MAPS_API_KEY=$GOOGLE_MAPS_API_KEY
ENV
npm ci
NODE_OPTIONS=--max-old-space-size=3072 npm run build

echo "################ 8/9  PM2 (frontend, reverb, queue) ################"
cd "$APP_DIR"
pm2 delete all 2>/dev/null || true
pm2 start ecosystem.config.cjs
pm2 save
sudo env PATH="$PATH:/usr/bin" pm2 startup systemd -u "$(whoami)" --hp "$HOME"
pm2 save
pm2 install pm2-logrotate || true

echo "################ 9/9  SSL + TURN ################"
bash "$HERE/certbot-and-turn.sh"

# daily backups
mkdir -p "$HOME/backups"
cat > "$HOME/.my.cnf" <<CNF
[client]
user=$DB_USER
password=$DB_PASS
CNF
chmod 600 "$HOME/.my.cnf"
cat > "$HOME/backup.sh" <<BK
#!/bin/bash
S=\$(date +%Y%m%d_%H%M%S); D=\$HOME/backups
mysqldump --single-transaction --routines $DB_NAME | gzip > \$D/db_\$S.sql.gz
tar -czf \$D/storage_\$S.tar.gz -C $APP_DIR/backend/storage/app public private
find \$D -type f -mtime +14 -delete
BK
chmod +x "$HOME/backup.sh"
( crontab -l 2>/dev/null | grep -vF backup.sh; echo "30 3 * * * $HOME/backup.sh" ) | crontab -

CRED="$HOME/mp_credentials.txt"; umask 077
cat > "$CRED" <<C
Storefront     : https://$DOMAIN
Admin panel    : https://admin.$DOMAIN   (admin@admin.com / 123456 if seeded -> CHANGE NOW)
phpMyAdmin     : https://pma.$DOMAIN  basic-auth pmaadmin / $PMA_PASS ; MySQL login $DB_USER / $DB_PASS
DB             : $DB_NAME  user $DB_USER  pass $DB_PASS
TURN           : streamuser / $TURN_PASS
Reverb key     : $REVERB_KEY
C
echo
echo "=============== INSTALL COMPLETE ==============="
cat "$CRED"
echo "(saved to $CRED - delete after storing safely)"
echo "Verify: pm2 status ; curl -I https://api.$DOMAIN/up ; php $APP_DIR/backend/artisan schedule:list"
echo "Later redeploys: bash $APP_DIR/deploy/deploy.sh"
