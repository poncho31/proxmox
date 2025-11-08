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
    try_files {path} index.php
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

# AI API
http://$CADDY_MAIN_IP:83 {
  @allow_from_caddy remote_ip $CADDY_MAIN_IP

  handle @allow_from_caddy {
    reverse_proxy http://$AI_IP:$AI_PORT {
      flush_interval -1
      transport http {
        versions h1
      }
      header_up Host {http.reverse_proxy.upstream.hostport}
      header_up X-Real-IP {http.request.remote}
      header_up X-Forwarded-For {http.request.remote}
      header_up X-Forwarded-Proto {http.request.scheme}
    }
  }

  respond "Unauthorized PROXMOX" 403
}


EOF

    # Restart Caddy to apply configuration
    systemctl restart caddy

    echo "==> Caddy installation and configuration completed"
}


# configure_caddy_tailscale_SSL_certs() {
#     set -euo pipefail

#     DOMAIN="$TAILSCALE_DNS"
#     CERT_DIR="/etc/caddy/certs"
#     CERT_FILE="$CERT_DIR/proxmox.crt"
#     KEY_FILE="$CERT_DIR/proxmox.key"
#     LOG_FILE="/var/log/caddy-cert.log"
#     CADDY_JSON="/etc/caddy/caddy.json"

#     mkdir -p "$CERT_DIR"

#     echo "[$(date)] Génération du certificat Tailscale pour $DOMAIN" | tee -a "$LOG_FILE"
#     tailscale cert "$DOMAIN" --cert-file "$CERT_FILE" --key-file "$KEY_FILE" | tee -a "$LOG_FILE"

#     echo "[$(date)] Vérification du certificat..." | tee -a "$LOG_FILE"
#     openssl x509 -in "$CERT_FILE" -noout -subject -issuer | tee -a "$LOG_FILE"

#     echo "[$(date)] Injection de la configuration JSON dans Caddy" | tee -a "$LOG_FILE"
#     cat > "$CADDY_JSON" <<EOF
#     {
#     "apps": {
#         "tls": {
#         "certificates": {
#             "load_files": [
#             {
#                 "certificate": "$CERT_FILE",
#                 "key": "$KEY_FILE",
#                 "tags": ["proxmox-cert"]
#             }
#             ]
#         },
#         "automation": {
#             "policies": [
#             {
#                 "subjects": ["$DOMAIN"],
#                 "management": "manual"
#             }
#             ]
#         }
#         },
#         "http": {
#         "servers": {
#             "proxmox": {
#             "listen": [":443"],
#             "routes": [
#                 {
#                 "match": [
#                     {
#                     "host": ["$DOMAIN"]
#                     }
#                 ],
#                 "handle": [
#                     {
#                     "handler": "subroute",
#                     "routes": [
#                         {
#                         "handle": [
#                             {
#                             "handler": "authentication",
#                             "providers": {
#                                 "http_basic": {
#                                 "accounts": [
#                                     {
#                                     "username": "root",
#                                     "password": "$2a$14$23l6zMSbktwKqcJdwyJZnecVIyDabKwpbPjdoCxo.k0Mn2BUKthky"
#                                     }
#                                 ]
#                                 }
#                             }
#                             }
#                         ]
#                         },
#                         {
#                         "handle": [
#                             {
#                             "handler": "file_server",
#                             "root": "/var/www/proxmox/public"
#                             },
#                             {
#                             "handler": "php_fastcgi",
#                             "root": "/var/www/proxmox/public",
#                             "socket": "/run/php/php8.4-fpm.sock"
#                             }
#                         ]
#                         }
#                     ]
#                     }
#                 ],
#                 "tls_connection_policies": [
#                     {
#                     "certificate_selection": {
#                         "any_tag": ["proxmox-cert"]
#                     }
#                     }
#                 ],
#                 "terminal": true
#                 }
#             ]
#             }
#         }
#         }
#     }
#     }
# EOF

#     echo "[$(date)] Rechargement de Caddy..." | tee -a "$LOG_FILE"
#     caddy reload --config "$CADDY_JSON" | tee -a "$LOG_FILE"

#     echo "[$(date)] ✅ Certificat TLS Tailscale appliqué avec succès à Caddy" | tee -a "$LOG_FILE"

# }

