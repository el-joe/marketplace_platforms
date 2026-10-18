# Live Streams (WebRTC) on Ubuntu + nginx — Production Runbook

Target: Ubuntu 22.04 / 24.04, PHP 8.3 (PHP-FPM), nginx, MySQL, Node.js LTS. Goal: admin studio (`admin.example.com/.../live-streams`) and the Next.js `/live-streams` pages work with WebRTC **audio only, video only, or both**, also for viewers on mobile data.

Placeholders used everywhere: `example.com` (your domain), `203.0.113.10` (server public IP), `deploy` (Linux user that owns the code), `/var/www/marketplace` (app dir; contains `backend/` and `frontend/`). Replace them consistently.

> **Apache vs nginx:** this is the **nginx** twin of [`docs/LIVE_STREAMS_VPS_APACHE.md`](LIVE_STREAMS_VPS_APACHE.md). Both guides are structured identically (sections 0–10) and share the same `.env` values, Reverb/queue systemd units, coturn config, PM2 setup and verification steps; only the web server parts (packages, virtual hosts/server blocks, TLS wiring, troubleshooting rows) differ. Pick **one** web server per machine — never run both on ports 80/443.
> This guide extends the nginx sites of `docs/VPS_DEPLOYMENT.md` (§11/12) with HTTPS, WebSocket proxying and the live-stream specifics. Do the generic parts of that guide (users, MySQL, code checkout, `.env`, seeding) first; use this document for the web server, Reverb, TURN and Next.js. Where it differs from `VPS_DEPLOYMENT.md` (Reverb under systemd instead of PM2, explicit TLS blocks instead of letting certbot edit the files), this document wins for live streams.
> Hard rule from that guide: **never run `php artisan config:cache` / `optimize`** — `routes/web.php` calls `env('APP_DOMAIN')` directly and every panel would 404. Only use `php artisan optimize:clear`.
> Rule for every config change in this guide: **`sudo nginx -t` first, reload only if it prints `syntax is ok` and `test is successful`.**

---

## 0. Architecture, DNS, ports

```
 Admin browser (broadcaster)                     Viewer browsers (Next.js /live-streams)
   │  HTTPS  admin.example.com                      │  HTTPS  example.com  /  api.example.com
   │  WSS    ws.example.com ─┐              ┌───────┘  WSS    ws.example.com
   ▼                         ▼              ▼
 ┌───────────────────────────── nginx :443 (TLS, HTTP/2) ─────────────────────────────┐
 │ admin./api./partner./...  → PHP-FPM (Laravel, backend/public)                       │
 │ example.com, www          → 127.0.0.1:3000 (Next.js, PM2)   + /storage/ alias       │
 │ ws.example.com            → 127.0.0.1:8080 (Laravel Reverb, systemd)                │
 └─────────────────────────────────────────────────────────────────────────────────────┘
        Signalling (SDP offer/answer, ICE) = Reverb WebSocket + REST  → via nginx
        Media (audio/video) = WebRTC peer-to-peer (UDP/SRTP)
              └─ if P2P is impossible (mobile CGNAT, corporate firewall) → coturn relay
                 ws.example.com:3478 / :5349  (UDP+TCP, relay ports 49152-65535/udp)
```

Media never goes through nginx. nginx only carries signalling. Topology is a P2P mesh (admin → one connection per viewer), good for roughly 30–50 concurrent viewers (see section 10).

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
| 80 | TCP | yes | nginx | ACME challenge + redirect to HTTPS |
| 443 | TCP | yes | nginx | all sites + `wss://ws.example.com` |
| 3478 | UDP + TCP | yes | coturn | STUN/TURN |
| 5349 | TCP (+UDP) | yes | coturn | TURN over TLS (`turns:`) – gets through strict firewalls |
| 49152–65535 | UDP | yes | coturn | relay allocation range |
| 8080 | TCP | **no** (127.0.0.1) | Reverb | only nginx talks to it |
| 3000 | TCP | **no** (127.0.0.1) | Next.js | only nginx talks to it |
| 3306 | TCP | **no** | MySQL | |

### 0.3 Why HTTPS is mandatory

`navigator.mediaDevices.getUserMedia()` (camera/mic) and `getDisplayMedia()` (screen share) exist **only in a secure context** (HTTPS, or `localhost`). On plain `http://admin.example.com` the browser exposes `navigator.mediaDevices === undefined` and no permission prompt ever appears. Also, a page served over HTTPS may only open `wss://` (never `ws://`) sockets — mixed content is blocked. So: valid TLS certificates on every host, `REVERB_SCHEME=https`, port 443.

---

## 1. Install packages

```bash
sudo apt update && sudo apt -y upgrade

# nginx + PHP-FPM + extensions Laravel needs
sudo apt install -y nginx php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml \
  php8.3-curl php8.3-zip php8.3-gd php8.3-intl php8.3-bcmath php8.3-opcache \
  unzip git curl acl ufw coturn certbot

# Optional but recommended if you expect >1000 simultaneous WebSocket connections
# (Reverb's default loop is limited to 1024 file descriptors by stream_select)
sudo apt install -y php8.3-dev php-pear libuv1-dev && sudo pecl install uv   # then add extension=uv.so to /etc/php/8.3/cli/conf.d/20-uv.ini
```

> Ubuntu 22.04 does not ship PHP 8.3 — add `sudo add-apt-repository ppa:ondrej/php` first. 24.04 has it natively.
> Certbot: we use `certbot` with the **webroot** method below (works with server blocks you write by hand, and keeps certbot from rewriting your files). If you prefer the nginx plugin: `sudo apt install python3-certbot-nginx` (see the note in 2.1).
> Ubuntu ships nginx 1.18 (22.04) / 1.24 (24.04). Both support everything below; HTTP/2 is enabled with `listen 443 ssl http2;` (on nginx ≥ 1.25.1 this prints a harmless "directive is deprecated" warning; there you may use a separate `http2 on;` line instead).

