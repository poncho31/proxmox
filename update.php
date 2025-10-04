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
    'initial_check' => [
        'description' => 'Diagnostic initial du système',
        'command' => 'initialSystemCheck',
        'success_message' => 'Diagnostic initial terminé',
        'error_message' => 'Problème diagnostic initial'
    ],
    'system_update' => [
        'description' => 'Mise à jour des paquets système',
        'command' => 'apt update && apt upgrade -y',
        'success_message' => 'Système mis à jour avec succès',
        'error_message' => 'Erreur lors de la mise à jour système'
    ],
    'install_dependencies' => [
        'description' => 'Installation des dépendances requises',
        'command' => 'apt install -y nginx php8.2-fpm php8.2-common php8.2-mysql php8.2-xml php8.2-xmlrpc php8.2-curl php8.2-gd php8.2-imagick php8.2-cli php8.2-dev php8.2-imap php8.2-mbstring php8.2-opcache php8.2-soap php8.2-zip openssl curl',
        'success_message' => 'Dépendances Nginx/PHP installées avec succès',
        'error_message' => 'Erreur lors de l\'installation des dépendances'
    ],
    'configure_ip' => [
        'description' => 'Configuration de l\'IP 192.168.0.50',
        'command' => 'configureProxmoxIP',
        'success_message' => 'IP 192.168.0.50 configurée',
        'error_message' => 'Erreur configuration IP'
    ],

    'configure_nginx' => [
        'description' => 'Configuration Nginx HTTP pour 192.168.0.50',
        'command' => 'configureNginx',
        'success_message' => 'Configuration Nginx HTTP appliquée',
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
    echo "   - nginx -t (test config)\n";
} else {
    printStatus("⚠️ MISE À JOUR PARTIELLEMENT RÉUSSIE", false);
    echo "\n🔧 Vérifiez les erreurs et relancez si nécessaire\n";
}

exit($failed > 0 ? 1 : 0);

// ========================================
// FONCTIONS COMPLEXES
// ========================================

