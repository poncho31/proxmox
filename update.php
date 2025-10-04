<?php
#!/usr/bin/env php

/**
 * Script de mise à jour automatique Proxmox avec support SSL
 * Version simplifiée - Tout en un
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
    return null;
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

function checkSslCertificate() {
    // Vérifier si le certificat existe et est valide
    if (file_exists('/etc/ssl/certs/nginx-selfsigned.crt')) {
        $result = execCommand("openssl x509 -in /etc/ssl/certs/nginx-selfsigned.crt -checkend 2592000 -noout");
        return $result['success']; // Retourne true si le certificat est valide pour au moins 30 jours
    }
    return false;
}

// ========================================
// CONFIGURATION DES OPÉRATIONS
// ========================================

$operations = [
    'git_reset' => [
        'description' => 'Réinitialisation des fichiers modifiés',
        'command' => 'cd /var/www/html/php && git reset --hard',
        'icon' => '🔄',
        'success_message' => 'Fichiers locaux réinitialisés',
        'error_message' => 'Échec de la réinitialisation Git',
        'skip_output_patterns' => ['HEAD is now at']
    ],
    'git' => [
        'description' => 'Mise à jour du code depuis Git',
        'command' => 'cd /var/www/html/php && git pull origin main',
        'icon' => '📥',
        'success_message' => 'Code mis à jour depuis le dépôt Git',
        'error_message' => 'Échec de la mise à jour Git',
        'skip_output_patterns' => ['Already up to date.', 'FETCH_HEAD']
    ],
    'ssl_cert' => [
        'description' => 'Vérification/Génération du certificat SSL',
        'command' => function() {
            if (checkSslCertificate()) {
                return null; // Skip si le certificat est encore valide
            }
            return 'mkdir -p /etc/ssl/private /etc/ssl/certs && openssl req -x509 -nodes -days 365 -newkey rsa:2048 -keyout /etc/ssl/private/nginx-selfsigned.key -out /etc/ssl/certs/nginx-selfsigned.crt -subj "/C=FR/ST=France/L=Paris/O=Proxmox/OU=IT Department/CN=localhost" && chmod 600 /etc/ssl/private/nginx-selfsigned.key && chmod 644 /etc/ssl/certs/nginx-selfsigned.crt';
        },
        'icon' => '🔒',
        'success_message' => 'Certificat SSL généré et sécurisé',
        'error_message' => 'Échec de la génération du certificat SSL',
        'skip_message' => 'Certificat SSL encore valide',
        'skip_output_patterns' => ['Generating a RSA private key', 'writing new private key']
    ],
    'nginx_config' => [
        'description' => 'Mise à jour de la configuration Nginx',
        'command' => function() {
            $socket = getPhpSocket();
            return "cp /var/www/html/php/config/nginx.conf /etc/nginx/nginx.conf && sed -i 's|unix:/var/run/php/php8.2-fpm.sock|unix:$socket|g' /etc/nginx/nginx.conf && nginx -t";
        },
        'icon' => '⚙️',
        'success_message' => 'Configuration Nginx mise à jour et validée',
        'error_message' => 'Échec de la mise à jour de la configuration Nginx',
        'skip_output_patterns' => ['syntax is ok', 'test is successful']
    ],
    'php' => [
        'description' => 'Redémarrage du service PHP-FPM',
        'command' => function() {
            $service = getPhpService();
            return $service ? "systemctl restart $service" : null;
        },
        'icon' => '🔄',
        'success_message' => 'Service PHP-FPM redémarré avec succès',
        'error_message' => 'Échec du redémarrage de PHP-FPM',
        'custom_error' => function() {
            return getPhpService() === null ? 'Aucun service PHP-FPM détecté sur ce système' : null;
        }
    ],
    'nginx' => [
        'description' => 'Rechargement de la configuration Nginx',
        'command' => 'systemctl reload nginx',
        'icon' => '🌐',
        'success_message' => 'Configuration Nginx rechargée',
        'error_message' => 'Échec du rechargement de Nginx'
    ],
    'permissions' => [
        'description' => 'Mise à jour des permissions des fichiers',
        'command' => 'chown -R www-data:www-data /var/www/html/php && chmod -R 755 /var/www/html/php',
        'icon' => '🔐',
        'success_message' => 'Permissions des fichiers mises à jour',
        'error_message' => 'Échec de la mise à jour des permissions'
    ],
    'cache' => [
        'description' => 'Nettoyage du cache système',
        'command' => 'sync && echo 3 > /proc/sys/vm/drop_caches 2>/dev/null || echo "Cache clearing not available"',
        'icon' => '🧹',
        'success_message' => 'Cache système nettoyé',
        'error_message' => 'Nettoyage du cache non disponible',
        'optional' => true
    ],
    'status_check' => [
        'description' => 'Vérification du statut des services',
        'command' => function() {
            $phpService = getPhpService();
            return "systemctl is-active nginx && echo 'Nginx: OK' && systemctl is-active $phpService && echo 'PHP-FPM: OK'";
        },
        'icon' => '🔍',
        'success_message' => 'Tous les services sont actifs',
        'error_message' => 'Certains services ne fonctionnent pas correctement',
        'optional' => true
    ]
];

// ========================================
// FONCTIONS DE DIAGNOSTIC
// ========================================

function checkSystemStatus() {
    echo "🔍 Vérification préliminaire du système...\n";
    echo "==========================================\n";
    
    $issues = [];
    
    // Vérifier nginx
    $nginxResult = execCommand("systemctl is-active nginx");
    if ($nginxResult['success']) {
        printStatus("✅ Nginx est actif");
    } else {
        printStatus("❌ Nginx n'est pas actif", false);
        $issues[] = "Nginx inactif - Exécutez: systemctl start nginx";
    }
    
    // Vérifier PHP-FPM
    $phpService = getPhpService();
    if ($phpService) {
        $phpResult = execCommand("systemctl is-active $phpService");
        if ($phpResult['success']) {
            printStatus("✅ PHP-FPM ($phpService) est actif");
        } else {
            printStatus("❌ PHP-FPM ($phpService) n'est pas actif", false);
            $issues[] = "PHP-FPM inactif - Exécutez: systemctl start $phpService";
        }
    } else {
        printStatus("❌ Aucun service PHP-FPM détecté", false);
        $issues[] = "Aucun service PHP-FPM installé";
    }
    
    // Vérifier les ports
    $portCheck = execCommand("ss -tlnp | grep ':443'");
    if ($portCheck['success'] && !empty($portCheck['output'])) {
        printStatus("✅ Port HTTPS (443) ouvert");
    } else {
        printStatus("❌ Port HTTPS (443) fermé", false);
        $issues[] = "Port 443 non disponible";
    }
    
    // Vérifier le socket PHP
    $socket = getPhpSocket();
    if (file_exists($socket)) {
        printStatus("✅ Socket PHP-FPM disponible: $socket");
    } else {
        printStatus("❌ Socket PHP-FPM introuvable: $socket", false);
        $issues[] = "Socket PHP-FPM manquant: $socket";
    }
    
    echo "\n";
    
    if (!empty($issues)) {
        printStatus("⚠️ Problèmes détectés:", false);
        foreach ($issues as $issue) {
            echo "  • $issue\n";
        }
        echo "\n";
        return false;
    }
    
    printStatus("✅ Système prêt pour la mise à jour");
    echo "\n";
    return true;
}

// ========================================
// EXÉCUTION PRINCIPALE
// ========================================

echo "\n🚀 Démarrage de la mise à jour du serveur Proxmox...\n";
echo "====================================================\n\n";

// Vérification préliminaire
$systemReady = checkSystemStatus();

$results = [];
$successful = 0;
$failed = 0;
$skipped = 0;
$criticalFailed = 0;
$errors = [];
$warnings = [];
$httpsWorking = false;

// Exécution des opérations
foreach ($operations as $key => $operation) {
    $icon = $operation['icon'];
    $description = $operation['description'];
    
    printInfo("$icon $description...");
    
    // Vérifier s'il y a une erreur personnalisée
    if (isset($operation['custom_error']) && is_callable($operation['custom_error'])) {
        $customError = $operation['custom_error']();
        if ($customError !== null) {
            printStatus("❌ $customError", false);
            $errors[] = $customError;
            $results[$key] = ['success' => false, 'output' => $customError, 'skipped' => true];
            $skipped++;
            echo "\n";
            continue;
        }
    }
    
    // Obtenir la commande à exécuter
    $command = $operation['command'];
    if (is_callable($command)) {
        $command = $command();
    }
    
    if ($command === null) {
        $skipMessage = $operation['skip_message'] ?? 'Opération ignorée';
        printStatus("⏭️ $skipMessage");
        $results[$key] = ['success' => true, 'output' => 'Skipped', 'skipped' => true];
        $skipped++;
        echo "\n";
        continue;
    }
    
    // Exécuter la commande
    $result = execCommand($command);
    $results[$key] = $result;
    
    if ($result['success']) {
        printStatus("✅ " . $operation['success_message']);
        $successful++;
        
        // Afficher la sortie si elle n'est pas dans la liste à ignorer
        $output = trim($result['output']);
        $shouldSkip = false;
        
        if (isset($operation['skip_output_patterns'])) {
            foreach ($operation['skip_output_patterns'] as $pattern) {
                if (strpos($output, $pattern) !== false) {
                    $shouldSkip = true;
                    break;
                }
            }
        }
        
        if (!empty($output) && !$shouldSkip) {
            echo "   📄 " . $output . "\n";
        }
    } else {
        printStatus("❌ " . $operation['error_message'], false);
        $failed++;
        
        if (!empty($result['output'])) {
            echo "   💬 " . $result['output'] . "\n";
        }
        
        if (isset($operation['optional']) && $operation['optional']) {
            $warnings[] = $operation['description'] . " (optionnel)";
        } else {
            $errors[] = $operation['description'];
            $criticalFailed++;
        }
    }
    
    echo "\n";
}

// ========================================
// RÉSUMÉ FINAL
// ========================================

echo "====================================================\n";
echo "� RÉSUMÉ DE LA MISE À JOUR\n";
echo "====================================================\n";

foreach ($results as $key => $result) {
    $operation = $operations[$key];
    $status = $result['success'] ? '✅' : (isset($result['skipped']) ? '⏭️' : '❌');
    $statusText = $result['success'] ? 'RÉUSSI' : (isset($result['skipped']) ? 'IGNORÉ' : 'ÉCHEC');
    
    echo sprintf("%-35s %s %s\n", $operation['description'], $status, $statusText);
}

echo "\n📈 Statistiques: $successful réussies, $failed échouées, $skipped ignorées\n";

// Test final de connectivité
echo "\n🔗 Test de connectivité finale...\n";
$connectivityTest = execCommand("curl -I -k https://localhost 2>/dev/null | head -1");
if ($connectivityTest['success'] && !empty($connectivityTest['output'])) {
    printStatus("✅ HTTPS local fonctionne: " . trim($connectivityTest['output']));
    $httpsWorking = true;
} else {
    printStatus("❌ HTTPS local ne répond pas", false);
    $httpsWorking = false;
}

// Message final
if ($criticalFailed === 0) {
    printStatus("🎉 Mise à jour terminée avec succès !");
    echo "Tous les services critiques ont été mis à jour correctement.\n";
    if ($httpsWorking) {
        echo "🌐 Serveur accessible en HTTPS sur : https://192.168.0.50\n";
    } else {
        echo "⚠️ Le serveur web ne répond pas localement - Vérifiez la configuration\n";
    }
} elseif ($criticalFailed > 0 && $successful > 0) {
    printStatus("⚠️  Mise à jour partiellement réussie", false);
    echo "Certaines opérations critiques ont échoué.\n";
} else {
    printStatus("💥 Mise à jour échouée", false);
    echo "Plusieurs opérations critiques ont échoué.\n";
}

if (!empty($warnings)) {
    echo "\n⚠️  Avertissements:\n";
    foreach ($warnings as $warning) {
        echo "   • $warning\n";
    }
}

if (!empty($errors)) {
    echo "\n❌ Erreurs critiques:\n";
    foreach ($errors as $error) {
        echo "   • $error\n";
    }
}

// Section de dépannage rapide
if ($criticalFailed > 0 || !$httpsWorking) {
    echo "\n🔧 DÉPANNAGE RAPIDE\n";
    echo "==================\n";
    echo "Commandes utiles pour diagnostiquer:\n";
    echo "  • Statut des services: systemctl status nginx php-fpm\n";
    echo "  • Logs Nginx: journalctl -u nginx --no-pager -n 20\n";
    echo "  • Test config Nginx: nginx -t\n";
    echo "  • Ports ouverts: ss -tlnp | grep ':80\\|:443'\n";
    echo "  • Processus web: ps aux | grep nginx\n";
    echo "\nSi le serveur ne répond pas:\n";
    echo "  1. Vérifiez que nginx est démarré: systemctl start nginx\n";
    echo "  2. Vérifiez la configuration: nginx -t\n";
    echo "  3. Vérifiez les permissions: ls -la /var/www/html/php/public/\n";
    echo "  4. Testez localement: curl -I -k https://localhost\n";
}

echo "\n";
exit($criticalFailed > 0 ? 1 : 0);
