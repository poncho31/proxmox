<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Debug Environnement</h1>";

echo "<h2>Chemins testés :</h2>";
$possiblePaths = [
    __DIR__ . '/../src/env.php',
    __DIR__ . '/src/env.php',
    dirname(__DIR__) . '/src/env.php',
    realpath(__DIR__ . '/../src/env.php')
];

foreach ($possiblePaths as $i => $path) {
    echo "<p>Path $i: " . ($path ?: 'null') . ' (exists: ' . ($path && file_exists($path) ? 'yes' : 'no') . ')</p>';
}

echo "<h2>Test de chargement :</h2>";

$envPath = null;
foreach ($possiblePaths as $path) {
    if ($path && file_exists($path)) {
        $envPath = $path;
        break;
    }
}

if ($envPath) {
    echo "<p>Chemin trouvé : $envPath</p>";
    
    try {
        require_once $envPath;
        echo "<p>✅ Classe Env chargée avec succès</p>";
        
        try {
            Env::load();
            echo "<p>✅ Variables d'environnement chargées</p>";
            
            try {
                $config = Env::getDatabaseConfig();
                echo "<p>✅ Configuration DB récupérée :</p>";
                echo "<pre>" . print_r($config, true) . "</pre>";
                
                // Test de connexion
                $dsn = "mysql:host={$config['host']};port={$config['port']};charset=utf8mb4";
                $pdo = new PDO($dsn, $config['username'], $config['password'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => 5
                ]);
                
                echo "<p>✅ Connexion MySQL réussie</p>";
                
                $stmt = $pdo->query("SELECT VERSION() as version");
                $result = $stmt->fetch();
                echo "<p>Version MySQL : " . ($result['version'] ?? 'N/A') . "</p>";
                
            } catch (Exception $e) {
                echo "<p>❌ Erreur config DB : " . $e->getMessage() . "</p>";
            }
            
        } catch (Exception $e) {
            echo "<p>❌ Erreur chargement env : " . $e->getMessage() . "</p>";
        }
        
    } catch (Exception $e) {
        echo "<p>❌ Erreur chargement classe : " . $e->getMessage() . "</p>";
    }
    
} else {
    echo "<p>❌ Aucun fichier env.php trouvé</p>";
}
?>