Make sure nothing else owns ports 80/443 and nginx has HTTP/2 compiled in:

```bash
sudo systemctl disable --now apache2 2>/dev/null || true    # only if apache2 got installed
sudo systemctl enable --now nginx php8.3-fpm
nginx -v && nginx -V 2>&1 | grep -o with-http_v2_module     # must print the module name
ls /run/php/php8.3-fpm.sock                                  # the socket the fastcgi_pass lines use
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
sudo ufw allow 'Nginx Full'                  # 80 + 443 (if the profile is missing: sudo ufw allow 80,443/tcp)
sudo ufw allow 3478/tcp && sudo ufw allow 3478/udp
sudo ufw allow 5349/tcp && sudo ufw allow 5349/udp
sudo ufw allow 49152:65535/udp               # TURN relay range
sudo ufw enable && sudo ufw status verbose
```

Do NOT open 8080, 3000, 3306.

---

## 2. nginx server blocks and certificates

Set variables once per SSH session:

```bash
export DOMAIN=example.com
export APP_DIR=/var/www/marketplace
```

### 2.1 Step A — global nginx settings, HTTP bootstrap block, one certificate for every hostname

**(a) Worker limits.** Every proxied WebSocket costs nginx **two** file descriptors/connections (client side + Reverb side). Raise the limits once (edit `/etc/nginx/nginx.conf`; `worker_rlimit_nofile` belongs to the main context, `worker_connections` to the `events {}` block). Run this **once** (re-running would add the line twice and nginx would refuse to start):

```bash
grep -q worker_rlimit_nofile /etc/nginx/nginx.conf || sudo sed -i '1i worker_rlimit_nofile 65535;' /etc/nginx/nginx.conf
sudo sed -i 's/^\(\s*\)worker_connections .*/\1worker_connections 8192;/' /etc/nginx/nginx.conf
grep -nE 'worker_rlimit_nofile|worker_connections|worker_processes' /etc/nginx/nginx.conf
```

**(b) Shared http-context settings.** `/etc/nginx/conf.d/mp-http.conf` (files in `conf.d/` are included inside `http {}`, which is exactly where `map` is valid):

```nginx
# Maps the client's Upgrade header to the Connection header sent to Reverb / Next.js.
# WebSocket handshake (Upgrade: websocket) -> "upgrade"; any normal request -> "close".
map $http_upgrade $connection_upgrade {
    default upgrade;
    ''      close;
}

ssl_session_cache   shared:SSL:10m;
ssl_session_timeout 1d;
```

Shared TLS snippet `/etc/nginx/snippets/mp-ssl.conf` (one certificate covers all hosts, so every block includes the same file):

```nginx
ssl_certificate     /etc/letsencrypt/live/example.com/fullchain.pem;
ssl_certificate_key /etc/letsencrypt/live/example.com/privkey.pem;
ssl_protocols       TLSv1.2 TLSv1.3;
ssl_prefer_server_ciphers off;
ssl_session_tickets off;
```

```bash
sudo tee /etc/nginx/conf.d/mp-http.conf >/dev/null <<'EOF'
map $http_upgrade $connection_upgrade {
    default upgrade;
    ''      close;
}

ssl_session_cache   shared:SSL:10m;
ssl_session_timeout 1d;
EOF

sudo tee /etc/nginx/snippets/mp-ssl.conf >/dev/null <<'EOF'
ssl_certificate     /etc/letsencrypt/live/example.com/fullchain.pem;
ssl_certificate_key /etc/letsencrypt/live/example.com/privkey.pem;
ssl_protocols       TLSv1.2 TLSv1.3;
ssl_prefer_server_ciphers off;
ssl_session_tickets off;
EOF
sudo sed -i "s/example\.com/$DOMAIN/g" /etc/nginx/snippets/mp-ssl.conf
```

**(c) HTTP bootstrap block.** Create the ACME webroot and a port-80 block that answers Let's Encrypt challenges and redirects everything else to HTTPS (`/etc/nginx/sites-available/00-http-redirect.conf`):

```nginx
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name example.com www.example.com api.example.com admin.example.com partner.example.com
                portal.example.com marketer.example.com delivery.example.com
                carrier.example.com travel-agency.example.com ws.example.com;

    location ^~ /.well-known/acme-challenge/ {
        root /var/www/letsencrypt;
        default_type "text/plain";
    }

    location / {
        return 301 https://$host$request_uri;
    }

    access_log /var/log/nginx/http-redirect.access.log;
    error_log  /var/log/nginx/http-redirect.error.log;
}
```

```bash
sudo mkdir -p /var/www/letsencrypt
sudo tee /etc/nginx/sites-available/00-http-redirect.conf >/dev/null <<'EOF'
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name example.com www.example.com api.example.com admin.example.com partner.example.com
                portal.example.com marketer.example.com delivery.example.com
                carrier.example.com travel-agency.example.com ws.example.com;

    location ^~ /.well-known/acme-challenge/ {
        root /var/www/letsencrypt;
        default_type "text/plain";
    }

    location / {
        return 301 https://$host$request_uri;
    }

    access_log /var/log/nginx/http-redirect.access.log;
    error_log  /var/log/nginx/http-redirect.error.log;
}
EOF
sudo sed -i "s/example\.com/$DOMAIN/g" /etc/nginx/sites-available/00-http-redirect.conf
sudo rm -f /etc/nginx/sites-enabled/default
sudo ln -sf /etc/nginx/sites-available/00-http-redirect.conf /etc/nginx/sites-enabled/00-http-redirect.conf
sudo nginx -t && sudo systemctl reload nginx     # must print "syntax is ok" / "test is successful"
```

