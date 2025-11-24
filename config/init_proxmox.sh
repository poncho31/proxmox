#!/bin/bash

set -e

# Mise à jour et installation des paquets nécessaires
apt update
DEBIAN_FRONTEND=noninteractive apt install -y xfce4 xfce4-goodies tigervnc-standalone-server tigervnc-common novnc websockify wget dbus-x11 dbus firefox-esr xfce4-terminal curl

# Création des dossiers de config pour VNC
mkdir -p /root/.config/tigervnc /root/.vnc
chown -R root:root /root/.config/tigervnc /root/.vnc
chmod 700 /root/.config/tigervnc /root/.vnc

# Définir le mot de passe VNC root (proxmvnc)
echo "proxmvnc" | vncpasswd -f > /root/.vnc/passwd
chmod 600 /root/.vnc/passwd

# Configuration VNC avec fichier config
cat > /root/.vnc/config <<'EOF'
session=xfce
geometry=1920x1080
localhost=no
alwaysshared
dpi=96
EOF

# Script xstartup pour lancer XFCE
cat > /root/.vnc/xstartup <<'EOF'
#!/bin/sh

# Nettoyer l'environnement
unset SESSION_MANAGER
unset DBUS_SESSION_BUS_ADDRESS

# Démarrer le démon D-Bus
if [ -x /usr/bin/dbus-launch ]; then
    eval `dbus-launch --sh-syntax --exit-with-session`
fi

# Variables d'environnement pour XFCE
export XKL_XMODMAP_DISABLE=1
export XDG_CURRENT_DESKTOP="XFCE"
export XDG_MENU_PREFIX="xfce-"
export XDG_SESSION_DESKTOP="xfce"
export XDG_SESSION_TYPE="x11"

# Définir le navigateur par défaut
export BROWSER="firefox-esr"

# Démarrer XFCE
exec startxfce4
EOF
chmod +x /root/.vnc/xstartup

# Configuration des applications par défaut XFCE
mkdir -p /root/.config/xfce4/helpers
cat > /root/.config/xfce4/helpers.rc <<'EOF'
WebBrowser=firefox
EOF

# Création d'un raccourci desktop pour code-server
mkdir -p /root/Desktop
cat > /root/Desktop/VSCode.desktop <<'EOF'
[Desktop Entry]
Version=1.0
Type=Application
Name=VS Code Server
Comment=Code editor in browser
Exec=firefox-esr http://localhost:8081
Icon=applications-development
Terminal=false
Categories=Development;IDE;
EOF
chmod +x /root/Desktop/VSCode.desktop

# Création d'un raccourci dans le menu applications
mkdir -p /root/.local/share/applications
cp /root/Desktop/VSCode.desktop /root/.local/share/applications/

# Création du fichier systemd pour TigerVNC corrigé
cat > /etc/systemd/system/vncserver@.service <<'EOF'
[Unit]
Description=TigerVNC Server for display %i
After=network.target graphical-session.target
Wants=graphical-session.target

[Service]
Type=simple
User=root
Group=root
WorkingDirectory=/root
Environment="HOME=/root"
Environment="XDG_CURRENT_DESKTOP=XFCE"
Environment="XDG_SESSION_DESKTOP=xfce"
Environment="XDG_SESSION_TYPE=x11"
ExecStartPre=-/bin/sh -c 'pkill -9 -f "Xtigervnc.*:%i" || true'
ExecStartPre=-/bin/sh -c 'rm -f /root/.vnc/*:%i.* /tmp/.X%i-lock /tmp/.X11-unix/X%i || true'
ExecStartPre=/bin/sleep 2
ExecStart=/usr/bin/vncserver :%i -fg -geometry 1920x1080 -depth 24 -localhost no -dpi 96
ExecStop=/bin/sh -c 'pkill -TERM -f "Xtigervnc.*:%i" || /usr/bin/vncserver -kill :%i || true'
KillMode=mixed
Restart=on-failure
RestartSec=10
TimeoutStartSec=30
TimeoutStopSec=15

[Install]
WantedBy=multi-user.target
EOF

# Rechargement systemd et nettoyage des anciennes sessions
systemctl daemon-reload

