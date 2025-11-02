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
        export VSCODE_AUTH=$(grep '^VSCODE_AUTH=' .env | cut -d'=' -f2-)
        export VSCODE_PASSWORD=$(grep '^VSCODE_PASSWORD=' .env | cut -d'=' -f2-)
        export TAILSCALE_AUTHKEY=$(grep '^TAILSCALE_AUTHKEY=' .env | cut -d'=' -f2-)
        export TAILSCALE_HOSTNAME=$(grep '^TAILSCALE_HOSTNAME=' .env | cut -d'=' -f2-)
        
        # Variables go2rtc
        export INSTALL_GO2RTC=$(grep '^INSTALL_GO2RTC=' .env | cut -d'=' -f2-)
        export GO2RTC_IP=$(grep '^GO2RTC_IP=' .env | cut -d'=' -f2-)
        export GO2RTC_PORT=$(grep '^GO2RTC_PORT=' .env | cut -d'=' -f2-)
        export GO2RTC_WEBRTC_PORT=$(grep '^GO2RTC_WEBRTC_PORT=' .env | cut -d'=' -f2-)
        
        # Variables caméras
        export CAMERA1_NAME=$(grep '^CAMERA1_NAME=' .env | cut -d'=' -f2-)
        export CAMERA1_LABEL=$(grep '^CAMERA1_LABEL=' .env | cut -d'=' -f2-)
        export CAMERA1_IP=$(grep '^CAMERA1_IP=' .env | cut -d'=' -f2-)
        export CAMERA1_USER=$(grep '^CAMERA1_USER=' .env | cut -d'=' -f2-)
        export CAMERA1_PASS=$(grep '^CAMERA1_PASS=' .env | cut -d'=' -f2-)

        # Variables caméras
        export CAMERA2_NAME=$(grep '^CAMERA2_NAME=' .env | cut -d'=' -f2-)
        export CAMERA2_LABEL=$(grep '^CAMERA2_LABEL=' .env | cut -d'=' -f2-)
        export CAMERA2_IP=$(grep '^CAMERA2_IP=' .env | cut -d'=' -f2-)
        export CAMERA2_USER=$(grep '^CAMERA2_USER=' .env | cut -d'=' -f2-)
        export CAMERA2_PASS=$(grep '^CAMERA2_PASS=' .env | cut -d'=' -f2-)

        # Variables VS Code
        export VSCODE_PORT=$(grep '^VSCODE_PORT=' .env | cut -d'=' -f2-)
        export VSCODE_IP=$(grep '^VSCODE_IP=' .env | cut -d'=' -f2-)

        echo "Environment variables loaded from .env"
    else
        echo "Warning: .env file not found"
        return 1
    fi
}
