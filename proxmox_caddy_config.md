#!/bin/bash
set -e

### === CONFIGURATION GÉNÉRALE === ###
PASSWORD="MOTDEPASSE"
MAIN_IP="IP_PVE"                          # IP locale ou publique du PVE
APP_GIT="https://github.com/USER/REPO.git"
APP_DOMAIN="app.domaine.tld"

# ID des conteneurs
LXC_APP_ID=101
LXC_DB_ID=102

# Modèle Debian (doit être déjà présent sur le stockage)
LXC_TEMPLATE="local:vztmpl/debian-12-standard_12.7-1_amd64.tar.zst"

### === ALLOCATION DES RESSOURCES === ###
# Total 12 Go RAM / 4 CPU / 250 Go SSD
# On garde ~1.5 Go pour PVE lui-même

APP_RAM=6144          # 6 Go
APP_CPU=2
APP_DISK=80

DB_RAM=3072           # 3 Go
DB_CPU=1
DB_DISK=40

# Reste au PVE : ~3 Go RAM + 130 Go disque

### === INSTALLATION CADDY + TAILSCALE SUR LE PVE === ###
apt update && apt install -y debian-keyring debian-archive-keyring apt-transport-https curl tailscale
curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.sh' | bash
apt install -y caddy

HASH=$(caddy hash-password --plaintext "$PASSWORD")

tee /etc/caddy/Caddyfile > /dev/null <<EOF
https://$MAIN_IP {
    basicauth * {
        root $HASH
    }
    reverse_proxy http://127.0.0.1:8080
}
EOF

caddy validate --config /etc/caddy/Caddyfile
systemctl enable --now caddy tailscaled

echo "==> Connecte ce nœud à Tailscale (authkey ou CLI):"
echo "tailscale up --authkey <AUTHKEY> --hostname pve-main"

### === CRÉATION CONTENEUR APPLICATION (CADDY + GIT) === ###
pct create $LXC_APP_ID $LXC_TEMPLATE \
  --hostname app-server \
  --net0 name=eth0,bridge=vmbr0,ip=dhcp \
  --cores $APP_CPU --memory $APP_RAM --swap 512 \
  --rootfs local-lvm:${APP_DISK} \
  --unprivileged 1 --features nesting=1

pct start $LXC_APP_ID
sleep 10

pct exec $LXC_APP_ID -- bash -c "
  apt update && apt install -y git curl debian-keyring debian-archive-keyring apt-transport-https php php-fpm
  curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.sh' | bash
  apt install -y caddy
  git clone $APP_GIT /var/www/app || true
  chown -R www-data:www-data /var/www/app

  tee /etc/caddy/Caddyfile > /dev/null <<EOF2
https://$APP_DOMAIN {
    root * /var/www/app
    encode gzip
    php_fastcgi unix//run/php/php8.2-fpm.sock
    file_server
}
EOF2

  systemctl enable --now caddy php8.2-fpm
"

### === CRÉATION CONTENEUR BASE DE DONNÉES (MARIADB) === ###
pct create $LXC_DB_ID $LXC_TEMPLATE \
  --hostname mysql-server \
  --net0 name=eth0,bridge=vmbr0,ip=dhcp \
  --cores $DB_CPU --memory $DB_RAM --swap 256 \
  --rootfs local-lvm:${DB_DISK} \
  --unprivileged 1

pct start $LXC_DB_ID
sleep 10

pct exec $LXC_DB_ID -- bash -c "
  apt update && apt install -y mariadb-server
  systemctl enable --now mariadb
"

echo "✅ Installation terminée."
echo "➡️ Caddy principal : https://$MAIN_IP (auth Basic root)"
echo "➡️ App container #$LXC_APP_ID ($APP_RAM Mo RAM / $APP_CPU CPU)"
echo "➡️ MySQL container #$LXC_DB_ID ($DB_RAM Mo RAM / $DB_CPU CPU)"
echo "➡️ Pense à exécuter 'tailscale up' sur chaque conteneur si tu veux les relier au VPN."
