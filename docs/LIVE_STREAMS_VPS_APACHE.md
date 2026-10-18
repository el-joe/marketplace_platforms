# Live Streams (WebRTC) on Ubuntu + Apache2 — Production Runbook

> **Using nginx instead of Apache?** See [`docs/LIVE_STREAMS_VPS_NGINX.md`](LIVE_STREAMS_VPS_NGINX.md) — the same runbook (sections 0-10) with nginx server blocks.

Target: Ubuntu 22.04 / 24.04, PHP 8.3, Apache2, MySQL, Node.js LTS. Goal: admin studio (`admin.example.com/.../live-streams`) and the Next.js `/live-streams` pages work with WebRTC **audio only, video only, or both**, also for viewers on mobile data.

Placeholders used everywhere: `example.com` (your domain), `203.0.113.10` (server public IP), `deploy` (Linux user that owns the code), `/var/www/marketplace` (app dir; contains `backend/` and `frontend/`). Replace them consistently.

> This guide replaces the nginx/PM2-for-Reverb parts of `docs/VPS_DEPLOYMENT.md` when you use **Apache2**. Do the generic parts of that guide (users, MySQL, code checkout, `.env`, seeding) first; use this document for web server, Reverb, TURN and Next.js.
> Hard rule from that guide: **never run `php artisan config:cache` / `optimize`** — `routes/web.php` calls `env('APP_DOMAIN')` directly and every panel would 404. Only use `php artisan optimize:clear`.

---

## 0. Architecture, DNS, ports

```
 Admin browser (broadcaster)                     Viewer browsers (Next.js /live-streams)
   │  HTTPS  admin.example.com                      │  HTTPS  example.com  /  api.example.com
   │  WSS    ws.example.com ─┐              ┌───────┘  WSS    ws.example.com
   ▼                         ▼              ▼
 ┌──────────────────────────── Apache2 :443 (TLS, HTTP/2) ────────────────────────────┐
 │ admin./api./partner./...  → PHP-FPM (Laravel, backend/public)                       │
 │ example.com, www          → 127.0.0.1:3000 (Next.js, PM2)   + /storage alias        │
 │ ws.example.com            → 127.0.0.1:8080 (Laravel Reverb, systemd)                │
 └─────────────────────────────────────────────────────────────────────────────────────┘
        Signalling (SDP offer/answer, ICE) = Reverb WebSocket + REST  → via Apache
        Media (audio/video) = WebRTC peer-to-peer (UDP/SRTP)
              └─ if P2P is impossible (mobile CGNAT, corporate firewall) → coturn relay
                 ws.example.com:3478 / :5349  (UDP+TCP, relay ports 49152-65535/udp)
```

Media never goes through Apache. Apache only carries signalling. Topology is a P2P mesh (admin → one connection per viewer), good for roughly 30–50 concurrent viewers (see section 10).

### 0.1 DNS (all A records → server IP, TTL 300)

| Host | Serves |
|---|---|
| `@`, `www` | Next.js storefront / `/live-streams` |
| `api` | Laravel API (`/api/...`) |
| `admin` | Admin panel (live-stream studio) |
| `partner`, `portal`, `marketer`, `delivery`, `carrier`, `travel-agency` | other Laravel panels (same app, routed by `APP_DOMAIN` subdomain) |
| `ws` | Reverb WebSocket **and** coturn (TURN) |

> If you use Cloudflare: `ws` must be **DNS only (grey cloud)** because TURN (UDP 3478/5349) cannot be proxied. Other records may be proxied.
> Check: `dig +short admin.example.com ws.example.com` must print `203.0.113.10`.

### 0.2 Ports

| Port | Proto | Public? | Service | Notes |
|---|---|---|---|---|
| 22 | TCP | yes (restrict if possible) | SSH | |
| 80 | TCP | yes | Apache | ACME challenge + redirect to HTTPS |
| 443 | TCP | yes | Apache | all sites + `wss://ws.example.com` |
| 3478 | UDP + TCP | yes | coturn | STUN/TURN |
| 5349 | TCP (+UDP) | yes | coturn | TURN over TLS (`turns:`) – gets through strict firewalls |
| 49152–65535 | UDP | yes | coturn | relay allocation range |
| 8080 | TCP | **no** (127.0.0.1) | Reverb | only Apache talks to it |
| 3000 | TCP | **no** (127.0.0.1) | Next.js | only Apache talks to it |
| 3306 | TCP | **no** | MySQL | |

### 0.3 Why HTTPS is mandatory

`navigator.mediaDevices.getUserMedia()` (camera/mic) and `getDisplayMedia()` (screen share) exist **only in a secure context** (HTTPS, or `localhost`). On plain `http://admin.example.com` the browser exposes `navigator.mediaDevices === undefined` and no permission prompt ever appears. Also, a page served over HTTPS may only open `wss://` (never `ws://`) sockets — mixed content is blocked. So: valid TLS certificates on every host, `REVERB_SCHEME=https`, port 443.

---

## 1. Install packages

```bash
sudo apt update && sudo apt -y upgrade

# Apache + PHP-FPM (needed for HTTP/2) + extensions Laravel needs
sudo apt install -y apache2 php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml \
  php8.3-curl php8.3-zip php8.3-gd php8.3-intl php8.3-bcmath php8.3-opcache \
  unzip git curl acl ufw coturn certbot

# Optional but recommended if you expect >1000 simultaneous WebSocket connections
# (Reverb's default loop is limited to 1024 file descriptors by stream_select)
sudo apt install -y php8.3-dev php-pear libuv1-dev && sudo pecl install uv   # then add extension=uv.so to /etc/php/8.3/cli/conf.d/20-uv.ini
```

