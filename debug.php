<?php
#!/usr/bin/env php

/**
 * Script de diagnostic pour identifier les problèmes de serveur
 */

echo "🔍 DIAGNOSTIC DU SERVEUR PROXMOX\n";
echo "================================\n\n";

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

function checkService($service) {
    $result = execCommand("systemctl is-active $service");
    $status = $result['success'] ? '✅ Actif' : '❌ Inactif';
    echo "  $service: $status\n";
    
    if (!$result['success']) {
        $statusResult = execCommand("systemctl status $service --no-pager -l");
        echo "    Détails: " . trim($statusResult['output']) . "\n";
    }
}

function checkPort($port) {
    $result = execCommand("ss -tlnp | grep :$port");
    if ($result['success'] && !empty($result['output'])) {
        echo "  Port $port: ✅ Ouvert\n";
        echo "    " . trim($result['output']) . "\n";
    } else {
        echo "  Port $port: ❌ Fermé ou non utilisé\n";
    }
}

// Vérification des services
echo "📋 STATUT DES SERVICES\n";
echo "----------------------\n";
checkService('nginx');
checkService('php8.2-fpm');
checkService('php8.1-fpm');
checkService('php8.0-fpm');
checkService('php7.4-fpm');

echo "\n";

// Vérification des ports
echo "🌐 PORTS RÉSEAU\n";
echo "---------------\n";
checkPort('80');
checkPort('443');
checkPort('8006'); // Proxmox Web UI
checkPort('22');   // SSH

echo "\n";

// Vérification des certificats SSL
echo "🔒 CERTIFICATS SSL\n";
echo "------------------\n";
if (file_exists('/etc/ssl/certs/nginx-selfsigned.crt')) {
    echo "  Certificat SSL: ✅ Présent\n";
    $result = execCommand("openssl x509 -in /etc/ssl/certs/nginx-selfsigned.crt -text -noout | grep 'Not After'");
    if ($result['success']) {
        echo "    Expiration: " . trim($result['output']) . "\n";
    }
} else {
    echo "  Certificat SSL: ❌ Absent\n";
}

echo "\n";

// Vérification de la configuration Nginx
echo "⚙️ CONFIGURATION NGINX\n";
echo "----------------------\n";
$result = execCommand("nginx -t");
if ($result['success']) {
    echo "  Configuration: ✅ Valide\n";
} else {
    echo "  Configuration: ❌ Erreur\n";
    echo "    " . trim($result['output']) . "\n";
}

echo "\n";

// Vérification des fichiers web
echo "📁 FICHIERS WEB\n";
echo "---------------\n";
$webRoot = '/var/www/html/php/public';
if (file_exists($webRoot . '/proxmox_main_web_server.php')) {
    echo "  Fichier principal: ✅ Présent\n";
    $perms = execCommand("ls -la $webRoot/proxmox_main_web_server.php");
    echo "    " . trim($perms['output']) . "\n";
} else {
    echo "  Fichier principal: ❌ Absent\n";
}

echo "\n";

// Vérification réseau
echo "🌍 CONNECTIVITÉ RÉSEAU\n";
echo "----------------------\n";
$result = execCommand("ip addr show | grep 'inet ' | grep -v '127.0.0.1'");
if ($result['success']) {
    echo "  Adresses IP:\n";
    $lines = explode("\n", $result['output']);
    foreach ($lines as $line) {
        if (!empty(trim($line))) {
            echo "    " . trim($line) . "\n";
        }
    }
} else {
    echo "  ❌ Impossible de récupérer les adresses IP\n";
}

echo "\n";

// Test de connectivité local
echo "🔗 TESTS DE CONNECTIVITÉ\n";
echo "-------------------------\n";
$result = execCommand("curl -I -k https://localhost 2>/dev/null | head -1");
if ($result['success'] && !empty($result['output'])) {
    echo "  HTTPS local: ✅ " . trim($result['output']) . "\n";
} else {
    echo "  HTTPS local: ❌ Pas de réponse\n";
}

$result = execCommand("curl -I http://localhost 2>/dev/null | head -1");
if ($result['success'] && !empty($result['output'])) {
    echo "  HTTP local: ✅ " . trim($result['output']) . "\n";
} else {
    echo "  HTTP local: ❌ Pas de réponse\n";
}

echo "\n";
echo "🏁 Diagnostic terminé\n";
echo "\n💡 Conseils:\n";
echo "  - Si Nginx est inactif: systemctl start nginx\n";
echo "  - Si PHP-FPM est inactif: systemctl start php-fpm\n";
echo "  - Si les ports sont fermés: vérifier le firewall\n";
echo "  - Pour voir les logs: journalctl -u nginx -f\n";
echo "\n";
