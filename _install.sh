# Installation de GIT et de l'application (à faire manuellement une seule fois)
# apt install git -y
# git clone https://github.com/poncho31/proxmox.git /var/www/proxmox
chmod +x /var/www/proxmox/_install.sh
cd /var/www/proxmox
git reset --hard
git pull origin main

# Load environment variables from .env file
if [ -f .env ]; then
    # Load only the variables we actually need, skip problematic SSH commands
    export DATABASE_URL=$(grep '^DATABASE_URL=' .env | cut -d'=' -f2-)
    export IP_PROXMOX_PUBLIC=$(grep '^IP_PROXMOX_PUBLIC=' .env | cut -d'=' -f2-)
    export IP_PROXMOX_LOCAL=$(grep '^IP_PROXMOX_LOCAL=' .env | cut -d'=' -f2-)
    export CADDY_MAIN_IP=$(grep '^CADDY_MAIN_IP=' .env | cut -d'=' -f2-)
    export CADDY_PASSWORD=$(grep '^CADDY_PASSWORD=' .env | cut -d'=' -f2-)
    export CADDY_USER=$(grep '^CADDY_USER=' .env | cut -d'=' -f2-)
    export TAILSCALE_AUTHKEY=$(grep '^TAILSCALE_AUTHKEY=' .env | cut -d'=' -f2-)
    export TAILSCALE_HOSTNAME=$(grep '^TAILSCALE_HOSTNAME=' .env | cut -d'=' -f2-)
    echo "Environment variables loaded from .env"
else
    echo "Warning: .env file not found"
fi

# Make scripts executable
chmod +x config/*.sh

# Fix Proxmox repositories (disable enterprise repos that cause 401 errors)
echo "==> Fixing Proxmox repositories DEFINITIVELY..."

# Remove ALL enterprise repository files
rm -f /etc/apt/sources.list.d/pve-enterprise.list
rm -f /etc/apt/sources.list.d/ceph.list
rm -f /etc/apt/sources.list.d/pve-install-repo.list

# Check and disable enterprise repos in main sources.list
if [ -f /etc/apt/sources.list ]; then
    sed -i 's|^deb.*enterprise.proxmox.com.*|# &|g' /etc/apt/sources.list
    sed -i 's|^deb-src.*enterprise.proxmox.com.*|# &|g' /etc/apt/sources.list
fi

# Check all sources.list.d files for enterprise repos
find /etc/apt/sources.list.d/ -name "*.list" -exec sed -i 's|^deb.*enterprise.proxmox.com.*|# &|g' {} \;

# Add Proxmox no-subscription repository
echo "deb http://download.proxmox.com/debian/pve trixie pve-no-subscription" > /etc/apt/sources.list.d/pve-no-subscription.list

echo "Enterprise repositories DISABLED"
echo "No-subscription repository ADDED"

# Update package list after fixing repositories
apt update

# Install PHP and essential libraries
apt install php libapache2-mod-php php-mysql php-curl php-json php-mbstring php-xml php-zip php-gd php-intl php-bcmath php-soap php-sqlite3 php-cli php-common php-opcache php-fpm -y

# Enable and start PHP-FPM
systemctl enable --now php8.4-fpm

# Install Tailscale FIRST (required for the IP to exist)
echo "==> Installing Tailscale..."

# Install required dependencies for Tailscale
apt install -y curl gnupg lsb-release

# Add Tailscale's package signing key and repository
curl -fsSL https://pkgs.tailscale.com/stable/debian/trixie.noarmor.gpg | tee /usr/share/keyrings/tailscale-archive-keyring.gpg >/dev/null
curl -fsSL https://pkgs.tailscale.com/stable/debian/trixie.tailscale-keyring.list | tee /etc/apt/sources.list.d/tailscale.list

# Update and install Tailscale
apt update
apt install -y tailscale

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