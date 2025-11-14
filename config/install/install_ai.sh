#!/bin/bash

# -------------------------------------------------------
# Variables globales pour le serveur (surchargées par .env)
# -------------------------------------------------------
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
    reverse_proxy localhost:7860
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
    echo "==> Initialisation du conteneur $SERVER_CONTAINER_ID..."

    if declare -f init_ai >/dev/null 2>&1; then
        init_ai
    fi

    echo "==> Vérification ressources LXC..."
    pct config "$SERVER_CONTAINER_ID" || { echo "Container $SERVER_CONTAINER_ID introuvable"; return 1; }

    echo "==> Mise en place du swap (si nécessaire)..."
    pct exec "$SERVER_CONTAINER_ID" -- bash -lc "set -euo pipefail
if ! swapon --show | grep -q '^/swapfile'; then
  fallocate -l 4G /swapfile || dd if=/dev/zero of=/swapfile bs=1M count=4096
  chmod 600 /swapfile
  mkswap /swapfile
  swapon /swapfile
  if ! grep -q '/swapfile' /etc/fstab; then
    echo '/swapfile none swap sw 0 0' >> /etc/fstab
  fi
fi
"

    echo "==> Installation des dépendances système dans le conteneur..."
    pct exec "$SERVER_CONTAINER_ID" -- bash -lc "apt update && DEBIAN_FRONTEND=noninteractive apt install -y --no-install-recommends git wget ca-certificates python3 python3-venv python3-full build-essential pkg-config libglib2.0-0"

    echo "==> Clonage/MISE A JOUR du dépôt Stable Diffusion WebUI..."
    pct exec "$SERVER_CONTAINER_ID" -- bash -lc "set -euo pipefail
REPO=/root/stable-diffusion-webui
if [ -d \"\$REPO/.git\" ]; then
  cd \"\$REPO\"
  git fetch --all --prune || true
  git reset --hard origin/master || true
  git pull --rebase || true
else
  git clone https://github.com/AUTOMATIC1111/stable-diffusion-webui \"\$REPO\"
fi
"

    echo "==> Création de l'environnement virtuel Python..."
    pct exec "$SERVER_CONTAINER_ID" -- bash -lc "python3 -m venv /root/stable-diffusion-webui/venv"

    echo "==> Installation ordonnée des paquets Python dans le venv (numpy<2 en priorité)..."
    pct exec "$SERVER_CONTAINER_ID" -- bash -lc "set -euo pipefail
VENV=/root/stable-diffusion-webui/venv
source \"\$VENV/bin/activate\"

python -m pip install --upgrade pip setuptools wheel --no-cache-dir

# Downgrade numpy first to avoid ABI clash with torch build
pip install --no-cache-dir 'numpy<2'

# Install critical python deps required by the webui
pip install --no-cache-dir packaging pytorch_lightning gradio

# Install torch and torchvision (ajuste si tu veux CPU-only)
pip install --no-cache-dir torch==2.1.2 torchvision==0.16.2 --extra-index-url https://download.pytorch.org/whl/cu121
"

    echo "==> Téléchargement du modèle SD v1.5 (si absent)..."
    MODEL_DIR="/root/stable-diffusion-webui/models/Stable-diffusion"
    MODEL_FILE="v1-5-pruned-emaonly.safetensors"
    MODEL_URL="https://huggingface.co/runwayml/stable-diffusion-v1-5/resolve/main/$MODEL_FILE"

    pct exec "$SERVER_CONTAINER_ID" -- bash -lc "mkdir -p '$MODEL_DIR'"
    pct exec "$SERVER_CONTAINER_ID" -- bash -lc "if [ ! -f '$MODEL_DIR/$MODEL_FILE' ]; then wget -O '$MODEL_DIR/$MODEL_FILE' '$MODEL_URL'; else echo 'Modèle déjà présent'; fi"

    echo "==> Vérifications post-installation (versions)..."
    pct exec "$SERVER_CONTAINER_ID" -- bash -lc "set -euo pipefail
VENV=/root/stable-diffusion-webui/venv
source \"\$VENV/bin/activate\"
python -c \"import sys, numpy, torch, packaging, pytorch_lightning, gradio; print('python:', sys.version.split()[0]); print('numpy:', numpy.__version__); print('torch:', torch.__version__); print('pytorch_lightning:', getattr(__import__('pytorch_lightning'), '__version__', 'unknown')); print('gradio:', getattr(__import__('gradio'), '__version__', 'unknown'))\"
"

    echo "==> Installation terminée. Démarrage possible via start_sd_webui"
    echo "🖼️ Accès prévu: http://$SERVER_IP:7860"
}
