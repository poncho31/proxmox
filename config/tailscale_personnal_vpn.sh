#!/bin/bash

# Installation simple Tailscale pour Proxmox
# Usage: chmod +x tailscale_personnal_vpn.sh && sudo ./tailscale_personnal_vpn.sh

set -e

echo "=== Installation Tailscale pour Proxmox ==="

# Vérification root
if [[ $EUID -ne 0 ]]; then
    echo "❌ Ce script doit être exécuté en tant que root"
    exit 1
fi

echo "📦 Installation de Tailscale..."
# Installation via script officiel
curl -fsSL https://tailscale.com/install.sh | sh

echo "🚀 Démarrage du service..."
systemctl enable --now tailscaled

echo "🌐 Connexion à Tailscale..."
# Détecter réseaux locaux
LOCAL_NETS=$(ip route | grep -E "192\.168\.|10\.|172\.(1[6-9]|2[0-9]|3[0-1])\." | awk '{print $1}' | grep -E "192\.168\.|10\.|172\." | sort -u | tr '\n' ',' | sed 's/,$//')

# Connexion avec options optimales
if [ -n "$LOCAL_NETS" ]; then
    echo "📡 Réseaux détectés: $LOCAL_NETS"
    tailscale up --accept-routes --advertise-routes="$LOCAL_NETS" --ssh
else
    tailscale up --accept-routes --ssh
fi

# Attendre connexion
echo "⏳ Attente de la connexion..."
sleep 5

# Afficher les informations
if tailscale status >/dev/null 2>&1; then
    TAILSCALE_IP=$(tailscale ip -4 2>/dev/null)
    echo ""
    echo "✅ INSTALLATION TERMINÉE !"
    echo "� IP Tailscale: $TAILSCALE_IP"
    echo "🌐 Accès Proxmox: https://$TAILSCALE_IP:8006"
    echo "🔐 SSH: ssh root@$TAILSCALE_IP"
    echo ""
    echo "👉 Allez sur https://login.tailscale.com/admin/ pour approuver cette machine"
else
    echo "⚠️  Connexion manuelle requise: tailscale up"
fi

echo "🎉 Fini !"