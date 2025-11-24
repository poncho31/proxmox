#!/bin/bash

# Script d'installation du service systemd pour le serveur web Proxmox Rust
# Ce script doit être exécuté avec les privilèges root

# Fonction principale appelable depuis _install.sh
install_rust_web_service() {
    local PROJECT_DIR="${1:-/var/www/proxmox}"
    local SERVICE_FILE="${PROJECT_DIR}/config/proxmox-web.service"
    local SYSTEMD_DIR="/etc/systemd/system"

    print_info "Installation du service systemd pour Proxmox Web Server..."

    # Vérifier que le fichier de service existe
    if [ ! -f "$SERVICE_FILE" ]; then
        print_error "Fichier de service non trouvé: $SERVICE_FILE"
        return 1
    fi

    # Vérifier et installer Rust/Cargo si nécessaire
    if [ ! -f "$HOME/.cargo/bin/cargo" ]; then
        print_info "Rust/Cargo n'est pas installé. Installation en cours..."

        # Installer les dépendances nécessaires
        print_info "Installation des dépendances système..."
        apt-get update -qq
        apt-get install -y curl build-essential gcc make pkg-config libssl-dev

        # Installer Rust via rustup
        print_info "Installation de Rust via rustup..."
        curl --proto '=https' --tlsv1.2 -sSf https://sh.rustup.rs | sh -s -- -y --default-toolchain stable

        # Charger l'environnement Rust
        source "$HOME/.cargo/env"

        # Vérifier l'installation
        if [ -f "$HOME/.cargo/bin/cargo" ]; then
            print_success "Rust et Cargo installés avec succès"
            print_info "Version de Rust: $(rustc --version)"
            print_info "Version de Cargo: $(cargo --version)"
        else
            print_error "L'installation de Rust a échoué"
            return 1
        fi
    else
        print_success "Rust et Cargo sont déjà installés"
        print_info "Version de Rust: $(rustc --version)"
        print_info "Version de Cargo: $(cargo --version)"
    fi

    # Compiler l'application en mode release
    print_info "Compilation de l'application en mode release..."
    cd "$PROJECT_DIR"

    # S'assurer que cargo est dans le PATH
    export PATH="$HOME/.cargo/bin:$PATH"

    cargo build --release

    if [ ! -f "${PROJECT_DIR}/target/release/Proxmox" ]; then
        print_error "La compilation a échoué"
        return 1
    fi

    print_success "Compilation réussie"

    # Copier le fichier de service vers systemd
    print_info "Copie du fichier de service vers ${SYSTEMD_DIR}..."
    cp "$SERVICE_FILE" "${SYSTEMD_DIR}/proxmox-web.service"

    # Recharger systemd pour prendre en compte le nouveau service
    print_info "Rechargement de systemd..."
    systemctl daemon-reload

    # Activer le service pour qu'il démarre automatiquement
    print_info "Activation du service au démarrage..."
    systemctl enable proxmox-web.service

    # Démarrer le service
    print_info "Démarrage du service..."
    systemctl start proxmox-web.service

    # Attendre un peu que le service démarre
    sleep 2

    # Vérifier le statut du service
    if systemctl is-active --quiet proxmox-web.service; then
        print_success "Service proxmox-web.service démarré avec succès"
        print_info "Commandes utiles:"
        print_info "  • Voir les logs:        journalctl -u proxmox-web.service -f"
        print_info "  • Redémarrer:          systemctl restart proxmox-web.service"
        print_info "  • Arrêter:             systemctl stop proxmox-web.service"
        print_info "  • Vérifier le statut:  systemctl status proxmox-web.service"
    else
        print_error "Le service n'a pas pu démarrer"
        systemctl status proxmox-web.service --no-pager
        return 1
    fi
}

# Si le script est exécuté directement (pas sourcé)
if [ "${BASH_SOURCE[0]}" -ef "$0" ]; then
    set -e

    echo "🔧 Installation du service systemd pour Proxmox Web Server..."

    # Couleurs pour les messages
    GREEN='\033[0;32m'
    BLUE='\033[0;34m'
    RED='\033[0;31m'
    NC='\033[0m' # No Color

    # Fonctions de print simples pour exécution standalone
    print_info() { echo -e "${BLUE}ℹ️  $1${NC}"; }
    print_success() { echo -e "${GREEN}✅ $1${NC}"; }
    print_error() { echo -e "${RED}❌ $1${NC}"; }

    # Vérifier si on est root
    if [ "$EUID" -ne 0 ]; then
        print_error "Ce script doit être exécuté en tant que root"
        exit 1
    fi

    # Définir le répertoire du projet par défaut
    PROJECT_DIR="${1:-/root/proxmox}"

    # Appeler la fonction principale
    install_rust_web_service "$PROJECT_DIR"
fi