> Ubuntu 22.04 does not ship PHP 8.3 — add `sudo add-apt-repository ppa:ondrej/php` first. 24.04 has it natively.
> Certbot: we use `certbot` with the **webroot** method below (works with the Apache vhosts you write by hand). If you prefer the apache plugin: `sudo apt install python3-certbot-apache`.

Apache modules (event MPM + PHP-FPM; this is what makes `http2` work):

```bash
sudo a2dismod php8.3 mpm_prefork 2>/dev/null || true
sudo a2enmod mpm_event proxy proxy_http proxy_wstunnel proxy_fcgi setenvif \
  rewrite ssl headers http2 expires alias
sudo a2enconf php8.3-fpm
sudo systemctl restart apache2 php8.3-fpm
apache2ctl -M | grep -E 'proxy|rewrite|ssl|headers|http2|expires|mpm_event'
```

Node.js LTS + PM2 (for Next.js) and Yarn (backend asset build):

```bash
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt install -y nodejs
sudo corepack enable            # provides yarn
sudo npm install -g pm2
node -v && npm -v && yarn -v
```

Composer: `curl -sS https://getcomposer.org/installer | php && sudo mv composer.phar /usr/local/bin/composer`.

Firewall (do this before coturn so nothing is forgotten; keep SSH open!):

```bash
sudo ufw allow OpenSSH
sudo ufw allow 'Apache Full'                 # 80 + 443
sudo ufw allow 3478/tcp && sudo ufw allow 3478/udp
sudo ufw allow 5349/tcp && sudo ufw allow 5349/udp
sudo ufw allow 49152:65535/udp               # TURN relay range
sudo ufw enable && sudo ufw status verbose
```

Do NOT open 8080, 3000, 3306.

---

## 2. Apache virtual hosts and certificates

Set variables once per SSH session:

```bash
export DOMAIN=example.com
export APP_DIR=/var/www/marketplace
```

### 2.1 Step A — HTTP bootstrap vhost + one certificate for every hostname

Create the ACME webroot and a port-80 vhost that redirects everything except Let's Encrypt challenges:

```bash
sudo mkdir -p /var/www/letsencrypt
sudo tee /etc/apache2/sites-available/00-http-redirect.conf >/dev/null <<'EOF'
<VirtualHost *:80>
    ServerName example.com
    ServerAlias www.example.com api.example.com admin.example.com partner.example.com \
                portal.example.com marketer.example.com delivery.example.com \
                carrier.example.com travel-agency.example.com ws.example.com

    DocumentRoot /var/www/letsencrypt
    Alias /.well-known/acme-challenge/ /var/www/letsencrypt/.well-known/acme-challenge/
    <Directory /var/www/letsencrypt>
        Require all granted
    </Directory>

    RewriteEngine On
    RewriteCond %{REQUEST_URI} !^/\.well-known/acme-challenge/
    RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]

    ErrorLog  ${APACHE_LOG_DIR}/http-redirect.error.log
    CustomLog ${APACHE_LOG_DIR}/http-redirect.access.log combined
</VirtualHost>
EOF
sudo sed -i "s/example\.com/$DOMAIN/g" /etc/apache2/sites-available/00-http-redirect.conf
sudo a2dissite 000-default.conf
sudo a2ensite 00-http-redirect.conf
sudo apache2ctl configtest && sudo systemctl reload apache2
```

Issue **one** certificate covering all names (lineage name = `$DOMAIN`, files in `/etc/letsencrypt/live/$DOMAIN/`):

```bash
sudo certbot certonly --webroot -w /var/www/letsencrypt \
  --cert-name $DOMAIN --agree-tos -m you@example.com --no-eff-email \
  -d $DOMAIN -d www.$DOMAIN -d api.$DOMAIN -d admin.$DOMAIN -d partner.$DOMAIN \
  -d portal.$DOMAIN -d marketer.$DOMAIN -d delivery.$DOMAIN -d carrier.$DOMAIN \
  -d travel-agency.$DOMAIN -d ws.$DOMAIN
sudo certbot renew --dry-run          # auto-renew timer is installed by the package
```

Add a renewal hook that reloads Apache and refreshes coturn's copy of the certificate (coturn runs as another user and cannot read `/etc/letsencrypt` directly):

```bash
sudo mkdir -p /etc/coturn/certs
sudo tee /etc/letsencrypt/renewal-hooks/deploy/reload-services.sh >/dev/null <<'EOF'
#!/bin/bash
# Runs after every successful renewal.
LIVE=/etc/letsencrypt/live/example.com
install -o turnserver -g turnserver -m 640 "$LIVE/fullchain.pem" /etc/coturn/certs/fullchain.pem
install -o turnserver -g turnserver -m 640 "$LIVE/privkey.pem"   /etc/coturn/certs/privkey.pem
systemctl reload apache2
systemctl restart coturn
EOF
sudo sed -i "s/example\.com/$DOMAIN/" /etc/letsencrypt/renewal-hooks/deploy/reload-services.sh
sudo chmod +x /etc/letsencrypt/renewal-hooks/deploy/reload-services.sh
sudo /etc/letsencrypt/renewal-hooks/deploy/reload-services.sh || true   # coturn not installed/configured yet is fine; re-run after section 5
```

### 2.2 Step B — Laravel vhost: API + ALL panels (admin, partner, portal, marketer, delivery, carrier, travel-agency)

All these hostnames are the **same** Laravel app; `routes/web.php` picks the panel from the Host header, so they share one vhost. `/etc/apache2/sites-available/mp-backend.conf`:

