<?php
// Charger les variables d'environnement
require_once __DIR__ . '/../src/env.php';
Env::load();

// Fonction pour obtenir les métriques système
function getSystemMetrics() {
    $metrics = [];
    
    // CPU Load
    $load = sys_getloadavg();
    $metrics['cpu_load_1min'] = round($load[0], 2);
    $metrics['cpu_load_5min'] = round($load[1], 2);
    $metrics['cpu_load_15min'] = round($load[2], 2);
    $metrics['cpu_cores'] = (int)shell_exec('nproc') ?: 1;
    
    // CPU Usage plus robuste
    $cpu_usage = shell_exec("top -bn1 | grep 'Cpu(s)' | awk '{print \$2}' | cut -d'%' -f1");
    $metrics['cpu_usage'] = $cpu_usage ? (float)trim($cpu_usage) : 0;
    
    // Informations CPU détaillées
    $cpu_model = shell_exec("grep 'model name' /proc/cpuinfo | head -1 | cut -d':' -f2");
    $metrics['cpu_model'] = $cpu_model ? trim($cpu_model) : 'N/A';
    
    // Mémoire
    $mem_info = shell_exec('free');
    if (preg_match('/Mem:\s+(\d+)\s+(\d+)\s+(\d+)/', $mem_info, $mem_matches)) {
        $metrics['memory_total'] = (int)$mem_matches[1];
        $metrics['memory_used'] = (int)$mem_matches[2];
        $metrics['memory_free'] = (int)$mem_matches[3];
        $metrics['memory_percent'] = round(($mem_matches[2] / $mem_matches[1]) * 100, 1);
    } else {
        $metrics['memory_total'] = 0;
        $metrics['memory_used'] = 0;
        $metrics['memory_free'] = 0;
        $metrics['memory_percent'] = 0;
    }
    
    // Swap
    if (preg_match('/Swap:\s+(\d+)\s+(\d+)\s+(\d+)/', $mem_info, $swap_matches)) {
        $metrics['swap_total'] = (int)$swap_matches[1];
        $metrics['swap_used'] = (int)$swap_matches[2];
        $metrics['swap_percent'] = $swap_matches[1] > 0 ? round(($swap_matches[2] / $swap_matches[1]) * 100, 1) : 0;
    } else {
        $metrics['swap_total'] = 0;
        $metrics['swap_used'] = 0;
        $metrics['swap_percent'] = 0;
    }
    
    // Stockage
    $disk_info = shell_exec("df / | tail -1");
    if (preg_match('/(\d+)\s+(\d+)\s+(\d+)\s+(\d+)%/', $disk_info, $disk_matches)) {
        $metrics['disk_total'] = (int)$disk_matches[1];
        $metrics['disk_used'] = (int)$disk_matches[2];
        $metrics['disk_free'] = (int)$disk_matches[3];
        $metrics['disk_percent'] = (int)$disk_matches[4];
    } else {
        $metrics['disk_total'] = 0;
        $metrics['disk_used'] = 0;
        $metrics['disk_free'] = 0;
        $metrics['disk_percent'] = 0;
    }
    
    // VMs/Containers (si Proxmox disponible)
    $metrics['vms_running'] = (int)shell_exec("qm list 2>/dev/null | grep -c 'running' || echo 0");
    $metrics['vms_stopped'] = (int)shell_exec("qm list 2>/dev/null | grep -c 'stopped' || echo 0");
    $metrics['lxc_running'] = (int)shell_exec("pct list 2>/dev/null | grep -c 'running' || echo 0");
    $metrics['lxc_stopped'] = (int)shell_exec("pct list 2>/dev/null | grep -c 'stopped' || echo 0");
    
    // Uptime
    $uptime = shell_exec("uptime -p");
    $metrics['uptime'] = $uptime ? trim($uptime) : 'N/A';
    
    // Température CPU (si disponible)
    $temp = shell_exec("sensors 2>/dev/null | grep -E 'Core|Package' | head -1 | awk '{print \$3}' | grep -oE '[0-9]+\.[0-9]+'");
    $metrics['cpu_temperature'] = $temp ? (float)trim($temp) : null;
    
    // Interfaces réseau
    $metrics['network_interfaces'] = [];
    $interfaces = shell_exec("ip link show | grep 'state UP' | awk -F': ' '{print \$2}'");
    if ($interfaces) {
        foreach (explode("\n", trim($interfaces)) as $interface) {
            if (!empty($interface)) {
                $rx_bytes = (int)shell_exec("cat /sys/class/net/$interface/statistics/rx_bytes 2>/dev/null || echo 0");
                $tx_bytes = (int)shell_exec("cat /sys/class/net/$interface/statistics/tx_bytes 2>/dev/null || echo 0");
                $metrics['network_interfaces'][$interface] = [
                    'rx_mb' => round($rx_bytes / 1024 / 1024, 2),
                    'tx_mb' => round($tx_bytes / 1024 / 1024, 2)
                ];
            }
        }
    }
    
    // Score global de charge
    $cpu_score = min($metrics['cpu_usage'], 100);
    $mem_score = $metrics['memory_percent'];
    $load_score = min(($metrics['cpu_load_1min'] / $metrics['cpu_cores']) * 100, 100);
    $disk_score = $metrics['disk_percent'];
    
    $metrics['global_score'] = round(($cpu_score * 0.3 + $mem_score * 0.3 + $load_score * 0.2 + $disk_score * 0.2), 1);
    
    return $metrics;
}

