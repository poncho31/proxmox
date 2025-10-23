#!/bin/bash

# =======================================================
# VS CODE WEB INSTALLATION MODULE
# =======================================================

install_vscode_web() {
    echo "==> Installing VS Code web server..."
    
    # Installation de code-server (VS Code léger) si pas déjà installé
    if [ ! -f "/usr/bin/code-server" ]; then
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

    # Création du service systemd pour code-server
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

    # Rechargement systemd et activation du service
    echo "Starting code-server service..."
    systemctl daemon-reload
    systemctl enable code-server.service

    # Nettoyer les anciens processus code-server
    pkill -f code-server || true
    sleep 2

    systemctl start code-server.service

    # Vérification que code-server fonctionne
    echo "Verifying code-server installation..."
    sleep 5
    if systemctl is-active --quiet code-server.service; then
        echo "✓ Code-server started successfully on port 8081"
        echo "Testing connectivity..."
        if curl -s http://localhost:8081 >/dev/null 2>&1; then
            echo "✓ Code-server is responding correctly"
        else
            echo "⚠ Code-server is not responding on port 8081"
        fi
    else
        echo "✗ Warning: Code-server failed to start"
        echo "Service logs:"
        journalctl -xeu code-server.service --no-pager -n 10
    fi

    echo "==> VS Code web installation completed"
}
