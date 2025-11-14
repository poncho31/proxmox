<?php
// Charger les variables d'environnement
require_once __DIR__ . '/../src/env.php';
Env::load();

// Fonction pour tester MySQL et récupérer des informations
function getMySQLInfo()
{
    $info = [
        'status' => 'Déconnecté',
        'version' => 'N/A',
        'uptime' => 'N/A',
        'connections' => 'N/A',
        'databases' => 'N/A',
        'tables' => 'N/A',
        'error' => null
    ];

    try {
        // Lire directement le fichier .env
        $envPaths = [
            __DIR__ . '/../.env',
            '/var/www/proxmox/git_app/.env',
            dirname(__DIR__) . '/.env'
        ];

        $envContent = null;
        foreach ($envPaths as $envPath) {
            if (file_exists($envPath) && is_readable($envPath)) {
                $envContent = file_get_contents($envPath);
                break;
            }
        }

        if (!$envContent) {
            throw new Exception("Fichier .env non trouvé dans les chemins: " . implode(', ', $envPaths));
        }

        // Parser DATABASE_URL depuis le contenu .env
        if (preg_match('/DATABASE_URL=(.+)/', $envContent, $matches)) {
            $databaseUrl = trim($matches[1]);

            // Parser l'URL de base de données
            $parsed = parse_url($databaseUrl);
            if (!$parsed) {
                throw new Exception("Format DATABASE_URL invalide: $databaseUrl");
            }

            $config = [
                'host' => $parsed['host'] ?? 'localhost',
                'port' => $parsed['port'] ?? 3306,
                'dbname' => ltrim($parsed['path'] ?? '', '/'),
                'username' => $parsed['user'] ?? '',
                'password' => $parsed['pass'] ?? ''
            ];
        } else {
            throw new Exception("DATABASE_URL non trouvée dans le fichier .env");
        }

        // Tests de connectivité avant la connexion PDO
        $host = $config['host'];
        $port = $config['port'];

        // Test 1: Ping du serveur MySQL
        $pingResult = exec("ping -c 1 -W 1 $host 2>/dev/null", $pingOutput, $pingCode);
        $pingSuccess = $pingCode === 0;

        // Test 2: Test de port avec telnet/nc
        $portResult = exec("timeout 3 bash -c \"echo > /dev/tcp/$host/$port\" 2>/dev/null", $portOutput, $portCode);
        $portOpen = $portCode === 0;

        // Si les tests de base échouent, donner plus d'infos
        if (!$pingSuccess || !$portOpen) {
            $diagnostics = [
                "Host: $host:$port",
                "Ping: " . ($pingSuccess ? "✓" : "✗ (Host unreachable)"),
                "Port: " . ($portOpen ? "✓" : "✗ (Port closed/filtered)")
            ];
            throw new Exception("Connectivité MySQL échouée - " . implode(", ", $diagnostics));
        }

        $dsn = "mysql:host={$config['host']};port={$config['port']};charset=utf8mb4";
        $pdo = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5
        ]);

        $info['status'] = 'Connecté';

        // Version MySQL
        $stmt = $pdo->query("SELECT VERSION() as version");
        $result = $stmt->fetch();
        $info['version'] = $result['version'] ?? 'N/A';

        // Uptime du serveur
        $stmt = $pdo->query("SHOW STATUS WHERE Variable_name = 'Uptime'");
        $result = $stmt->fetch();
        if ($result) {
            $uptime = intval($result['Value']);
            $days = floor($uptime / 86400);
            $hours = floor(($uptime % 86400) / 3600);
            $minutes = floor(($uptime % 3600) / 60);
            $info['uptime'] = "{$days}j {$hours}h {$minutes}m";
        }

        // Nombre de connexions
        $stmt = $pdo->query("SHOW STATUS WHERE Variable_name = 'Threads_connected'");
        $result = $stmt->fetch();
        $info['connections'] = $result['Value'] ?? 'N/A';

        // Nombre de bases de données
        $stmt = $pdo->query("SHOW DATABASES");
        $databases = $stmt->fetchAll();
        $info['databases'] = count($databases);

        // Nombre de tables dans la base actuelle (si elle existe)
        try {
            $pdo->exec("USE " . $config['dbname']);
            $stmt = $pdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll();
            $info['tables'] = count($tables);
        } catch (Exception $e) {
            $info['tables'] = 'DB non trouvée';
        }
    } catch (Exception $e) {
        $info['status'] = 'Erreur';
        $info['error'] = $e->getMessage();
    }

    return $info;
}