```apache
<VirtualHost *:443>
    ServerName admin.example.com
    ServerAlias api.example.com partner.example.com portal.example.com marketer.example.com \
                delivery.example.com carrier.example.com travel-agency.example.com

    DocumentRoot /var/www/marketplace/backend/public
    Protocols h2 http/1.1

    SSLEngine on
    SSLCertificateFile    /etc/letsencrypt/live/example.com/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/example.com/privkey.pem
    SSLProtocol           all -SSLv3 -TLSv1 -TLSv1.1
    SSLSessionTickets     off

    <Directory /var/www/marketplace/backend/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All          # Laravel's public/.htaccess (rewrite to index.php) must be honoured
        Require all granted
    </Directory>

    # PHP via FPM
    <FilesMatch "\.php$">
        SetHandler "proxy:unix:/run/php/php8.3-fpm.sock|fcgi://localhost"
    </FilesMatch>
    # Make the Authorization header reach PHP (Sanctum / bearer tokens)
    SetEnvIfNoCase ^Authorization$ "(.+)" HTTP_AUTHORIZATION=$1

    LimitRequestBody 67108864      # 64 MB uploads (also set upload_max_filesize/post_max_size in php.ini)

    # ---- Headers -------------------------------------------------------------
    # REQUIRED for the live-stream studio: allow camera, microphone and screen capture on this origin.
    Header always set Permissions-Policy "camera=(self), microphone=(self), display-capture=(self)"
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set Strict-Transport-Security "max-age=31536000"
    # Do NOT add a Content-Security-Policy unless it allows: connect-src wss://ws.example.com https://api.example.com,
    # media-src blob: mediastream:, img-src data: blob:, script-src for your CDNs. A too-strict CSP silently breaks Echo and the preview.
    # Do NOT send "Feature-Policy" or "Permissions-Policy: camera=()" anywhere (including .htaccess, PHP middleware, or Cloudflare rules).

    <IfModule mod_expires.c>
        ExpiresActive On
        ExpiresByType text/css                 "access plus 30 days"
        ExpiresByType application/javascript   "access plus 30 days"
        ExpiresByType image/png                "access plus 30 days"
        ExpiresByType image/jpeg               "access plus 30 days"
        ExpiresByType image/webp               "access plus 30 days"
        ExpiresByType image/svg+xml            "access plus 30 days"
        ExpiresByType font/woff2               "access plus 30 days"
    </IfModule>

    # Never serve dotfiles (except ACME)
    <DirectoryMatch "/\.(?!well-known)">
        Require all denied
    </DirectoryMatch>

    ErrorLog  ${APACHE_LOG_DIR}/mp-backend.error.log
    CustomLog ${APACHE_LOG_DIR}/mp-backend.access.log combined
</VirtualHost>
```

Notes:
- The `Permissions-Policy` header is only needed on the **page that calls getUserMedia** (admin studio). It is harmless on the others. Viewers never use camera/mic.
- If the studio is ever embedded in an `<iframe>`, the parent must add `allow="camera; microphone; display-capture"`.
- CORS for the API is handled by Laravel (`backend/config/cors.php`), **not** by Apache — see 2.5.

### 2.3 Step C — Next.js storefront vhost (reverse proxy to 127.0.0.1:3000)

`/etc/apache2/sites-available/mp-frontend.conf`:

```apache
<VirtualHost *:443>
    ServerName example.com
    ServerAlias www.example.com
    Protocols h2 http/1.1

    SSLEngine on
    SSLCertificateFile    /etc/letsencrypt/live/example.com/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/example.com/privkey.pem
    SSLProtocol           all -SSLv3 -TLSv1 -TLSv1.1

    Header always set Strict-Transport-Security "max-age=31536000"
    Header always set X-Content-Type-Options "nosniff"

    # Uploaded files referenced as /storage/... by the API are served straight from Laravel's public disk
    Alias /storage/ /var/www/marketplace/backend/storage/app/public/
    <Directory /var/www/marketplace/backend/storage/app/public/>
        Options -Indexes +FollowSymLinks
        AllowOverride None
        Require all granted
        Header set Access-Control-Allow-Origin "*"
        ExpiresActive On
        ExpiresDefault "access plus 30 days"
    </Directory>

    ProxyPreserveHost On
    ProxyRequests Off
    RequestHeader set X-Forwarded-Proto "https"
    RequestHeader set X-Forwarded-Port  "443"

    # ORDER MATTERS: the exclusion must come BEFORE the catch-all ProxyPass
    ProxyPass /storage/ !

    # (Optional) WebSocket upgrade passthrough; production Next.js does not need HMR sockets, harmless if unused
    RewriteEngine On
    RewriteCond %{HTTP:Upgrade} =websocket [NC]
    RewriteRule ^/(.*)$ ws://127.0.0.1:3000/$1 [P,L]

    ProxyPass        / http://127.0.0.1:3000/ retry=0 timeout=120
    ProxyPassReverse / http://127.0.0.1:3000/

    ErrorLog  ${APACHE_LOG_DIR}/mp-frontend.error.log
    CustomLog ${APACHE_LOG_DIR}/mp-frontend.access.log combined
</VirtualHost>
```

### 2.4 Step D — `ws.example.com`: WSS → Reverb (127.0.0.1:8080)

`/etc/apache2/sites-available/mp-ws.conf`:

```apache
<VirtualHost *:443>
    ServerName ws.example.com

    # WebSockets are HTTP/1.1 Upgrade requests; keep this host on HTTP/1.1 only
    Protocols http/1.1

    SSLEngine on
    SSLCertificateFile    /etc/letsencrypt/live/example.com/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/example.com/privkey.pem
    SSLProtocol           all -SSLv3 -TLSv1 -TLSv1.1

    ProxyPreserveHost On
    ProxyRequests Off
    RequestHeader set X-Forwarded-Proto "https"

    # Long-lived sockets: do not let Apache cut idle connections (Reverb pings every ~60 s)
    ProxyTimeout 3600
    Timeout 3600

    # 1) WebSocket handshake (Upgrade: websocket) on any path -> ws://
    RewriteEngine On
    RewriteCond %{HTTP:Upgrade} =websocket [NC]
    RewriteRule ^/(.*)$ ws://127.0.0.1:8080/$1 [P,L]

    # 2) Plain HTTP endpoints of Reverb
    #    /apps/{app_id}/events  = Laravel -> Reverb broadcasting API (POST, signed)
    #    /app/{key}             = client endpoint (non-upgrade requests, health probes)
    ProxyPass        /app  http://127.0.0.1:8080/app  timeout=3600 keepalive=On
    ProxyPassReverse /app  http://127.0.0.1:8080/app
    ProxyPass        /apps http://127.0.0.1:8080/apps timeout=3600 keepalive=On
    ProxyPassReverse /apps http://127.0.0.1:8080/apps

    # No CORS headers needed: browsers open WebSockets (not subject to CORS) and the
    # events API is called server-to-server by Laravel.

    ErrorLog  ${APACHE_LOG_DIR}/mp-ws.error.log
    CustomLog ${APACHE_LOG_DIR}/mp-ws.access.log combined
</VirtualHost>
```

