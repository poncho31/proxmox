# Installation de GIT et de l'application (à faire manuellement une seule fois)
# apt install git -y
# git clone https://github.com/poncho31/proxmox.git /var/www/proxmox
# chmod +x /var/www/proxmox/_install.sh

cd /var/www/proxmox
git reset --hard
git pull origin main

# Load environment variables from .env file
if [ -f .env ]; then
    export $(cat .env | grep -v '^#' | xargs)
    echo "Environment variables loaded from .env"
else
    echo "Warning: .env file not found"
fi

# Make scripts executable
chmod +x config/*.sh

# Install PHP and essential libraries
apt install php libapache2-mod-php php-mysql php-curl php-json php-mbstring php-xml php-zip php-gd php-intl php-bcmath php-soap php-sqlite3 php-cli php-common php-opcache php-fpm -y

# Enable and start PHP-FPM
systemctl enable --now php8.4-fpm

# Install Tailscale FIRST (required for the IP to exist)
echo "==> Installing Tailscale..."
echo "Get your auth key from https://login.tailscale.com/admin/settings/keys"
read -p "Press [ENTER] to continue..."

curl -fsSL https://tailscale.com/install.sh | sh
systemctl enable --now tailscaled

echo "==> Connecting to Tailscale with authkey from .env..."
tailscale up --authkey "$TAILSCALE_AUTHKEY" --hostname "$TAILSCALE_HOSTNAME"

echo "==> Waiting for Tailscale connection..."
sleep 5

# Verify Tailscale IP is available
CURRENT_IP=$(tailscale ip -4)
echo "==> Tailscale IP: $CURRENT_IP"
echo "==> Expected IP: $CADDY_MAIN_IP"

if [ "$CURRENT_IP" != "$CADDY_MAIN_IP" ]; then
    echo "WARNING: Tailscale IP ($CURRENT_IP) doesn't match expected IP ($CADDY_MAIN_IP)"
    echo "You may need to update CADDY_MAIN_IP in .env file"
fi

# Install caddy and configure it
apt install caddy -y
HASH=$(caddy hash-password --plaintext "$CADDY_PASSWORD")
cat > /etc/caddy/Caddyfile << EOF
$CADDY_MAIN_IP {
    basicauth * {
        $CADDY_USER $HASH
    }
    root * /var/www/proxmox/public
    php_fastcgi unix//run/php/php8.4-fpm.sock
    file_server
    try_files {path} proxmox_main_web_server.php
}
EOF
systemctl restart caddy

# # Execute configuration scripts
# ./config/init_proxmox.sh
# ./config/tailscale_personnal_vpn.sh