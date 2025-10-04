<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page de Test - Proxmox Project</title>
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
            display: flex;
            align-items: center;
            justify-content: center;
            color: #333;
        }

        .container {
            background: rgba(255, 255, 255, 0.95);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 600px;
            width: 90%;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        h1 {
            color: #764ba2;
            font-size: 2.5rem;
            margin-bottom: 20px;
            font-weight: 700;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
        }

        .subtitle {
            color: #666;
            font-size: 1.2rem;
            margin-bottom: 30px;
            font-weight: 300;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }

        .info-card {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
            transform: translateY(0);
            transition: transform 0.3s ease;
        }

        .info-card:hover {
            transform: translateY(-5px);
        }

        .info-card h3 {
            margin-bottom: 10px;
            font-size: 1.3rem;
        }

        .info-card p {
            font-size: 1rem;
            opacity: 0.9;
        }

        .proxmox-section {
            margin: 40px 0;
            text-align: left;
        }

        .proxmox-section h2 {
            color: #764ba2;
            margin-bottom: 25px;
            font-size: 1.8rem;
            text-align: center;
        }

        .proxmox-description {
            background: rgba(118, 75, 162, 0.05);
            padding: 25px;
            border-radius: 15px;
            border-left: 4px solid #764ba2;
        }

        .proxmox-description > p {
            font-size: 1.1rem;
            line-height: 1.6;
            margin-bottom: 25px;
            text-align: justify;
        }

        .tech-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin: 25px 0;
        }

        .tech-card {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }

        .tech-card h4 {
            margin-bottom: 10px;
            font-size: 1.2rem;
        }

        .tech-card p {
            font-size: 0.95rem;
            opacity: 0.9;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin: 25px 0;
        }

        .feature-category {
            background: rgba(255, 255, 255, 0.7);
            padding: 20px;
            border-radius: 10px;
            border: 1px solid rgba(118, 75, 162, 0.2);
        }

        .feature-category h3 {
            color: #764ba2;
            margin-bottom: 15px;
            font-size: 1.3rem;
            text-align: center;
        }

        .proxmox-stats {
            margin: 40px 0;
            text-align: center;
        }

        .proxmox-stats h2 {
            color: #764ba2;
            margin-bottom: 25px;
            font-size: 1.8rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 25px 0;
        }

        .stat-item {
            background: linear-gradient(135deg, #FF6B6B 0%, #4ECDC4 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            transition: transform 0.3s ease;
        }

        .stat-item:hover {
            transform: translateY(-3px);
        }

        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
            margin-bottom: 8px;
            font-weight: 300;
        }

        .stat-value {
            font-size: 1.1rem;
            font-weight: bold;
        }

        .features {
            margin: 30px 0;
        }

        .features h2 {
            color: #764ba2;
            margin-bottom: 20px;
            font-size: 1.8rem;
        }

        .feature-list {
            list-style: none;
            text-align: left;
            display: inline-block;
        }

        .feature-list li {
            padding: 10px 0;
            font-size: 1.1rem;
            position: relative;
            padding-left: 30px;
        }

        .feature-list li::before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #4CAF50;
            font-weight: bold;
            font-size: 1.2rem;
        }

        .button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 25px;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            margin: 10px;
        }

        .button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }

        .timestamp {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            color: #888;
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            .container {
                padding: 20px;
                margin: 20px;
            }
            
            h1 {
                font-size: 2rem;
            }
            
            .info-grid {
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
    </style>
</head>
<body>
    <div class="container">
        <h1 class="pulse">🚀 Page de Test</h1>
        <p class="subtitle">Projet Proxmox - Environnement de développement</p>
        
        <div class="info-grid">
            <div class="info-card">
                <h3>📅 Date</h3>
                <p><?php echo date('d/m/Y H:i:s'); ?></p>
            </div>
            <div class="info-card">
                <h3>🖥️ Serveur</h3>
                <p><?php echo $_SERVER['SERVER_NAME'] ?? 'localhost'; ?></p>
            </div>
            <div class="info-card">
                <h3>🌐 IP Client</h3>
                <p><?php echo $_SERVER['REMOTE_ADDR'] ?? 'Unknown'; ?></p>
            </div>
            <div class="info-card">
                <h3>🔧 PHP Version</h3>
                <p><?php echo PHP_VERSION; ?></p>
            </div>
        </div>

        <div class="proxmox-section">
            <h2>🖥️ À propos de Proxmox VE</h2>
            <div class="proxmox-description">
                <p><strong>Proxmox Virtual Environment (Proxmox VE)</strong> est une plateforme de virtualisation open-source complète qui combine deux technologies de virtualisation :</p>
                
                <div class="tech-grid">
                    <div class="tech-card">
                        <h4>🐧 Conteneurs LXC</h4>
                        <p>Virtualisation légère au niveau du système d'exploitation</p>
                    </div>
                    <div class="tech-card">
                        <h4>🖥️ Machines Virtuelles KVM</h4>
                        <p>Virtualisation complète avec hyperviseur de type 1</p>
                    </div>
                </div>

                <div class="features-grid">
                    <div class="feature-category">
                        <h3>⚡ Avantages Clés</h3>
                        <ul class="feature-list">
                            <li>Interface web intuitive</li>
                            <li>Haute disponibilité (HA)</li>
                            <li>Sauvegarde et restauration intégrées</li>
                            <li>Clustering et migration à chaud</li>
                            <li>Gestion centralisée des ressources</li>
                        </ul>
                    </div>
                    <div class="feature-category">
                        <h3>🔧 Cas d'usage</h3>
                        <ul class="feature-list">
                            <li>Consolidation de serveurs</li>
                            <li>Environnements de développement</li>
                            <li>Infrastructure cloud privée</li>
                            <li>Laboratoires de test</li>
                            <li>Hébergement de services</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="features">
            <h2>🎯 Fonctionnalités testées</h2>
            <ul class="feature-list">
                <li>Interface utilisateur responsive</li>
                <li>Intégration PHP dynamique</li>
                <li>Design moderne avec CSS3</li>
                <li>Animations et transitions</li>
                <li>Affichage des informations serveur</li>
                <li>Compatibilité mobile</li>
            </ul>
        </div>

        <div class="proxmox-stats">
            <h2>📊 Environnement Technique</h2>
            <div class="stats-grid">
                <div class="stat-item">
                    <span class="stat-label">🌐 Interface Web</span>
                    <span class="stat-value">Port 8006 (HTTPS)</span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">🐧 Système Base</span>
                    <span class="stat-value">Debian GNU/Linux</span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">⚡ Hyperviseur</span>
                    <span class="stat-value">KVM + LXC</span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">🔒 Authentification</span>
                    <span class="stat-value">PAM, LDAP, AD</span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">💾 Stockage</span>
                    <span class="stat-value">ZFS, Ceph, NFS</span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">🌍 Réseau</span>
                    <span class="stat-value">SDN, VLAN, Firewall</span>
                </div>
            </div>
        </div>

        <div>
            <a href="index.php" class="button">🏠 Page principale</a>
            <button class="button" onclick="location.reload()">🔄 Actualiser</button>
            <a href="https://www.proxmox.com/" target="_blank" class="button">📖 Documentation Proxmox</a>
        </div>

        <div class="timestamp">
            <p>Dernière mise à jour : <?php echo date('d/m/Y à H:i:s'); ?></p>
            <p>Statut : <span style="color: #4CAF50; font-weight: bold;">✅ Opérationnel</span></p>
        </div>
    </div>

    <script>
        // Animation des cartes au chargement
        document.addEventListener('DOMContentLoaded', function() {
            // Animation des cartes d'information
            const infoCards = document.querySelectorAll('.info-card');
            infoCards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(20px)';
                    card.style.transition = 'all 0.5s ease';
                    
                    setTimeout(() => {
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    }, 100);
                }, index * 200);
            });

            // Animation des cartes techniques
            const techCards = document.querySelectorAll('.tech-card');
            techCards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '0';
                    card.style.transform = 'scale(0.8)';
                    card.style.transition = 'all 0.6s ease';
                    
                    setTimeout(() => {
                        card.style.opacity = '1';
                        card.style.transform = 'scale(1)';
                    }, 100);
                }, (index + 4) * 200);
            });

            // Animation des statistiques
            const statItems = document.querySelectorAll('.stat-item');
            statItems.forEach((item, index) => {
                setTimeout(() => {
                    item.style.opacity = '0';
                    item.style.transform = 'translateX(-20px)';
                    item.style.transition = 'all 0.4s ease';
                    
                    setTimeout(() => {
                        item.style.opacity = '1';
                        item.style.transform = 'translateX(0)';
                    }, 100);
                }, (index + 8) * 150);
            });
        });

        // Messages de bienvenue dans la console
        setTimeout(() => {
            console.log('🎉 Page de test Proxmox chargée avec succès !');
            console.log('📊 Toutes les fonctionnalités sont opérationnelles');
            console.log('🖥️ Proxmox VE: Plateforme de virtualisation enterprise');
            console.log('⚡ KVM + LXC pour une virtualisation complète');
        }, 1000);

        // Animation du titre
        setInterval(() => {
            const title = document.querySelector('h1');
            title.style.transform = 'scale(1.02)';
            setTimeout(() => {
                title.style.transform = 'scale(1)';
            }, 200);
        }, 5000);
    </script>
</body>
</html>