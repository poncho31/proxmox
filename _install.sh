#!/bin/bash
# =======================================================
# PROXMOX CENTRALIZED INSTALLATION SCRIPT
# =======================================================
# Installation de GIT et de l'application (à faire manuellement une seule fois)
# apt install git -y
# git clone https://github.com/poncho31/proxmox.git /var/www/proxmox

# Make this script executable and change to project directory
chmod +x /var/www/proxmox/_install.sh
cd /var/www/proxmox

# Load common functions first
source config/install/common_functions.sh

print_header "PROXMOX INSTALLATION STARTING"

# Load all installation modules
source config/install/load_env.sh
source config/install/update_git.sh
source config/install/setup_permissions.sh
source config/install/setup_repositories.sh
source config/install/install_php.sh
source config/install/install_tailscale.sh
source config/install/install_caddy.sh
source config/install/install_vscode.sh
source config/install/install_ai.sh
source config/install/install_systemd_service.sh

# Execute installation steps in order
print_step "1" "Updating from Git repository"
update_from_git

print_step "2" "Loading environment variables"
load_environment_variables

print_step "3" "Setting up file permissions"
setup_permissions

print_step "4" "Configuring Proxmox repositories"
setup_proxmox_repositories

print_step "5" "Installing PHP"
install_php

print_step "6" "Installing and configuring Tailscale"
install_and_configure_tailscale

print_step "7" "Installing and configuring Caddy"
install_and_configure_caddy

print_step "8" "Installing VS Code web server"
install_vscode_web

print_step "9" "Installing Ollama AI"
install_ollama

print_info "DEBUG: INSTALL_GO2RTC=$INSTALL_GO2RTC"
if [ "$INSTALL_GO2RTC" = "true" ]; then
    print_step "10" "Installing go2rtc video proxy"
    source config/install/install_go2rtc.sh
    install_go2rtc
else
    print_step "10" "Skipping go2rtc installation"
fi

print_step "11" "Installing Rust Web Server as systemd service"
install_rust_web_service

# Installation summary
print_installation_summary "https://$CADDY_MAIN_IP" "$CADDY_USER"

# Services summary
print_services_summary "$CADDY_MAIN_IP" "$VSCODE_IP" "$VSCODE_PORT" "$GO2RTC_IP" "$GO2RTC_PORT"