(Do not enable the three TLS site files of 2.2–2.4 yet: they reference a certificate that does not exist until the next step, and `nginx -t` would fail with `cannot load certificate`.)

Issue **one** certificate covering all names (lineage name = `$DOMAIN`, files in `/etc/letsencrypt/live/$DOMAIN/`):

```bash
sudo certbot certonly --webroot -w /var/www/letsencrypt \
  --cert-name $DOMAIN --agree-tos -m you@example.com --no-eff-email \
  -d $DOMAIN -d www.$DOMAIN -d api.$DOMAIN -d admin.$DOMAIN -d partner.$DOMAIN \
  -d portal.$DOMAIN -d marketer.$DOMAIN -d delivery.$DOMAIN -d carrier.$DOMAIN \
  -d travel-agency.$DOMAIN -d ws.$DOMAIN
sudo certbot renew --dry-run          # auto-renew timer (certbot.timer) is installed by the package
```

> **Alternative – nginx plugin:** if you prefer certbot to edit the server blocks itself (as `docs/VPS_DEPLOYMENT.md` §13 does), skip the TLS parts of 2.2–2.4 (keep only `listen 80;` blocks), then run
> `sudo certbot --nginx --cert-name $DOMAIN -d $DOMAIN -d www.$DOMAIN -d api.$DOMAIN -d admin.$DOMAIN -d partner.$DOMAIN -d portal.$DOMAIN -d marketer.$DOMAIN -d delivery.$DOMAIN -d carrier.$DOMAIN -d travel-agency.$DOMAIN -d ws.$DOMAIN --redirect`
> and afterwards add the `Permissions-Policy` header, the `map`, the long timeouts and `proxy_buffering off` of this guide by hand, followed by `sudo nginx -t && sudo systemctl reload nginx`. The hand-written webroot variant below is easier to keep correct.

Add a renewal hook that reloads nginx and refreshes coturn's copy of the certificate (coturn runs as another user and cannot read `/etc/letsencrypt` directly):

```bash
sudo mkdir -p /etc/coturn/certs
sudo tee /etc/letsencrypt/renewal-hooks/deploy/reload-services.sh >/dev/null <<'EOF'
#!/bin/bash
# Runs after every successful renewal.
LIVE=/etc/letsencrypt/live/example.com
install -o turnserver -g turnserver -m 640 "$LIVE/fullchain.pem" /etc/coturn/certs/fullchain.pem
install -o turnserver -g turnserver -m 640 "$LIVE/privkey.pem"   /etc/coturn/certs/privkey.pem
nginx -t && systemctl reload nginx
systemctl restart coturn
EOF
sudo sed -i "s/example\.com/$DOMAIN/" /etc/letsencrypt/renewal-hooks/deploy/reload-services.sh
sudo chmod +x /etc/letsencrypt/renewal-hooks/deploy/reload-services.sh
sudo /etc/letsencrypt/renewal-hooks/deploy/reload-services.sh || true   # coturn not installed/configured yet is fine; re-run after section 5
```

### 2.2 Step B — Laravel server block: API + ALL panels (admin, partner, portal, marketer, delivery, carrier, travel-agency)

All these hostnames are the **same** Laravel app; `routes/web.php` picks the panel from the Host header, so they share one server block. `/etc/nginx/sites-available/mp-backend.conf`:

```nginx
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name admin.example.com api.example.com partner.example.com portal.example.com
                marketer.example.com delivery.example.com carrier.example.com
                travel-agency.example.com;

    include snippets/mp-ssl.conf;

    root /var/www/marketplace/backend/public;
    index index.php;
    charset utf-8;
    client_max_body_size 64M;      # uploads (also set upload_max_filesize/post_max_size in php.ini)

    # ---- Headers -------------------------------------------------------------
    # REQUIRED for the live-stream studio: allow camera, microphone and screen capture on this origin.
    add_header Permissions-Policy "camera=(self), microphone=(self), display-capture=(self)" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header Strict-Transport-Security "max-age=31536000" always;
    # NOTE: add_header lines are inherited by a location ONLY if that location has no add_header of its own.
    # Do NOT add a Content-Security-Policy unless it allows: connect-src wss://ws.example.com https://api.example.com,
    # media-src blob: mediastream:, img-src data: blob:, script-src for your CDNs. A too-strict CSP silently breaks Echo and the preview.
    # Do NOT send "Feature-Policy" or "Permissions-Policy: camera=()" anywhere (including PHP middleware or Cloudflare rules).

    gzip on;
    gzip_vary on;
    gzip_comp_level 5;
    gzip_min_length 1024;
    gzip_types text/css application/javascript application/json image/svg+xml text/plain text/xml;

    # Laravel front controller
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP via FPM
    location ~ \.php$ {
        try_files $uri =404;                                 # never hand a non-existent script to FPM
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;                              # also forwards HTTPS=on (via $https) and every request header, incl. Authorization (Sanctum bearer tokens)
        fastcgi_param HTTP_PROXY "";                         # httpoxy mitigation
        fastcgi_read_timeout 120s;
        fastcgi_buffers 16 16k;
        fastcgi_buffer_size 32k;
    }

    # Never serve dotfiles (except ACME)
    location ~ /\.(?!well-known).* { deny all; }

    # Static assets (includes public/js/admin/live-stream-studio.js). No add_header here so the server-level security headers stay inherited.
    location ~* \.(?:css|js|woff2?|ttf|svg|png|jpe?g|gif|webp|ico)$ {
        expires 30d;
        access_log off;
        try_files $uri =404;
    }

    access_log /var/log/nginx/mp-backend.access.log;
    error_log  /var/log/nginx/mp-backend.error.log;
}
```

