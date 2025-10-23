# Installation de GIT et de l'application (à faire manuellement une seule fois)
# apt install git -y
# git clone https://github.com/poncho31/proxmox.git /var/www/proxmox
cd /var/www/proxmox

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
apt install php libapache2-mod-php php-mysql php-curl php-json php-mbstring php-xml php-zip php-gd php-intl php-bcmath php-soap php-sqlite3 php-cli php-common php-opcache -y

# Install caddy and configure it
apt install caddy -y
HASH=$(caddy hash-password --plaintext "$CADDY_PASSWORD")
cat > /etc/caddy/Caddyfile << 'EOF'
https://$CADDY_MAIN_IP {
    basicauth * {
        $CADDY_USER $HASH
    }
    root * /var/www/proxmox/public
    php_fastcgi unix//run/php/php8.2-fpm.sock
    file_server
    try_files {path} proxmox_main_web_server.php
}
EOF
systemctl restart caddy

# # Execute configuration scripts
# ./config/init_proxmox.sh
# ./config/tailscale_personnal_vpn.sh