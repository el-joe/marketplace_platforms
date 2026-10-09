# Marketplace Platform — Full VPS Deployment Guide (Ubuntu 24.04)

Stack deployed by this guide:

| Part | Tech | Runs as |
|---|---|---|
| Backend (admin / partner / portal / delivery / carrier / marketer / travel panels + all APIs) | Laravel 13, PHP 8.3 | nginx → PHP-FPM |
| Customer storefront | Next.js 16 (Node ≥ 20.9) | **PM2** → nginx reverse proxy |
| Database | MySQL 8.0 + phpMyAdmin | systemd |
| WebSockets (live streams, notifications) | Laravel Reverb | **PM2** → nginx (`ws.` subdomain) |
| Queue worker (`QUEUE_CONNECTION=database`) | `artisan queue:work` | **PM2** |
| Scheduler (≈40 scheduled jobs in `routes/console.php`) | `artisan schedule:run` | cron |
| WebRTC TURN (live-stream viewers behind NAT) | coturn | systemd |
| SSL | Let's Encrypt (certbot) | systemd timer |

> Flutter apps (`carrier_app`, `delivery_app`, `partner_app`, `travel_app`) are mobile apps — they are **not** deployed to the VPS. They only need the API domain.

---

## 0. Read this first (things specific to this project)

1. **Subdomain routing is mandatory.** `backend/routes/web.php` binds each panel to `<sub>.APP_DOMAIN`:
   `admin`, `portal`, `partner`, `delivery`, `travel-agency`, `carrier`, `marketer`. The API is served on `api.`. All of them point to the **same** Laravel app, so you need a DNS record for each.
2. **Never run `php artisan optimize` or `php artisan config:cache`.** `routes/web.php` calls `env('APP_DOMAIN')` directly; once config is cached `env()` returns `null` outside config files and every panel route breaks (404). The project's own `update.sh` only uses `optimize:clear` — keep it that way.
3. **`NEXT_PUBLIC_*` variables are baked in at build time.** Edit the frontend `.env.production` → you must run `npm run build` again.
4. **Image URLs.** The API returns relative paths like `/storage/products/x.jpg`; the frontend prefixes `NEXT_PUBLIC_STORAGE_URL` (= the frontend domain). So the frontend nginx block must serve `/storage/` from the backend's `storage/app/public` (done in step 12.2).
5. **CORS is hard-coded** in `backend/config/cors.php` to `noon.codefanz.com` origins. If your new domain is different you **must** edit it (step 8.8).
6. **Full seeding creates demo accounts with weak passwords** (`123456`, `password123`). Either import a real DB dump (Option B, step 8) or change/delete them right after seeding (step 8, Option A).
7. `.env` files are git-ignored → you create them on the server by hand. Uploaded files (`storage/app/public`) and `firebase-service-account.json` are also not in git.

---

## Plan / order of work

```
 1  Variables + DNS records                 9  Frontend build (Next.js)
 2  First login, deploy user, SSH           10  PM2 (frontend, Reverb, queue) + cron scheduler
 3  Firewall, swap, fail2ban                11-12  Nginx sites (backend, frontend, ws, phpMyAdmin)
 4  Install PHP, MySQL, Nginx, Node, PM2    13  SSL (certbot)
 5  MySQL database + user                   14  coturn (TURN) - optional, recommended for live streams
 6  phpMyAdmin                              15  Verify  16  Backups  17  Update procedure  18  Troubleshooting
 7  Clone project + copy non-git files
 8  Backend: .env, composer, yarn, storage, migrate + seed (or import dump), CORS
```

Minimum VPS: **2 vCPU, 4 GB RAM** (Next.js build is memory hungry), 40 GB SSD, Ubuntu 24.04 LTS.

---

## 1. Variables + DNS

Pick your values once and reuse them (re-run these `export`s in every new SSH session):

```bash
export DOMAIN="example.com"          # <-- your real domain
export SERVER_IP="203.0.113.10"      # <-- your VPS public IP
export DB_NAME="marketplace_platform"
export DB_USER="marketplace"
export APP_DIR="/var/www/marketplace"
```

Create these **A records** at your DNS provider, all → `SERVER_IP`:

| Host | Purpose |
|---|---|
| `@` and `www` | Next.js storefront |
| `api` | Public/customer/vendor/… APIs |
| `admin` | Admin panel |
| `portal` | Vendor-registration portal |
| `partner` | Vendor (seller) panel |
| `delivery` | Delivery agents panel |
| `carrier` | Shipping-carrier panel |
| `marketer` | Marketer panel |
| `travel-agency` | Travel-agency panel |
| `ws` | Reverb WebSocket + TURN |
| `pma` | phpMyAdmin |