function initialSystemCheck() {
    echo "=== DIAGNOSTIC INITIAL SYSTÈME ===\n";
    
    // Vérifier l'OS
    $os = execCommand('cat /etc/os-release | grep PRETTY_NAME');
    echo "Système: " . trim(str_replace('PRETTY_NAME=', '', str_replace('"', '', $os['output']))) . "\n";
    
    // Vérifier les services
    $nginx = execCommand('systemctl is-active nginx');
    $php = execCommand('systemctl is-active php8.2-fpm');
    echo "Nginx: " . ($nginx['success'] ? "✅ ACTIF" : "❌ INACTIF") . "\n";
    echo "PHP-FPM: " . ($php['success'] ? "✅ ACTIF" : "❌ INACTIF") . "\n";
    
    // Vérifier la configuration Nginx actuelle
    $nginxTest = execCommand('nginx -t');
    echo "Config Nginx: " . ($nginxTest['success'] ? "✅ VALIDE" : "❌ ERREURS") . "\n";
    
    if (!$nginxTest['success']) {
        echo "Erreurs Nginx actuelles:\n" . $nginxTest['output'] . "\n";
    }
    
    // Vérifier les interfaces réseau
    $interfaces = execCommand('ip addr show | grep "inet " | grep -v "127.0.0.1"');
    echo "Interfaces réseau:\n" . trim($interfaces['output']) . "\n";
    
    // Vérifier si 192.168.0.50 est déjà configurée
    $checkIP = execCommand('ip addr show | grep "192.168.0.50"');
    echo "IP 192.168.0.50: " . (!empty($checkIP['output']) ? "✅ DÉJÀ CONFIGURÉE" : "❌ À CONFIGURER") . "\n";
    
    // Vérifier les ports en écoute
    $ports = execCommand('ss -tlnp | grep ":80\|:8080"');
    echo "Ports 80/8080 en écoute:\n" . ($ports['output'] ?: "Aucun port en écoute") . "\n";
    
    // Vérifier si le fichier de config existe
    $configFile = '/var/www/html/php/config/nginx.conf';
    echo "Fichier config nginx.conf: " . (file_exists($configFile) ? "✅ EXISTE" : "❌ MANQUANT") . "\n";
    
    return ['success' => true, 'output' => 'Diagnostic système effectué'];
}

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
    echo "=== CONFIGURATION NGINX HTTP ===\n";
    
    // Localiser le fichier de config du projet
    $projectConfigPath = '/var/www/html/php/config/nginx.conf';
    $systemConfigPath = '/etc/nginx/nginx.conf';
    
    // Vérifier si le fichier de config du projet existe
    if (!file_exists($projectConfigPath)) {
        return ['success' => false, 'output' => "Fichier config introuvable: $projectConfigPath"];
    }
    
    echo "Fichier config trouvé: ✅\n";
    
    // Sauvegarder la config système actuelle
    $backup = execCommand("cp $systemConfigPath $systemConfigPath.backup");
    echo "Sauvegarde config système: " . ($backup['success'] ? "✅" : "❌") . "\n";
    
    // Copier la config du projet vers le système
    $copy = execCommand("cp $projectConfigPath $systemConfigPath");
    if (!$copy['success']) {
        return ['success' => false, 'output' => "Impossible de copier la config: " . $copy['output']];
    }
    echo "Config copiée: ✅\n";
    
    // Tester la configuration
    $test = execCommand('nginx -t');
    echo "Test syntaxe: " . ($test['success'] ? "✅" : "❌") . "\n";
    
    if (!$test['success']) {
        echo "Erreur syntaxe:\n" . $test['output'] . "\n";
        // Restaurer la sauvegarde
        execCommand("cp $systemConfigPath.backup $systemConfigPath");
        return ['success' => false, 'output' => 'Configuration Nginx invalide: ' . $test['output']];
    }
    
    // Vérifier/créer les répertoires web
    $webDir = '/var/www/html/php/public';
    $checkDir = execCommand("ls -la $webDir");
    echo "Répertoire web: " . ($checkDir['success'] ? "✅" : "❌") . "\n";
    
    if (!$checkDir['success']) {
        echo "Création répertoire web...\n";
        execCommand("mkdir -p $webDir");
        
        // Créer un fichier index simple pour test
        $indexContent = '<?php
echo "<h1>🚀 Serveur Proxmox HTTP</h1>";
echo "<p>✅ Serveur fonctionnel sur: " . $_SERVER["HTTP_HOST"] . "</p>";
echo "<p>🕐 Heure: " . date("Y-m-d H:i:s") . "</p>";
echo "<hr>";
echo "<h3>📋 Informations PHP</h3>";
phpinfo();
?>';
        file_put_contents("$webDir/index.php", $indexContent);
        echo "Fichier index.php créé: ✅\n";
        
        // Créer un fichier pour proxmox_main_web_server.php s'il n'existe pas
        if (!file_exists("$webDir/proxmox_main_web_server.php")) {
            $mainContent = '<?php
echo "<h1>🌐 Proxmox Main Web Server</h1>";
echo "<p>Serveur principal fonctionnel</p>";
echo "<p>IP: " . $_SERVER["SERVER_ADDR"] . "</p>";
echo "<p>Host: " . $_SERVER["HTTP_HOST"] . "</p>";
?>';
            file_put_contents("$webDir/proxmox_main_web_server.php", $mainContent);
            echo "Fichier proxmox_main_web_server.php créé: ✅\n";
        }
    }
    
    return ['success' => true, 'output' => 'Configuration Nginx HTTP appliquée depuis config/nginx.conf'];
}

