#!/bin/bash
# Script de monitoring système Proxmox
# Utilise les variables d'environnement pour la configuration

set -e

# Charger les variables d'environnement si le fichier .env existe
if [ -f "/var/www/html/.env" ]; then
    export $(grep -v '^#' /var/www/html/.env | xargs)
elif [ -f "$(dirname "$0")/.env" ]; then
    export $(grep -v '^#' "$(dirname "$0")/.env" | xargs)
fi

# Couleurs pour l'affichage
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
NC='\033[0m' # No Color

# Configuration par défaut (peut être surchargée par les variables d'environnement)
PROXMOX_IP=${IP_PROXMOX_LOCAL:-"https://192.168.0.50:8006"}
PASSWORD=${CADDY_PASSWORD:-"MOTDEPASSE"}

print_header() {
    echo -e "${BLUE}============================================${NC}"
    echo -e "${GREEN} 🖥️  MONITORING SYSTÈME PROXMOX${NC}"
    echo -e "${GREEN} Date: $(date '+%d/%m/%Y %H:%M:%S')${NC}"
    echo -e "${BLUE}============================================${NC}"
    echo ""
}

# Fonction pour obtenir l'état du CPU
get_cpu_status() {
    echo -e "${BLUE}📊 PROCESSEUR${NC}"
    echo "----------------------------------------"
    
    # Informations de base
    local cpu_model=$(grep 'model name' /proc/cpuinfo | head -1 | cut -d':' -f2 | xargs)
    local cpu_cores=$(nproc)
    local cpu_usage=$(top -bn1 | grep "Cpu(s)" | awk '{print $2}' | cut -d'%' -f1)
    
    echo -e "Modèle: ${GREEN}$cpu_model${NC}"
    echo -e "Cœurs: ${GREEN}$cpu_cores${NC}"
    echo -e "Utilisation: ${YELLOW}$cpu_usage%${NC}"
    
    # Load average
    local load_avg=$(uptime | awk -F'load average:' '{print $2}')
    echo -e "Load Average:${YELLOW}$load_avg${NC}"
    
    # Température si disponible
    if command -v sensors &> /dev/null; then
        local temp=$(sensors 2>/dev/null | grep -E "Core|Package" | head -1 | awk '{print $3}' | grep -oE '[0-9]+\.[0-9]+' || echo "N/A")
        echo -e "Température: ${YELLOW}${temp}°C${NC}"
    fi
    
    echo ""
}

# Fonction pour obtenir l'état de la mémoire
get_memory_status() {
    echo -e "${BLUE}💾 MÉMOIRE${NC}"
    echo "----------------------------------------"
    
    local mem_info=$(free -h | grep "Mem:")
    local total_mem=$(echo $mem_info | awk '{print $2}')
    local used_mem=$(echo $mem_info | awk '{print $3}')
    local free_mem=$(echo $mem_info | awk '{print $4}')
    local mem_percent=$(free | grep Mem | awk '{printf("%.1f", ($3/$2) * 100.0)}')
    
    echo -e "Total: ${GREEN}$total_mem${NC}"
    echo -e "Utilisé: ${YELLOW}$used_mem${NC} (${mem_percent}%)"
    echo -e "Libre: ${GREEN}$free_mem${NC}"
    
    # Swap si disponible
    local swap_info=$(free -h | grep "Swap:" | awk '{print $2 " " $3}')
    if [[ -n "$swap_info" && "$swap_info" != "0B 0B" ]]; then
        echo -e "Swap: ${YELLOW}$swap_info${NC}"
    fi
    
    echo ""
}

# Fonction pour obtenir l'état du stockage
get_storage_status() {
    echo -e "${BLUE}💿 STOCKAGE${NC}"
    echo "----------------------------------------"
    
    # Espace disque principal
    df -h / | tail -1 | while read filesystem size used avail percent mountpoint; do
        echo -e "Racine (/): ${GREEN}$used${NC} / $size (${YELLOW}$percent${NC})"
    done
    
    # Autres partitions importantes
    if mountpoint -q /var; then
        df -h /var | tail -1 | while read filesystem size used avail percent mountpoint; do
            echo -e "/var: ${GREEN}$used${NC} / $size (${YELLOW}$percent${NC})"
        done
    fi
    
    # ZFS pools si disponibles
    if command -v zpool &> /dev/null && zpool list &>/dev/null; then
        echo -e "\n${PURPLE}Pools ZFS:${NC}"
        zpool list | tail -n +2 | while read name size alloc free expandsz frag cap dedup health altroot; do
            echo -e "  $name: ${GREEN}$alloc${NC} / $size (${YELLOW}$cap${NC}) - $health"
        done
    fi
    
    echo ""
}

