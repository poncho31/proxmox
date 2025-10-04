<?php
#!/usr/bin/env php

/**
 * Script de mise à jour Proxmox LXC - Serveur Web PHP
 * Accès: https://192.168.0.50/
 */

set_time_limit(60);

// ========================================
// TABLEAU DES OPÉRATIONS
// ========================================

$operations = [
    'system_update' => [
        'description' => 'Mise à jour des paquets système',
        'command' => 'apt update && apt upgrade -y',
        'success_message' => 'Système mis à jour avec succès',
        'error_message' => 'Erreur lors de la mise à jour système'
    ],
    'install_dependencies' => [
        'description' => 'Installation des dépendances requises',
        'command' => 'apt install -y nginx php8.2-fpm php8.2-common php8.2-mysql php8.2-xml php8.2-xmlrpc php8.2-curl php8.2-gd php8.2-imagick php8.2-cli php8.2-dev php8.2-imap php8.2-mbstring php8.2-opcache php8.2-soap php8.2-zip openssl curl',
        'success_message' => 'Dépendances installées avec succès',
        'error_message' => 'Erreur lors de l\'installation des dépendances'
    ],
    'configure_ip' => [
        'description' => 'Configuration de l\'IP 192.168.0.50',
        'command' => 'configureProxmoxIP',
        'success_message' => 'IP 192.168.0.50 configurée',
        'error_message' => 'Erreur configuration IP'
    ],

    'configure_nginx' => [
        'description' => 'Configuration Nginx pour 192.168.0.50',
        'command' => 'configureNginx',
        'success_message' => 'Configuration Nginx appliquée',
        'error_message' => 'Erreur configuration Nginx'
    ],
    'fix_permissions' => [
        'description' => 'Correction des permissions',
        'command' => 'chown -R www-data:www-data /var/www/html && chmod -R 755 /var/www/html',
        'success_message' => 'Permissions corrigées',
        'error_message' => 'Erreur correction permissions'
    ],
    'restart_php' => [
        'description' => 'Redémarrage PHP-FPM',
        'command' => 'systemctl restart php8.2-fpm && systemctl enable php8.2-fpm',
        'success_message' => 'PHP-FPM redémarré et activé',
        'error_message' => 'Erreur redémarrage PHP-FPM'
    ],
    'restart_nginx' => [
        'description' => 'Redémarrage Nginx',
        'command' => 'systemctl restart nginx && systemctl enable nginx',
        'success_message' => 'Nginx redémarré et activé',
        'error_message' => 'Erreur redémarrage Nginx'
    ],
    'test_connectivity' => [
        'description' => 'Test de connectivité finale',
        'command' => 'testConnectivity',
        'success_message' => 'Tests de connectivité réussis',
        'error_message' => 'Problème de connectivité détecté'
    ],

];

// ========================================
// FONCTIONS UTILITAIRES
// ========================================

function execCommand($command) {
    $output = [];
    $returnCode = 0;
    exec($command . " 2>&1", $output, $returnCode);
    return [
        'success' => $returnCode === 0,
        'output' => implode("\n", $output),
        'code' => $returnCode
    ];
}

function printStatus($message, $success = true) {
    $color = $success ? "\033[32m" : "\033[31m";
    $reset = "\033[0m";
    echo $color . $message . $reset . "\n";
}

function printInfo($message) {
    echo "\033[34m" . $message . "\033[0m\n";
}

// ========================================
// EXÉCUTION PRINCIPALE
// ========================================

echo "🚀 MISE À JOUR SERVEUR PROXMOX LXC\n";
echo "===================================\n";
echo "🌐 Configuration pour: http://192.168.0.50/\n\n";

$successful = 0;
$failed = 0;

$stepNum = 0;
foreach ($operations as $operationKey => $operation) {
    $stepNum++;
    printInfo("[$stepNum/" . count($operations) . "] [$operationKey] " . $operation['description'] . "...");
    
    // Exécuter la commande
    if (function_exists($operation['command'])) {
        $result = call_user_func($operation['command']);
    } else {
        $result = execCommand($operation['command']);
    }
    
    if ($result['success']) {
        printStatus("✅ " . $operation['success_message']);
        $successful++;
        
        // Afficher sortie si pertinente
        if (!empty($result['output']) && 
            !in_array(trim($result['output']), ['', 'OK', 'Done'])) {
            echo "   " . trim($result['output']) . "\n";
        }
    } else {
        printStatus("❌ " . $operation['error_message'], false);
        $failed++;
        
        if (!empty($result['output'])) {
            echo "   Erreur: " . trim($result['output']) . "\n";
        }
        
        // Optionnel: arrêter en cas d'erreur critique
        if (in_array($operationKey, ['system_update', 'install_dependencies'])) {
            printStatus("⚠️ Erreur critique détectée, arrêt du processus", false);
            break;
        }
    }
    
    echo "\n";
}