Notes:
- The `Permissions-Policy` header is only needed on the **page that calls getUserMedia** (admin studio). It is harmless on the others. Viewers never use camera/mic. `always` makes nginx send it on error responses as well.
- If the studio is ever embedded in an `<iframe>`, the parent must add `allow="camera; microphone; display-capture"`.
- CORS for the API is handled by Laravel (`backend/config/cors.php`), **not** by nginx — see 2.6.
- `try_files $uri $uri/ /index.php?$query_string;` is what makes every Laravel route work; there is no `.htaccess` in nginx (the one in `public/` is ignored).

### 2.3 Step C — Next.js storefront server block (reverse proxy to 127.0.0.1:3000)

`/etc/nginx/sites-available/mp-frontend.conf`:

```nginx
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name example.com www.example.com;

    include snippets/mp-ssl.conf;

    client_max_body_size 64M;

    add_header Strict-Transport-Security "max-age=31536000" always;
    add_header X-Content-Type-Options "nosniff" always;

    gzip on;
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 5;
    gzip_min_length 1024;
    gzip_types text/css application/javascript application/json image/svg+xml text/plain text/xml;

    # Uploaded files referenced as /storage/... by the API are served straight from Laravel's public disk.
    # (The prefix location wins over "location /" below, so no ordering trick is needed - unlike Apache's ProxyPass.)
    location /storage/ {
        alias /var/www/marketplace/backend/storage/app/public/;
        expires 30d;
        access_log off;
        # A location with its own add_header does NOT inherit the server-level ones -> repeat them here
        add_header Access-Control-Allow-Origin "*" always;
        add_header Strict-Transport-Security "max-age=31536000" always;
        add_header X-Content-Type-Options "nosniff" always;
    }

    location / {
        proxy_pass http://127.0.0.1:3000;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Forwarded-Port 443;
        # (Optional) WebSocket upgrade passthrough; production Next.js does not need HMR sockets, harmless if unused
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection $connection_upgrade;
        proxy_connect_timeout 10s;
        proxy_read_timeout 120s;
        proxy_send_timeout 120s;
    }

    access_log /var/log/nginx/mp-frontend.access.log;
    error_log  /var/log/nginx/mp-frontend.error.log;
}
```

`www-data` must be able to traverse `/var/www/marketplace/backend/storage/app/public` (execute bit on every parent directory) — see 2.5.

### 2.4 Step D — `ws.example.com`: WSS → Reverb (127.0.0.1:8080)

`/etc/nginx/sites-available/mp-ws.conf`:

```nginx
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name ws.example.com;

    include snippets/mp-ssl.conf;

    # WebSocket upgrades are HTTP/1.1. Browsers always open wss:// sockets with ALPN "http/1.1" (never h2), so sharing
    # the h2-enabled :443 socket with the other server blocks is fine. Do NOT enable gzip on this host (no gzip directives = off for ws frames).
    gzip off;

    add_header Strict-Transport-Security "max-age=31536000" always;

    # Reverb's HTTP events API (/apps/<id>/events, called server-to-server by Laravel) carries the event payload
    # (signalling messages: SDP up to a few KB). Reverb's own cap is max_message_size = 10 000 bytes; 1m is a safe ceiling.
    client_max_body_size 1m;

    location / {
        proxy_pass http://127.0.0.1:8080;

        proxy_http_version 1.1;                                  # REQUIRED for Upgrade
        proxy_set_header Upgrade $http_upgrade;                  # REQUIRED for Upgrade
        proxy_set_header Connection $connection_upgrade;         # needs the "map" from /etc/nginx/conf.d/mp-http.conf (2.1)

        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;

        # Long-lived sockets: do not let nginx cut idle connections (default proxy_read_timeout is 60 s; Reverb pings every ~60 s)
        proxy_connect_timeout 10s;
        proxy_read_timeout 3600s;
        proxy_send_timeout 3600s;

        proxy_buffering off;                                     # push frames immediately
        proxy_request_buffering off;
    }

    # No CORS headers needed: browsers open WebSockets (not subject to CORS) and the
    # events API is called server-to-server by Laravel.

    access_log /var/log/nginx/mp-ws.access.log;
    error_log  /var/log/nginx/mp-ws.error.log;
}
```

How the paths are handled: a single `location /` forwards everything, so all of Reverb's endpoints work through it — `/app/{key}` (client WebSocket handshake; plain non-upgrade requests are refused by Reverb with 4xx, which is expected), `/apps/{app_id}/events` (Laravel → Reverb broadcasting API, POST, signed) and `/up` (health). Because `Connection` comes from the `map`, normal POSTs to `/apps/...` get `Connection: close` and WebSocket handshakes get `Connection: upgrade` from the same block.

> **Important – hairpin:** Laravel publishes events by POSTing to `https://ws.example.com/apps/<id>/events` (it builds the URL from `REVERB_HOST/PORT/SCHEME`). So the server itself must be able to reach its own public name. Verify: `curl -sI https://ws.example.com/ | head -1` **from the VPS**. If your provider blocks hairpin NAT, add `127.0.0.1 ws.example.com` to `/etc/hosts` (nginx answers locally; TURN on the same name is unaffected for remote clients).

### 2.5 Enable everything