Check propagation before SSL step: `dig +short api.$DOMAIN` must print `SERVER_IP`.

---

## 2. First login, deploy user, SSH hardening

```bash
ssh root@$SERVER_IP
apt update && apt -y upgrade
timedatectl set-timezone UTC          # keep UTC; the dump is UTC
hostnamectl set-hostname marketplace

# non-root sudo user
adduser deploy                        # prompts: password, full name… (Enter to skip the rest), Y
usermod -aG sudo,www-data deploy

# copy your SSH key to the new user (run from YOUR PC):
#   ssh-copy-id deploy@$SERVER_IP
```

Test `ssh deploy@SERVER_IP` works **in a second terminal**, then harden:

```bash
sudo sed -i 's/^#\?PermitRootLogin.*/PermitRootLogin no/' /etc/ssh/sshd_config
sudo sed -i 's/^#\?PasswordAuthentication.*/PasswordAuthentication no/' /etc/ssh/sshd_config
sudo systemctl restart ssh
```

> Only disable password auth after the key login works, or you lock yourself out.

From now on work as `deploy` (use `sudo` when needed).

---

## 3. Firewall, swap, fail2ban

```bash
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'            # 80 + 443 (profile exists after nginx install; if not, run: sudo ufw allow 80,443/tcp)
sudo ufw allow 3478/tcp && sudo ufw allow 3478/udp          # TURN
sudo ufw allow 5349/tcp && sudo ufw allow 5349/udp          # TURN TLS
sudo ufw allow 50000:60000/udp                              # TURN relay range
sudo ufw enable                         # prompt: "Command may disrupt SSH connections. Proceed?" → y
sudo ufw status
```

> Do NOT open 3306 (MySQL), 8080 (Reverb) or 3000 (Next.js). They stay on localhost.

Swap (needed for `next build` on small servers):

```bash
sudo fallocate -l 4G /swapfile && sudo chmod 600 /swapfile
sudo mkswap /swapfile && sudo swapon /swapfile
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
free -h
```

```bash
sudo apt install -y fail2ban && sudo systemctl enable --now fail2ban
```

---

## 4. Install the software

### 4.1 Base tools

```bash
sudo apt install -y git curl unzip zip ca-certificates software-properties-common \
  build-essential openssl apache2-utils ufw
```

### 4.2 Nginx

```bash
sudo apt install -y nginx
sudo systemctl enable --now nginx
```

> If something else already holds port 80 (`apache2`), run `sudo systemctl disable --now apache2`.

### 4.3 PHP 8.3 + extensions (Ubuntu 24.04 ships 8.3 natively)

```bash
sudo apt install -y php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml \
  php8.3-curl php8.3-zip php8.3-gd php8.3-bcmath php8.3-intl php8.3-opcache php8.3-readline \
  php8.3-imagick php8.3-redis
php -v && php -m | grep -Ei "pdo_mysql|mbstring|gd|zip|xml|bcmath|intl|curl|fileinfo|dom"
```

(`gd`/`zip`/`xml` are required by phpspreadsheet, phpword and endroid/qr-code.)

Tune PHP (uploads, memory):

```bash
for f in /etc/php/8.3/fpm/php.ini /etc/php/8.3/cli/php.ini; do
  sudo sed -i 's/^;\?upload_max_filesize.*/upload_max_filesize = 64M/' $f
  sudo sed -i 's/^;\?post_max_size.*/post_max_size = 64M/' $f
  sudo sed -i 's/^;\?memory_limit.*/memory_limit = 512M/' $f
  sudo sed -i 's/^;\?max_execution_time.*/max_execution_time = 120/' $f
done
sudo sed -i 's/^;\?expose_php.*/expose_php = Off/' /etc/php/8.3/fpm/php.ini
sudo systemctl restart php8.3-fpm
```

