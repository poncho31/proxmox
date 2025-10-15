#!/bin/bash

# 1. Installer Caddy 
apt update && apt install -y debian-keyring debian-archive-keyring apt-transport-https curl
curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.sh' | bash
apt install -y caddy

# 2. Définir le mot de passe 
PASSWORD="MOT_DE_PASSE"
HASH=$(caddy hash-password --plaintext "$PASSWORD")

# 3. Créer le Caddyfile
tee /etc/caddy/Caddyfile > /dev/null <<EOF
http://IP_DU_SERVEUR {
    basicauth * {
        root $HASH
    }

    respond "Bienvenue sur Caddy !"
}
EOF

# 4. Valider la config et démarrer Caddy
caddy validate --config /etc/caddy/Caddyfile
systemctl enable caddy
systemctl restart caddy
systemctl status caddy --no-pager -l
