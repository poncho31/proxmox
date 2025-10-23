#!/bin/bash
# Module: install_tailscale.sh
# Description: Install and configure Tailscale

install_and_configure_tailscale() {
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
    
    echo "==> Tailscale installation and configuration completed"
}
