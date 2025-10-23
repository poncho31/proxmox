#!/bin/bash
# Module: setup_permissions.sh
# Description: Set up file permissions for the application

setup_permissions() {
    echo "==> Setting up file permissions..."
    
    # Make scripts executable
    chmod +x config/*.sh
    chmod +x config/install/*.sh
    
    echo "==> File permissions configured successfully"
}
