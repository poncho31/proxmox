# -------------------------------------------------------
# Fonction de création et configuration du conteneur
# -------------------------------------------------------
CONTAINER_ID="${SERVER_CONTAINER_ID:-102}"
HOSTNAME="${SERVER_HOSTNAME:-ai-model}"
CORES="${SERVER_CORES:-4}"
MEMORY="${SERVER_MEMORY:-8192}"
SWAP="${SERVER_SWAP:-2048}"
ROOTFS="${SERVER_ROOTFS:-local-lvm:100}"
IP="${SERVER_IP:-192.168.0.52}"
GATEWAY="${SERVER_GATEWAY:-192.168.0.1}"
SERVER_PASSWORD="${SERVER_PASSWORD:-changeme}"
SERVER_INIT="${SERVER_INIT:-false}"
SERVER_FORCE_INIT="${SERVER_FORCE_INIT:-false}"

init_server() {

    if [ "$SERVER_INIT" = "false" ] && [ "$SERVER_FORCE_INIT" = "false" ]; then
        echo "⏩ Initialisation du conteneur désactivée (SERVER_INIT=$SERVER_INIT)"
        return
    fi

    if pct status "$SERVER_CONTAINER_ID" &>/dev/null; then
        if [ "$SERVER_FORCE_INIT" = "false" ]; then
            echo "✅ Conteneur $SERVER_CONTAINER_ID déjà existant. Aucune recréation."
            return
        fi
    fi

    echo "==> Téléchargement du template Debian..."
    pveam download local debian-12-standard_12.12-1_amd64.tar.zst

    echo "==> Création du conteneur $CONTAINER_ID ($HOSTNAME)..."
    pct create "$CONTAINER_ID" local:vztmpl/debian-12-standard_12.12-1_amd64.tar.zst \
        --hostname "$HOSTNAME" \
        --features nesting=1,keyctl=1 \
        --cores "$CORES" \
        --memory "$MEMORY" \
        --swap "$SWAP" \
        --rootfs "$ROOTFS" \
        --net0 name=eth0,bridge=vmbr0,ip="$IP"/24,gw="$GATEWAY" \
        --unprivileged 0 \
        --password "$SERVER_PASSWORD" \
        --start 1

    echo "==> Configuration du conteneur $CONTAINER_ID ($HOSTNAME)..."
    sleep 10

    echo "==> Configuration DNS..."
    pct exec "$CONTAINER_ID" -- bash -c "echo 'nameserver 8.8.8.8' > /etc/resolv.conf"
    pct exec "$CONTAINER_ID" -- bash -c "echo 'nameserver 8.8.4.4' >> /etc/resolv.conf"

    echo "==> Test de connectivité DNS..."
    pct exec "$CONTAINER_ID" -- nslookup deb.debian.org || echo "⚠️ Problème DNS détecté"

    echo "==> Mise à jour et installation des paquets de base..."
    pct exec "$CONTAINER_ID" -- apt update
    pct exec "$CONTAINER_ID" -- apt install -y curl sudo
    pct exec "$CONTAINER_ID" -- apt install -y jq
    pct exec "$CONTAINER_ID" -- apt install -y caddy

    pct mount "$CONTAINER_ID"
}
