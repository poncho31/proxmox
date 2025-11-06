#!/bin/bash

# =======================================================
# VS CODE WEB + OLLAMA + CONTINUE INSTALLATION MODULE
# =======================================================

install_vscode_web() {
    echo "==> Installing VS Code web server..."

    # Installation de code-server si absent
    if ! command -v code-server >/dev/null 2>&1; then
        echo "Downloading and installing code-server..."
        curl -fsSL https://code-server.dev/install.sh | sh
    else
        echo "code-server already installed, skipping download"
    fi

    # Configuration de code-server
    echo "Configuring code-server..."
    mkdir -p /root/.config/code-server /root/.local/share/code-server

    cat > /root/.config/code-server/config.yaml <<EOF
bind-addr: 0.0.0.0:8081
auth: $VSCODE_AUTH
password: $VSCODE_PASSWORD
cert: false
disable-telemetry: true
disable-update-check: true
disable-workspace-trust: true
EOF

    # Service systemd unique pour code-server
    echo "Creating systemd service for code-server..."
    cat > /etc/systemd/system/code-server.service <<'EOF'
[Unit]
Description=code-server
After=network.target

[Service]
Type=simple
User=root
Environment=HOME=/root
Environment=XDG_CONFIG_HOME=/root/.config
Environment=XDG_DATA_HOME=/root/.local/share
Environment=XDG_CACHE_HOME=/root/.cache
Environment=SHELL=/bin/bash
Environment=USER=root
WorkingDirectory=/var/www/proxmox
ExecStartPre=/bin/mkdir -p /root/.local/share/code-server
ExecStartPre=/bin/chown -R root:root /root/.local/share/code-server
ExecStart=/usr/bin/code-server --config /root/.config/code-server/config.yaml /var/www/proxmox
Restart=always
RestartSec=10
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
EOF

    systemctl daemon-reload
    systemctl enable --now code-server.service

#!/bin/bash

# -------------------------------------------------------
# Installation d’Ollama et du modèle Starcoder
# -------------------------------------------------------
echo "==> Checking Ollama installation..."
if ! command -v ollama >/dev/null 2>&1; then
    curl -fsSL https://ollama.com/install.sh | sh
else
    echo "Ollama already installed."
fi

echo "==> Pulling coding models..."

# Liste des modèles à télécharger
MODELS=(
    "starcoder:1b"
    "codellama:7b-code"
    "codellama:7b-instruct"
    "phi3:14b"
)

echo "==> Starting model download loop..."
for model in "${MODELS[@]}"; do
    echo "🔍 Checking model: $model"
    if ! ollama list | grep -q "$model"; then
        echo "⬇️  Pulling $model..."
        ollama pull "$model"
        echo "✅ $model downloaded successfully"
    else
        echo "✅ $model already present."
    fi
done
echo "==> Model download loop completed"

# -------------------------------------------------------
# Installation de l’extension Continue
# -------------------------------------------------------
echo "==> Installing Continue extension in code-server..."
CODE_SERVER_BIN=$(which code-server || true)
if [ -n "$CODE_SERVER_BIN" ]; then
    $CODE_SERVER_BIN --install-extension continue.continue
else
    echo "!! code-server binary not found in PATH"
fi

# -------------------------------------------------------
# Config Continue (global + extension)
# -------------------------------------------------------
echo "==> Writing global Continue config to /root/.continue/config.yaml"
mkdir -p /root/.continue
cat > /root/.continue/config.yaml <<EOF
name: Local Config
version: 1.0.0
schema: v1
models:
  - name: Local StarCoder 1b
    provider: ollama
    model: starcoder:1b
    apiBase: http://${TAILSCALE_IP}:83/ollama/${AI_API_TOKEN}
    temperature: 0.2
    maxTokens: 2048
    systemPrompt: |
      You are a helpful coding assistant.
      Provide concise and accurate code suggestions.
      Always format your responses as markdown code blocks.
    roles: [chat, edit, apply, summarize]

  - name: CodeLlama 7b Code
    provider: ollama
    model: codellama:7b-code
    apiBase: http://${TAILSCALE_IP}:83/ollama/${AI_API_TOKEN}
    temperature: 0.1
    maxTokens: 4096
    systemPrompt: |
      You are an expert coding assistant specialized in code generation and completion.
      Focus on writing clean, efficient, and well-documented code.
      Provide contextual code suggestions with proper syntax.
    roles: [chat, edit, apply]

  - name: CodeLlama 7b Instruct
    provider: ollama
    model: codellama:7b-instruct
    apiBase: http://${TAILSCALE_IP}:83/ollama/${AI_API_TOKEN}
    temperature: 0.3
    maxTokens: 4096
    systemPrompt: |
      You are a coding mentor and assistant.
      Help with debugging, code explanation, and best practices.
      Provide step-by-step guidance for complex problems.
    roles: [chat, summarize]

  - name: Phi3 Medium 14b
    provider: ollama
    model: phi3:14b
    apiBase: http://${TAILSCALE_IP}:83/ollama/${AI_API_TOKEN}
    temperature: 0.3
    maxTokens: 4096
    systemPrompt: |
      You are Phi-3, a capable coding and reasoning assistant.
      Provide detailed explanations and help with complex logic.
      Focus on code quality, testing, and maintainability.
    roles: [chat, summarize]
EOF


echo "==> Locating Continue extension directory..."
CONTINUE_DIR=$(find /root/.local/share/code-server/extensions -maxdepth 1 -type d -name "continue.continue-*" | sort | tail -n 1)

if [ -n "$CONTINUE_DIR" ]; then
    echo "==> Writing Continue config also to $CONTINUE_DIR/.continue/config.yaml"
    mkdir -p "$CONTINUE_DIR/.continue"
    cp /root/.continue/config.yaml "$CONTINUE_DIR/.continue/config.yaml"
else
    echo "!! Continue extension not found. Please check code-server installation."
fi

echo "==> Setup complete."
echo "Open VS Code Web on https://100.104.128.114:81/"
echo "In the Continue extension, 'Local StarCoder' should now appear automatically."

}

uninstall_vscode(){
    echo "==> Arrêt des services code-server..."
    systemctl stop code-server.service 2>/dev/null || true
    systemctl stop code-server@root.service 2>/dev/null || true

    echo "==> Désactivation des services..."
    systemctl disable code-server.service 2>/dev/null || true
    systemctl disable code-server@root.service 2>/dev/null || true

    echo "==> Suppression des unités systemd..."
    rm -f /etc/systemd/system/code-server.service
    rm -f /etc/systemd/system/code-server@root.service
    systemctl daemon-reload
    systemctl reset-failed

    echo "==> Suppression du binaire code-server..."
    rm -f /usr/bin/code-server

    echo "==> Suppression des répertoires de configuration et données..."
    rm -rf /root/.config/code-server
    rm -rf /root/.local/share/code-server
    rm -rf /root/.cache/code-server
    rm -rf /root/.vscode-oss
    rm -rf /root/.continue   # ancien emplacement inutile

    echo "==> Vérification qu’aucun processus code-server ne tourne..."
    pkill -9 -f code-server 2>/dev/null || true

    echo "==> Nettoyage terminé. code-server est supprimé."

}

# Lance la fonction
install_vscode_web