Ordering rules that bite: `RewriteRule ... [P]` is evaluated before `ProxyPass`; `ProxyPass` lines are matched in file order (put specific paths and `!` exclusions before `/`); `/app` also matches `/apps` as a prefix, which is why both point at the same backend with their own full path.

> **Important – hairpin:** Laravel publishes events by POSTing to `https://ws.example.com/apps/<id>/events` (it builds the URL from `REVERB_HOST/PORT/SCHEME`). So the server itself must be able to reach its own public name. Verify: `curl -sI https://ws.example.com/ | head -1` **from the VPS**. If your provider blocks hairpin NAT, add `127.0.0.1 ws.example.com` to `/etc/hosts` (Apache answers locally; TURN on the same name is unaffected for remote clients).

### 2.5 Enable everything

```bash
for f in mp-backend mp-frontend mp-ws; do sudo sed -i "s/example\.com/$DOMAIN/g; s#/var/www/marketplace#$APP_DIR#g" /etc/apache2/sites-available/$f.conf; done
sudo a2ensite mp-backend mp-frontend mp-ws
sudo apache2ctl configtest      # must print "Syntax OK"
sudo systemctl reload apache2
```

(The `00-http-redirect.conf` from step A stays enabled and keeps redirecting port 80.) Permissions for Laravel must allow PHP-FPM (`www-data`) to write: `sudo chown -R deploy:www-data $APP_DIR/backend/storage $APP_DIR/backend/bootstrap/cache && sudo chmod -R ug+rwX,o-rwx` and setgid on directories (`find ... -type d -exec chmod 2775 {} +`), exactly as in `docs/VPS_DEPLOYMENT.md` §8.5. Then `php artisan storage:link`.

### 2.6 CORS notes (frontend domain → API domain)

- The storefront (`https://example.com`) calls `https://api.example.com/api/...` from the browser, so Laravel must allow that origin. `backend/config/cors.php` has a **hard-coded** `allowed_origins` list (nawyweb.com / noon.codefanz.com). **Add** `https://example.com`, `https://www.example.com`, `https://admin.example.com`, `https://api.example.com` to it (this file is not affected by the "no config:cache" rule). `supports_credentials` is already `true`.
- WebSocket origin check: `config/reverb.php` → `apps.apps[0].allowed_origins` is `['*']` (works). Optional hardening: set it to the hosts only, no scheme: `['example.com', 'www.example.com', 'admin.example.com']`, then `php artisan reverb:restart`.
- Do **not** also add `Access-Control-Allow-Origin` in Apache for the API – duplicated CORS headers make browsers reject the response.
- Session cookies for the admin panel: `SESSION_DOMAIN=null`, `SESSION_SECURE_COOKIE=true` (HTTPS).

---

## 3. Laravel `.env` and build-time variables

`$APP_DIR/backend/.env` (only the relevant lines; keep the rest as in `docs/VPS_DEPLOYMENT.md` §8.1):

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://admin.example.com
APP_DOMAIN=example.com
FRONTEND_URL=https://example.com
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=true
CACHE_STORE=database           # reverb:restart and queue:restart use the cache to signal workers
QUEUE_CONNECTION=database

# --- Reverb (server-side; used by Laravel to PUBLISH events and by the reverb process)
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=123456                       # php artisan reverb:install, or: shuf -i 100000-999999 -n1
REVERB_APP_KEY=0123456789abcdef0123456789abcdef   # openssl rand -hex 16
REVERB_APP_SECRET=fedcba9876543210fedcba9876543210 # openssl rand -hex 16
REVERB_HOST=ws.example.com                 # PUBLIC host (what browsers + Laravel's HTTP client use)
REVERB_PORT=443
REVERB_SCHEME=https
REVERB_SERVER_HOST=127.0.0.1               # where the reverb process LISTENS (never 0.0.0.0 behind Apache)
REVERB_SERVER_PORT=8080

# --- Reverb (browser bundle of the Laravel panels, compiled by Vite)
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"

# --- TURN (coturn, section 5). Injected into the admin studio via meta tags
TURN_URL=turn:ws.example.com:3478
TURN_TLS_URL=turns:ws.example.com:5349
TURN_USERNAME=streamuser
TURN_CREDENTIAL=PUT_A_LONG_RANDOM_PASSWORD_HERE     # openssl rand -base64 24 | tr -d '/+='
```

Frontend `$APP_DIR/frontend/.env.production`:

```ini
NODE_ENV=production
NEXT_PUBLIC_APP_URL=https://example.com
NEXT_PUBLIC_BASE_API_URL=https://api.example.com/api/customer/v1
NEXT_PUBLIC_API_PUBLIC_URL=https://api.example.com/api/public/v1
NEXT_PUBLIC_STORAGE_URL=https://example.com

