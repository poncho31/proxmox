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
        export TAILSCALE_DNS=$(grep '^TAILSCALE_DNS=' .env | cut -d'=' -f2-)
        export TAILSCALE_IP=$(grep '^TAILSCALE_IP=' .env | cut -d'=' -f2-)
        export TAILSCALE_SUBNETS=$(grep '^TAILSCALE_SUBNETS=' .env | cut -d'=' -f2-)

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

        # AI API
        export AI_CONTAINER_ID=$(grep '^AI_CONTAINER_ID=' .env | cut -d'=' -f2-)
        export AI_HOSTNAME=$(grep '^AI_HOSTNAME=' .env | cut -d'=' -f2-)
        export AI_CORES=$(grep '^AI_CORES=' .env | cut -d'=' -f2-)
        export AI_MEMORY=$(grep '^AI_MEMORY=' .env | cut -d'=' -f2-)
        export AI_SWAP=$(grep '^AI_SWAP=' .env | cut -d'=' -f2-)
        export AI_ROOTFS=$(grep '^AI_ROOTFS=' .env | cut -d'=' -f2-)
        export AI_IP=$(grep '^AI_IP=' .env | cut -d'=' -f2-)
        export AI_IP_PORT=$(grep '^AI_IP_PORT=' .env | cut -d'=' -f2-)
        export AI_GATEWAY=$(grep '^AI_GATEWAY=' .env | cut -d'=' -f2-)
        export AI_SERVER_PASSWORD=$(grep '^AI_SERVER_PASSWORD=' .env | cut -d'=' -f2-)
        export AI_BASE_MODEL=$(grep '^AI_BASE_MODEL=' .env | cut -d'=' -f2-)

        # SERVER INIT
        export SERVER_CONTAINER_ID=$(grep '^SERVER_CONTAINER_ID=' .env | cut -d'=' -f2-)
        export SERVER_HOSTNAME=$(grep '^SERVER_HOSTNAME=' .env | cut -d'=' -f2-)
        export SERVER_CORES=$(grep '^SERVER_CORES=' .env | cut -d'=' -f2-)
        export SERVER_MEMORY=$(grep '^SERVER_MEMORY=' .env | cut -d'=' -f2-)
        export SERVER_SWAP=$(grep '^SERVER_SWAP=' .env | cut -d'=' -f2-)
        export SERVER_ROOTFS=$(grep '^SERVER_ROOTFS=' .env | cut -d'=' -f2-)
        export SERVER_IP=$(grep '^SERVER_IP=' .env | cut -d'=' -f2-)
        export SERVER_GATEWAY=$(grep '^SERVER_GATEWAY=' .env | cut -d'=' -f2-)
        export SERVER_PASSWORD=$(grep '^SERVER_PASSWORD=' .env | cut -d'=' -f2-)
        export SERVER_INIT=$(grep '^SERVER_INIT=' .env | cut -d'=' -f2-)
        export SERVER_FORCE_INIT=$(grep '^SERVER_FORCE_INIT=' .env | cut -d'=' -f2-)

        # COMFYUI Configuration
        export COMFYUI_IP=$(grep '^COMFYUI_IP=' .env | cut -d'=' -f2-)
        export COMFYUI_PORT=$(grep '^COMFYUI_PORT=' .env | cut -d'=' -f2-)

        echo "Environment variables loaded from .env"
    else
        echo "Warning: .env file not found"
        return 1
    fi
}
