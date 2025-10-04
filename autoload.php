<?php

/**
 * Autoloader simple pour le projet
 */
spl_autoload_register(function ($class) {
    // Convertir le namespace en chemin de fichier
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/src/';

    // Vérifier si la classe utilise le namespace de base
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    // Obtenir le nom de classe relatif
    $relative_class = substr($class, $len);

    // Remplacer le namespace par le séparateur de répertoire, ajouter .php
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    // Si le fichier existe, l'inclure
    if (file_exists($file)) {
        require $file;
    }
});
