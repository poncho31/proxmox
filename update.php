<?php
#!/usr/bin/env php

/**
 * Script de mise à jour et diagnostic Proxmox - Version simplifiée
 */

set_time_limit(60);

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

function getPhpService() {
    $services = ['php8.2-fpm', 'php8.1-fpm', 'php8.0-fpm', 'php7.4-fpm', 'php-fpm'];
    
    foreach ($services as $service) {
        $result = execCommand("systemctl is-active $service");
        if ($result['success'] || strpos($result['output'], 'inactive') !== false) {
            return $service;
        }
    }
    return 'php8.2-fpm'; // Défaut
}

function getPhpSocket() {
    $sockets = [
        '/var/run/php/php8.2-fpm.sock',
        '/var/run/php/php8.1-fpm.sock',
        '/var/run/php/php8.0-fpm.sock',
        '/var/run/php/php7.4-fpm.sock',
        '/run/php/php-fpm.sock'
    ];
    
    foreach ($sockets as $socket) {
        if (file_exists($socket)) {
            return $socket;
        }
    }
    return '/var/run/php/php8.2-fpm.sock'; // Défaut
}

// ========================================
// OPÉRATIONS UNIFIÉES
// ========================================

$operations = [
    'diagnostic' => [
        'description' => 'Diagnostic complet du système',
        'command' => function() {
            echo "\n=== DIAGNOSTIC SYSTÈME ===\n";
            
            // Test nginx
            $nginx = execCommand('systemctl is-active nginx');
            echo "Nginx: " . ($nginx['success'] ? "✅ ACTIF" : "❌ INACTIF") . "\n";
            
            // Test configuration nginx
            $nginx_test = execCommand('nginx -t');
            echo "Config nginx: " . ($nginx_test['success'] ? "✅ VALIDE" : "❌ ERREURS") . "\n";
            if (!$nginx_test['success']) {
                echo "Erreurs config: " . $nginx_test['output'] . "\n";
            }
            
            // Test ports spécifiques
            $port80 = execCommand('ss -tlnp | grep ":80 "');
            $port443 = execCommand('ss -tlnp | grep ":443 "');
            echo "Port 80: " . (!empty($port80['output']) ? "✅ OUVERT" : "❌ FERMÉ") . "\n";
            echo "Port 443: " . (!empty($port443['output']) ? "✅ OUVERT" : "❌ FERMÉ") . "\n";
            
            // Afficher tous les ports nginx
            $all_ports = execCommand('ss -tlnp | grep nginx');
            echo "Tous ports nginx:\n" . $all_ports['output'] . "\n";
            
            // Test certificats SSL
            $ssl_cert = file_exists('/etc/ssl/certs/nginx-selfsigned.crt');
            $ssl_key = file_exists('/etc/ssl/private/nginx-selfsigned.key');
            echo "Certificat SSL: " . ($ssl_cert ? "✅ EXISTE" : "❌ MANQUANT") . "\n";
            echo "Clé SSL: " . ($ssl_key ? "✅ EXISTE" : "❌ MANQUANTE") . "\n";
            
            // Test validité certificat
            if ($ssl_cert) {
                $cert_info = execCommand('openssl x509 -in /etc/ssl/certs/nginx-selfsigned.crt -text -noout | grep "Subject:"');
                echo "Info certificat: " . $cert_info['output'] . "\n";
            }
            
            // Test PHP-FPM
            $php_services = ['php8.2-fpm', 'php8.1-fpm', 'php8.0-fpm', 'php-fpm'];
            $php_active = null;
            foreach ($php_services as $service) {
                $result = execCommand("systemctl is-active $service");
                if ($result['success']) {
                    $php_active = $service;
                    break;
                }
            }
            echo "PHP-FPM: " . ($php_active ? "✅ $php_active ACTIF" : "❌ AUCUN SERVICE ACTIF") . "\n";
            
            // Test sockets PHP
            $sockets = ['/var/run/php/php8.2-fpm.sock', '/run/php/php-fpm.sock'];
            foreach ($sockets as $socket) {
                $exists = file_exists($socket);
                echo "Socket $socket: " . ($exists ? "✅ EXISTE" : "❌ MANQUANT") . "\n";
            }
            
            // Test connectivité détaillée
            echo "\n--- Tests connectivité ---\n";
            $http_local = execCommand('curl -I -s http://localhost');
            echo "HTTP localhost: " . trim($http_local['output']) . "\n";
            
            $https_local = execCommand('curl -I -s -k https://localhost');
            echo "HTTPS localhost: " . trim($https_local['output']) . "\n";
            
            $https_ip = execCommand('curl -I -s -k https://192.168.0.50');
            echo "HTTPS 192.168.0.50: " . trim($https_ip['output']) . "\n";
            
            // Test interface réseau
            echo "\n--- Interfaces réseau ---\n";
            $ip_info = execCommand('ip addr show | grep "inet " | grep -v 127.0.0.1');
            echo $ip_info['output'] . "\n";
            
            // Logs récents
            echo "\n--- Logs nginx récents ---\n";
            $logs = execCommand('tail -10 /var/log/nginx/error.log 2>/dev/null');
            if (!empty($logs['output'])) {
                echo $logs['output'] . "\n";
            } else {
                echo "Aucun log d'erreur récent\n";
            }
            
            return 'echo "Diagnostic terminé"';
        },
        'icon' => '🔍',
        'success_message' => 'Diagnostic système terminé',
        'error_message' => 'Échec du diagnostic'
    ],
    
    'ssl_cert' => [
        'description' => 'Génération du certificat SSL',
        'command' => function() {
            if (file_exists('/etc/ssl/certs/nginx-selfsigned.crt')) {
                $result = execCommand("openssl x509 -in /etc/ssl/certs/nginx-selfsigned.crt -checkend 2592000 -noout");
                if ($result['success']) {
                    return null; // Skip si le certificat est encore valide
                }
            }
            return 'mkdir -p /etc/ssl/private /etc/ssl/certs && openssl req -x509 -nodes -days 365 -newkey rsa:2048 -keyout /etc/ssl/private/nginx-selfsigned.key -out /etc/ssl/certs/nginx-selfsigned.crt -subj "/C=FR/ST=France/L=Paris/O=Proxmox/OU=IT Department/CN=192.168.0.50" && chmod 600 /etc/ssl/private/nginx-selfsigned.key && chmod 644 /etc/ssl/certs/nginx-selfsigned.crt';
        },
        'icon' => '🔒',
        'success_message' => 'Certificat SSL généré',
        'error_message' => 'Échec génération SSL',
        'skip_message' => 'Certificat SSL encore valide'
    ],
    
    'copy_nginx_config' => [
        'description' => 'Copie configuration Nginx corrigée',
        'command' => function() {
            // Copie directe du fichier de config du projet
            return 'cp /var/www/html/php/config/nginx.conf /etc/nginx/nginx.conf && nginx -t';
        },
        'icon' => '📋',
        'success_message' => 'Configuration nginx copiée',
        'error_message' => 'Échec copie config nginx'
    ],
    
    'fix_nginx_config' => [
        'description' => 'Génération configuration Nginx optimisée',
        'command' => function() {
            $socket = getPhpSocket();
            
            $config = 'user www-data;
worker_processes auto;
pid /run/nginx.pid;

events {
    worker_connections 1024;
}

http {
    include /etc/nginx/mime.types;
    default_type application/octet-stream;
    sendfile on;
    keepalive_timeout 65;
    
    access_log /var/log/nginx/access.log;
    error_log /var/log/nginx/error.log;

    # Serveur HTTP - Redirection HTTPS
    server {
        listen 80;
        server_name 192.168.0.50 localhost _;
        return 301 https://$host$request_uri;
    }

    # Serveur HTTPS principal
    server {
        listen 443 ssl http2;
        server_name 192.168.0.50 localhost _;
        root /var/www/html/php/public;
        index proxmox_main_web_server.php;

        ssl_certificate /etc/ssl/certs/nginx-selfsigned.crt;
        ssl_certificate_key /etc/ssl/private/nginx-selfsigned.key;
        ssl_protocols TLSv1.2 TLSv1.3;
        ssl_ciphers ECDHE-RSA-AES128-GCM-SHA256:ECDHE-RSA-AES256-GCM-SHA384;
        ssl_prefer_server_ciphers on;

        location / {
            try_files $uri $uri/ =404;
        }

        location ~ \.php$ {
            fastcgi_split_path_info ^(.+\.php)(/.+)$;
            fastcgi_pass unix:' . $socket . ';
            fastcgi_index index.php;
            include fastcgi_params;
            fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
            fastcgi_param PATH_INFO $fastcgi_path_info;
        }

        location ~ /\. {
            deny all;
        }
    }

    # Todo List sur port 8443
    server {
        listen 8443 ssl;
        server_name 192.168.0.50;
        root /var/www/html/php/public;
        index todo_list.php;

        ssl_certificate /etc/ssl/certs/nginx-selfsigned.crt;
        ssl_certificate_key /etc/ssl/private/nginx-selfsigned.key;
        ssl_protocols TLSv1.2 TLSv1.3;

        location / {
            try_files $uri $uri/ =404;
        }

        location ~ \.php$ {
            fastcgi_pass unix:' . $socket . ';
            include fastcgi_params;
            fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        }
    }
}';
            
            file_put_contents('/tmp/nginx_fixed.conf', $config);
            return 'cp /tmp/nginx_fixed.conf /etc/nginx/nginx.conf && nginx -t';
        },
        'icon' => '⚙️',
        'success_message' => 'Configuration Nginx corrigée',
        'error_message' => 'Échec correction nginx'
    ],
    
    'restart_php' => [
        'description' => 'Redémarrage PHP-FPM',
        'command' => function() {
            $service = getPhpService();
            return "systemctl restart $service";
        },
        'icon' => '🔄',
        'success_message' => 'PHP-FPM redémarré',
        'error_message' => 'Échec redémarrage PHP'
    ],
    
    'restart_nginx' => [
        'description' => 'Redémarrage Nginx',
        'command' => 'systemctl restart nginx',
        'icon' => '🌐',
        'success_message' => 'Nginx redémarré',
        'error_message' => 'Échec redémarrage Nginx'
    ],
    
    'fix_permissions' => [
        'description' => 'Correction des permissions',
        'command' => 'chown -R www-data:www-data /var/www/html/php && chmod -R 755 /var/www/html/php',
        'icon' => '🔐',
        'success_message' => 'Permissions corrigées',
        'error_message' => 'Échec permissions'
    ],
    
    'test_final' => [
        'description' => 'Tests de connectivité final',
        'command' => function() {
            echo "\n=== TESTS FINAUX ===\n";
            
            // Test local HTTPS
            $https_local = execCommand('curl -I -s -k https://localhost');
            $local_ok = strpos($https_local['output'], '200') !== false;
            echo "HTTPS localhost: " . ($local_ok ? "✅ OK" : "❌ KO") . "\n";
            
            // Test IP externe
            $https_external = execCommand('curl -I -s -k https://192.168.0.50');
            $external_ok = strpos($https_external['output'], '200') !== false;
            echo "HTTPS 192.168.0.50: " . ($external_ok ? "✅ ACCESSIBLE" : "❌ INACCESSIBLE") . "\n";
            
            if (!$local_ok) {
                echo "Détails localhost: " . $https_local['output'] . "\n";
            }
            if (!$external_ok) {
                echo "Détails 192.168.0.50: " . $https_external['output'] . "\n";
            }
            
            // Test des services
            $nginx = execCommand('systemctl is-active nginx');
            $php = execCommand('systemctl is-active ' . getPhpService());
            echo "Services - Nginx: " . ($nginx['success'] ? "✅" : "❌") . " PHP: " . ($php['success'] ? "✅" : "❌") . "\n";
            
            return 'echo "Tests terminés"';
        },
        'icon' => '🧪',
        'success_message' => 'Tests finaux terminés',
        'error_message' => 'Problèmes détectés'
    ]
];

