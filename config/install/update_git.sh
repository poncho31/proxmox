#!/bin/bash
# Module: update_git.sh
# Description: Update the application from Git repository

update_from_git() {
    echo "==> Updating from Git repository..."
    
    cd /var/www/proxmox
    git reset --hard
    git pull origin switch_to_rust
    
    echo "==> Git update completed"
}