### 4.4 Composer

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
composer --version
```

### 4.5 MySQL 8

```bash
sudo apt install -y mysql-server
sudo systemctl enable --now mysql
sudo mysql_secure_installation
```

Prompts and recommended answers:

| Prompt | Answer |
|---|---|
| VALIDATE PASSWORD component? | `n` (or `y` + level `1` if you want policy enforcement) |
| Remove anonymous users? | `y` |
| Disallow root login remotely? | `y` |
| Remove test database? | `y` |
| Reload privilege tables now? | `y` |

(On Ubuntu, MySQL `root` authenticates via the unix socket — use `sudo mysql`, no password. We create a dedicated app user next, which is also what phpMyAdmin will use.)

### 4.6 Node.js 22 LTS, Yarn (corepack), PM2

```bash
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs
sudo corepack enable                 # gives `yarn` (backend declares yarn@4)
node -v && npm -v && yarn -v
sudo npm install -g pm2
pm2 -v
```

---

## 5. Create the database and user

Generate a strong password and keep it (you need it for `.env` and phpMyAdmin):

```bash
export DB_PASS="$(openssl rand -base64 24 | tr -d '/+=' | cut -c1-24)"
echo "DB_PASS=$DB_PASS"    # SAVE THIS
```

```bash
sudo mysql <<SQL
CREATE DATABASE \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL
mysql -u $DB_USER -p"$DB_PASS" -e "SHOW DATABASES;"      # must list marketplace_platform
```

---

## 6. phpMyAdmin (served on `pma.$DOMAIN`, protected twice)

```bash
sudo DEBIAN_FRONTEND=noninteractive apt install -y --no-install-recommends phpmyadmin
```

If you prefer the interactive installer (without `DEBIAN_FRONTEND`) the prompts are:

| Prompt | Answer |
|---|---|
| Web server to reconfigure automatically | **Do not tick anything** (Tab → OK). Nginx is not offered; we configure it by hand |
| Configure database for phpmyadmin with dbconfig-common? | `No` |

If `apt` pulled in apache2 anyway: `sudo systemctl disable --now apache2`.

Create the HTTP basic-auth gate (first lock; MySQL login is the second):

```bash
sudo htpasswd -c /etc/nginx/.pma_htpasswd pmaadmin     # prompts: New password / Re-type
```

The nginx server block for it is in step 12. Log in later with MySQL user `$DB_USER` + `$DB_PASS` (not `root`).

Optional extra hardening: restrict to your IP by adding `allow YOUR.IP; deny all;` inside the `pma` server block.

---

## 7. Get the code

The repo is `https://github.com/el-joe/marketplace_platforms.git`. If private, create a **deploy key** (`ssh-keygen -t ed25519`, add `~/.ssh/id_ed25519.pub` in GitHub → repo → Settings → Deploy keys) and use the SSH URL; or use an HTTPS Personal Access Token as the password when prompted.

```bash
sudo mkdir -p $APP_DIR && sudo chown deploy:www-data $APP_DIR
git clone https://github.com/el-joe/marketplace_platforms.git $APP_DIR
cd $APP_DIR && git checkout main && git log --oneline -3
```

Files NOT in git that you must bring over (from your old/dev machine, run on **your PC**):

```bash
# DB dump (Option B), uploaded images/contracts, Firebase key
scp backups/marketplace_platform_live.sql deploy@$SERVER_IP:/home/deploy/
rsync -avz --progress backend/storage/app/public/  deploy@$SERVER_IP:/var/www/marketplace/backend/storage/app/public/
rsync -avz --progress backend/storage/app/private/ deploy@$SERVER_IP:/var/www/marketplace/backend/storage/app/private/   # vendor/marketer/exclusive contracts, QR codes
scp backend/storage/app/firebase-service-account.json deploy@$SERVER_IP:/var/www/marketplace/backend/storage/app/   # if you use push notifications
```

Upload **this guide** the same way:

```bash
scp docs/VPS_DEPLOYMENT.md deploy@$SERVER_IP:/home/deploy/
```

---

## 8. Backend: `.env`, install, database

### 8.1 Create `.env`

```bash
cd $APP_DIR/backend
cp .env.example .env
nano .env
```

Set (replace `example.com` and the passwords). Everything not listed can stay as in `.env.example`.