NEXT_PUBLIC_REVERB_APP_KEY=0123456789abcdef0123456789abcdef   # identical to backend REVERB_APP_KEY
NEXT_PUBLIC_REVERB_HOST=ws.example.com
NEXT_PUBLIC_REVERB_PORT=443
NEXT_PUBLIC_REVERB_SCHEME=https

NEXT_PUBLIC_TURN_URL=turn:ws.example.com:3478
NEXT_PUBLIC_TURN_USERNAME=streamuser
NEXT_PUBLIC_TURN_CREDENTIAL=SAME_AS_BACKEND_TURN_CREDENTIAL
NEXT_PUBLIC_TURN_TLS_URL=turns:ws.example.com:5349
```

The TURN URL / user / credential must be **identical in three places**: coturn config, backend `.env`, frontend `.env.production`.

### What is baked at build time (changing it requires a rebuild)

| Variable group | Read when | After changing run |
|---|---|---|
| `REVERB_*`, `TURN_*`, `APP_*` (PHP side) | request/runtime | `php artisan optimize:clear` and `sudo systemctl restart reverb` (Reverb reads `.env` at start) |
| `VITE_REVERB_*` (admin/partner/etc. JS bundle) | **`vite build`** | `cd backend && yarn build` (then `php artisan optimize:clear`) |
| `NEXT_PUBLIC_*` (storefront) | **`next build`** | `cd frontend && NODE_OPTIONS=--max-old-space-size=3072 npm run build && pm2 restart mp-frontend` |

```bash
# backend assets (VITE_*)
cd $APP_DIR/backend && yarn install && yarn build && php artisan optimize:clear

# frontend (NEXT_PUBLIC_*)
cd $APP_DIR/frontend && npm ci && NODE_OPTIONS=--max-old-space-size=3072 npm run build
```

> Reminder: **never** `php artisan config:cache` / `php artisan optimize`. Use `php artisan optimize:clear` only.
> The key mismatch is the #1 setup error: `REVERB_APP_KEY` = `VITE_REVERB_APP_KEY` = `NEXT_PUBLIC_REVERB_APP_KEY`, and the bundles must be rebuilt after any change.

---

## 4. Reverb and the queue worker as systemd services

(Skip/`pm2 delete mp-reverb mp-queue` if you previously started them under PM2 following `docs/VPS_DEPLOYMENT.md` — run each only once.)
Use the Linux user that owns the code (`deploy`) with group `www-data` so files created in `storage/` stay writable by PHP-FPM.

`/etc/systemd/system/reverb.service`:

```ini
[Unit]
Description=Laravel Reverb WebSocket server
After=network.target mysql.service
Wants=mysql.service

[Service]
Type=simple
User=deploy
Group=www-data
UMask=0002
WorkingDirectory=/var/www/marketplace/backend
ExecStart=/usr/bin/php artisan reverb:start --host=127.0.0.1 --port=8080 --no-interaction
# Raise the open-file limit: every WebSocket connection is one file descriptor
LimitNOFILE=65535
# reverb:restart makes the process exit cleanly (code 0) -> must restart on ANY exit, not only failures
Restart=always
RestartSec=3
SyslogIdentifier=reverb
StandardOutput=journal
StandardError=journal
# Hardening (optional)
NoNewPrivileges=true
PrivateTmp=true

[Install]
WantedBy=multi-user.target
```

`/etc/systemd/system/mp-queue.service` (broadcast events of live streams are `ShouldBroadcastNow` and do not need it, but notifications, mail and jobs do):

```ini
[Unit]
Description=Laravel queue worker
After=network.target mysql.service
Wants=mysql.service

[Service]
Type=simple
User=deploy
Group=www-data
UMask=0002
WorkingDirectory=/var/www/marketplace/backend
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --timeout=90 --max-time=3600
Restart=always
RestartSec=3
SyslogIdentifier=mp-queue
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now reverb mp-queue
systemctl status reverb mp-queue --no-pager
sudo ss -ltnp | grep 8080          # must show 127.0.0.1:8080 (php)
```

- **Logs:** `journalctl -u reverb -f` and `journalctl -u mp-queue -f` (journald handles rotation). Debug mode with every message: temporarily add `--debug` to `ExecStart`.
- **After each deploy:** `php artisan reverb:restart` (graceful – Reverb drops connections, clients auto-reconnect) and `php artisan queue:restart`; or `sudo systemctl restart reverb`. Because of `Restart=always` systemd brings it back automatically.
- Scheduler cron stays as in `docs/VPS_DEPLOYMENT.md` §10.
- Check the limit applied: `cat /proc/$(systemctl show -p MainPID --value reverb)/limits | grep 'open files'` → 65535.

---

## 5. coturn (TURN/STUN) — required for mobile and corporate viewers

STUN alone fails for roughly 10–20 % of real-world peers (carrier-grade NAT, symmetric NAT, firewalls). TURN relays their media.

### 5.1 Install and enable

```bash
sudo apt install -y coturn
sudo sed -i 's/^#\?TURNSERVER_ENABLED=.*/TURNSERVER_ENABLED=1/' /etc/default/coturn   # harmless on 24.04
sudo mkdir -p /etc/coturn/certs && sudo /etc/letsencrypt/renewal-hooks/deploy/reload-services.sh   # copies certs for coturn
```

### 5.2 `/etc/turnserver.conf` (replace the whole file)

```ini
# --- Listening
listening-port=3478
tls-listening-port=5349
# listening-ip: leave unset to listen on all interfaces (works with NAT/cloud private IPs)

# --- Public address. Required behind NAT (AWS/GCP/Oracle/Hetzner Cloud with a private NIC IP):
#     external-ip=<PUBLIC_IP>/<PRIVATE_IP>      e.g. 203.0.113.10/10.0.0.5
#     If the VPS NIC already has the public IP, use the single form:
external-ip=203.0.113.10

# --- Identity
realm=example.com
server-name=ws.example.com

