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

echo "\n🔄 Démarrage de la mise à jour du serveur...\n";
echo "================================================\n\n";

$errors = [];

// 1. Mise à jour du code depuis Git
printInfo("🔄 Mise à jour du code depuis Git...");
$gitResult = execCommand("git pull origin main");

if ($gitResult['success']) {
    printStatus("✅ Git pull réussi");
    if (trim($gitResult['output']) !== "Already up to date.") {
        echo "   " . $gitResult['output'] . "\n";
    }
} else {
    printStatus("❌ Erreur Git pull", false);
    echo "   " . $gitResult['output'] . "\n";
    $errors[] = "Git pull failed";
}

echo "\n";

// 2. Redémarrage du service PHP
$phpService = getPhpService();
if ($phpService) {
    printInfo("🔄 Redémarrage de $phpService...");
    $phpResult = execCommand("systemctl restart $phpService");
    
    if ($phpResult['success']) {
        printStatus("✅ Service PHP redémarré avec succès");
    } else {
        printStatus("❌ Erreur lors du redémarrage de PHP", false);
        echo "   " . $phpResult['output'] . "\n";
        $errors[] = "PHP service restart failed";
    }
} else {
    printStatus("❌ Aucun service PHP-FPM détecté", false);
    $errors[] = "No PHP-FPM service found";
}

echo "\n";

// 3. Rechargement de Nginx
printInfo("🔄 Rechargement de Nginx...");
$nginxResult = execCommand("systemctl reload nginx");

if ($nginxResult['success']) {
    printStatus("✅ Nginx rechargé avec succès");
} else {
    printStatus("❌ Erreur lors du rechargement de Nginx", false);
    echo "   " . $nginxResult['output'] . "\n";
    $errors[] = "Nginx reload failed";
}

echo "\n";

// 4. Vérification des permissions
printInfo("🔄 Mise à jour des permissions...");
$permResult = execCommand("chown -R www-data:www-data /var/www/html && chmod -R 755 /var/www/html");

if ($permResult['success']) {
    printStatus("✅ Permissions mises à jour");
} else {
    printStatus("❌ Erreur lors de la mise à jour des permissions", false);
    echo "   " . $permResult['output'] . "\n";
    $errors[] = "Permissions update failed";
}

// Résumé final
echo "\n================================================\n";
if (empty($errors)) {
    printStatus("🎉 Mise à jour terminée avec succès !");
    echo "Tous les services ont été mis à jour correctement.\n";
} else {
    printStatus("⚠️  Mise à jour terminée avec des erreurs :", false);
    foreach ($errors as $error) {
        echo "   • $error\n";
    }
}
echo "\n";