```bash
for f in mp-backend mp-frontend mp-ws; do sudo sed -i "s/example\.com/$DOMAIN/g; s#/var/www/marketplace#$APP_DIR#g" /etc/nginx/sites-available/$f.conf; done
for f in mp-backend mp-frontend mp-ws; do sudo ln -sf /etc/nginx/sites-available/$f.conf /etc/nginx/sites-enabled/$f.conf; done
sudo nginx -t                      # must print "syntax is ok" and "test is successful"
sudo systemctl reload nginx
sudo nginx -T 2>/dev/null | grep -E 'server_name|listen|connection_upgrade'   # sanity: all blocks + the map are loaded
```

(The `00-http-redirect.conf` from step A stays enabled and keeps redirecting port 80. If you need the hostname list changed later, edit it and the certificate's `-d` list together.) Permissions for Laravel must allow PHP-FPM (`www-data`, which is also the nginx worker user) to write: `sudo chown -R deploy:www-data $APP_DIR/backend/storage $APP_DIR/backend/bootstrap/cache && sudo chmod -R ug+rwX,o-rwx $APP_DIR/backend/storage $APP_DIR/backend/bootstrap/cache` and setgid on directories (`find $APP_DIR/backend/storage $APP_DIR/backend/bootstrap/cache -type d -exec chmod 2775 {} +`), exactly as in `docs/VPS_DEPLOYMENT.md` §8.5. Then `php artisan storage:link`.

### 2.6 CORS notes (frontend domain → API domain)

- The storefront (`https://example.com`) calls `https://api.example.com/api/...` from the browser, so Laravel must allow that origin. `backend/config/cors.php` has a **hard-coded** `allowed_origins` list (nawyweb.com / noon.codefanz.com). **Add** `https://example.com`, `https://www.example.com`, `https://admin.example.com`, `https://api.example.com` to it (this file is not affected by the "no config:cache" rule). `supports_credentials` is already `true`.
- WebSocket origin check: `config/reverb.php` → `apps.apps[0].allowed_origins` is `['*']` (works). Optional hardening: set it to the hosts only, no scheme: `['example.com', 'www.example.com', 'admin.example.com']`, then `php artisan reverb:restart`.
- Do **not** also add `Access-Control-Allow-Origin` in nginx for the API – duplicated CORS headers make browsers reject the response. (The `/storage/` location in 2.3 is the only place nginx adds it, on purpose, for static images.)
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
REVERB_SERVER_HOST=127.0.0.1               # where the reverb process LISTENS (never 0.0.0.0 behind nginx)
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
- **After each deploy:** `php artisan reverb:restart` (graceful – Reverb drops connections, clients auto-reconnect) and `php artisan queue:restart`; or `sudo systemctl restart reverb`. Because of `Restart=always` systemd brings it back automatically. nginx needs no reload for this: it reconnects to 127.0.0.1:8080 on the next request.
- Scheduler cron stays as in `docs/VPS_DEPLOYMENT.md` §10.
- Check the limit applied: `cat /proc/$(systemctl show -p MainPID --value reverb)/limits | grep 'open files'` → 65535. The same check for nginx workers: `cat /proc/$(pgrep -f 'nginx: worker' | head -1)/limits | grep 'open files'` → 65535 (from `worker_rlimit_nofile`, section 2.1).

---

## 5. coturn (TURN/STUN) — required for mobile and corporate viewers

STUN alone fails for roughly 10–20 % of real-world peers (carrier-grade NAT, symmetric NAT, firewalls). TURN relays their media. coturn is independent of the web server: this section is identical to the Apache guide except that the renewal hook reloads nginx.

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

Certificate renewal: handled by `/etc/letsencrypt/renewal-hooks/deploy/reload-services.sh` from section 2.1 (re-copies the certificate, runs `nginx -t && systemctl reload nginx`, restarts coturn). Test it with `sudo certbot renew --dry-run`. If TLS TURN (`turns:`) suddenly stops working after ~90 days, this hook did not run or the server clock is wrong (`timedatectl` → `System clock synchronized: yes`).

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

nginx itself needs **no** action for a normal code release (PHP-FPM picks up new PHP files; opcache revalidates, or run `sudo systemctl reload php8.3-fpm` if `opcache.validate_timestamps=0`). Only when you changed a file under `/etc/nginx/`:

```bash
sudo nginx -t && sudo systemctl reload nginx     # reload is graceful: open WebSockets on the old workers drain, new ones use the new config
```

The repo already ships `deploy/deploy.sh` with the same flow; add the `reverb:restart` line and `pm2 restart mp-frontend` only (not `mp-reverb`/`mp-queue`) if you use the systemd units. Static studio script: `backend/public/js/admin/live-stream-studio.js` is served directly by nginx — hard-refresh the admin page (Ctrl+F5) after a release because of the 30-day `expires` rule.

---

## 8. Verification checklist

**Services**
```bash
sudo nginx -t                                                         # syntax is ok / test is successful
systemctl is-active nginx php8.3-fpm mysql reverb mp-queue coturn     # all "active"
pm2 status                                                            # mp-frontend online
sudo nginx -T 2>/dev/null | grep -E '^\s*server_name'                 # lists all server blocks / hostnames
sudo ss -ltnp | grep -E ':(8080|3000)\b'                              # bound to 127.0.0.1 only
sudo ss -ltnp | grep -E ':(80|443)\b'                                 # nginx on 0.0.0.0 and [::]
curl -sI http://example.com | head -3                                 # 301 -> https://example.com/
curl -sI https://admin.example.com/ | grep -i -E 'HTTP/|permissions-policy'   # camera=(self), microphone=(self), display-capture=(self)
```

**WebSocket endpoint** (use your real `REVERB_APP_KEY`):
```bash
# Plain GET: 400/426 (Reverb refuses non-upgrade requests) is OK; 502/504/404 from nginx is NOT
curl -sI https://ws.example.com/app/YOUR_KEY | head -1
# Real handshake (force HTTP/1.1, as browsers do for WebSockets): must answer "HTTP/1.1 101 Switching Protocols"
curl --http1.1 -si -N --max-time 3 \
  -H "Connection: Upgrade" -H "Upgrade: websocket" -H "Sec-WebSocket-Version: 13" \
  -H "Sec-WebSocket-Key: SGVsbG8sIHdvcmxkIQ==" \
  "https://ws.example.com/app/YOUR_KEY?protocol=7&client=js&version=8.4.0&flash=false" | head -5
# Interactive: expect {"event":"pusher:connection_established",...}
npx wscat -c "wss://ws.example.com/app/YOUR_KEY?protocol=7&client=js&version=8.4.0"
# Server-side check that Laravel can publish (hairpin): any HTTP status except 000/502/504 proves nginx -> Reverb works (401/404 is fine without a signature)
curl -s -o /dev/null -w '%{http_code}\n' -X POST https://ws.example.com/apps/YOUR_APP_ID/events
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
7. **Idle-socket test:** keep a viewer tab open and idle for 5+ minutes; the WS must still be connected (no reconnect in the Network → WS frames list). A drop around 60 s means `proxy_read_timeout` is still the default somewhere.

Expected admin states: *Idle → Preview → Live (N viewers)*; expected viewer states: *Connecting → Live / Reconnecting → Ended*.

**Logs while testing:** `sudo tail -f /var/log/nginx/mp-ws.error.log /var/log/nginx/mp-ws.access.log` (the WS handshake shows as `GET /app/<key> ... 101`), `/var/log/nginx/mp-backend.error.log` (PHP/FPM problems), `/var/log/nginx/error.log` (global/startup problems), `backend/storage/logs/laravel-*.log`, `journalctl -u reverb -f`.

---

## 9. Troubleshooting

| Symptom | Likely cause | Fix |
|---|---|---|
| No camera/mic permission prompt; `navigator.mediaDevices` is `undefined` | Page served over `http://` (not a secure context) or certificate error | Use `https://admin.example.com` with a valid cert; no click-through of cert warnings |
| Prompt never appears / "Permission denied by permissions policy" | `Permissions-Policy` header missing, or overridden/blocked (`camera=()` from Cloudflare, PHP middleware, a second `add_header` set in a nested `location`); page inside an iframe without `allow=` | `curl -sI https://admin.example.com/ \| grep -i permissions` must show `camera=(self), microphone=(self), display-capture=(self)`; remove conflicting headers; add `allow="camera; microphone; display-capture"` to iframes |
| Header present on `/` but missing on some URLs | A `location` that defines its own `add_header` drops all inherited server-level `add_header`s | Repeat the headers in that location, or keep `add_header` out of locations (the backend static-asset location intentionally has none) |
| Browser says permission blocked | User denied once; or OS-level block | Site settings → reset Camera/Microphone; macOS/Windows privacy settings; close other apps holding the camera |
| `NotFoundError: Requested device not found` | No camera on the machine but studio asked for video | Choose **Microphone only** mode, or plug in a camera |
| `wss://ws…` returns 502 | Reverb not running or listening on another port; nginx cannot connect to the upstream (`connect() failed (111: Connection refused)` in `mp-ws.error.log`) | `systemctl status reverb`, `ss -ltnp \| grep 8080`; `journalctl -u reverb -n 50`; `proxy_pass` must be `http://127.0.0.1:8080` |
| `wss://ws…` returns 504 or the handshake hangs | Reverb stuck/overloaded, or firewall/loopback problem; `upstream timed out (110)` in the log | `sudo systemctl restart reverb`; `curl -sI http://127.0.0.1:8080/up`; check `LimitNOFILE` and CPU |
| Handshake answered with 400/426 or the socket closes immediately (no `101`) | `Upgrade`/`Connection` headers not forwarded: missing `proxy_http_version 1.1;`, missing `proxy_set_header Upgrade $http_upgrade;`, or `Connection` not set (nginx then sends `Connection: close`) | Use exactly the three lines from 2.4; `sudo nginx -T \| grep -A12 'server_name ws'`; test with the `curl --http1.1` handshake of section 8 |
| `nginx: [emerg] unknown "connection_upgrade" variable` | The `map $http_upgrade $connection_upgrade {…}` block is missing or not in `http` context (e.g. placed inside `server {}`) | Create `/etc/nginx/conf.d/mp-http.conf` (2.1), then `sudo nginx -t && sudo systemctl reload nginx`. It must be in `conf.d/` (http context), not in `sites-available` inside a `server` block |
| `[error] upstream sent no valid HTTP/1.0 header while reading response header from upstream` (nginx words it this way for any non-HTTP reply, also on HTTP/1.1) | `proxy_pass` points to something that does not speak plain HTTP: a TLS port (`http://` to an `https` upstream), the wrong port, or another daemon on 8080/3000 | `sudo ss -ltnp \| grep -E ':(8080\|3000)\b'` must show Reverb (php) / node; keep `proxy_pass http://127.0.0.1:8080;` (no `https`); stop the stray service |
| `upstream prematurely closed connection while reading response header` | Reverb crashed/restarted (`reverb:restart`), or the origin check rejected it | `journalctl -u reverb`; after deploy it recovers by itself because of `Restart=always` |
| WS connects but is dropped after ~60 s of silence (`upstream timed out (110: Connection timed out) while proxying upgraded connection`) | `proxy_read_timeout` is the 60 s default (missing in the `ws` block), or an intermediate proxy (Cloudflare free plan ≈100 s idle) | Keep `proxy_read_timeout 3600s; proxy_send_timeout 3600s;` in `location /` of `mp-ws.conf`; Reverb ping (60 s) keeps it alive; avoid second proxies |
| `worker_connections are not enough` in `/var/log/nginx/error.log`; new sockets refused under load | Each proxied WebSocket uses 2 nginx connections; default 768 is far too low | `worker_connections 8192;` in `events {}` of `nginx.conf` (2.1), `nginx -t`, reload (a full `restart` is needed only to change `worker_rlimit_nofile`) |
| `socket() failed (24: Too many open files)` / `accept4() failed (24: Too many open files)` | nginx file-descriptor limit hit | `worker_rlimit_nofile 65535;` in the main context of `nginx.conf`, `sudo systemctl restart nginx`, verify with `cat /proc/<worker-pid>/limits` |
| WS handshake 403 / "Origin not allowed" | `allowed_origins` in `config/reverb.php` doesn't list the page host | Add the page hosts (no scheme) or leave `['*']`; `reverb:restart` |
| WS 404 / nginx 404 on `/app/...` | Request hits another server block (`ws.` DNS missing, `server_name` typo, block not symlinked into `sites-enabled`) | `sudo nginx -T \| grep -E 'server_name'`; `ls -l /etc/nginx/sites-enabled`; `curl -sI --resolve ws.example.com:443:127.0.0.1 https://ws.example.com/` |
| Mixed content error / socket blocked | `REVERB_SCHEME=http`, `NEXT_PUBLIC_REVERB_SCHEME=http`, or bundle built before the change | Set `https`/`443`, **rebuild** (`yarn build` + `npm run build`) |
| Echo never connects, no WS request in DevTools | Missing/placeholder `NEXT_PUBLIC_REVERB_APP_KEY` or `VITE_REVERB_APP_KEY` in the build | Fix env, rebuild, hard refresh; console shows `[Echo] ... not set` |
| Echo connects to `ws://host:8080` or `:6001` | Port/host not baked: bundle used defaults | Set `*_REVERB_PORT=443` and rebuild |
| Connected socket but viewers receive no signals | Laravel can't publish to Reverb: wrong `REVERB_*`, hairpin blocked, `client_max_body_size` too small on `ws` (413 in `mp-ws.access.log`); or secret mismatch | `tail storage/logs/laravel-*.log`; from the VPS `curl -sI https://ws.example.com/apps/ID/events`; add `127.0.0.1 ws.example.com` to `/etc/hosts`; keep `client_max_body_size 1m;` in `mp-ws.conf`; verify `REVERB_APP_ID/KEY/SECRET` equal everywhere; `optimize:clear` + `systemctl restart reverb` |
| Viewer sees black video but hears audio | Autoplay/`playsInline`/muted policy, or the stream is audio-only | Viewer taps the video/"Tap to listen"; check studio mode; in `webrtc-internals` `framesDecoded` = 0 means no video track was sent |
| Viewer gets audio-only stream, expected video | Studio started with *Microphone only*, or camera permission denied silently | Restart the stream with Camera + mic; check browser site settings |
| Works on LAN/Wi-Fi, fails on mobile data or other networks | No TURN, TURN credentials mismatch, UDP blocked | Section 5.3 tests; UFW `3478` & `49152:65535/udp`; same credentials in coturn/backend/frontend and **frontend rebuilt**; `external-ip` correct; Cloudflare orange cloud on `ws` |
| ICE state `failed` | Both peers failed to connect and no relay candidate | Trickle ICE page must show `relay`; check `turnserver.log` for 401; `denied-peer-ip` too strict for your NAT layout (see `allowed-peer-ip`) |
| `turns:` fails, `turn:` works | Expired/unreadable cert for coturn, clock skew, 5349 closed | `openssl s_client -connect ws.example.com:5349`; rerun the renewal hook; `timedatectl`; open 5349 tcp/udp |
| API calls blocked by CORS in the browser console | Frontend origin missing from `config/cors.php`; duplicate CORS headers from nginx | Add `https://example.com` etc. to `allowed_origins`; remove nginx `add_header Access-Control-*` for the API; `php artisan optimize:clear` |
| Every Laravel URL except `/` returns nginx 404 | `location /` lacks `try_files $uri $uri/ /index.php?$query_string;`, or `root` is not `…/backend/public` | Fix 2.2; `sudo nginx -t && sudo systemctl reload nginx` (nginx ignores `.htaccess`) |
| Browser downloads `index.php` instead of running it / blank source | `location ~ \.php$` missing or never matched (typo, nested wrongly) | Restore the PHP location from 2.2, reload |
| `File not found.` (plain text from FPM) | `SCRIPT_FILENAME` wrong: `fastcgi_param SCRIPT_FILENAME` missing/overridden, wrong `root` | Keep `fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;` before `include fastcgi_params;`; check `root` path |
| Laravel returns 404 on all panels from a correct server block | `config:cache` was run or `APP_DOMAIN` wrong | `php artisan optimize:clear`; check `APP_DOMAIN=example.com`; DNS and `server_name` for the sub-domain exist |
| `502 Bad Gateway` on the backend hosts, `connect() to unix:/run/php/php8.3-fpm.sock failed (2: No such file or directory)` | PHP-FPM down, or a different PHP version's socket | `sudo systemctl status php8.3-fpm`; `ls /run/php/`; fix the `fastcgi_pass` path |
| `connect() to unix:/run/php/php8.3-fpm.sock failed (13: Permission denied)` | nginx worker user differs from the FPM socket owner | `user www-data;` in `nginx.conf`; `listen.owner/group = www-data` in `/etc/php/8.3/fpm/pool.d/www.conf` |
| `504 Gateway Time-out` on slow admin pages | `fastcgi_read_timeout` / PHP `max_execution_time` too low | Raise `fastcgi_read_timeout` in 2.2 and PHP's limit, `nginx -t`, reload |
| `413 Request Entity Too Large` on uploads | `client_max_body_size` too small | Raise it in the server block (default 1m) and PHP's `upload_max_filesize`/`post_max_size` |
| `403 Forbidden` on backend files | Permissions on `/var/www/...` (needs `x` for `www-data` on every parent directory) | `namei -l $APP_DIR/backend/public/index.php`; fix modes/group |
| `The Authorization header is missing` / Sanctum 401 | Header stripped: a custom `fastcgi_pass_request_headers off;`, or a proxy in front removing it | nginx forwards `Authorization` to FPM by default via `include fastcgi_params`; remove any such override; no Apache-style `SetEnvIf` is needed |
| Laravel generates `http://` URLs, redirect loops or mixed content on the panels | `HTTPS` param not reaching PHP (the `include fastcgi_params;` line missing) or `APP_URL` not https | Keep `include fastcgi_params;` (it sets `HTTPS $https if_not_empty`); `APP_URL=https://…`; `optimize:clear` |
| `/storage/...` images 404 on the storefront | `location /storage/` missing, `alias` path wrong, or `storage:link`/permissions missing | Check the `alias` ends with `/` like the location; `php artisan storage:link`; `www-data` must traverse the path (`namei -l`) |
| `/storage/...` images load but CORS/HSTS headers vanish | `add_header` inside the location removed the inherited ones | Repeat the headers inside the location (as in 2.3) |
| Reverb refuses new connections at some point ("too many open files", ~1000 clients) | `LimitNOFILE` too low or `stream_select` 1024 limit | `LimitNOFILE=65535` in the unit (section 4) and install `ext-uv`; set `REVERB_APP_MAX_CONNECTIONS` only if you want a deliberate cap |
| After a deploy WebSocket stuck / old code running | Reverb or queue process kept old code | `php artisan reverb:restart`, `queue:restart`; confirm `Restart=always` |
| Frontend still uses old keys/URLs | `NEXT_PUBLIC_*` edited without rebuilding | `npm run build` + `pm2 restart mp-frontend` |
| 502 on `https://example.com` | Next.js not running (`connect() failed (111)` to 127.0.0.1:3000) | `pm2 logs mp-frontend`, `pm2 restart mp-frontend` |
| Certificate for one subdomain missing (`NET::ERR_CERT_COMMON_NAME_INVALID`) | Hostname not included in the SAN list | Re-run the `certbot certonly ... --cert-name example.com -d ...` with the full `-d` list (adds names), then `sudo nginx -t && sudo systemctl reload nginx` |
| `nginx: [emerg] cannot load certificate "/etc/letsencrypt/live/…/fullchain.pem"` | TLS block enabled before the certificate exists / wrong `--cert-name` / wrong domain in the snippet | Finish 2.1 first; `sudo ls /etc/letsencrypt/live/`; check `/etc/nginx/snippets/mp-ssl.conf` |
| `nginx: [emerg] bind() to 0.0.0.0:80 failed (98: Address already in use)` | Apache2 (or another daemon) holds 80/443 | `sudo ss -ltnp \| grep -E ':(80\|443)\b'`; `sudo systemctl disable --now apache2` |
| `nginx: [warn] conflicting server name "…" on 0.0.0.0:443, ignored` / `duplicate default server` | Same hostname in two blocks, or the stock `sites-enabled/default` still present | `sudo rm -f /etc/nginx/sites-enabled/default`; each hostname in exactly one `server_name` per port |
| `nginx: [warn] the "listen ... http2" directive is deprecated` | nginx ≥ 1.25.1 | Harmless; optionally replace by a separate `http2 on;` line in each 443 block (not supported before 1.25.1 – do not use it on 22.04/24.04 packages) |
| `Failed to renew certificate` / HTTP-01 challenge 404 | `location ^~ /.well-known/acme-challenge/` missing in the port-80 block, or the port-80 block is not the one answering for that host, or webroot path differs | `curl -I http://admin.example.com/.well-known/acme-challenge/x` should give 404 from nginx (not a redirect); check `-w /var/www/letsencrypt`; `sudo certbot renew --dry-run` |
| Changes to nginx files seem ignored | Edited `sites-available` but not symlinked, or reload skipped after a failed `nginx -t` | `ls -l /etc/nginx/sites-enabled`; `sudo nginx -t && sudo systemctl reload nginx`; inspect the live config with `sudo nginx -T` |

---

## 10. Optional: scaling beyond ~30–50 viewers

The current design is a **P2P mesh**: the admin's browser encodes and uploads one separate stream per viewer. Upload bandwidth (≈ 0.5–1.5 Mbit/s × viewers) and CPU of the broadcaster are the limit, typically 30–50 viewers at 720p, fewer at 1080p. TURN relays additionally load the VPS bandwidth (size the server's network for `viewers × bitrate` when many viewers are relayed).

Beyond that, switch the media path to an **SFU** (Selective Forwarding Unit) such as **LiveKit** (self-hosted, Apache 2.0) or mediasoup/Janus: the admin publishes **one** stream to the SFU, which fans it out to hundreds or thousands of viewers. Reverb keeps its role for chat/likes/notifications; LiveKit replaces only the WebRTC signalling+media. It brings its own server (UDP 7882 / TCP 7881 / WSS via an nginx `lk.example.com` server block with the same `Upgrade`/`$connection_upgrade`/long-timeout proxy settings as `ws.`, its built-in TURN), a token endpoint in Laravel and a client SDK on both sides. For one-to-many audiences in the thousands, HLS (RTMP ingest + CDN) is the alternative at the cost of 5–30 s latency.