# --- Authentication: static long-term user (matches TURN_USERNAME / TURN_CREDENTIAL used by the apps)
lt-cred-mech
user=streamuser:PUT_A_LONG_RANDOM_PASSWORD_HERE
# (Alternative with expiring credentials: replace the two lines above with
#    use-auth-secret / static-auth-secret=<secret>
#  but the apps must then generate time-limited usernames - NOT supported by the current code. Keep static.)

# --- Relay ports (must match the UFW rule)
min-port=49152
max-port=65535

# --- TLS (copied from Let's Encrypt by the renewal hook, readable by user turnserver)
cert=/etc/coturn/certs/fullchain.pem
pkey=/etc/coturn/certs/privkey.pem
no-tlsv1
no-tlsv1_1
cipher-list="ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256"

# --- Hardening
fingerprint
no-multicast-peers
no-cli
stale-nonce=600
# Never let a TURN client reach private/internal networks through the relay (SSRF protection)
denied-peer-ip=0.0.0.0-0.255.255.255
denied-peer-ip=10.0.0.0-10.255.255.255
denied-peer-ip=100.64.0.0-100.127.255.255
denied-peer-ip=127.0.0.0-127.255.255.255
denied-peer-ip=169.254.0.0-169.254.255.255
denied-peer-ip=172.16.0.0-172.31.255.255
denied-peer-ip=192.0.0.0-192.0.0.255
denied-peer-ip=192.168.0.0-192.168.255.255
denied-peer-ip=198.18.0.0-198.19.255.255
denied-peer-ip=240.0.0.0-255.255.255.255
# If the TURN server itself sits behind a private NIC IP (NAT case above) allow its own address:
# allowed-peer-ip=10.0.0.5

