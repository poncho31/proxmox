#!/bin/bash

# Script de désinstallation du service systemd pour le serveur web Proxmox Rust

set -e

echo "🗑️  Désinstallation du service systemd pour Proxmox Web Server..."

# Couleurs pour les messages
GREEN='\033[0;32m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Vérifier si on est root
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}❌ Ce script doit être exécuté en tant que root${NC}"
    exit 1
fi

SERVICE_NAME="proxmox-web.service"

# Vérifier si le service existe
if ! systemctl list-unit-files | grep -q "$SERVICE_NAME"; then
    echo -e "${RED}❌ Le service $SERVICE_NAME n'existe pas${NC}"
    exit 1
fi

# Arrêter le service s'il est en cours d'exécution
echo -e "${BLUE}🛑 Arrêt du service...${NC}"
systemctl stop "$SERVICE_NAME" 2>/dev/null || true

# Désactiver le service
echo -e "${BLUE}❌ Désactivation du service au démarrage...${NC}"
systemctl disable "$SERVICE_NAME" 2>/dev/null || true

# Supprimer le fichier de service
echo -e "${BLUE}🗑️  Suppression du fichier de service...${NC}"
rm -f "/etc/systemd/system/$SERVICE_NAME"

# Recharger systemd
echo -e "${BLUE}🔄 Rechargement de systemd...${NC}"
systemctl daemon-reload

# Réinitialiser les états d'échec
systemctl reset-failed 2>/dev/null || true

echo -e "\n${GREEN}✅ Désinstallation terminée !${NC}"
echo -e "Le service $SERVICE_NAME a été complètement supprimé."