// ========================================
// RÉSUMÉ FINAL
// ========================================

echo "===================================\n";
echo "📊 RÉSUMÉ: $successful réussies / $failed échouées\n";
echo "===================================\n";

if ($failed === 0) {
    printStatus("🎉 MISE À JOUR TERMINÉE AVEC SUCCÈS !");
    echo "\n🌐 Serveur accessible sur: http://192.168.0.50/\n";
    echo "📝 Todo List sur: http://192.168.0.50:8080/\n";
    echo "\n💡 Commandes utiles:\n";
    echo "   - systemctl status nginx php8.2-fpm\n";
    echo "   - curl http://192.168.0.50/\n";
} else {
    printStatus("⚠️ MISE À JOUR PARTIELLEMENT RÉUSSIE", false);
    echo "\n🔧 Vérifiez les erreurs et relancez si nécessaire\n";
}

exit($failed > 0 ? 1 : 0);

// ========================================
// FONCTIONS COMPLEXES
// ========================================

function configureProxmoxIP() {
    echo "=== CONFIGURATION IP 192.168.0.50 ===\n";
    
    // Afficher les interfaces actuelles
    $interfaces = execCommand('ip addr show | grep "inet " | grep -v "127.0.0.1"');
    echo "Interfaces actuelles:\n" . $interfaces['output'] . "\n";
    
    // Vérifier si l'IP existe déjà
    $checkIP = execCommand('ip addr show | grep "192.168.0.50"');
    if (!empty($checkIP['output'])) {
        echo "IP 192.168.0.50 déjà configurée\n";
        return ['success' => true, 'output' => 'IP 192.168.0.50 déjà configurée'];
    }
    
    // Détecter l'interface réseau principale
    $interface = execCommand('ip route | grep default | awk \'{print $5}\' | head -1');
    $iface = trim($interface['output']);
    
    if (empty($iface)) {
        // Essayer de détecter eth0 ou la première interface
        $firstIface = execCommand('ip link show | grep "^[0-9]" | grep -v "lo:" | head -1 | awk -F": " \'{print $2}\'');
        $iface = trim($firstIface['output']);
        if (empty($iface)) {
            $iface = 'eth0'; // Défaut
        }
    }
    
    echo "Interface détectée: $iface\n";
    
    // Tentatives d'ajout de l'IP avec différentes méthodes
    $methods = [
        "ip addr add 192.168.0.50/24 dev $iface",
        "ip addr add 192.168.0.50/32 dev $iface",
        "ifconfig $iface:1 192.168.0.50 netmask 255.255.255.0"
    ];
    
    foreach ($methods as $method) {
        echo "Essai: $method\n";
        $result = execCommand($method);
        
        // Vérifier si l'ajout a réussi
        $verify = execCommand('ip addr show | grep "192.168.0.50"');
        if (!empty($verify['output'])) {
            echo "✅ IP 192.168.0.50 ajoutée avec succès\n";
            return ['success' => true, 'output' => "IP 192.168.0.50 ajoutée sur $iface"];
        }
        
        if (!$result['success'] && !strpos($result['output'], 'File exists')) {
            echo "Échec: " . $result['output'] . "\n";
        }
    }
    
    // Si toutes les méthodes échouent, donner des instructions
    echo "⚠️ Impossible d'ajouter automatiquement l'IP 192.168.0.50\n";
    echo "📝 Instructions manuelles:\n";
    echo "1. Dans Proxmox Web UI > Container > Network\n";
    echo "2. Ajouter une IP statique: 192.168.0.50/24\n";
    echo "3. Ou dans le conteneur: ip addr add 192.168.0.50/24 dev $iface\n";
    
    return ['success' => false, 'output' => 'Configuration manuelle requise pour IP 192.168.0.50'];
}