# --- Logging
log-file=/var/log/turnserver/turnserver.log
simple-log
```

```bash
sudo mkdir -p /var/log/turnserver && sudo chown turnserver:turnserver /var/log/turnserver
sudo systemctl enable --now coturn
sudo systemctl restart coturn
systemctl status coturn --no-pager | head -12
sudo ss -lunp | grep -E '3478|5349'; sudo ss -ltnp | grep -E '3478|5349'
```

Certificate renewal: handled by `/etc/letsencrypt/renewal-hooks/deploy/reload-services.sh` from section 2.1 (re-copies the certificate and restarts coturn). Test it with `sudo certbot renew --dry-run`. If TLS TURN (`turns:`) suddenly stops working after ~90 days, this hook did not run or the server clock is wrong (`timedatectl` → `System clock synchronized: yes`).

Optional logrotate: `/etc/logrotate.d/turnserver` → `/var/log/turnserver/*.log { weekly rotate 4 compress missingok notifempty copytruncate }`.

### 5.3 Verify TURN

1. **Trickle ICE page** – open <https://webrtc.github.io/samples/src/content/peerconnection/trickle-ice/> and remove the default Google entry. Add:
   - `turn:ws.example.com:3478` with username `streamuser` and the password → **Gather candidates** → a row with component type **`relay`** must appear.
   - repeat with `turns:ws.example.com:5349`. Only `host`/`srflx` candidates = TURN unreachable (firewall, wrong external-ip, wrong credentials).
2. **Command line** (from another machine with `coturn` installed, or from the server itself):
   ```bash
   turnutils_uclient -v -y -u streamuser -w 'PASSWORD' -p 3478 ws.example.com        # UDP
   turnutils_uclient -v -y -t -u streamuser -w 'PASSWORD' -p 3478 ws.example.com     # TCP
   ```
   Expect "allocation success"/"Total lost packets 0". `401 Unauthorized` = wrong user/realm/password.
3. Server side: `sudo tail -f /var/log/turnserver/turnserver.log` while testing from a phone on mobile data — you should see `session ... realm <example.com> user <streamuser>: new, allocation`.

---

## 6. Next.js with PM2

```bash
cd $APP_DIR/frontend
npm ci
NODE_OPTIONS=--max-old-space-size=3072 npm run build      # reads .env.production -> NEXT_PUBLIC_* baked in
```

`$APP_DIR/ecosystem.config.cjs` (frontend only; Reverb and the queue run under systemd):

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
      autorestart: true,
    },
  ],
};
```

```bash
cd $APP_DIR
pm2 start ecosystem.config.cjs
pm2 save
pm2 startup systemd -u deploy --hp /home/deploy      # prints a "sudo env PATH=... pm2 startup ..." line: copy and run it
pm2 save
pm2 install pm2-logrotate
curl -sI http://127.0.0.1:3000 | head -1             # HTTP/1.1 200 OK
```

Changing any `NEXT_PUBLIC_*` value later = edit `.env.production` → `npm run build` → `pm2 restart mp-frontend`.

---

## 7. Deploy / update sequence for future releases

```bash
#!/usr/bin/env bash
set -euo pipefail
APP_DIR=/var/www/marketplace
cd $APP_DIR && git pull origin main

# backend
cd backend
composer install --no-dev --optimize-autoloader --no-interaction
php artisan down --retry=30 || true
trap 'php artisan up' EXIT
php artisan migrate --force
php artisan seed:run --permissions-only
yarn install && yarn build              # rebuilds admin JS incl. VITE_REVERB_* and public/js assets
php artisan optimize:clear              # NEVER config:cache / optimize
php artisan queue:restart
php artisan reverb:restart              # graceful; systemd (Restart=always) relaunches it

# frontend (skip when only backend changed)
cd ../frontend
npm ci
NODE_OPTIONS=--max-old-space-size=3072 npm run build
pm2 restart mp-frontend
```

The repo already ships `deploy/deploy.sh` with the same flow; add the `reverb:restart` line and `pm2 restart mp-frontend` only (not `mp-reverb`/`mp-queue`) if you use the systemd units. Static studio script: `backend/public/js/admin/live-stream-studio.js` is served directly by Apache — hard-refresh the admin page (Ctrl+F5) after a release because of the 30-day `Expires` rule.

---

## 8. Verification checklist

**Services**
```bash
systemctl is-active apache2 php8.3-fpm mysql reverb mp-queue coturn   # all "active"
pm2 status                                                            # mp-frontend online
sudo apache2ctl -S                                                    # lists all vhosts
sudo ss -ltnp | grep -E ':(8080|3000)\b'                              # bound to 127.0.0.1 only
```

**WebSocket endpoint** (use your real `REVERB_APP_KEY`):
```bash
# Plain GET: 400/426 (Reverb refuses non-upgrade requests) is OK; 502/503/404 from Apache is NOT
curl -sI https://ws.example.com/app/YOUR_KEY | head -1
# Real handshake: must answer "HTTP/1.1 101 Switching Protocols"
curl -si -N --max-time 3 \
  -H "Connection: Upgrade" -H "Upgrade: websocket" -H "Sec-WebSocket-Version: 13" \
  -H "Sec-WebSocket-Key: SGVsbG8sIHdvcmxkIQ==" \
  "https://ws.example.com/app/YOUR_KEY?protocol=7&client=js&version=8.4.0&flash=false" | head -5
# Interactive: expect {"event":"pusher:connection_established",...}
npx wscat -c "wss://ws.example.com/app/YOUR_KEY?protocol=7&client=js&version=8.4.0"
```

**Browser**
1. Open `https://example.com/live-streams` → DevTools → Network → **WS**: one connection to `wss://ws.example.com/app/<key>…` with status **101**; Messages tab shows `pusher:connection_established` and `pusher_internal:subscription_succeeded`.
2. Admin: `https://admin.example.com` → Live Streams → open/create a stream → studio. The padlock must be closed; the console must not show `[Echo] VITE_REVERB_APP_KEY not set`.
3. **Media modes** (grant permission when the browser prompts; check each):
   - Camera + mic → preview shows picture, level meter moves.
   - Camera only → viewers see video, no audio.
   - **Microphone only (audio-only)** → works even on a machine without a camera; viewers see the "Audio only" card and a *Tap to listen* button.
   - Screen share (pick a tab and tick "share audio" if wanted).
4. **Go live**, then open the stream on a second browser/device (`https://example.com/live-streams/<id>`): state goes *Connecting → Live*; video/audio plays (audio may need one tap because of autoplay policy). Switch mode in the studio while live → viewer follows without reload. Click **End** → viewer shows "stream ended".
5. **Mobile data test (TURN):** on a phone with Wi-Fi OFF open the stream. While connected, `sudo tail -f /var/log/turnserver/turnserver.log` should show an allocation.
6. `chrome://webrtc-internals` (admin and viewer tab): expand the `RTCPeerConnection`; `iceConnectionState` should reach `connected`/`completed`; in *Stats graphs* → `candidate-pair` with `state: succeeded` and `nominated: true` → look at its `local-candidate`/`remote-candidate` `candidateType`: `host`/`srflx` = direct P2P, **`relay` = through coturn** (expected for mobile). `inbound-rtp` (viewer) `bytesReceived`/`framesDecoded` must increase; `packetsLost` low.

Expected admin states: *Idle → Preview → Live (N viewers)*; expected viewer states: *Connecting → Live / Reconnecting → Ended*.

---

## 9. Troubleshooting

| Symptom | Likely cause | Fix |
|---|---|---|
| No camera/mic permission prompt; `navigator.mediaDevices` is `undefined` | Page served over `http://` (not a secure context) or certificate error | Use `https://admin.example.com` with a valid cert; no click-through of cert warnings |
| Prompt never appears / "Permission denied by permissions policy" | `Permissions-Policy` header missing, or overridden/blocked (`camera=()` from Cloudflare, .htaccess, middleware); page inside an iframe without `allow=` | `curl -sI https://admin.example.com/ | grep -i permissions` must show `camera=(self), microphone=(self), display-capture=(self)`; remove conflicting headers; add `allow="camera; microphone; display-capture"` to iframes |
| Browser says permission blocked | User denied once; or OS-level block | Site settings → reset Camera/Microphone; macOS/Windows privacy settings; close other apps holding the camera |
| `NotFoundError: Requested device not found` | No camera on the machine but studio asked for video | Choose **Microphone only** mode, or plug in a camera |
| `wss://ws…` returns 502/503 | Reverb not running or on another port; `proxy_wstunnel` not enabled | `systemctl status reverb`, `ss -ltnp | grep 8080`; `sudo a2enmod proxy_wstunnel && sudo systemctl restart apache2` |
| WS handshake 403 / "Origin not allowed" | `allowed_origins` in `config/reverb.php` doesn't list the page host | Add the page hosts (no scheme) or leave `['*']`; `reverb:restart` |
| WS 404 / Apache 404 on `/app/...` | `ws.` vhost not enabled, or request hits the default vhost (DNS/ServerName), or `ProxyPass` placed after a broader rule | `apache2ctl -S`; confirm `mp-ws` is enabled; keep specific `ProxyPass` lines before `/` |
| Mixed content error / socket blocked | `REVERB_SCHEME=http`, `NEXT_PUBLIC_REVERB_SCHEME=http`, or bundle built before the change | Set `https`/`443`, **rebuild** (`yarn build` + `npm run build`) |
| Echo never connects, no WS request in DevTools | Missing/placeholder `NEXT_PUBLIC_REVERB_APP_KEY` or `VITE_REVERB_APP_KEY` in the build | Fix env, rebuild, hard refresh; console shows `[Echo] ... not set` |
| Echo connects to `ws://host:8080` or `:6001` | Port/host not baked: bundle used defaults | Set `*_REVERB_PORT=443` and rebuild |
| WS connects but "Connection closed after ~60 s" | Apache `ProxyTimeout`/`Timeout` too low or an intermediate proxy (Cloudflare free plan ≈100 s idle) | Keep `ProxyTimeout 3600`; Reverb ping (60 s) keeps it alive; avoid second proxies |
| Connected socket but viewers receive no signals | Laravel can't publish to Reverb: wrong `REVERB_*`, hairpin blocked, `/apps` not proxied; or secret mismatch | `tail storage/logs/laravel-*.log`; from the VPS `curl -sI https://ws.example.com/apps/ID/events`; add `127.0.0.1 ws.example.com` to `/etc/hosts`; verify `REVERB_APP_ID/KEY/SECRET` equal everywhere; `optimize:clear` + `systemctl restart reverb` |
| Viewer sees black video but hears audio | Autoplay/`playsInline`/muted policy, or the stream is audio-only | Viewer taps the video/"Tap to listen"; check studio mode; in `webrtc-internals` `framesDecoded` = 0 means no video track was sent |
| Viewer gets audio-only stream, expected video | Studio started with *Microphone only*, or camera permission denied silently | Restart the stream with Camera + mic; check browser site settings |
| Works on LAN/Wi-Fi, fails on mobile data or other networks | No TURN, TURN credentials mismatch, UDP blocked | Section 5.3 tests; UFW `3478` & `49152:65535/udp`; same credentials in coturn/backend/frontend and **frontend rebuilt**; `external-ip` correct; Cloudflare orange cloud on `ws` |
| ICE state `failed` | Both peers failed to connect and no relay candidate | Trickle ICE page must show `relay`; check `turnserver.log` for 401; `denied-peer-ip` too strict for your NAT layout (see `allowed-peer-ip`) |
| `turns:` fails, `turn:` works | Expired/unreadable cert for coturn, clock skew, 5349 closed | `openssl s_client -connect ws.example.com:5349`; rerun the renewal hook; `timedatectl`; open 5349 tcp/udp |
| API calls blocked by CORS in the browser console | Frontend origin missing from `config/cors.php`; duplicate CORS headers from Apache | Add `https://example.com` etc. to `allowed_origins`; remove Apache CORS headers for the API; `php artisan optimize:clear` |
| Laravel pages 404 for every route (Apache default page / 404 on `/admin`) | `AllowOverride None` so `.htaccess` ignored, `mod_rewrite` off, or wrong `DocumentRoot` | `AllowOverride All` in the backend vhost, `a2enmod rewrite`, `DocumentRoot …/backend/public` |
| Laravel returns 404 on all panels from a correct vhost | `config:cache` was run or `APP_DOMAIN` wrong | `php artisan optimize:clear`; check `APP_DOMAIN=example.com`; DNS for the sub-domain exists |
| `403 Forbidden` on backend files | Missing `Require all granted`, or permissions on `/var/www/...` (needs `x` for `www-data`) | Fix the `<Directory>` block; `namei -l $APP_DIR/backend/public/index.php` |
| `The Authorization header is missing` / Sanctum 401 | Header stripped by FastCGI | Keep the `SetEnvIfNoCase ^Authorization$` line in the backend vhost |
| `/storage/...` images 404 on the storefront | `Alias /storage/` missing or `ProxyPass /storage/ !` placed after `ProxyPass /` | Keep the alias + exclusion before the catch-all `ProxyPass`; `php artisan storage:link` |
| Reverb refuses new connections at some point ("too many open files", ~1000 clients) | `LimitNOFILE` too low or `stream_select` 1024 limit | `LimitNOFILE=65535` in the unit (section 4) and install `ext-uv`; set `REVERB_APP_MAX_CONNECTIONS` only if you want a deliberate cap |
| After a deploy WebSocket stuck / old code running | Reverb or queue process kept old code | `php artisan reverb:restart`, `queue:restart`; confirm `Restart=always` |
| Frontend still uses old keys/URLs | `NEXT_PUBLIC_*` edited without rebuilding | `npm run build` + `pm2 restart mp-frontend` |
| 502 on `https://example.com` | Next.js not running | `pm2 logs mp-frontend`, `pm2 restart mp-frontend` |
| `AH01144: No protocol handler` / `AH00898` in Apache log | Missing proxy module (`proxy_http`, `proxy_wstunnel`, `proxy_fcgi`) | `a2enmod` the missing one; restart Apache |
| Certificate for one subdomain missing (`NET::ERR_CERT_COMMON_NAME_INVALID`) | Hostname not included in the SAN list | Re-run the `certbot certonly ... --cert-name example.com -d ...` with the full `-d` list (adds names), reload Apache |
| `H2`/WebSocket oddities on ws host | HTTP/2 negotiated for the socket vhost | Keep `Protocols http/1.1` in `mp-ws.conf` |

---

## 10. Optional: scaling beyond ~30–50 viewers

The current design is a **P2P mesh**: the admin's browser encodes and uploads one separate stream per viewer. Upload bandwidth (≈ 0.5–1.5 Mbit/s × viewers) and CPU of the broadcaster are the limit, typically 30–50 viewers at 720p, fewer at 1080p. TURN relays additionally load the VPS bandwidth (size the server's network for `viewers × bitrate` when many viewers are relayed).

Beyond that, switch the media path to an **SFU** (Selective Forwarding Unit) such as **LiveKit** (self-hosted, Apache 2.0) or mediasoup/Janus: the admin publishes **one** stream to the SFU, which fans it out to hundreds or thousands of viewers. Reverb keeps its role for chat/likes/notifications; LiveKit replaces only the WebRTC signalling+media. It brings its own server (UDP 7882 / TCP 7881 / WSS via Apache `lk.example.com` proxy, its built-in TURN), a token endpoint in Laravel and a client SDK on both sides. For one-to-many audiences in the thousands, HLS (RTMP ingest + CDN) is the alternative at the cost of 5–30 s latency.
