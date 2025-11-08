#!/bin/bash

# -------------------------------------------------------
# Variables globales pour le serveur (surchargées par .env)
# -------------------------------------------------------
source load_env.sh
source init_server.sh

CONTAINER_ID="${AI_CONTAINER_ID:-102}"
HOSTNAME="${AI_HOSTNAME:-ai-model}"
CORES="${AI_CORES:-4}"
MEMORY="${AI_MEMORY:-8192}"
SWAP="${AI_SWAP:-2048}"
ROOTFS="${AI_ROOTFS:-local-lvm:100}"
IP="${AI_IP:-192.168.0.102}"
GATEWAY="${AI_GATEWAY:-192.168.0.1}"
SERVER_PASSWORD="${AI_SERVER_PASSWORD:-changeme}"
AI_BASE_MODEL="${AI_BASE_MODEL:-deepseek-coder:1.3b}"

init_ai(){
    init_server

    echo "==> Configuration du reverse proxy Caddy..."
    pct exec "$CONTAINER_ID" -- bash -c "cat > /etc/caddy/Caddyfile <<EOF
http://$AI_IP:81 {
    reverse_proxy localhost:11434
}
http://$AI_IP:82 {
    reverse_proxy localhost:11434
}
EOF"

    echo "==> Redémarrage de Caddy dans le conteneur..."
    pct exec "$CONTAINER_ID" -- systemctl restart caddy
}

# -------------------------------------------------------
# Installation d’Ollama et des modèles de code
# -------------------------------------------------------
install_ollama() {
    echo "==> Initialisation du conteneur..."
    init_ai

    echo "==> Vérification de l’installation d’Ollama dans le conteneur $CONTAINER_ID..."

    # Vérifie si le binaire existe
    if ! pct exec "$CONTAINER_ID" -- test -x /usr/bin/ollama; then
        echo "📦 Installation d’Ollama dans le conteneur..."
        pct exec "$CONTAINER_ID" -- bash -c "curl -fsSL https://ollama.com/install.sh | sh"
    else
        echo "✅ Ollama déjà installé dans le conteneur."
    fi

    echo "==> Téléchargement des modèles de code..."

    MODELS=(
        "$AI_BASE_MODEL"
        # "llava:7b"
    )

    for model in "${MODELS[@]}"; do
        base="${model%%:*}"  # extrait 'deepseek-coder' depuis 'deepseek-coder:1.3b'
        echo "🔍 Vérification du modèle : $base"
        if ! pct exec "$CONTAINER_ID" -- ollama list | grep -q "^$base[[:space:]]"; then
            echo "⬇️ Téléchargement de $base..."
            pct exec "$CONTAINER_ID" -- ollama pull "$base"
            echo "✅ Modèle $base téléchargé."
        else
            echo "✅ Modèle $base déjà présent."
        fi
    done


    echo "✅ Tous les modèles sont prêts."
}



# -------------------------------------------------------
# Installation locale de Stable Diffusion WebUI
# -------------------------------------------------------
install_stable_diffusion() {
    echo "==> Initialisation du conteneur..."
    init_ai

    echo "==> Installation de Stable Diffusion WebUI dans le conteneur $CONTAINER_ID..."

    # Dépendances minimales dans le conteneur
    pct exec "$CONTAINER_ID" -- bash -c "apt update && apt install -y git wget python3 python3-venv"

    # Clonage du dépôt dans le conteneur
    if ! pct exec "$CONTAINER_ID" -- test -d /root/stable-diffusion-webui; then
        pct exec "$CONTAINER_ID" -- git clone https://github.com/AUTOMATIC1111/stable-diffusion-webui /root/stable-diffusion-webui
    else
        echo "✅ Dépôt déjà présent dans le conteneur."
    fi

    # Téléchargement du modèle SD v1.5 dans le conteneur
    MODEL_DIR="/root/stable-diffusion-webui/models/Stable-diffusion"
    MODEL_FILE="v1-5-pruned-emaonly.safetensors"
    MODEL_URL="https://huggingface.co/runwayml/stable-diffusion-v1-5/resolve/main/$MODEL_FILE"

    pct exec "$CONTAINER_ID" -- mkdir -p "$MODEL_DIR"
    if ! pct exec "$CONTAINER_ID" -- test -f "$MODEL_DIR/$MODEL_FILE"; then
        echo "⬇️ Téléchargement du modèle SD v1.5 dans le conteneur..."
        pct exec "$CONTAINER_ID" -- wget -O "$MODEL_DIR/$MODEL_FILE" "$MODEL_URL"
    else
        echo "✅ Modèle déjà présent dans le conteneur."
    fi

    echo "==> Lancement du serveur WebUI dans le conteneur..."
    echo "🖼️ Accès via http://$AI_IP:7860 une fois démarré."

    COMMAND="cd /root/stable-diffusion-webui && python3 launch.py --precision full --no-half --skip-torch-cuda-test"
    echo "📦 Commande : pct exec $CONTAINER_ID -- bash -c '$COMMAND'"
    echo "💡 Tu peux l’exécuter manuellement ou en arrière-plan avec : pct exec $CONTAINER_ID -- nohup bash -c '$COMMAND' &"

    # Optionnel : lancer automatiquement
    # pct exec "$CONTAINER_ID" -- bash -c "$COMMAND"
}