function testConnectivity() {
    echo "=== TESTS DE CONNECTIVITÉ FINAUX ===\n";
    
    // Vérifier l'état des services
    $nginxStatus = execCommand('systemctl is-active nginx');
    $phpStatus = execCommand('systemctl is-active php8.2-fpm');
    echo "État services:\n";
    echo "  - Nginx: " . ($nginxStatus['success'] ? "✅ ACTIF" : "❌ INACTIF") . "\n";
    echo "  - PHP-FPM: " . ($phpStatus['success'] ? "✅ ACTIF" : "❌ INACTIF") . "\n";
    
    // Vérifier la config Nginx
    $nginxTest = execCommand('nginx -t');
    echo "  - Config Nginx: " . ($nginxTest['success'] ? "✅ VALIDE" : "❌ ERREURS") . "\n";
    
    // Vérifier les ports en écoute
    $ports80 = execCommand('ss -tlnp | grep ":80 "');
    $ports8080 = execCommand('ss -tlnp | grep ":8080 "');
    echo "Ports en écoute:\n";
    echo "  - Port 80: " . (!empty($ports80['output']) ? "✅ OUVERT" : "❌ FERMÉ") . "\n";
    echo "  - Port 8080: " . (!empty($ports8080['output']) ? "✅ OUVERT" : "❌ FERMÉ") . "\n";
    
    // Vérifier IP 192.168.0.50
    $checkIP = execCommand('ip addr show | grep "192.168.0.50"');
    echo "  - IP 192.168.0.50: " . (!empty($checkIP['output']) ? "✅ CONFIGURÉE" : "❌ NON CONFIGURÉE") . "\n";
    
    // Tests de connectivité
    echo "Tests HTTP:\n";
    
    // Test localhost
    $httpLocal = execCommand('curl -I -s http://localhost --connect-timeout 3');
    $localOK = strpos($httpLocal['output'], '200 OK') !== false || strpos($httpLocal['output'], '301') !== false;
    echo "  - http://localhost: " . ($localOK ? "✅ OK" : "❌ KO") . "\n";
    
    if (!$localOK && !empty($httpLocal['output'])) {
        echo "    Réponse: " . trim(explode("\n", $httpLocal['output'])[0]) . "\n";
    }
    
    // Test 192.168.0.50 seulement si l'IP est configurée
    if (!empty($checkIP['output'])) {
        $httpIP = execCommand('curl -I -s http://192.168.0.50 --connect-timeout 3');
        $ipOK = strpos($httpIP['output'], '200 OK') !== false || strpos($httpIP['output'], '301') !== false;
        echo "  - http://192.168.0.50: " . ($ipOK ? "✅ OK" : "❌ KO") . "\n";
        
        if (!$ipOK && !empty($httpIP['output'])) {
            echo "    Réponse: " . trim(explode("\n", $httpIP['output'])[0]) . "\n";
        }
    } else {
        $ipOK = false;
        echo "  - http://192.168.0.50: ❌ IP NON CONFIGURÉE\n";
        echo "    💡 Configurer l\'IP dans Proxmox Web UI\n";
    }
    
    // Test du fichier index
    $indexTest = file_exists('/var/www/html/php/public/index.php');
    echo "  - Fichier index.php: " . ($indexTest ? "✅ EXISTE" : "❌ MANQUANT") . "\n";
    
    $allOK = $nginxStatus['success'] && $phpStatus['success'] && $localOK;
    
    if ($allOK && !empty($checkIP['output'])) {
        echo "\n🎉 Serveur accessible sur:\n";
        echo "   - http://localhost/ (local)\n";
        if (!empty($checkIP['output'])) {
            echo "   - http://192.168.0.50/ (réseau)\n";
            echo "   - http://192.168.0.50:8080/ (todo list)\n";
        }
    }
    
    return [
        'success' => $allOK,
        'output' => "Services: " . ($nginxStatus['success'] && $phpStatus['success'] ? "✅" : "❌") . 
                   ", HTTP local: " . ($localOK ? "✅" : "❌") . 
                   ", HTTP IP: " . ($ipOK ? "✅" : "❌")
    ];
}



?>