```ini
APP_NAME="Marketplace"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://admin.example.com
APP_DOMAIN=example.com
APP_ADMIN_SUBDOMAIN=admin
APP_PORTAL_SUBDOMAIN=portal
APP_PARTNER_SUBDOMAIN=partner
APP_DELIVERY_SUBDOMAIN=delivery
APP_MARKETER_SUBDOMAIN=marketer
APP_TRAVEL_SUBDOMAIN=travel-agency
APP_CARRIER_SUBDOMAIN=carrier
FRONTEND_URL=https://example.com

LOG_CHANNEL=stack
LOG_STACK=daily
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=marketplace_platform
DB_USERNAME=marketplace
DB_PASSWORD=PASTE_DB_PASS

SESSION_DRIVER=database
SESSION_DOMAIN=null
QUEUE_CONNECTION=database
CACHE_STORE=database
FILESYSTEM_DISK=local

# Reverb (generated in 8.3)
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=
REVERB_APP_KEY=
REVERB_APP_SECRET=
REVERB_HOST=ws.example.com
REVERB_PORT=443
REVERB_SCHEME=https
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8080
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"

# TURN (same values as step 14 and the frontend env)
TURN_URL=turn:ws.example.com:3478
TURN_USERNAME=streamuser
TURN_CREDENTIAL=PASTE_TURN_PASS

# Mail — real SMTP, otherwise emails only go to the log
MAIL_MAILER=smtp
MAIL_HOST=smtp.your-provider.com
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS="no-reply@example.com"
MAIL_FROM_NAME="${APP_NAME}"

GOOGLE_MAPS_API_KEY=
FIREBASE_PROJECT_ID=your-project-id
FIREBASE_CREDENTIALS_PATH=storage/app/firebase-service-account.json

# Payment gateways — add whichever you actually use
PAYTABS_PROFILE_ID=
PAYTABS_SERVER_KEY=
PAYTABS_REGION=ARE
TABBY_SECRET_KEY=
TABBY_PUBLIC_KEY=
TABBY_MERCHANT_CODE=
NOON_PAY_APP_KEY=
NOON_PAY_APP_SECRET=
NOON_PAY_BUSINESS_ID=
NOON_PAY_ENV=live
```

> `SCOUT_*` / `MEILISEARCH_*` lines in older `.env` files are leftovers — `laravel/scout` is not in `composer.json`, ignore them.

### 8.2 Install PHP + JS dependencies and generate keys

```bash
cd $APP_DIR/backend
composer install --no-dev --optimize-autoloader --no-interaction
php artisan key:generate --force
php artisan jwt:secret --force                     # writes JWT_SECRET (API auth for customer/vendor/… apps)
```

### 8.3 Reverb keys

```bash
php artisan reverb:install --no-interaction        # if it asks to overwrite config/reverb.php answer "no"
grep REVERB_APP .env                               # APP_ID / KEY / SECRET must now be filled
```

If `reverb:install` didn't fill them, generate by hand:

```bash
sed -i "s/^REVERB_APP_ID=.*/REVERB_APP_ID=$(shuf -i 100000-999999 -n1)/" .env
sed -i "s/^REVERB_APP_KEY=.*/REVERB_APP_KEY=$(openssl rand -hex 16)/" .env
sed -i "s/^REVERB_APP_SECRET=.*/REVERB_APP_SECRET=$(openssl rand -hex 16)/" .env
```

Write down `REVERB_APP_KEY` — the frontend needs the same value.

### 8.4 Frontend assets of the Blade panels (Vite/Tailwind)

```bash
cd $APP_DIR/backend
yarn install
yarn build           # outputs public/build  (re-run whenever .env VITE_* change)
```

### 8.5 Storage link + permissions (no `chmod 777`)

```bash
cd $APP_DIR/backend
php artisan storage:link
sudo chown -R deploy:www-data storage bootstrap/cache
sudo find storage bootstrap/cache -type d -exec chmod 2775 {} \;
sudo find storage bootstrap/cache -type f -exec chmod 664 {} \;
```

### 8.6 Database — choose ONE option

#### Option A — Fresh install (migrations + seeders)

```bash
cd $APP_DIR/backend
php artisan migrate --force                 # creates all tables (225 migration files)
php artisan seed:run                        # = DatabaseSeeder: countries, cities, category tree, settings,
                                            #   block types, contract templates, roles/permissions, admins, demo data…
php artisan seed:run --permissions-only     # (optional) re-sync only roles & permissions
```

`seed:run` is idempotent (`firstOrCreate`) — safe to repeat. It also seeds **demo** vendors, customers, products etc.

After seeding **immediately**:

1. Log in to `https://admin.example.com` as `admin@admin.com` / `123456` and change the password (also for `mohamed@`, `layla@`, `sara@admin.com`) — or delete those accounts.
2. Delete demo vendors/customers/marketers/delivery/travel accounts you do not want (list in `backend/accounts.txt`, all use `password123`).

