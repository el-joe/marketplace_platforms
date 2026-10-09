#!/usr/bin/env bash
# SSL for all hosts + coturn. Run AFTER DNS points to the server:
#   DOMAIN=example.com SERVER_IP=1.2.3.4 EMAIL=you@mail.com bash certbot-and-turn.sh
set -euo pipefail
: "${DOMAIN:?}"; : "${SERVER_IP:?}"; : "${EMAIL:?set EMAIL=you@mail.com}"

sudo apt install -y certbot python3-certbot-nginx coturn acl
ARGS=""; for h in "" www. api. admin. portal. partner. delivery. carrier. marketer. travel-agency. ws. pma.; do ARGS="$ARGS -d ${h}${DOMAIN}"; done
sudo certbot --nginx $ARGS --non-interactive --agree-tos -m "$EMAIL" --redirect

TURN_PASS="${TURN_PASS:-$(openssl rand -base64 24 | tr -d '/+=' | cut -c1-24)}"
sudo sed -i 's/^#\?TURNSERVER_ENABLED=.*/TURNSERVER_ENABLED=1/' /etc/default/coturn
sudo tee /etc/turnserver.conf >/dev/null <<CONF
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
CONF
sudo setfacl -R -m u:turnserver:rX /etc/letsencrypt/live /etc/letsencrypt/archive
sudo systemctl enable --now coturn && sudo systemctl restart coturn

cat <<MSG
TURN password (put in backend .env TURN_CREDENTIAL and frontend NEXT_PUBLIC_TURN_CREDENTIAL, then rebuild frontend):
  $TURN_PASS
MSG