# Fonction pour obtenir l'état des VMs/Containers
get_vms_status() {
    echo -e "${BLUE}🖥️ MACHINES VIRTUELLES${NC}"
    echo "----------------------------------------"
    
    if command -v qm &> /dev/null; then
        local running_vms=$(qm list | grep -c "running" 2>/dev/null || echo 0)
        local stopped_vms=$(qm list | grep -c "stopped" 2>/dev/null || echo 0)
        local total_vms=$((running_vms + stopped_vms))
        echo -e "VMs: ${GREEN}$running_vms actives${NC} / ${YELLOW}$stopped_vms arrêtées${NC} (Total: $total_vms)"
    fi
    
    if command -v pct &> /dev/null; then
        local running_lxc=$(pct list | grep -c "running" 2>/dev/null || echo 0)
        local stopped_lxc=$(pct list | grep -c "stopped" 2>/dev/null || echo 0)
        local total_lxc=$((running_lxc + stopped_lxc))
        echo -e "Conteneurs: ${GREEN}$running_lxc actifs${NC} / ${YELLOW}$stopped_lxc arrêtés${NC} (Total: $total_lxc)"
    fi
    
    echo ""
}

# Fonction pour obtenir l'état des services
get_services_status() {
    echo -e "${BLUE}⚙️ SERVICES${NC}"
    echo "----------------------------------------"
    
    local services=("pveproxy" "pvedaemon" "pve-cluster" "corosync" "ksmtuned" "nginx" "caddy")
    
    for service in "${services[@]}"; do
        if systemctl is-active --quiet $service 2>/dev/null; then
            echo -e "$service: ${GREEN}✓ Actif${NC}"
        else
            if systemctl list-unit-files | grep -q "^$service"; then
                echo -e "$service: ${RED}✗ Inactif${NC}"
            fi
        fi
    done
    
    echo ""
}

# Fonction pour calculer un score de charge global
calculate_global_score() {
    echo -e "${BLUE}📈 SCORE DE CHARGE GLOBAL${NC}"
    echo "----------------------------------------"
    
    # CPU Load (0-100)
    local cpu_load=$(top -bn1 | grep "Cpu(s)" | awk '{print $2}' | cut -d'%' -f1)
    cpu_load=${cpu_load:-0}
    
    # Memory Load (0-100)
    local mem_load=$(free | grep Mem | awk '{printf("%.0f", ($3/$2) * 100.0)}')
    
    # Load Average normalisé
    local cores=$(nproc)
    local load_1min=$(uptime | awk '{print $10}' | cut -d',' -f1)
    local normalized_load=$(echo "scale=1; ($load_1min / $cores) * 100" | bc -l 2>/dev/null || echo 0)
    
    # Disk usage
    local disk_load=$(df / | tail -1 | awk '{print $5}' | cut -d'%' -f1)
    
    # Score global (moyenne pondérée)
    local global_score=$(echo "scale=1; ($cpu_load * 0.3 + $mem_load * 0.3 + $normalized_load * 0.2 + $disk_load * 0.2)" | bc -l 2>/dev/null || echo 0)
    
    echo -e "CPU: ${YELLOW}${cpu_load}%${NC}"
    echo -e "Mémoire: ${YELLOW}${mem_load}%${NC}"
    echo -e "Load normalisé: ${YELLOW}${normalized_load}%${NC}"
    echo -e "Disque: ${YELLOW}${disk_load}%${NC}"
    echo ""
    
    if (( $(echo "$global_score < 30" | bc -l 2>/dev/null || echo 0) )); then
        echo -e "Score Global: ${GREEN}${global_score}% - CHARGE FAIBLE${NC}"
    elif (( $(echo "$global_score < 70" | bc -l 2>/dev/null || echo 0) )); then
        echo -e "Score Global: ${YELLOW}${global_score}% - CHARGE MODÉRÉE${NC}"
    else
        echo -e "Score Global: ${RED}${global_score}% - CHARGE ÉLEVÉE${NC}"
    fi
    
    echo ""
}

