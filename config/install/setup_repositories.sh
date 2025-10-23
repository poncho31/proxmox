#!/bin/bash
# Module: setup_repositories.sh
# Description: Fix Proxmox repositories and disable enterprise repos

setup_proxmox_repositories() {
    echo "==> Setting up Proxmox repositories..."
    
    # Fix Proxmox repositories (disable enterprise repos that cause 401 errors)
    echo "==> DESTROYING enterprise repositories COMPLETELY..."

    # NUCLEAR OPTION: Remove and recreate ALL apt configuration
    rm -rf /etc/apt/sources.list.d/*
    rm -f /etc/apt/sources.list

    # Create a CLEAN sources.list
    cat > /etc/apt/sources.list << 'EOFAPT'
deb http://deb.debian.org/debian trixie main contrib non-free-firmware
deb-src http://deb.debian.org/debian trixie main contrib non-free-firmware

deb http://deb.debian.org/debian-security/ trixie-security main contrib non-free-firmware
deb-src http://deb.debian.org/debian-security/ trixie-security main contrib non-free-firmware

deb http://deb.debian.org/debian trixie-updates main contrib non-free-firmware
deb-src http://deb.debian.org/debian trixie-updates main contrib non-free-firmware
EOFAPT

    # Add ONLY the no-subscription Proxmox repo
    echo "deb http://download.proxmox.com/debian/pve trixie pve-no-subscription" > /etc/apt/sources.list.d/pve-no-subscription.list

    # Update package list after fixing repositories
    echo "==> Updating package lists..."
    apt update
    
    echo "==> Proxmox repositories configured successfully"
}
