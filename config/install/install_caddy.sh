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
EOF

    # Restart Caddy to apply configuration
    systemctl restart caddy
    
    echo "==> Caddy installation and configuration completed"
}