# Fonction pour afficher les informations réseau
get_network_info() {
    echo -e "${BLUE}🌐 RÉSEAU${NC}"
    echo "----------------------------------------"
    
    # Interfaces réseau actives
    local interfaces=$(ip link show | grep "state UP" | awk -F': ' '{print $2}' | grep -v lo)
    
    for interface in $interfaces; do
        if [ -n "$interface" ]; then
            local ip_addr=$(ip addr show $interface | grep 'inet ' | awk '{print $2}' | cut -d'/' -f1)
            local rx_bytes=$(cat /sys/class/net/$interface/statistics/rx_bytes 2>/dev/null || echo 0)
            local tx_bytes=$(cat /sys/class/net/$interface/statistics/tx_bytes 2>/dev/null || echo 0)
            local rx_mb=$((rx_bytes / 1024 / 1024))
            local tx_mb=$((tx_bytes / 1024 / 1024))
            
            echo -e "$interface (${GREEN}$ip_addr${NC}): RX ${YELLOW}${rx_mb}MB${NC} / TX ${YELLOW}${tx_mb}MB${NC}"
        fi
    done
    
    echo ""
}

# Fonction pour afficher les recommandations
show_recommendations() {
    echo -e "${BLUE}💡 RECOMMANDATIONS${NC}"
    echo "----------------------------------------"
    
    local cpu_load=$(top -bn1 | grep "Cpu(s)" | awk '{print $2}' | cut -d'%' -f1)
    local mem_load=$(free | grep Mem | awk '{printf("%.0f", ($3/$2) * 100.0)}')
    local disk_load=$(df / | tail -1 | awk '{print $5}' | cut -d'%' -f1)
    
    cpu_load=${cpu_load:-0}
    
    if (( $(echo "$cpu_load > 80" | bc -l 2>/dev/null || echo 0) )); then
        echo -e "${RED}⚠️  CPU surchargé (${cpu_load}%)${NC}"
        echo -e "   → Migrer des VMs vers d'autres nœuds"
        echo -e "   → Réduire le nombre de VMs actives"
        echo ""
    fi
    
    if (( $(echo "$mem_load > 85" | bc -l 2>/dev/null || echo 0) )); then
        echo -e "${RED}⚠️  Mémoire critique (${mem_load}%)${NC}"
        echo -e "   → Arrêter des VMs non essentielles"
        echo -e "   → Augmenter la RAM du serveur"
        echo ""
    fi
    
    if (( disk_load > 90 )); then
        echo -e "${RED}⚠️  Stockage critique (${disk_load}%)${NC}"
        echo -e "   → Nettoyer les snapshots anciens"
        echo -e "   → Migrer des VMs vers autre stockage"
        echo ""
    fi
}

# Fonction principale
main() {
    clear
    print_header
    get_cpu_status
    get_memory_status
    get_storage_status
    get_network_info
    get_services_status
    get_vms_status
    calculate_global_score
    show_recommendations
    
    echo -e "${BLUE}Interface Proxmox: ${GREEN}$PROXMOX_IP${NC}"
    echo -e "${BLUE}Monitoring web: ${GREEN}http://$(hostname -I | awk '{print $1}')/info_server.php${NC}"
}

# Vérifier les arguments
case "${1:-}" in
    --continuous|-c)
        while true; do
            main
            echo -e "\n${YELLOW}Actualisation dans 60 secondes... (Ctrl+C pour arrêter)${NC}"
            sleep 60
        done
        ;;
    --help|-h)
        echo "Usage: $0 [options]"
        echo "Options:"
        echo "  -c, --continuous    Monitoring continu (rafraîchissement toutes les 60s)"
        echo "  -h, --help          Afficher cette aide"
        echo ""
        echo "Variables d'environnement supportées:"
        echo "  IP_PROXMOX_LOCAL    Adresse IP de l'interface Proxmox"
        echo "  CADDY_PASSWORD      Mot de passe pour l'authentification Caddy"
        ;;
    *)
        main
        ;;
esac
