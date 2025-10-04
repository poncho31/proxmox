<?php
#!/usr/bin/env php

/**
 * Point d'entrée principal pour le script de mise à jour
 * Version refactorisée avec architecture MVC
 */

require_once __DIR__ . '/autoload.php';

use App\Controllers\UpdateController;

try {
    $controller = new UpdateController();
    $controller->run();
} catch (Throwable $e) {
    echo "\033[31m💥 Erreur fatale: " . $e->getMessage() . "\033[0m\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}
