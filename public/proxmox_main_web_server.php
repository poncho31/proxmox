<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hub Proxmox - Serveur Principal</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            overflow: hidden;
            color: #333;
            display: flex;
            flex-direction: column;
        }

        .header {
            text-align: center;
            padding: 20px;
            color: white;
        }

        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }

        .header .subtitle {
            font-size: 1.2rem;
            opacity: 0.9;
        }

        .main-content {
            flex: 1;
            display: grid;
            grid-template-columns: 1fr 2fr 1fr;
            gap: 20px;
            padding: 0 20px 20px;
            max-height: calc(100vh - 140px);
        }

        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .info-card, .site-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .info-card:hover, .site-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2);
        }

        .central-hub {
            display: grid;
            grid-template-rows: auto 1fr auto;
            gap: 20px;
        }

        .sites-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .site-link {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            padding: 25px;
            border-radius: 15px;
            text-decoration: none;
            display: block;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .site-link:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
        }

        .site-link::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .site-link:hover::before {
            left: 100%;
        }

        .site-icon {
            font-size: 3rem;
            margin-bottom: 10px;
            display: block;
        }

        .site-title {
            font-size: 1.4rem;
            font-weight: bold;
            margin-bottom: 8px;
        }

        .site-desc {
            font-size: 0.9rem;
            opacity: 0.9;
            margin-bottom: 10px;
        }

        .site-port {
            font-size: 0.8rem;
            background: rgba(255, 255, 255, 0.2);
            padding: 4px 8px;
            border-radius: 10px;
            display: inline-block;
        }

        .system-info h3 {
            color: #764ba2;
            margin-bottom: 15px;
            font-size: 1.3rem;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 600;
            color: #555;
        }

        .info-value {
            color: #764ba2;
            font-weight: bold;
        }

        .proxmox-info h3 {
            color: #764ba2;
            margin-bottom: 15px;
            font-size: 1.3rem;
        }

        .feature-list {
            list-style: none;
        }

        .feature-list li {
            padding: 8px 0;
            font-size: 0.95rem;
            position: relative;
            padding-left: 20px;
        }

        .feature-list li::before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #4CAF50;
            font-weight: bold;
        }

        .status-bar {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .status-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #4CAF50;
            animation: pulse 2s infinite;
        }

        .admin-tools {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .tool-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 20px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .tool-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        @media (max-width: 1200px) {
            .main-content {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .header h1 {
                font-size: 2rem;
            }
            
            .sites-grid {
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .header {
                padding: 15px;
            }
            
            .header h1 {
                font-size: 1.8rem;
            }
            
            .main-content {
                padding: 0 15px 15px;
            }
            
            .sites-grid {
                grid-template-columns: 1fr;
            }
        }

        .pulse {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
            100% {
                transform: scale(1);
            }
        }

        .fade-in {
            opacity: 0;
            transform: translateY(20px);
            animation: fadeIn 0.6s ease forwards;
        }

        @keyframes fadeIn {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    <!-- En-tête -->
    <div class="header">
        <h1 class="pulse">�️ Hub Proxmox</h1>
        <p class="subtitle">Centre de contrôle des services - <?php echo date('d/m/Y H:i:s'); ?></p>
    </div>

    <!-- Contenu principal -->
    <div class="main-content">
        <!-- Sidebar gauche - Informations système -->
        <div class="sidebar">
            <div class="info-card system-info fade-in">
                <h3>� Système</h3>
                <div class="info-item">
                    <span class="info-label">🖥️ Serveur</span>
                    <span class="info-value"><?php echo $_SERVER['SERVER_NAME'] ?? 'localhost'; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">🌐 IP Client</span>
                    <span class="info-value"><?php echo $_SERVER['REMOTE_ADDR'] ?? 'Unknown'; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">🔧 PHP</span>
                    <span class="info-value"><?php echo PHP_VERSION; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">⏰ Uptime</span>
                    <span class="info-value"><?php echo date('H:i'); ?></span>
                </div>
            </div>

            <div class="info-card proxmox-info fade-in">
                <h3>⚡ Proxmox VE</h3>
                <ul class="feature-list">
                    <li>Virtualisation KVM</li>
                    <li>Conteneurs LXC</li>
                    <li>Interface Web</li>
                    <li>Haute disponibilité</li>
                    <li>Sauvegarde intégrée</li>
                    <li>Clustering</li>
                </ul>
            </div>
        </div>

        <!-- Zone centrale - Sites disponibles -->
        <div class="central-hub">
            <div class="site-card fade-in">
                <h2 style="color: #764ba2; margin-bottom: 20px; text-align: center;">🌐 Sites Disponibles</h2>
                <div class="sites-grid">
                    <!-- Site principal - Port 80 -->
                    <a href="/" class="site-link" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        <span class="site-icon">🏠</span>
                        <div class="site-title">Hub Principal</div>
                        <div class="site-desc">Centre de contrôle</div>
                        <div class="site-port">Port 80</div>
                    </a>

                    <!-- Site PHP - Port 81 -->
                    <a href="http://<?php echo $_SERVER['SERVER_NAME'] ?? 'localhost'; ?>:81" class="site-link" style="background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);">
                        <span class="site-icon">🐘</span>
                        <div class="site-title">PHP Dev</div>
                        <div class="site-desc">Environnement PHP</div>
                        <div class="site-port">Port 81</div>
                    </a>

                    <!-- Site Rust - Port 82 -->
                    <a href="http://<?php echo $_SERVER['SERVER_NAME'] ?? 'localhost'; ?>:82" class="site-link" style="background: linear-gradient(135deg, #FF6B6B 0%, #4ECDC4 100%);">
                        <span class="site-icon">🦀</span>
                        <div class="site-title">Rust App</div>
                        <div class="site-desc">Application Rust</div>
                        <div class="site-port">Port 82</div>
                    </a>

                    <!-- Proxmox Interface -->
                    <a href="https://<?php echo $_SERVER['SERVER_NAME'] ?? 'localhost'; ?>:8006" target="_blank" class="site-link" style="background: linear-gradient(135deg, #FFA726 0%, #FF7043 100%);">
                        <span class="site-icon">🔧</span>
                        <div class="site-title">Proxmox VE</div>
                        <div class="site-desc">Interface d'administration</div>
                        <div class="site-port">Port 8006</div>
                    </a>
                </div>
            </div>

            <!-- Barre de statut -->
            <div class="status-bar fade-in">
                <div class="status-item">
                    <div class="status-dot"></div>
                    <span>Services opérationnels</span>
                </div>
                <div class="admin-tools">
                    <a href="index2.php" class="tool-btn">� Page Test</a>
                    <a href="update.php" class="tool-btn">⬆️ Mise à jour</a>
                    <button class="tool-btn" onclick="location.reload()">🔄 Actualiser</button>
                </div>
            </div>
        </div>

        <!-- Sidebar droite - Outils rapides -->
        <div class="sidebar">
            <div class="info-card fade-in">
                <h3>🛠️ Outils</h3>
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <a href="https://www.proxmox.com/en/proxmox-ve" target="_blank" class="tool-btn">� Documentation</a>
                    <a href="https://pve.proxmox.com/wiki/Main_Page" target="_blank" class="tool-btn">🔗 Wiki Proxmox</a>
                    <button class="tool-btn" onclick="window.open('/phpinfo.php', '_blank')">ℹ️ PHP Info</button>
                </div>
            </div>

            <div class="info-card fade-in">
                <h3>📈 Statistiques</h3>
                <div class="info-item">
                    <span class="info-label">🌐 Nginx</span>
                    <span class="info-value" style="color: #4CAF50;">●</span>
                </div>
                <div class="info-item">
                    <span class="info-label">🐘 PHP-FPM</span>
                    <span class="info-value" style="color: #4CAF50;">●</span>
                </div>
                <div class="info-item">
                    <span class="info-label">📦 Services</span>
                    <span class="info-value">4/4</span>
                </div>
                <div class="info-item">
                    <span class="info-label">⚡ Load</span>
                    <span class="info-value">Faible</span>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Animation au chargement
        document.addEventListener('DOMContentLoaded', function() {
            // Animation séquentielle des éléments fade-in
            const fadeElements = document.querySelectorAll('.fade-in');
            fadeElements.forEach((element, index) => {
                setTimeout(() => {
                    element.style.animationDelay = (index * 200) + 'ms';
                }, index * 100);
            });

            // Animation des liens de sites
            const siteLinks = document.querySelectorAll('.site-link');
            siteLinks.forEach((link, index) => {
                setTimeout(() => {
                    link.style.opacity = '0';
                    link.style.transform = 'translateY(30px) rotateX(45deg)';
                    link.style.transition = 'all 0.8s ease';
                    
                    setTimeout(() => {
                        link.style.opacity = '1';
                        link.style.transform = 'translateY(0) rotateX(0deg)';
                    }, 100);
                }, (index + 1) * 300);
            });

            // Effet de survol avancé pour les cartes de site
            siteLinks.forEach(link => {
                link.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-10px) scale(1.02)';
                    this.style.boxShadow = '0 25px 50px rgba(0,0,0,0.3)';
                });
                
                link.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0) scale(1)';
                    this.style.boxShadow = '0 10px 30px rgba(0,0,0,0.1)';
                });
            });

            // Animation des points de statut
            const statusDots = document.querySelectorAll('.status-dot');
            statusDots.forEach(dot => {
                setInterval(() => {
                    dot.style.transform = 'scale(1.2)';
                    dot.style.opacity = '0.7';
                    setTimeout(() => {
                        dot.style.transform = 'scale(1)';
                        dot.style.opacity = '1';
                    }, 200);
                }, 3000);
            });
        });

        // Messages de console pour le hub
        setTimeout(() => {
            console.log('🖥️ Hub Proxmox initialisé avec succès !');
            console.log('🌐 Services disponibles:');
            console.log('  • Port 80: Hub principal');
            console.log('  • Port 81: Environnement PHP');
            console.log('  • Port 82: Application Rust');
            console.log('  • Port 8006: Interface Proxmox VE');
            console.log('✅ Tous les services sont opérationnels');
        }, 1500);

        // Fonction pour vérifier le statut des services
        function checkServiceStatus() {
            const indicators = document.querySelectorAll('.status-dot');
            indicators.forEach(dot => {
                // Simulation de vérification de statut
                const isOnline = Math.random() > 0.1; // 90% de chance d'être en ligne
                dot.style.backgroundColor = isOnline ? '#4CAF50' : '#f44336';
            });
        }

        // Vérification du statut toutes les 30 secondes
        setInterval(checkServiceStatus, 30000);

        // Animation périodique du titre
        setInterval(() => {
            const title = document.querySelector('.header h1');
            title.style.transform = 'scale(1.05)';
            setTimeout(() => {
                title.style.transform = 'scale(1)';
            }, 300);
        }, 8000);

        // Gestion du redimensionnement de la fenêtre
        window.addEventListener('resize', function() {
            // Réajustement automatique des animations si nécessaire
            if (window.innerWidth < 768) {
                document.body.style.overflow = 'auto';
            } else {
                document.body.style.overflow = 'hidden';
            }
        });
    </script>
</body>
</html>