# Nettoyage complet avant démarrage
echo "Nettoyage des processus VNC existants..."
pkill -9 -f "Xtigervnc" || true
pkill -9 -f "vncserver" || true
pkill -9 -f "websockify" || true
rm -rf /root/.vnc/*.log /root/.vnc/*.pid || true
rm -f /root/.vnc/*:*.* || true
rm -f /tmp/.X*-lock /tmp/.X11-unix/X* || true
sleep 3

# Test manuel du serveur VNC d'abord
echo "Test de démarrage VNC manuel..."
if vncserver :1 -geometry 1920x1080 -depth 24 -localhost no -dpi 96 >/dev/null 2>&1; then
    echo "VNC manuel OK, arrêt pour passer au service systemd"
    vncserver -kill :1 || true
    sleep 2
else
    echo "Erreur: VNC ne peut pas démarrer manuellement"
    echo "Vérification des logs..."
    ls -la /root/.vnc/
    cat /root/.vnc/*.log 2>/dev/null || echo "Pas de logs VNC trouvés"
    exit 1
fi

# Activer et démarrer le service VNC display :1
echo "Démarrage du service VNC systemd..."
systemctl enable vncserver@1.service
systemctl start vncserver@1.service

# Vérifier que le service VNC a bien démarré
sleep 3
if systemctl is-active --quiet vncserver@1.service; then
    echo "Service VNC démarré avec succès"
else
    echo "Erreur: Le service VNC n'a pas pu démarrer"
    echo "Status du service:"
    systemctl status vncserver@1.service
    echo "Logs du service:"
    journalctl -xeu vncserver@1.service --no-pager
    exit 1
fi

# Télécharger noVNC dans /opt/novnc si non présent
if [ ! -d "/opt/novnc" ]; then
    mkdir -p /opt/novnc
    wget -qO- https://github.com/novnc/noVNC/archive/refs/tags/v1.4.0.tar.gz | tar xz --strip-components=1 -C /opt/novnc
fi

# Installation de code-server (VS Code léger)
echo "Installation de code-server..."
if [ ! -f "/usr/bin/code-server" ]; then
    curl -fsSL https://code-server.dev/install.sh | sh
fi

# Configuration de code-server
mkdir -p /root/.config/code-server /root/.local/share/code-server
cat > /root/.config/code-server/config.yaml <<'EOF'
bind-addr: 0.0.0.0:8081
auth: password
password: proxmvnc
cert: false
disable-telemetry: true
disable-update-check: true
disable-workspace-trust: true
EOF

# Création du service systemd pour code-server
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
WorkingDirectory=/root
ExecStartPre=/bin/mkdir -p /root/.local/share/code-server
ExecStartPre=/bin/chown -R root:root /root/.local/share/code-server
ExecStart=/usr/bin/code-server --config /root/.config/code-server/config.yaml
Restart=always
RestartSec=10
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
EOF

# Configuration personnalisée de noVNC pour un meilleur scaling
cat > /opt/novnc/vnc_auto.html <<'EOF'
<!DOCTYPE html>
<html>
<head>
    <title>Remote Desktop - XFCE</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" type="text/css" href="app/styles/base.css">
    <style>
        body { margin: 0; padding: 0; overflow: hidden; }
        #noVNC_container { width: 100vw; height: 100vh; }
    </style>
</head>
<body>
    <div id="noVNC_container">
        <div id="noVNC_status_bar">
            <div id="noVNC_left_dummy_elem"></div>
            <div id="noVNC_status">Loading</div>
        </div>
        <div id="noVNC_screen">
            <div id="noVNC_menu_bar">
                <div id="noVNC_control_bar">
                    <div class="noVNC_scroll">
                        <input type="button" class="noVNC_button" id="noVNC_view_drag_button"
                            value="Drag" title="Move/Drag Viewport">
                        <input type="button" class="noVNC_button" id="noVNC_clipboard_button"
                            value="Clipboard" title="Clipboard">
                        <input type="button" class="noVNC_button" id="noVNC_fullscreen_button"
                            value="Fullscreen" title="Fullscreen">
                        <input type="button" class="noVNC_button" id="noVNC_settings_button"
                            value="Settings" title="Settings">
                        <input type="button" class="noVNC_button" id="noVNC_disconnect_button"
                            value="Disconnect" title="Disconnect">
                    </div>
                </div>
            </div>
            <canvas id="noVNC_canvas" width="0" height="0" tabindex="-1">
                Canvas not supported.
            </canvas>
        </div>
    </div>

    <script type="module" crossorigin="anonymous">
        import RFB from './core/rfb.js';

        let rfb;

        function connect() {
            const host = window.location.hostname;
            const port = window.location.port || (window.location.protocol === 'https:' ? 443 : 80);
            const path = window.location.pathname.replace(/\/[^\/]*$/, '/websockify');

            const url = (window.location.protocol === 'https:' ? 'wss://' : 'ws://') +
                       host + ':' + port + path;

            rfb = new RFB(document.getElementById('noVNC_canvas'), url, {
                credentials: { password: 'proxmvnc' },
                repeaterID: '',
                shared: true,
                local_cursor: true,
                view_only: false,
                resize: 'scale',
                show_dot: false,
                background: 'rgb(40, 40, 40)',
                qualityLevel: 6,
                compressionLevel: 2
            });

            rfb.addEventListener('connect', () => {
                document.getElementById('noVNC_status').textContent = 'Connected';
                // Auto-fit to browser window
                rfb.scaleViewport = true;
                rfb.resizeSession = true;
            });

            rfb.addEventListener('disconnect', () => {
                document.getElementById('noVNC_status').textContent = 'Disconnected';
            });

            rfb.addEventListener('credentialsrequired', () => {
                rfb.sendCredentials({ password: 'proxmvnc' });
            });
        }

        // Auto-connect on page load
        window.addEventListener('load', connect);

        // Handle window resize
        window.addEventListener('resize', () => {
            if (rfb) {
                rfb.scaleViewport = true;
            }
        });
    </script>
</body>
</html>
EOF

# Création du service systemd pour noVNC
cat > /etc/systemd/system/novnc.service <<'EOF'
[Unit]
Description=noVNC WebSocket proxy
After=network.target

[Service]
Type=simple
User=root
ExecStart=/usr/bin/websockify --web=/opt/novnc --heartbeat=30 6080 localhost:5901
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
EOF

# Rechargement systemd et activation du service noVNC
systemctl daemon-reload
systemctl enable --now novnc.service

# Activation du service code-server
echo "Démarrage de code-server..."
systemctl daemon-reload
systemctl enable code-server.service

# Nettoyer les anciens processus code-server
pkill -f code-server || true
sleep 2

systemctl start code-server.service

# Vérification que code-server fonctionne
sleep 8
if systemctl is-active --quiet code-server.service; then
    echo "Code-server démarré avec succès sur le port 8081"
    echo "Test de connectivité..."
    if curl -s http://localhost:8081 >/dev/null 2>&1; then
        echo "Code-server répond correctement"
    else
        echo "Code-server ne répond pas sur le port 8081"
    fi
else
    echo "Attention: Code-server n'a pas pu démarrer"
    echo "Logs du service:"
    journalctl -xeu code-server.service --no-pager -n 30
    echo ""
    echo "Tentative de démarrage manuel pour diagnostic..."
    /usr/bin/code-server --version
    echo "Test manuel:"
    /usr/bin/code-server --bind-addr=127.0.0.1:8082 --auth=none /root &
    MANUAL_PID=$!
    sleep 5
    if curl -s http://localhost:8082 >/dev/null 2>&1; then
        echo "Le démarrage manuel fonctionne"
    else
        echo "Le démarrage manuel échoue aussi"
    fi
    kill $MANUAL_PID 2>/dev/null || true
fi

echo "Installation et configuration terminées."
echo "VNC accessible sur :5901, noVNC accessible via le port 6080."
echo "Code-server (VS Code) accessible sur le port 8081 (mot de passe: proxmvnc)"
echo ""
echo "Accès via Caddy:"
echo "- Interface XFCE: *:82"
echo "- Pour code-server, ajouter un bloc Caddy pour le port 8081"