Specific seeders can be run alone:

```bash
php artisan db:seed --class=ContractTemplateSeeder --force
php artisan db:seed --class=SettingsSeeder --force
php artisan db:seed --class=CategoryTreeSeeder --force
```

(`OrderLifecycleTestSeeder` is TEST-only — never run in production.)

#### Option B — Import the existing database dump (recommended when going live with your current data)

```bash
mysql -u $DB_USER -p"$DB_PASS" $DB_NAME < /home/deploy/marketplace_platform_live.sql
cd $APP_DIR/backend
php artisan migrate --force                 # applies any migrations newer than the dump
php artisan seed:run --permissions-only     # keeps roles/permissions in sync
```

Do **not** run the full `seed:run` after an import (it would add demo rows to real data). You can also import via phpMyAdmin (step 12) but the file limit there is the PHP `upload_max_filesize` (64M, fine).

### 8.7 Post-DB commands

```bash
cd $APP_DIR/backend
php artisan qr:generate-missing             # generates missing QR codes (storage/app/private/marketer-qr etc.)
php artisan optimize:clear                  # NOT "optimize" — see section 0, item 2
php artisan about | head -30                # sanity: env=production, debug OFF, storage linked
```

Useful maintenance commands (all scheduled automatically, can also run by hand):
`rankings:recalculate`, `buybox:rebuild`, `inventory:reconcile`, `coupons:deactivate-expired`, `gift-cards:expire`, `exclusive-contracts:expire`, `images:audit`.

### 8.8 CORS for your domain

Edit `backend/config/cors.php` → replace the `*.noon.codefanz.com` entries in `allowed_origins` with your real ones:

```php
'allowed_origins' => [
    'https://example.com',
    'https://www.example.com',
    'https://admin.example.com',
    'https://api.example.com',
],
```

