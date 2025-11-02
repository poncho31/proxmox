#!/bin/bash
# =======================================================
# PROXMOX CENTRALIZED INSTALLATION SCRIPT
# =======================================================
# Installation de GIT et de l'application (à faire manuellement une seule fois)
# apt install git -y
# git clone https://github.com/poncho31/proxmox.git /var/www/proxmox

echo "=========================================="
echo "    PROXMOX INSTALLATION STARTING"
echo "=========================================="

# Make this script executable and change to project directory
chmod +x /var/www/proxmox/_install.sh
cd /var/www/proxmox

# Load all installation modules
source config/install/load_env.sh
source config/install/update_git.sh
source config/install/setup_permissions.sh
source config/install/setup_repositories.sh
source config/install/install_php.sh
source config/install/install_tailscale.sh
source config/install/install_caddy.sh
source config/install/install_vscode.sh

# Execute installation steps in order
echo "==> Step 1: Updating from Git repository"
update_from_git

echo "==> Step 2: Loading environment variables"
load_environment_variables

echo "==> Step 3: Setting up file permissions"
setup_permissions

echo "==> Step 4: Configuring Proxmox repositories"
setup_proxmox_repositories

echo "==> Step 5: Installing PHP"
install_php

echo "==> Step 6: Installing and configuring Tailscale"
install_and_configure_tailscale

echo "==> Step 7: Installing and configuring Caddy"
install_and_configure_caddy

echo "==> Step 8: Installing VS Code web server"
install_vscode_web

echo "DEBUG: INSTALL_GO2RTC=$INSTALL_GO2RTC"
if [ "$INSTALL_GO2RTC" = "true" ]; then
    echo "==> Step 9: Installing go2rtc video proxy"
    source config/install/install_go2rtc.sh
    install_go2rtc
else
    echo "==> Step 9: Skipping go2rtc installation"
fi

echo "=========================================="
echo "    PROXMOX INSTALLATION COMPLETED"
echo "=========================================="
echo "==> All installation steps completed successfully!"
echo "==> You can now access your Proxmox web interface at: https://$CADDY_MAIN_IP"
echo "==> Login with username: $CADDY_USER"
echo "=========================================="