// Fonction pour déterminer le statut basé sur le score
function getStatusFromScore($score) {
    if ($score < 30) return ['status' => 'low', 'text' => 'Charge Faible', 'color' => '#27ae60', 'icon' => '✅'];
    if ($score < 70) return ['status' => 'medium', 'text' => 'Charge Modérée', 'color' => '#f39c12', 'icon' => '⚠️'];
    return ['status' => 'high', 'text' => 'Charge Élevée', 'color' => '#e74c3c', 'icon' => '🚨'];
}

// Fonction pour obtenir les recommandations
function getRecommendations($metrics) {
    $recommendations = [];
    
    if ($metrics['cpu_usage'] > 80) {
        $recommendations[] = [
            'type' => 'warning',
            'message' => "CPU surchargé ({$metrics['cpu_usage']}%)",
            'actions' => ['Migrer des VMs vers d\'autres nœuds', 'Réduire le nombre de VMs actives']
        ];
    }
    
    if ($metrics['memory_percent'] > 85) {
        $recommendations[] = [
            'type' => 'critical',
            'message' => "Mémoire critique ({$metrics['memory_percent']}%)",
            'actions' => ['Arrêter des VMs non essentielles', 'Augmenter la RAM du serveur']
        ];
    }
    
    if ($metrics['disk_percent'] > 90) {
        $recommendations[] = [
            'type' => 'critical',
            'message' => "Stockage critique ({$metrics['disk_percent']}%)",
            'actions' => ['Nettoyer les snapshots anciens', 'Migrer des VMs vers autre stockage']
        ];
    }
    
    if ($metrics['cpu_load_1min'] / $metrics['cpu_cores'] > 2) {
        $recommendations[] = [
            'type' => 'warning',
            'message' => "Load Average élevé ({$metrics['cpu_load_1min']})",
            'actions' => ['Vérifier les processus consommateurs', 'Optimiser les services actifs']
        ];
    }
    
    return $recommendations;
}

// Fonction pour vérifier les services Proxmox
function getProxmoxServices() {
    $services = ['pveproxy', 'pvedaemon', 'pve-cluster', 'corosync', 'ksmtuned'];
    $status = [];
    
    foreach ($services as $service) {
        $result = shell_exec("systemctl is-active $service 2>/dev/null");
        $status[$service] = trim($result) === 'active';
    }
    
    return $status;
}