(commit that change to git so the next `git pull` doesn't revert it), then `php artisan optimize:clear`.

---

## 9. Frontend build

```bash
cd $APP_DIR/frontend
nano .env.production
```

```ini
NODE_ENV=production
NEXT_PUBLIC_APP_URL=https://example.com
NEXT_PUBLIC_BASE_API_URL=https://api.example.com/api/customer/v1
NEXT_PUBLIC_API_PUBLIC_URL=https://api.example.com/api/public/v1
NEXT_PUBLIC_STORAGE_URL=https://example.com

NEXT_PUBLIC_REVERB_APP_KEY=SAME_AS_BACKEND_REVERB_APP_KEY
NEXT_PUBLIC_REVERB_HOST=ws.example.com
NEXT_PUBLIC_REVERB_PORT=443
NEXT_PUBLIC_REVERB_SCHEME=https

NEXT_PUBLIC_TURN_URL=turn:ws.example.com:3478
NEXT_PUBLIC_TURN_USERNAME=streamuser
NEXT_PUBLIC_TURN_CREDENTIAL=SAME_AS_BACKEND_TURN_CREDENTIAL

NEXT_PUBLIC_GOOGLE_MAPS_API_KEY=
```

```bash
npm ci
NODE_OPTIONS=--max-old-space-size=3072 npm run build      # takes a few minutes
```

The build must end with the route table and no errors. (Optional pre-check: `npm run check-locale-parity`.)

---

## 10. Run everything with PM2 (+ cron for the scheduler)

Create `$APP_DIR/ecosystem.config.cjs`:

```js
module.exports = {
  apps: [
    {
      name: 'mp-frontend',
      cwd: '/var/www/marketplace/frontend',
      script: 'node_modules/next/dist/bin/next',
      args: 'start -p 3000 -H 127.0.0.1',
      env: { NODE_ENV: 'production' },
      max_memory_restart: '1G',
    },
    {
      name: 'mp-reverb',
      cwd: '/var/www/marketplace/backend',
      script: 'artisan',
      interpreter: 'php',
      args: 'reverb:start --host=127.0.0.1 --port=8080 --no-interaction',
      autorestart: true,
    },
    {
      name: 'mp-queue',
      cwd: '/var/www/marketplace/backend',
      script: 'artisan',
      interpreter: 'php',
      args: 'queue:work --sleep=3 --tries=3 --timeout=90 --max-time=3600',
      autorestart: true,
    },
  ],
};
```

The backend processes write files in `storage/`, so they must run as a user that can write there. Simplest: run PM2 as `deploy` (owner of `storage`, group `www-data` with `g+w`) — already prepared in 8.5. Files created by PM2 jobs are then owned by `deploy:www-data` and `2775`+setgid keeps group access for PHP-FPM.

```bash
cd $APP_DIR
pm2 start ecosystem.config.cjs
pm2 status
pm2 save
pm2 startup systemd -u deploy --hp /home/deploy        # PRINTS a "sudo env PATH=… pm2 startup …" command → copy/paste & run it
pm2 save
pm2 install pm2-logrotate
```

Scheduler (cron, **as the same user** that owns storage):

```bash
crontab -e          # choose nano (1) if asked
```

add the line:

```
* * * * * cd /var/www/marketplace/backend && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Verify: `php artisan schedule:list` and, after a minute, no errors in `storage/logs/`.

> Reverb opens many sockets: for >1000 concurrent connections raise the limit (`ulimit -n 65535`, or `LimitNOFILE=` via `pm2 startup` unit override).

---

## 11/12. Nginx sites

All configs below are HTTP-only on purpose; **certbot (step 13) adds the HTTPS server blocks and redirects automatically**.

Create each file, then replace the placeholder domain in one go:

```bash
# after creating the 4 files below:
sudo sed -i "s/example\.com/$DOMAIN/g" /etc/nginx/sites-available/mp-*.conf
```

### 12.1 Backend (all panels + API) — `/etc/nginx/sites-available/mp-backend.conf`

```nginx
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

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 120;
        fastcgi_buffers 16 16k;
        fastcgi_buffer_size 32k;
    }

    location ~ /\.(?!well-known).* { deny all; }

    location ~* \.(?:css|js|woff2?|ttf|svg|png|jpe?g|gif|webp|ico)$ {
        expires 30d;
        access_log off;
        try_files $uri =404;
    }

    access_log /var/log/nginx/mp-backend.access.log;
    error_log  /var/log/nginx/mp-backend.error.log;
}
```

### 12.2 Frontend (Next.js) — `/etc/nginx/sites-available/mp-frontend.conf`

```nginx
server {
    listen 80;
    server_name example.com www.example.com;
    client_max_body_size 64M;

    # Uploaded files referenced as /storage/... by the API (see section 0, item 4)
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
```

### 12.3 WebSocket (Reverb) — `/etc/nginx/sites-available/mp-ws.conf`

```nginx
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
```

### 12.4 phpMyAdmin — `/etc/nginx/sites-available/mp-pma.conf`

```nginx
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
```

### 12.5 Enable and test

```bash
sudo ln -s /etc/nginx/sites-available/mp-backend.conf  /etc/nginx/sites-enabled/
sudo ln -s /etc/nginx/sites-available/mp-frontend.conf /etc/nginx/sites-enabled/
sudo ln -s /etc/nginx/sites-available/mp-ws.conf       /etc/nginx/sites-enabled/
sudo ln -s /etc/nginx/sites-available/mp-pma.conf      /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
```

`nginx -t` must say `syntax is ok` / `test is successful`.

phpMyAdmin needs a blowfish secret (otherwise a red warning):

```bash
sudo sed -i "s/\(\$cfg\['blowfish_secret'\] = \).*/\1'$(openssl rand -hex 16)';/" /etc/phpmyadmin/config.inc.php 2>/dev/null || true
```

---

## 13. SSL with Let's Encrypt

```bash
sudo apt install -y certbot python3-certbot-nginx

sudo certbot --nginx \
  -d $DOMAIN -d www.$DOMAIN -d api.$DOMAIN -d admin.$DOMAIN -d portal.$DOMAIN \
  -d partner.$DOMAIN -d delivery.$DOMAIN -d carrier.$DOMAIN -d marketer.$DOMAIN \
  -d travel-agency.$DOMAIN -d ws.$DOMAIN -d pma.$DOMAIN
```

Prompts:

| Prompt | Answer |
|---|---|
| Enter email address | your email |
| Terms of Service | `Y` |
| Share email with EFF | `N` |
| Redirect HTTP → HTTPS | `2` (redirect) |

Auto-renewal test: `sudo certbot renew --dry-run`.

Make sure nginx can still reload after each renewal: `sudo systemctl status certbot.timer`.

---

## 14. coturn (TURN server) — needed for live streams behind NAT

```bash
sudo apt install -y coturn
export TURN_PASS="$(openssl rand -base64 24 | tr -d '/+=' | cut -c1-24)"; echo "TURN_PASS=$TURN_PASS"   # SAVE; put it in backend .env + frontend .env.production
sudo sed -i 's/^#\?TURNSERVER_ENABLED=.*/TURNSERVER_ENABLED=1/' /etc/default/coturn

sudo tee /etc/turnserver.conf >/dev/null <<EOF
listening-port=3478
tls-listening-port=5349
listening-ip=$SERVER_IP
external-ip=$SERVER_IP
realm=$DOMAIN
server-name=ws.$DOMAIN
lt-cred-mech
user=streamuser:$TURN_PASS
fingerprint
no-multicast-peers
min-port=50000
max-port=60000
cert=/etc/letsencrypt/live/$DOMAIN/fullchain.pem
pkey=/etc/letsencrypt/live/$DOMAIN/privkey.pem
log-file=/var/log/turnserver.log
EOF

# coturn runs as user "turnserver" and must read the certificate
sudo chmod 750 /etc/letsencrypt/live /etc/letsencrypt/archive
sudo setfacl -R -m u:turnserver:rX /etc/letsencrypt/live /etc/letsencrypt/archive 2>/dev/null || sudo apt install -y acl
sudo systemctl enable --now coturn
sudo systemctl restart coturn && sudo systemctl status coturn --no-pager | head -8
```

> If `/etc/letsencrypt/live/$DOMAIN` doesn't exist, check `ls /etc/letsencrypt/live/` (certbot names the folder after the first `-d`).

Put `TURN_CREDENTIAL=$TURN_PASS` in backend `.env` and `NEXT_PUBLIC_TURN_CREDENTIAL=$TURN_PASS` in frontend `.env.production`, then `php artisan optimize:clear` and rebuild the frontend (step 9) + `pm2 restart mp-frontend`.

Test: https://webrtc.github.io/samples/src/content/peerconnection/trickle-ice/ → `turn:ws.example.com:3478`, user `streamuser`, your password → a candidate of type `relay` must appear.

---

## 15. Verify everything

```bash
# services
systemctl is-active nginx php8.3-fpm mysql coturn
pm2 status                                   # mp-frontend, mp-reverb, mp-queue = online

# HTTP checks
curl -I https://$DOMAIN                       # 200
curl -I https://api.$DOMAIN/up                # 200 (Laravel health route)
curl -I https://admin.$DOMAIN/login           # 200 or 302
curl -I https://ws.$DOMAIN                    # 200 / 404 / 426 are all fine (proves proxy works)
curl -s https://api.$DOMAIN/api/public/v1/ | head -c 300
```

Browser checks:

- [ ] `https://example.com` storefront loads, images appear (they come via `/storage/…`)
- [ ] `https://admin.example.com` login works (Option A: `admin@admin.com` / `123456` → change password)
- [ ] `https://partner.example.com`, `portal.`, `marketer.`, `delivery.`, `carrier.`, `travel-agency.` all show a login/landing page (a plain nginx 404 = missing DNS/server_name; a Laravel 404 = wrong `APP_DOMAIN`)
- [ ] Upload a product image in the panel → appears on the storefront
- [ ] Admin → Live streams → "Go live" → customer sees it in real time (WebSocket + TURN)
- [ ] Vendor contract signing (portal → register → contract page) produces a stored PDF/file
- [ ] `https://pma.example.com` asks basic-auth, then MySQL login with `$DB_USER`
- [ ] `php artisan schedule:list` lists tasks; `tail -f storage/logs/laravel-*.log` is clean
- [ ] Reboot test: `sudo reboot`, then re-check `pm2 status` (PM2 resurrection) and the site

---

## 16. Backups

```bash
sudo mkdir -p /var/backups/marketplace && sudo chown deploy /var/backups/marketplace
cat > ~/backup.sh <<'EOF'
#!/bin/bash
set -e
STAMP=$(date +%Y%m%d_%H%M%S)
DIR=/var/backups/marketplace
mysqldump --defaults-extra-file=/home/deploy/.my.cnf --single-transaction --routines marketplace_platform | gzip > $DIR/db_$STAMP.sql.gz
tar -czf $DIR/storage_$STAMP.tar.gz -C /var/www/marketplace/backend/storage/app public private
find $DIR -type f -mtime +14 -delete
EOF
chmod +x ~/backup.sh

cat > ~/.my.cnf <<EOF
[client]
user=$DB_USER
password=$DB_PASS
EOF
chmod 600 ~/.my.cnf

(crontab -l 2>/dev/null; echo "30 3 * * * /home/deploy/backup.sh") | crontab -
~/backup.sh && ls -lh /var/backups/marketplace
```

Copy backups off the server periodically (`rsync`/S3) — a backup on the same disk is not a backup.

---

## 17. Update / redeploy procedure

Save as `$APP_DIR/deploy.sh` (`chmod +x`), run after every `git push`:

```bash
#!/bin/bash
set -e
cd /var/www/marketplace
git pull origin main

# backend
cd backend
composer install --no-dev --optimize-autoloader --no-interaction
php artisan down --retry=30 || true
php artisan migrate --force
php artisan seed:run --permissions-only
yarn install && yarn build
php artisan optimize:clear
php artisan queue:restart
php artisan up

# frontend
cd ../frontend
npm ci
NODE_OPTIONS=--max-old-space-size=3072 npm run build

# processes
pm2 restart mp-frontend mp-reverb mp-queue
echo "Done ------------"
```

Backend-only change → skip the frontend block. Never use `chmod -R 777` (the old `update.sh` does) on the server; the group-write setup from 8.5 is enough.

---

## 18. Troubleshooting

| Symptom | Cause / fix |
|---|---|
| Every panel returns 404 (Laravel page) | `APP_DOMAIN` wrong **or** config was cached: `php artisan optimize:clear` and never run `config:cache` |
| nginx 502 Bad Gateway on backend | `sudo systemctl status php8.3-fpm`; socket path in nginx must be `/run/php/php8.3-fpm.sock` |
| 502 on storefront | `pm2 logs mp-frontend`; port 3000 not running → `pm2 restart mp-frontend` |
| 500 error, blank page | `tail -n 50 backend/storage/logs/laravel-*.log`; usually perms (re-run 8.5) or missing `.env` value |
| `Permission denied` on `storage/logs` | `sudo chown -R deploy:www-data storage bootstrap/cache && sudo chmod -R g+w storage bootstrap/cache` |
| Images broken on storefront | `NEXT_PUBLIC_STORAGE_URL` wrong, `storage:link` missing, or `/storage/` alias block missing in `mp-frontend.conf` |
| CORS error in browser console | Domain not in `backend/config/cors.php` (8.8) |
| Frontend still calls old API URL | `NEXT_PUBLIC_*` edited but not rebuilt → `npm run build` + `pm2 restart mp-frontend` |
| Emails/notifications not sent | queue worker down (`pm2 logs mp-queue`) or `MAIL_*` still `log` |
| Scheduled jobs not running | cron line missing / wrong user: `crontab -l`; run `php artisan schedule:run -v` |
| Live stream: no video for some users | TURN not reachable: ufw ports 3478/5349/50000-60000, `coturn` status, creds identical in 3 places |
| WebSocket fails `wss://ws.…` | `pm2 logs mp-reverb`; `REVERB_*` keys equal in backend `.env` and `NEXT_PUBLIC_REVERB_APP_KEY`; rebuild frontend |
| `next build` killed (`Killed`/OOM) | add swap (step 3) or bigger VPS; keep `NODE_OPTIONS=--max-old-space-size=3072` |
| `Class "ZipArchive" not found` / GD errors | missing `php8.3-zip` / `php8.3-gd` → install, `systemctl restart php8.3-fpm` |
| `SQLSTATE[HY000] [1045]` | wrong `DB_PASSWORD`; test: `mysql -u marketplace -p marketplace_platform` |
| certbot "Timeout during connect" | DNS not propagated or ufw blocking 80/443 |
| Imported dump fails with `Unknown collation utf8mb4_0900_ai_ci` | target is MariaDB/old MySQL; this guide installs MySQL 8, so use that |

Handy logs:

```bash
pm2 logs                                   # all PM2 apps
tail -f /var/www/marketplace/backend/storage/logs/laravel-$(date +%F).log
sudo tail -f /var/log/nginx/mp-backend.error.log
sudo journalctl -u php8.3-fpm -u mysql -u coturn -f
```

---

## Appendix — one-glance command cheat sheet

```bash
pm2 status | pm2 logs | pm2 restart all | pm2 save
sudo systemctl reload nginx          # after nginx edits (nginx -t first)
sudo systemctl restart php8.3-fpm    # after php.ini changes or code with opcache issues
php artisan migrate --force
php artisan seed:run --permissions-only
php artisan optimize:clear
php artisan queue:restart
php artisan schedule:list
php artisan qr:generate-missing
```
