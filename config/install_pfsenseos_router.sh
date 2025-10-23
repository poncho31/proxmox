#!/bin/bash

# ===============================
# Configuration réseau Proxmox
# ===============================
echo "🔧 Configuration du réseau Proxmox..."

cat > /etc/network/interfaces <<'EOF'
auto lo
iface lo inet loopback

iface enp2s0 inet manual

auto vmbr0
iface vmbr0 inet static
    address 192.168.0.50/24
    gateway 192.168.0.1
    bridge_ports enp2s0
    bridge_stp off
    bridge_fd 0

iface wlp1s0 inet manual

source /etc/network/interfaces.d/*

# Bridge LAN pour pfSense / réseau interne
auto vmbr1
iface vmbr1 inet static
    address 192.168.100.2/24
    netmask 255.255.255.0
    bridge_ports none
    bridge_stp off
    bridge_fd 0
    bridge_maxwait 0
EOF

# ===============================
# Application configuration réseau
# ===============================
echo "🔄 Application de la configuration réseau..."
systemctl restart networking
sleep 2
ip a show vmbr1
brctl show

# ===============================
# Téléchargement ISO pfSense
# ===============================
mkdir -p /var/lib/vz/template/iso
cd /var/lib/vz/template/iso

if [ ! -f "pfSense-CE-2.7.2-RELEASE-amd64.iso" ]; then
    echo "📥 Téléchargement de l'ISO pfSense..."
    wget -O pfSense-CE-2.7.2-RELEASE-amd64.iso.gz https://atxfiles.netgate.com/mirror/downloads/pfSense-CE-2.7.2-RELEASE-amd64.iso.gz
    gunzip -f pfSense-CE-2.7.2-RELEASE-amd64.iso.gz
    echo "✅ ISO pfSense téléchargé."
else
    echo "✅ ISO pfSense déjà présent."
fi

# ===============================
# Suppression éventuelle ancienne VM
# ===============================
echo "🧹 Suppression VM existante..."
qm stop 101 --skiplock 2>/dev/null || true
qm destroy 101 --purge --skiplock 2>/dev/null || true

# ===============================
# Création de la VM pfSense
# ===============================
echo "🔧 Création de la VM pfSense..."

qm create 101 \
  --name pfsense \
  --memory 2048 \
  --cores 2 \
  --net0 e1000,bridge=vmbr0 \
  --net1 e1000,bridge=vmbr1 \
  --sata0 local-lvm:20 \
  --ide2 /var/lib/vz/template/iso/pfSense-CE-2.7.2-RELEASE-amd64.iso,media=cdrom \
  --boot order=ide2;sata0 \
  --ostype other \
  --cpu kvm64 \
  --machine pc-i440fx-8.1 \
  --bios seabios \
  --vga cirrus \
  --keyboard fr \
  --tablet 0

echo "✅ VM pfSense créée."

# ===============================
# Démarrage de la VM
# ===============================
echo "🚀 Démarrage de la VM pfSense..."

qm start 101

echo "✅ VM pfSense créée et démarrée."
echo "➡️ Ouvre la console depuis Proxmox et installe pfSense manuellement."
echo ""
echo "🎯 INSTRUCTIONS :"
echo "   1. Va dans la console de la VM 101"
echo "   2. Choisis 'Install pfSense' dans le menu"
echo "   3. Suis l'installation complète"
echo "   4. Quand c'est terminé, reviens ici et appuie sur ENTRÉE"
echo ""
read -p "⌨️  Appuie sur ENTRÉE quand l'installation est VRAIMENT terminée : "

# Arrêt de la VM
echo "🛑 Arrêt de la VM pour finaliser..."
qm stop 101

# Suppression du CD-ROM
echo "💿 Suppression du CD-ROM..."
qm set 101 --ide2 none

# Configuration du boot sur disque dur
echo "🔧 Configuration boot sur disque dur..."
qm set 101 --boot order=sata0

# Redémarrage final
echo "🔄 Redémarrage final de pfSense..."
qm start 101

echo "✅ Installation pfSense terminée ! VM opérationnelle."
