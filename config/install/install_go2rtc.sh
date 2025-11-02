#!/bin/bash
# Module: install_go2rtc.sh
# Description: Install go2rtc container with environment variables from .env

install_go2rtc() {
    echo "==> Installing go2rtc video proxy..."
    
    # Vérifier les variables essentielles
    if [ -z "$GO2RTC_IP" ] || [ -z "$CAMERA1_IP" ] || [ -z "$CAMERA1_USER" ] || [ -z "$CAMERA1_PASS" ]; then
        echo "Erreur: Variables go2rtc manquantes dans .env"
        echo "Vérifiez: GO2RTC_IP, CAMERA1_IP, CAMERA1_USER, CAMERA1_PASS"
        return 1
    fi

echo "================================================================"
echo "🚀 Installation de go2rtc avec variables d'environnement"
echo "================================================================"
echo "Container IP: $GO2RTC_IP"
echo "Camera IP: $CAMERA1_IP"
echo "================================================================"

# Télécharger le template Debian
pveam download local debian-12-standard_12.12-1_amd64.tar.zst

# Créer le container avec l'IP définie dans .env
pct create 101 local:vztmpl/debian-12-standard_12.12-1_amd64.tar.zst \
  --hostname go2rtc-camera \
  --cores 1 \
  --memory 1024 \
  --swap 256 \
  --rootfs local-lvm:4 \
  --net0 name=eth0,bridge=vmbr0,ip=$GO2RTC_IP/24,gw=192.168.0.1 \
  --unprivileged 1 \
  --features nesting=1 \
  --password $CADDY_PASSWORD \
  --start 1

    echo "Configuration du container go2rtc..."
    
    # Attendre que le container soit démarré
    sleep 10
    
    # Configurer DNS pour résoudre les problèmes de résolution
    pct exec 101 -- bash -c "echo 'nameserver 8.8.8.8' > /etc/resolv.conf"
    pct exec 101 -- bash -c "echo 'nameserver 8.8.4.4' >> /etc/resolv.conf"

    # Tester la connectivité
    echo "Test de connectivité DNS..."
    pct exec 101 -- nslookup deb.debian.org || echo "Problème DNS détecté"

    # Mise à jour et installation des paquets
    pct exec 101 -- apt update
    pct exec 101 -- apt install -y curl sudo

    # Installation de Docker via le script officiel (plus fiable)
    pct exec 101 -- curl -fsSL https://get.docker.com -o get-docker.sh
    pct exec 101 -- sh get-docker.sh

    # Démarrer et activer Docker
    pct exec 101 -- systemctl start docker
    pct exec 101 -- systemctl enable docker

    # L'utilisateur root est déjà configuré avec le mot de passe du conteneur  
    # Ajouter root au groupe docker pour pouvoir gérer les conteneurs
    pct exec 101 -- usermod -aG docker root

    # Attendre que Docker soit complètement démarré
    sleep 5

    # Vérifier que Docker fonctionne
    pct exec 101 -- docker --version
    pct exec 101 -- systemctl status docker --no-pager

    # Créer le répertoire pour go2rtc uniquement
    pct exec 101 -- mkdir -p /opt/go2rtc/config
    pct exec 101 -- chown -R root:root /opt/go2rtc

    # Créer le fichier docker-compose.yml avec les variables d'environnement (temporairement sur l'hôte)
    cat > /tmp/docker-compose.yml <<EOF
services:
  # Proxy RTSP pour caméras Tapo
  go2rtc:
    container_name: go2rtc
    image: alexxit/go2rtc:latest
    restart: unless-stopped
    ports:
      - "${GO2RTC_PORT:-1984}:1984"
      - "${GO2RTC_WEBRTC_PORT:-8555}:8555"
    volumes:
      - /opt/go2rtc/config/go2rtc.yaml:/config/go2rtc.yaml
    environment:
      - TZ=Europe/Paris

EOF

    # Copier le fichier dans le container
    pct push 101 /tmp/docker-compose.yml /opt/go2rtc/docker-compose.yml

    # Créer la configuration go2rtc avec les variables d'environnement (temporairement sur l'hôte)
    cat > /tmp/go2rtc.yaml <<EOF
streams:
  # Pour caméras Tapo avec RTSP natif (FONCTIONNE !)
  ${CAMERA1_NAME:-tapo_camera1}:
    - "rtsp://${CAMERA1_USER}:${CAMERA1_PASS}@${CAMERA1_IP}:554/stream2"
  
  # Stream HD si besoin (optionnel)
  # ${CAMERA1_NAME:-tapo_camera1}_hd:
  #   - "rtsp://${CAMERA1_USER}:${CAMERA1_PASS}@${CAMERA1_IP}:554/stream1"
  
api:
  listen: ":${GO2RTC_PORT:-1984}"

webrtc:
  listen: ":${GO2RTC_WEBRTC_PORT:-8555}"
  candidates:
    - "${GO2RTC_IP}:${GO2RTC_WEBRTC_PORT:-8555}"

EOF

    # Copier le fichier de configuration dans le container
    pct push 101 /tmp/go2rtc.yaml /opt/go2rtc/config/go2rtc.yaml

    # Ajuster les permissions
    pct exec 101 -- chown -R root:root /opt/go2rtc/config

    # Vérifier que Docker est prêt
    echo "Vérification de Docker..."
    pct exec 101 -- docker info > /dev/null 2>&1
    if [ $? -ne 0 ]; then
        echo "Erreur: Docker n'est pas prêt. Redémarrage du service..."
        pct exec 101 -- systemctl restart docker
        sleep 10
    fi

    # Aller dans le répertoire go2rtc et démarrer le service
    echo "Démarrage du service go2rtc..."
    pct exec 101 -- bash -c "cd /opt/go2rtc && docker compose up -d"

    # Attendre le démarrage
    sleep 10

    # Nettoyer les fichiers temporaires
    rm -f /tmp/docker-compose.yml /tmp/go2rtc.yaml

# Afficher les informations de configuration avec variables d'environnement
echo "================================================================"
echo "📹 go2rtc installé avec succès !"
echo "================================================================"
echo "🌐 Interface go2rtc : http://${GO2RTC_IP}:${GO2RTC_PORT:-1984}"
echo "📡 Stream WebRTC : http://${GO2RTC_IP}:${GO2RTC_WEBRTC_PORT:-8555}"
echo "🔧 Configuration: /opt/go2rtc/config/"
echo "📋 Logs: docker logs -f go2rtc"
echo ""
echo "📹 Caméras configurées :"
echo "   📱 ${CAMERA1_LABEL:-Caméra 1} : ${CAMERA1_IP} (${CAMERA1_USER}:***)"
echo "   🎥 Stream disponible : http://${GO2RTC_IP}:${GO2RTC_PORT:-1984}/stream.html?src=${CAMERA1_NAME:-tapo_camera1}"
echo "   📷 API Frame : http://${GO2RTC_IP}:${GO2RTC_PORT:-1984}/api/frame.jpeg?src=${CAMERA1_NAME:-tapo_camera1}"
echo ""
echo "🔄 Redémarrer le service :"
echo "   docker restart go2rtc"
echo "================================================================"

    return 0
}
