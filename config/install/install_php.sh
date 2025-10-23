#!/bin/bash
# Module: install_php.sh
# Description: Install PHP and essential libraries

install_php() {
    echo "==> Installing PHP and essential libraries..."
    
    # Install PHP and essential libraries (WITHOUT Apache!)
    apt install php php-mysql php-curl php-json php-mbstring php-xml php-zip php-gd php-intl php-bcmath php-soap php-sqlite3 php-cli php-common php-opcache php-fpm -y

    # Enable and start PHP-FPM
    systemctl enable --now php8.4-fpm
    
    echo "==> PHP installation completed successfully"
}
