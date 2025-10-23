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

https://$CADDY_MAIN_IP:81 {
    tls internal
    basicauth * {
        $CADDY_USER $HASH
    }
    
    @websockets {
        header Connection *Upgrade*
        header Upgrade websocket
    }
    
    reverse_proxy @websockets localhost:8081
    reverse_proxy localhost:8081 {
        header_up Host {upstream_hostport}
        header_up X-Real-IP {remote_host}
        header_up X-Forwarded-For {remote_host}
        header_up X-Forwarded-Proto {scheme}
    }
    
    header {
        X-Content-Type-Options nosniff
        Referrer-Policy strict-origin-when-cross-origin
    }
}
EOF

    # Restart Caddy to apply configuration
    systemctl restart caddy
    
    echo "==> Caddy installation and configuration completed"
}