$metrics = getSystemMetrics();
$status = getStatusFromScore($metrics['global_score']);
$recommendations = getRecommendations($metrics);
$services = getProxmoxServices();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoring Système - Proxmox</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: linear-gradient(135deg, #2c3e50, #3498db);
            margin: 0;
            padding: 20px;
            min-height: 100vh;
            color: #333;
        }
        
        .header {
            text-align: center;
            color: white;
            margin-bottom: 30px;
        }
        
        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .dashboard {
            max-width: 1400px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 20px;
        }
        
        .metric-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            transition: transform 0.3s ease;
        }
        
        .metric-card:hover {
            transform: translateY(-5px);
        }
        
        .metric-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 20px;
            color: #2c3e50;
            border-bottom: 3px solid #3498db;
            padding-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .metric-value {
            font-size: 2.5rem;
            font-weight: bold;
            margin: 15px 0;
        }
        
        .metric-detail {
            font-size: 0.9rem;
            color: #666;
            margin: 5px 0;
        }
        
        .progress-bar {
            width: 100%;
            height: 25px;
            background: #ecf0f1;
            border-radius: 15px;
            overflow: hidden;
            margin: 15px 0;
            position: relative;
        }
        
        .progress-fill {
            height: 100%;
            transition: width 0.5s ease;
            border-radius: 15px;
            position: relative;
        }
        
        .progress-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
            text-shadow: 0 1px 2px rgba(0,0,0,0.3);
        }
        
        .status-indicator {
            padding: 12px 20px;
            border-radius: 25px;
            color: white;
            font-weight: 600;
            text-align: center;
            margin: 15px 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .service-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .service-item:last-child {
            border-bottom: none;
        }
        
        .service-status {
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        .recommendations {
            grid-column: 1 / -1;
        }
        
        .recommendation-item {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
        }
        
        .recommendation-item.critical {
            background: #f8d7da;
            border-left-color: #dc3545;
        }
        
        .recommendation-actions {
            margin-top: 10px;
            padding-left: 20px;
        }
        
        .recommendation-actions li {
            margin: 5px 0;
            color: #666;
        }
        
        .network-interface {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        
        .network-interface:last-child {
            border-bottom: none;
        }
        
        .refresh-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            font-size: 1.5rem;
            cursor: pointer;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            transition: all 0.3s ease;
        }
        
        .refresh-btn:hover {
            background: #2980b9;
            transform: scale(1.1);
        }
        
        @media (max-width: 768px) {
            .dashboard {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .grid-2 {
                grid-template-columns: 1fr;
            }
            
            .header h1 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🖥️ Monitoring Système Proxmox</h1>
        <p>Surveillance des performances et équilibrage des charges - <?php echo date('d/m/Y H:i:s'); ?></p>
    </div>

    <div class="dashboard">
        <!-- Score Global -->
        <div class="metric-card">
            <div class="metric-title">📈 Score de Charge Global</div>
            <div class="metric-value" style="color: <?php echo $status['color']; ?>">
                <?php echo $metrics['global_score']; ?>%
            </div>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo $metrics['global_score']; ?>%; background: <?php echo $status['color']; ?>">
                    <div class="progress-text"><?php echo $metrics['global_score']; ?>%</div>
                </div>
            </div>
            <div class="status-indicator" style="background: <?php echo $status['color']; ?>">
                <?php echo $status['icon']; ?> <?php echo $status['text']; ?>
            </div>
            <div class="metric-detail">Uptime: <?php echo $metrics['uptime']; ?></div>
        </div>
        
        <!-- CPU -->
        <div class="metric-card">
            <div class="metric-title">🖥️ Processeur</div>
            <div class="metric-value" style="color: #3498db;">
                <?php echo $metrics['cpu_usage']; ?>%
            </div>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo $metrics['cpu_usage']; ?>%; background: linear-gradient(135deg, #3498db, #2980b9);">
                    <div class="progress-text"><?php echo $metrics['cpu_usage']; ?>%</div>
                </div>
            </div>
            <div class="metric-detail">Modèle: <?php echo $metrics['cpu_model']; ?></div>
            <div class="metric-detail">Cœurs: <?php echo $metrics['cpu_cores']; ?></div>
            <div class="metric-detail">Load: <?php echo $metrics['cpu_load_1min']; ?> / <?php echo $metrics['cpu_load_5min']; ?> / <?php echo $metrics['cpu_load_15min']; ?></div>
            <?php if ($metrics['cpu_temperature']): ?>
            <div class="metric-detail">Température: <?php echo $metrics['cpu_temperature']; ?>°C</div>
            <?php endif; ?>
        </div>
        
        <!-- Mémoire -->
        <div class="metric-card">
            <div class="metric-title">💾 Mémoire RAM</div>
            <div class="metric-value" style="color: #9b59b6;">
                <?php echo $metrics['memory_percent']; ?>%
            </div>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo $metrics['memory_percent']; ?>%; background: linear-gradient(135deg, #9b59b6, #8e44ad);">
                    <div class="progress-text"><?php echo $metrics['memory_percent']; ?>%</div>
                </div>
            </div>
            <div class="metric-detail">Utilisé: <?php echo round($metrics['memory_used']/1024/1024, 1); ?> GB</div>
            <div class="metric-detail">Total: <?php echo round($metrics['memory_total']/1024/1024, 1); ?> GB</div>
            <div class="metric-detail">Libre: <?php echo round($metrics['memory_free']/1024/1024, 1); ?> GB</div>
            <?php if ($metrics['swap_total'] > 0): ?>
            <div class="metric-detail">Swap: <?php echo $metrics['swap_percent']; ?>% (<?php echo round($metrics['swap_used']/1024/1024, 1); ?> GB)</div>
            <?php endif; ?>
        </div>
        
        <!-- Stockage -->
        <div class="metric-card">
            <div class="metric-title">💿 Stockage</div>
            <div class="metric-value" style="color: #e67e22;">
                <?php echo $metrics['disk_percent']; ?>%
            </div>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo $metrics['disk_percent']; ?>%; background: linear-gradient(135deg, #e67e22, #d35400);">
                    <div class="progress-text"><?php echo $metrics['disk_percent']; ?>%</div>
                </div>
            </div>
            <div class="metric-detail">Utilisé: <?php echo round($metrics['disk_used']/1024/1024, 1); ?> GB</div>
            <div class="metric-detail">Total: <?php echo round($metrics['disk_total']/1024/1024, 1); ?> GB</div>
            <div class="metric-detail">Libre: <?php echo round($metrics['disk_free']/1024/1024, 1); ?> GB</div>
        </div>
        
        <!-- VMs/Containers -->
        <div class="metric-card">
            <div class="metric-title">🖥️ Machines Virtuelles</div>
            <div class="grid-2">
                <div style="text-align: center;">
                    <div class="metric-value" style="color: #27ae60; font-size: 2rem;">
                        <?php echo $metrics['vms_running']; ?>
                    </div>
                    <div class="metric-detail">VMs actives</div>
                    <div class="metric-detail"><?php echo $metrics['vms_stopped']; ?> arrêtées</div>
                </div>
                <div style="text-align: center;">
                    <div class="metric-value" style="color: #3498db; font-size: 2rem;">
                        <?php echo $metrics['lxc_running']; ?>
                    </div>
                    <div class="metric-detail">Conteneurs actifs</div>
                    <div class="metric-detail"><?php echo $metrics['lxc_stopped']; ?> arrêtés</div>
                </div>
            </div>
        </div>
        
        <!-- Services Proxmox -->
        <div class="metric-card">
            <div class="metric-title">⚙️ Services Proxmox</div>
            <?php foreach ($services as $service => $active): ?>
            <div class="service-item">
                <span><?php echo $service; ?></span>
                <span class="service-status <?php echo $active ? 'status-active' : 'status-inactive'; ?>">
                    <?php echo $active ? '✓ Actif' : '✗ Inactif'; ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Réseau -->
        <?php if (!empty($metrics['network_interfaces'])): ?>
        <div class="metric-card">
            <div class="metric-title">🌐 Interfaces Réseau</div>
            <?php foreach ($metrics['network_interfaces'] as $interface => $stats): ?>
            <div class="network-interface">
                <span><strong><?php echo $interface; ?></strong></span>
                <span>RX: <?php echo $stats['rx_mb']; ?> MB | TX: <?php echo $stats['tx_mb']; ?> MB</span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- Recommandations -->
        <?php if (!empty($recommendations)): ?>
        <div class="metric-card recommendations">
            <div class="metric-title">💡 Recommandations d'Équilibrage</div>
            <?php foreach ($recommendations as $rec): ?>
            <div class="recommendation-item <?php echo $rec['type']; ?>">
                <strong><?php echo $rec['message']; ?></strong>
                <ul class="recommendation-actions">
                    <?php foreach ($rec['actions'] as $action): ?>
                    <li><?php echo $action; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <button class="refresh-btn" onclick="location.reload()" title="Actualiser">
        🔄
    </button>
    
    <script>
        // Auto-refresh toutes les 30 secondes
        let refreshInterval = setInterval(() => {
            location.reload();
        }, 30000);
        
        // Animation au chargement
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.metric-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(30px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.6s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 150);
            });
            
            console.log('🖥️ Monitoring Proxmox chargé');
            console.log('📊 Score global:', <?php echo $metrics['global_score']; ?>);
        });
        
        // Pause auto-refresh si l'utilisateur interagit
        document.addEventListener('click', function() {
            clearInterval(refreshInterval);
            setTimeout(() => {
                refreshInterval = setInterval(() => {
                    location.reload();
                }, 30000);
            }, 60000); // Reprend après 1 minute d'inactivité
        });
    </script>
</body>
</html>
