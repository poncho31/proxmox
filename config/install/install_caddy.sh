#!/bin/bash
# Module: install_caddy.sh
# Description: Install and configure Caddy web server

install_and_configure_caddy() {
    echo "==> Installing and configuring Caddy..."
    
    # Install caddy
    apt install caddy -y

    # Generate password hash
    HASH=$(caddy hash-password --plaintext "$CADDY_PASSWORD")
    
    # Create Caddyfile configuration
    cat > /etc/caddy/Caddyfile << EOF
# Hub Proxmox
https://$CADDY_MAIN_IP {
    tls internal
    basicauth * {
        $CADDY_USER $HASH
    }
    root * /var/www/proxmox/public
    php_fastcgi unix//run/php/php8.4-fpm.sock
    file_server
    try_files {path} proxmox_main_web_server.php
}

# VS Code Web
https://$CADDY_MAIN_IP:81 {
    tls internal
    basicauth * {
        $CADDY_USER $HASH
    }
    reverse_proxy $VSCODE_IP:$VSCODE_PORT
}

# Interface Go2rtc pour gestion de flux vidéo en temps réel (ex: caméra)
https://$CADDY_MAIN_IP:82 {
    tls internal
    
    basicauth * {
        $CADDY_USER $HASH
    }

    # Headers pour iframe
    header {
        X-Frame-Options "SAMEORIGIN"
        Content-Security-Policy "frame-ancestors 'self' https://$CADDY_MAIN_IP"
    }

    reverse_proxy http://$GO2RTC_IP:$GO2RTC_PORT {
        transport http {
            versions h1
        }
    }
}
EOF

    # Restart Caddy to apply configuration
    systemctl restart caddy
    
    echo "==> Caddy installation and configuration completed"
}
