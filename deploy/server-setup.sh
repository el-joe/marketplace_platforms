#!/usr/bin/env bash
# One-time VPS provisioning for the marketplace (Ubuntu 24.04). Run as a sudo user (not root):
#   DOMAIN=example.com SERVER_IP=1.2.3.4 bash server-setup.sh
# Covers guide steps 3-6, 10 (PM2 file), 12 (nginx). You still do by hand: .env files, DB import/seed,
# builds (deploy.sh), certbot (certbot-and-turn.sh).
set -euo pipefail

: "${DOMAIN:?set DOMAIN=example.com}"
: "${SERVER_IP:?set SERVER_IP=x.x.x.x}"
DB_NAME="${DB_NAME:-marketplace_platform}"
DB_USER="${DB_USER:-marketplace}"
APP_DIR="${APP_DIR:-/var/www/marketplace}"
REPO="${REPO:-https://github.com/el-joe/marketplace_platforms.git}"
ME="$(whoami)"
[ "$ME" = root ] && { echo "Run as a normal sudo user, not root."; exit 1; }

echo "==> Packages"
sudo apt update && sudo apt -y upgrade
sudo apt install -y git curl unzip zip ca-certificates software-properties-common build-essential \
  openssl apache2-utils ufw fail2ban acl nginx mysql-server \
  php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-gd \
  php8.3-bcmath php8.3-intl php8.3-opcache php8.3-readline php8.3-imagick php8.3-redis
sudo systemctl disable --now apache2 2>/dev/null || true

echo "==> PHP tuning"
for f in /etc/php/8.3/fpm/php.ini /etc/php/8.3/cli/php.ini; do
  sudo sed -i 's/^;\?upload_max_filesize.*/upload_max_filesize = 64M/;s/^;\?post_max_size.*/post_max_size = 64M/;s/^;\?memory_limit.*/memory_limit = 512M/;s/^;\?max_execution_time.*/max_execution_time = 120/' "$f"
done
sudo systemctl restart php8.3-fpm

echo "==> Composer, Node 22, yarn, PM2"
command -v composer >/dev/null || { curl -sS https://getcomposer.org/installer | php && sudo mv composer.phar /usr/local/bin/composer; }
command -v node >/dev/null || { curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash - && sudo apt install -y nodejs; }
sudo corepack enable
command -v pm2 >/dev/null || sudo npm install -g pm2

echo "==> Swap (4G)"
if ! swapon --show | grep -q /swapfile; then
  sudo fallocate -l 4G /swapfile && sudo chmod 600 /swapfile && sudo mkswap /swapfile && sudo swapon /swapfile
  echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab >/dev/null
fi

echo "==> Firewall"
sudo ufw allow OpenSSH; sudo ufw allow 80,443/tcp
sudo ufw allow 3478; sudo ufw allow 5349; sudo ufw allow 50000:60000/udp
sudo ufw --force enable

echo "==> MySQL database + user"
DB_PASS="$(openssl rand -base64 24 | tr -d '/+=' | cut -c1-24)"
sudo mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL
umask 077; printf '%s' "$DB_PASS" > "$HOME/.mp_db_pass"; umask 002
sudo mysql -e "DELETE FROM mysql.user WHERE User=''; DROP DATABASE IF EXISTS test; FLUSH PRIVILEGES;" || true

echo "==> phpMyAdmin"
sudo DEBIAN_FRONTEND=noninteractive apt install -y --no-install-recommends phpmyadmin
sudo systemctl disable --now apache2 2>/dev/null || true
if [ -n "${PMA_PASS:-}" ]; then sudo htpasswd -bc /etc/nginx/.pma_htpasswd pmaadmin "$PMA_PASS"
else echo "Set phpMyAdmin basic-auth password:"; sudo htpasswd -c /etc/nginx/.pma_htpasswd pmaadmin; fi

echo "==> Code"
sudo mkdir -p "$APP_DIR" && sudo chown "$ME":www-data "$APP_DIR"
[ -d "$APP_DIR/.git" ] || git clone "$REPO" "$APP_DIR"
sudo usermod -aG www-data "$ME"

echo "==> Nginx sites"
SITES=/etc/nginx/sites-available
sudo tee $SITES/mp-backend.conf >/dev/null <<'NGX'
server {
    listen 80;
    server_name api.example.com admin.example.com portal.example.com partner.example.com
                delivery.example.com carrier.example.com marketer.example.com travel-agency.example.com;
    root /var/www/marketplace/backend/public;
    index index.php;
    charset utf-8;
    client_max_body_size 64M;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 120;
        fastcgi_buffers 16 16k;
        fastcgi_buffer_size 32k;
    }
    location ~ /\.(?!well-known).* { deny all; }
    location ~* \.(?:css|js|woff2?|ttf|svg|png|jpe?g|gif|webp|ico)$ { expires 30d; access_log off; try_files $uri =404; }
    access_log /var/log/nginx/mp-backend.access.log;
    error_log  /var/log/nginx/mp-backend.error.log;
}
NGX
sudo tee $SITES/mp-frontend.conf >/dev/null <<'NGX'
server {
    listen 80;
    server_name example.com www.example.com;
    client_max_body_size 64M;
    location /storage/ {
        alias /var/www/marketplace/backend/storage/app/public/;
        expires 30d;
        access_log off;
        add_header Access-Control-Allow-Origin "*";
    }
    location / {
        proxy_pass http://127.0.0.1:3000;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_read_timeout 60s;
    }
}
NGX
sudo tee $SITES/mp-ws.conf >/dev/null <<'NGX'
server {
    listen 80;
    server_name ws.example.com;
    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 3600s;
        proxy_send_timeout 3600s;
    }
}
NGX
sudo tee $SITES/mp-pma.conf >/dev/null <<'NGX'
server {
    listen 80;
    server_name pma.example.com;
    root /usr/share/phpmyadmin;
    index index.php;
    client_max_body_size 64M;
    auth_basic "Restricted";
    auth_basic_user_file /etc/nginx/.pma_htpasswd;
    location / { try_files $uri $uri/ /index.php?$args; }
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }
    location ~ /\.ht { deny all; }
}
NGX
sudo sed -i "s/example\.com/$DOMAIN/g" $SITES/mp-*.conf
sudo sed -i "s#/var/www/marketplace#$APP_DIR#g" $SITES/mp-*.conf
for s in backend frontend ws pma; do sudo ln -sf $SITES/mp-$s.conf /etc/nginx/sites-enabled/; done
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx

echo "==> PM2 ecosystem + scheduler cron"
cat > "$APP_DIR/ecosystem.config.cjs" <<JS
module.exports = { apps: [
  { name: 'mp-frontend', cwd: '$APP_DIR/frontend', script: 'node_modules/next/dist/bin/next',
    args: 'start -p 3000 -H 127.0.0.1', env: { NODE_ENV: 'production' }, max_memory_restart: '1G' },
  { name: 'mp-reverb', cwd: '$APP_DIR/backend', script: 'artisan', interpreter: 'php',
    args: 'reverb:start --host=127.0.0.1 --port=8080 --no-interaction', autorestart: true },
  { name: 'mp-queue', cwd: '$APP_DIR/backend', script: 'artisan', interpreter: 'php',
    args: 'queue:work --sleep=3 --tries=3 --timeout=90 --max-time=3600', autorestart: true },
]};
JS
CRON="* * * * * cd $APP_DIR/backend && /usr/bin/php artisan schedule:run >> /dev/null 2>&1"
( crontab -l 2>/dev/null | grep -vF "schedule:run"; echo "$CRON" ) | crontab -

cat <<MSG

=================== DONE - SAVE THIS ===================
DB name : $DB_NAME
DB user : $DB_USER
DB pass : $DB_PASS
phpMyAdmin: https://pma.$DOMAIN  (basic-auth user: pmaadmin)
=========================================================
Next (log out/in once so the www-data group applies):
 1. Create DNS A records (see guide step 1)
 2. cd $APP_DIR/backend && cp .env.example .env && nano .env   (guide 8.1)
 3. composer install --no-dev -o; php artisan key:generate; php artisan jwt:secret; php artisan reverb:install
 4. Database: migrate+seed:run  OR  import dump (guide 8.6)
 5. nano $APP_DIR/frontend/.env.production  (guide 9)
 6. bash $APP_DIR/deploy/deploy.sh
 7. pm2 start $APP_DIR/ecosystem.config.cjs && pm2 save && pm2 startup   (run the printed sudo command)
 8. DOMAIN=$DOMAIN SERVER_IP=$SERVER_IP bash $APP_DIR/deploy/certbot-and-turn.sh
MSG