// Récupération des informations système
function getSystemInfo()
{
    $info = [];

    // Informations serveur de base
    $server_name = $_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'] ?? gethostname();
    // Si c'est encore "_", on utilise l'IP du serveur ou localhost
    if ($server_name === '_' || empty($server_name)) {
        $server_name = $_SERVER['SERVER_ADDR'] ?? $_SERVER['LOCAL_ADDR'] ?? 'localhost';
    }
    $info['server_name'] = $server_name;
    $info['client_ip'] = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $info['php_version'] = PHP_VERSION;
    $info['current_time'] = date('d/m/Y H:i:s');

    // Load average (si disponible sur Linux)
    if (file_exists('/proc/loadavg')) {
        $load = file_get_contents('/proc/loadavg');
        $info['load_avg'] = explode(' ', $load)[0] ?? 'N/A';
    } else {
        $info['load_avg'] = 'N/A';
    }

    // Mémoire (si disponible)
    if (file_exists('/proc/meminfo')) {
        $meminfo = file_get_contents('/proc/meminfo');
        preg_match('/MemTotal:\s+(\d+)/', $meminfo, $matches);
        $total_mem = isset($matches[1]) ? round($matches[1] / 1024 / 1024, 1) : 'N/A';
        preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $matches);
        $avail_mem = isset($matches[1]) ? round($matches[1] / 1024 / 1024, 1) : 'N/A';
        $info['memory'] = $avail_mem . ' / ' . $total_mem . ' GB';
    } else {
        $info['memory'] = 'N/A';
    }

    return $info;
}

