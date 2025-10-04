<?php
#!/usr/bin/env php
// Script de mise à jour automatique pour console
set_time_limit(60);

// Fonction pour exécuter une commande et capturer le résultat
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

// Détecter le service PHP correct
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

// Messages console avec couleurs
function printStatus($message, $success = true) {
    $color = $success ? "\033[32m" : "\033[31m"; // Vert ou Rouge
    $reset = "\033[0m";
    echo $color . $message . $reset . "\n";
}

function printInfo($message) {
    echo "\033[34m" . $message . "\033[0m\n"; // Bleu
}

// Configuration des opérations à effectuer
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
        'error_message' => 'Nettoyage du cache non disponible (système en lecture seule)',
        'optional' => true
    ]
];

echo "\n🔄 Démarrage de la mise à jour du serveur Proxmox...\n";
echo "================================================\n\n";

$results = [];
$errors = [];
$warnings = [];

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
        printStatus("⚠️  Opération ignorée", false);
        $warnings[] = $operation['description'] . " - Commande non disponible";
        $results[$key] = ['success' => false, 'output' => 'Command not available', 'skipped' => true];
        echo "\n";
        continue;
    }
    
    // Exécuter la commande
    $result = execCommand($command);
    $results[$key] = $result;
    
    if ($result['success']) {
        printStatus("✅ " . $operation['success_message']);
        
        // Afficher la sortie si elle n'est pas dans la liste à ignorer
        $output = trim($result['output']);
        $shouldSkip = false;
        
        // Vérifier les patterns à ignorer
        if (isset($operation['skip_output_patterns'])) {
            foreach ($operation['skip_output_patterns'] as $pattern) {
                if (strpos($output, $pattern) !== false) {
                    $shouldSkip = true;
                    break;
                }
            }
        }
        
        // Vérifier les sorties exactes à ignorer (ancien système)
        if (isset($operation['skip_output']) && in_array($output, $operation['skip_output'])) {
            $shouldSkip = true;
        }
        
        if (!empty($output) && !$shouldSkip) {
            echo "   📄 " . $output . "\n";
        }
    } else {
        printStatus("❌ " . $operation['error_message'], false);
        if (!empty($result['output'])) {
            echo "   💬 " . $result['output'] . "\n";
        }
        
        if (isset($operation['optional']) && $operation['optional']) {
            $warnings[] = $operation['description'] . " (optionnel)";
        } else {
            $errors[] = $operation['description'];
        }
    }
    
    echo "\n";
}

// Affichage du résumé final
echo "================================================\n";
echo "📊 RÉSUMÉ DE LA MISE À JOUR\n";
echo "================================================\n";

$successful = 0;
$failed = 0;
$skipped = 0;

foreach ($results as $key => $result) {
    $operation = $operations[$key];
    $status = $result['success'] ? '✅' : (isset($result['skipped']) ? '⏭️' : '❌');
    $statusText = $result['success'] ? 'RÉUSSI' : (isset($result['skipped']) ? 'IGNORÉ' : 'ÉCHEC');
    
    echo sprintf("%-20s %s %s\n", $operation['description'], $status, $statusText);
    
    if ($result['success']) $successful++;
    elseif (isset($result['skipped'])) $skipped++;
    else $failed++;
}

echo "\n";
echo "📈 Statistiques: $successful réussies, $failed échouées, $skipped ignorées\n";

// Compter les échecs critiques (non optionnels)
$criticalFailed = 0;
foreach ($results as $key => $result) {
    if (!$result['success'] && !isset($result['skipped'])) {
        $operation = $operations[$key];
        if (!isset($operation['optional']) || !$operation['optional']) {
            $criticalFailed++;
        }
    }
}

// Message final basé sur les échecs critiques
if ($criticalFailed === 0) {
    printStatus("🎉 Mise à jour terminée avec succès !");
    echo "Tous les services critiques ont été mis à jour correctement.\n";
    if ($failed > 0) {
        echo "Note: Certaines opérations optionnelles ont échoué mais n'affectent pas le fonctionnement.\n";
    }
} elseif ($criticalFailed > 0 && $successful > 0) {
    printStatus("⚠️  Mise à jour partiellement réussie", false);
    echo "Certaines opérations critiques ont échoué mais les services principaux fonctionnent.\n";
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

echo "\n";
