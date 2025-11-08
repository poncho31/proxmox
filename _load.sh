#!/bin/bash
# chmod +x ./_load.sh ; ./_load.sh

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
source config/install/install_ai.sh
source config/install/init_server.sh

load_environment_variables

if [ "$1" = "" ]; then
    echo "Please provide an argument to specify the installation step to execute."
    echo "Available arguments: git, permissions, repositories, php, tailscale, caddy, vscode, go2rtc"
    exit 1
fi

# Execute based on argument
if [ "$1" = "git" ]; then
    echo "Updating from Git repository"
    update_from_git
fi

if [ "$1" = "permissions" ]; then
    echo "Setting up file permissions"
    setup_permissions
fi

if [ "$1" = "repositories" ]; then
    echo "Configuring Proxmox repositories"
    setup_proxmox_repositories
fi

if [ "$1" = "php" ]; then
    echo "Installing PHP"
    install_php
fi

if [ "$1" = "tailscale" ]; then
    echo "Installing and configuring Tailscale"
    install_and_configure_tailscale
fi

if [ "$1" = "caddy" ]; then
    echo "Installing and configuring Caddy"
    install_and_configure_caddy
fi

if [ "$1" = "vscode" ]; then
    echo "Installing VS Code web server"
    install_vscode_web
fi

if [ "$1" = "go2rtc" ]; then
    echo "Installing go2rtc video proxy"
    source config/install/install_go2rtc.sh
    install_go2rtc
fi

if [ "$1" = "install_ai_ollama" ]; then
    echo "Installing AI"
    source config/install/install_ai.sh
    install_ollama
fi

if [ "$1" = "install_ai_stable_diffusion" ]; then
    echo "Installing AI"
    source config/install/install_ai.sh
    install_ollama
fi

if [ "$1" = "ai_model" ]; then
    # chmod +x ./_load.sh ; ./_load.sh ai_model "Resume php language" false
    echo "Run AI Model : $AI_BASE_MODEL"
    source config/cmd/cmd_curl_run_ai.sh "$2" $3
fi

echo "=========================================="
echo "    PROXMOX INSTALLATION $1 COMPLETED"
echo "=========================================="
