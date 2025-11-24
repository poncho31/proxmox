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
{
	admin off
}

# Hub Proxmox
https://$CADDY_MAIN_IP {
    tls internal
    basicauth * {
        $CADDY_USER $HASH
    }
    reverse_proxy localhost:8080
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

# AI STABLE DIFFUSION API
http://$CADDY_MAIN_IP:84 {
	log {
		output stdout
		format console
		level DEBUG
	}

	@allow_from_caddy remote_ip $CADDY_MAIN_IP
	@sd path /sdapi/*

	handle @allow_from_caddy {
		handle @sd {
			reverse_proxy http://192.168.0.52:82 {
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

		respond "Bad path for Stable Diffusion" 404
	}

	respond "Unauthorized PROXMOX" 403
}


# COMFYUI : caddy run --config H:\LIBRAIRIES\Caddy\Caddyfile
https://$CADDY_MAIN_IP:86 {
    tls internal

    basicauth * {
        $CADDY_USER $HASH
    }

    reverse_proxy https://$COMFYUI_IP:$COMFYUI_PORT {
        transport http {
            tls_insecure_skip_verify
        }
        header_up Host $COMFYUI_IP:$COMFYUI_PORT
        header_up Origin https://$COMFYUI_IP:$COMFYUI_PORT
        header_up Referer https://$COMFYUI_IP:$COMFYUI_PORT
        header_up X-Forwarded-Host {http.request.host}
        header_up X-Forwarded-Proto {http.request.scheme}
    }
}

EOF

    # Restart Caddy to apply configuration
    systemctl restart caddy

    echo "==> Caddy installation and configuration completed"
}
