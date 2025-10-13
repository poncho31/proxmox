<?php
echo "<h1>Debug - Test des chemins pour env.php</h1>";

echo "<h2>1. Test des chemins possibles</h2>";
$possiblePaths = [
    __DIR__ . '/../src/env.php',
    __DIR__ . '/src/env.php',
    dirname(__DIR__) . '/src/env.php',
    realpath(__DIR__ . '/../src/env.php')
];

foreach ($possiblePaths as $i => $path) {
    echo "<strong>Chemin $i:</strong> " . ($path ?: 'null') . "<br>";
    echo "Existe: " . (file_exists($path) ? 'OUI' : 'NON') . "<br>";
    echo "Lisible: " . (is_readable($path) ? 'OUI' : 'NON') . "<br>";
    if ($path) {
        echo "Chemin réel: " . (realpath($path) ?: 'N/A') . "<br>";
    }
    echo "<hr>";
}

echo "<h2>2. Variables d'environnement PHP</h2>";
echo "Current working directory: " . getcwd() . "<br>";
echo "__DIR__: " . __DIR__ . "<br>";
echo "__FILE__: " . __FILE__ . "<br>";

echo "<h2>3. Test d'inclusion du fichier env.php</h2>";
$envPath = null;
foreach ($possiblePaths as $path) {
    if ($path && file_exists($path)) {
        $envPath = $path;
        break;
    }
}

if ($envPath) {
    echo "Fichier trouvé: $envPath<br>";
    try {
        require_once $envPath;
        echo "✅ Inclusion réussie!<br>";
        
        echo "<h2>4. Test de la classe Env</h2>";
        if (class_exists('Env')) {
            echo "✅ Classe Env disponible<br>";
            
            try {
                Env::load();
                echo "✅ Variables d'environnement chargées<br>";
                
                $config = Env::getDatabaseConfig();
                echo "✅ Configuration DB récupérée:<br>";
                echo "<pre>";
                print_r($config);
                echo "</pre>";
                
            } catch (Exception $e) {
                echo "❌ Erreur lors du chargement des variables: " . $e->getMessage() . "<br>";
            }
        } else {
            echo "❌ Classe Env non disponible<br>";
        }
        
    } catch (Exception $e) {
        echo "❌ Erreur lors de l'inclusion: " . $e->getMessage() . "<br>";
    }
} else {
    echo "❌ Aucun fichier env.php trouvé<br>";
}

echo "<h2>5. Contenu du répertoire src</h2>";
$srcDir = __DIR__ . '/../src';
if (is_dir($srcDir)) {
    echo "Contenu de $srcDir:<br>";
    $files = scandir($srcDir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            $fullPath = $srcDir . '/' . $file;
            echo "- $file (lisible: " . (is_readable($fullPath) ? 'OUI' : 'NON') . ")<br>";
        }
    }
} else {
    echo "Répertoire src non trouvé: $srcDir<br>";
}

echo "<h2>6. Test de connexion MySQL avec .env</h2>";
if (class_exists('Env')) {
    try {
        $config = Env::getDatabaseConfig();
        $dsn = "mysql:host={$config['host']};port={$config['port']};charset=utf8mb4";
        $pdo = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5
        ]);
        echo "✅ Connexion MySQL réussie avec .env<br>";
        
        $stmt = $pdo->query("SELECT VERSION() as version");
        $result = $stmt->fetch();
        echo "Version MySQL: " . ($result['version'] ?? 'N/A') . "<br>";
        
    } catch (Exception $e) {
        echo "❌ Erreur connexion MySQL: " . $e->getMessage() . "<br>";
    }
} else {
    echo "❌ Classe Env non disponible pour tester la connexion<br>";
}
?>
