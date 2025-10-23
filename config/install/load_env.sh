#!/bin/bash
# Module: load_env.sh
# Description: Load environment variables from .env file

load_environment_variables() {
    echo "==> Loading environment variables..."
    
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
        return 1
    fi
}