// ========================================
// EXÉCUTION
// ========================================

echo "🚀 MAINTENANCE PROXMOX - VERSION SIMPLIFIÉE\n";
echo "==========================================\n\n";

$results = [];
$successful = 0;
$failed = 0;
$skipped = 0;

foreach ($operations as $key => $operation) {
    $icon = $operation['icon'];
    $description = $operation['description'];
    
    printInfo("$icon $description...");
    
    $command = $operation['command'];
    if (is_callable($command)) {
        $command = $command();
    }
    
    if ($command === null) {
        $skipMessage = $operation['skip_message'] ?? 'Opération ignorée';
        printStatus("⏭️ $skipMessage");
        $results[$key] = ['success' => true, 'skipped' => true];
        $skipped++;
    } else {
        $result = execCommand($command);
        $results[$key] = $result;
        
        if ($result['success']) {
            printStatus("✅ " . $operation['success_message']);
            $successful++;
            
            if (!empty($result['output']) && $result['output'] !== 'Diagnostic terminé' && $result['output'] !== 'Tests terminés') {
                echo "   " . $result['output'] . "\n";
            }
        } else {
            printStatus("❌ " . $operation['error_message'], false);
            $failed++;
            
            if (!empty($result['output'])) {
                echo "   Erreur: " . $result['output'] . "\n";
            }
        }
    }
    
    echo "\n";
}

// ========================================
// RÉSUMÉ
// ========================================

echo "==========================================\n";
echo "📊 RÉSUMÉ: $successful réussies, $failed échouées, $skipped ignorées\n";
echo "==========================================\n";

if ($failed === 0) {
    printStatus("🎉 MAINTENANCE TERMINÉE AVEC SUCCÈS !");
    echo "🌐 Votre serveur devrait être accessible sur : https://192.168.0.50\n";
    echo "📝 Todo List disponible sur : https://192.168.0.50:8443\n";
} else {
    printStatus("⚠️ MAINTENANCE PARTIELLEMENT RÉUSSIE", false);
    echo "Vérifiez les erreurs ci-dessus.\n";
}

echo "\n🔧 COMMANDES DE DÉPANNAGE UTILES :\n";
echo "- systemctl status nginx php8.2-fpm\n";
echo "- nginx -t\n";
echo "- curl -I -k https://localhost\n";
echo "- tail -20 /var/log/nginx/error.log\n";

exit($failed > 0 ? 1 : 0);
?>