function configureNginx() {
    $config = 'user www-data;
worker_processes auto;
pid /run/nginx.pid;

events {
    worker_connections 1024;
    use epoll;
}

http {
    include /etc/nginx/mime.types;
    default_type application/octet-stream;
    
    sendfile on;
    tcp_nopush on;
    tcp_nodelay on;
    keepalive_timeout 65;
    
    access_log /var/log/nginx/access.log;
    error_log /var/log/nginx/error.log;
    
    gzip on;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml;

    # Serveur HTTP principal pour 192.168.0.50
    server {
        listen 80;
        listen 192.168.0.50:80;
        server_name 192.168.0.50 localhost _;
        root /var/www/html/php/public;
        index proxmox_main_web_server.php index.php index.html;

        # Configuration PHP
        location / {
            try_files $uri $uri/ /proxmox_main_web_server.php?$query_string;
        }

        location ~ \.php$ {
            include snippets/fastcgi-php.conf;
            fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
            fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
            include fastcgi_params;
        }

        # Sécurité
        location ~ /\. {
            deny all;
        }
        
        location ~* \.(jpg|jpeg|png|gif|ico|css|js)$ {
            expires 1y;
            add_header Cache-Control "public, immutable";
        }
    }

    # Todo List sur port 8080
    server {
        listen 8080;
        listen 192.168.0.50:8080;
        server_name 192.168.0.50 localhost _;
        root /var/www/html/php/public;
        index todo_list.php;

        location / {
            try_files $uri $uri/ /todo_list.php?$query_string;
        }

        location ~ \.php$ {
            include snippets/fastcgi-php.conf;
            fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
            fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
            include fastcgi_params;
        }

        location ~ /\. {
            deny all;
        }
    }
}';
    
    // Sauvegarder la config actuelle
    execCommand('cp /etc/nginx/nginx.conf /etc/nginx/nginx.conf.backup');
    
    // Écrire la nouvelle configuration
    file_put_contents('/etc/nginx/nginx.conf', $config);
    
    // Tester la configuration
    $test = execCommand('nginx -t');
    if (!$test['success']) {
        // Restaurer la sauvegarde en cas d'erreur
        execCommand('cp /etc/nginx/nginx.conf.backup /etc/nginx/nginx.conf');
        return ['success' => false, 'output' => 'Configuration Nginx invalide: ' . $test['output']];
    }
    
    return ['success' => true, 'output' => 'Configuration Nginx HTTP créée et validée'];
}

function testConnectivity() {
    echo "=== DIAGNOSTIC RÉSEAU HTTP ===\n";
    
    // Vérifier les interfaces réseau
    $interfaces = execCommand('ip addr show | grep "inet " | grep -v 127.0.0.1');
    echo "Interfaces réseau:\n" . $interfaces['output'] . "\n";
    
    // Vérifier si 192.168.0.50 est configurée
    $checkIP = execCommand('ip addr show | grep "192.168.0.50"');
    echo "IP 192.168.0.50: " . (!empty($checkIP['output']) ? "✅ CONFIGURÉE" : "❌ NON CONFIGURÉE") . "\n";
    
    if (empty($checkIP['output'])) {
        echo "⚠️ L'IP 192.168.0.50 n'est pas configurée sur ce conteneur\n";
        echo "💡 Configurer dans Proxmox Web UI ou manuellement:\n";
        echo "   ip addr add 192.168.0.50/24 dev eth0\n";
    }
    
    // Vérifier les ports ouverts
    $ports = execCommand('ss -tlnp | grep nginx');
    echo "Ports Nginx:\n" . $ports['output'] . "\n";
    
    // Test HTTP local
    $httpLocal = execCommand('curl -I -s http://localhost --connect-timeout 5');
    $localOK = strpos($httpLocal['output'], '200 OK') !== false;
    
    // Test HTTP sur IP
    $httpIP = execCommand('curl -I -s http://192.168.0.50 --connect-timeout 5');
    $ipOK = strpos($httpIP['output'], '200 OK') !== false;
    
    // Afficher détails si erreur
    if (!$localOK) {
        echo "Détail erreur localhost:\n" . trim($httpLocal['output']) . "\n";
    }
    if (!$ipOK && !empty($httpIP['output'])) {
        echo "Détail erreur 192.168.0.50:\n" . trim($httpIP['output']) . "\n";
    }
    
    // Test des services
    $nginxStatus = execCommand('systemctl is-active nginx');
    $phpStatus = execCommand('systemctl is-active php8.2-fpm');
    
    $messages = [];
    $messages[] = "HTTP localhost: " . ($localOK ? "✅" : "❌");
    $messages[] = "HTTP 192.168.0.50: " . ($ipOK ? "✅" : "❌");
    $messages[] = "Nginx: " . ($nginxStatus['success'] ? "✅" : "❌");
    $messages[] = "PHP-FPM: " . ($phpStatus['success'] ? "✅" : "❌");
    
    $allOK = $localOK && $ipOK && $nginxStatus['success'] && $phpStatus['success'];
    
    return [
        'success' => $allOK,
        'output' => implode(", ", $messages)
    ];
}



?>