$system = getSystemInfo();
$mysql = getMySQLInfo();
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hub Proxmox - Services</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: linear-gradient(135deg, #2c3e50, #3498db);
            min-height: 100vh;
            color: #333;
            padding: 10px;
            margin: 0;
        }

        .container {
            width: 100%;
            max-width: 100%;
            margin: 0;
            display: grid;
            grid-template-columns: 1fr;
            gap: 15px;
            min-height: calc(100vh - 20px);
        }

        .header {
            grid-column: 1 / -1;
            text-align: center;
            color: white;
            padding: 15px 0;
        }

        .header h1 {
            font-size: 2.2rem;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .sites-section {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            margin: 0 10px;
        }

        .sites-section h2 {
            color: #2c3e50;
            margin-bottom: 15px;
            font-size: 1.5rem;
            border-bottom: 2px solid #3498db;
            padding-bottom: 8px;
            font-weight: 600;
        }

        .sites-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
        }

        .site-card {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-decoration: none;
            transition: all 0.3s ease;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .site-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.25);
        }

        .site-card.php {
            background: linear-gradient(135deg, #4CAF50, #45a049);
        }

        .site-card.rust {
            background: linear-gradient(135deg, #FF6B35, #F7931E);
        }

        .site-card.domotique {
            background: linear-gradient(135deg, #9b59b6, #8e44ad);
        }

        .site-card.vscode {
            background: linear-gradient(135deg, #007ACC, #0066A8);
        }

        .site-card.proxmox {
            background: linear-gradient(135deg, #E74C3C, #C0392B);
        }

        .site-icon {
            font-size: 2.5rem;
            margin-bottom: 10px;
            display: block;
        }

        .site-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .site-port {
            font-size: 0.85rem;
            opacity: 0.9;
            background: rgba(255, 255, 255, 0.2);
            padding: 4px 8px;
            border-radius: 12px;
            display: inline-block;
            margin-top: 8px;
        }

        .info-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 15px;
            margin: 0 10px;
        }

        .info-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            padding: 18px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .info-card h3 {
            color: #2c3e50;
            margin-bottom: 15px;
            font-size: 1.2rem;
            border-bottom: 2px solid #3498db;
            padding-bottom: 6px;
            font-weight: 600;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
            font-size: 0.9rem;
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 600;
            color: #555;
        }

        .info-value {
            color: #2c3e50;
            font-weight: 600;
            font-family: 'Segoe UI', system-ui, sans-serif;
            font-size: 0.85rem;
        }

        .status-online {
            color: #27ae60;
        }

        .status-warning {
            color: #f39c12;
        }

        @media (max-width: 1200px) {
            .info-section {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            }
        }

        @media (max-width: 768px) {
            body {
                padding: 5px;
            }

            .container {
                gap: 10px;
            }

            .header h1 {
                font-size: 1.8rem;
            }

            .sites-section,
            .info-section {
                margin: 0 5px;
            }

            .sites-grid {
                grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
                gap: 10px;
            }

            .info-section {
                grid-template-columns: 1fr;
            }

            .info-card {
                padding: 15px;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- En-tête -->
        <div class="header">
            <h1>🖥️ Hub Proxmox</h1>
            <p>Centre de contrôle des services - <?php echo $system['current_time']; ?></p>
        </div>

        <!-- Section des sites -->
        <div class="sites-section">
            <h2>🌐 Sites Disponibles</h2>
            <div class="sites-grid">
                <!-- Environnement PHP -->
                <a href="http://<?php echo $system['server_name']; ?>/todo_list.php" target="_blank" class="site-card php">
                    <span class="site-icon">🐘</span>
                    <div class="site-title">TODO List</div>
                    <div class="site-port">Port 81</div>
                </a>

                <!-- Surveillance Caméras -->
                <a href="domotique.php" class="site-card domotique" target="_blank">
                    <span class="site-icon">📹</span>
                    <div class="site-title">Caméras</div>
                    <div class="site-port">Surveillance</div>
                </a>

                <!-- VS Code Server -->
                <a href="<?php echo Env::get('URL_VSCODE', ''); ?>" target="_blank" class="site-card vscode">
                    <span class="site-icon">💻</span>
                    <div class="site-title">VS Code</div>
                    <div class="site-port">Port 81</div>
                </a>

                <!-- Interface Proxmox -->
                <a href="<?php echo Env::get('IP_PROXMOX_PUBLIC', 'https://192.168.0.50:8006'); ?>" target="_blank" class="site-card proxmox">
                    <span class="site-icon">⚙️</span>
                    <div class="site-title">Proxmox VE</div>
                    <div class="site-port">Port 8006</div>
                </a>

                <!-- Comfyui -->
                <a href="<?php echo Env::get('URL_COMFYUI', ''); ?>" target="_blank" class="site-card vscode">
                    <span class="site-icon">💻</span>
                    <div class="site-title">ComfyUI</div>
                    <div class="site-port">Port 86</div>
                </a>

            </div>
        </div>

        <!-- Informations système et services -->
        <div class="info-section">
            <div class="info-card">
                <h3>📊 Système</h3>
                <div class="info-item">
                    <span class="info-label">🖥️ Serveur</span>
                    <span class="info-value"><?php echo $system['server_name']; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">🌐 IP Client</span>
                    <span class="info-value"><?php echo $system['client_ip']; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">🐘 PHP</span>
                    <span class="info-value"><?php echo $system['php_version']; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">⚡ Load</span>
                    <span class="info-value"><?php echo $system['load_avg']; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">💾 Mémoire</span>
                    <span class="info-value"><?php echo $system['memory']; ?></span>
                </div>
            </div>

            <div class="info-card">
                <h3>🔧 Services</h3>
                <div class="info-item">
                    <span class="info-label">Nginx</span>
                    <span class="info-value status-online">● En ligne</span>
                </div>
                <div class="info-item">
                    <span class="info-label">PHP-FPM</span>
                    <span class="info-value status-online">● En ligne</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Hub</span>
                    <span class="info-value status-online">● Port 80</span>
                </div>
                <div class="info-item">
                    <span class="info-label">PHP Dev</span>
                    <span class="info-value status-online">● Port 81</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Caméras</span>
                    <span class="info-value status-online">● Surveillance</span>
                </div>
                <div class="info-item">
                    <span class="info-label">VS Code</span>
                    <span class="info-value status-online">● Port 81</span>
                </div>
                <div class="info-item">
                    <span class="info-label">go2rtc</span>
                    <span class="info-value status-online">● Port 82</span>
                </div>
            </div>

            <div class="info-card">
                <h3>MySQL</h3>
                <div class="info-item">
                    <span class="info-label">📡 Statut</span>
                    <span class="info-value <?php echo $mysql['status'] === 'Connecté' ? 'status-online' : ($mysql['status'] === 'Erreur' ? 'status-warning' : ''); ?>">
                        <?php echo $mysql['status']; ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">🏷️ Version</span>
                    <span class="info-value"><?php echo $mysql['version']; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">⏱️ Uptime</span>
                    <span class="info-value"><?php echo $mysql['uptime']; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">🔗 Connexions</span>
                    <span class="info-value"><?php echo $mysql['connections']; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">🗃️ Bases</span>
                    <span class="info-value"><?php echo $mysql['databases']; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">📋 Tables</span>
                    <span class="info-value"><?php echo $mysql['tables']; ?></span>
                </div>
                <?php if ($mysql['error']): ?>
                    <div class="info-item">
                        <span class="info-label">⚠️ Erreur</span>
                        <span class="info-value status-warning" style="font-size: 0.8rem; word-break: break-all;"><?php echo htmlspecialchars($mysql['error']); ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="info-card">
                <h3>�🛠️ Outils</h3>
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <a href="info_server.php" style="color: #9b59b6; text-decoration: none; padding: 8px; border: 1px solid #9b59b6; border-radius: 5px; text-align: center;">📊 Monitoring Système</a>
                    <a href="index2.php" style="color: #3498db; text-decoration: none; padding: 8px; border: 1px solid #3498db; border-radius: 5px; text-align: center;">📄 Page Test</a>
                    <a href="update.php" style="color: #27ae60; text-decoration: none; padding: 8px; border: 1px solid #27ae60; border-radius: 5px; text-align: center;">🔄 Mise à jour</a>
                    <button onclick="location.reload()" style="color: #e74c3c; background: none; border: 1px solid #e74c3c; padding: 8px; border-radius: 5px; cursor: pointer;">🔄 Actualiser</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Animation simple au chargement
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.site-card, .info-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });

            console.log('🖥️ Hub Proxmox chargé');
            console.log('🌐 Sites: Port 80 (Hub), 81 (PHP), 82 (Rust), 8006 (Proxmox)');
        });
    </script>
</body>

</html>
