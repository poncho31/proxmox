<?php
// Charger les variables d'environnement
require_once __DIR__ . '/../src/env.php';
Env::load();

// Récupérer les paramètres des caméras depuis .env
$go2rtc_ip = Env::get('GO2RTC_IP', '192.168.0.51');
$go2rtc_port = Env::get('GO2RTC_PORT', '1984');
$go2rtc_webrtc_port = Env::get('GO2RTC_WEBRTC_PORT', '8555');
$camera1_name = Env::get('CAMERA1_NAME', 'tapo_camera1');
$camera1_label = Env::get('CAMERA1_LABEL', 'Caméra Tapo 1');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Surveillance Caméras - Hub Proxmox</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: linear-gradient(135deg, #2c3e50, #34495e);
            min-height: 100vh;
            color: #333;
        }

        .header {
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 15px 20px;
            text-align: center;
            position: sticky;
            top: 0;
            z-index: 100;
            backdrop-filter: blur(10px);
        }

        .header h1 {
            font-size: 1.8rem;
            margin-bottom: 5px;
        }

        .nav-buttons {
            margin-top: 10px;
            display: flex;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .nav-btn {
            background: #3498db;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .nav-btn:hover {
            background: #2980b9;
            transform: translateY(-1px);
        }

        .nav-btn.back {
            background: #95a5a6;
        }

        .nav-btn.back:hover {
            background: #7f8c8d;
        }

        .container {
            padding: 20px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .cameras-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .camera-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            transition: transform 0.3s ease;
        }

        .camera-card:hover {
            transform: translateY(-3px);
        }

        .camera-title {
            color: #2c3e50;
            font-size: 1.3rem;
            margin-bottom: 15px;
            text-align: center;
            font-weight: 600;
        }

        .stream-container {
            position: relative;
            background: #000;
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 15px;
            min-height: 300px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .stream-iframe {
            width: 100%;
            height: 300px;
            border: none;
            border-radius: 8px;
        }

        .stream-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 10px;
            margin-top: 15px;
        }

        .stream-btn {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            text-align: center;
            text-decoration: none;
            display: block;
        }

        .stream-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(231, 76, 60, 0.3);
        }

        .stream-btn.webrtc {
            background: linear-gradient(135deg, #27ae60, #229954);
        }

        .stream-btn.webrtc:hover {
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.3);
        }

        .stream-btn.api {
            background: linear-gradient(135deg, #f39c12, #e67e22);
        }

        .stream-btn.api:hover {
            box-shadow: 0 5px 15px rgba(243, 156, 18, 0.3);
        }

        .info-panel {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            margin-top: 20px;
        }

        .info-panel h3 {
            color: #2c3e50;
            margin-bottom: 15px;
            font-size: 1.2rem;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 12px;
            background: #f8f9fa;
            border-radius: 6px;
            border-left: 4px solid #3498db;
        }

        .info-label {
            font-weight: 600;
            color: #555;
        }

        .info-value {
            color: #2c3e50;
            font-family: monospace;
            font-size: 0.9rem;
        }

        .status-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 8px;
        }

        .status-online {
            background-color: #27ae60;
        }

        .status-offline {
            background-color: #e74c3c;
        }

        @media (max-width: 768px) {
            .cameras-grid {
                grid-template-columns: 1fr;
            }
            
            .container {
                padding: 10px;
            }
            
            .nav-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .stream-options {
                grid-template-columns: 1fr;
            }
        }

        .loading {
            color: #7f8c8d;
            font-style: italic;
            text-align: center;
            padding: 20px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📹 Surveillance Caméras</h1>
        <p>Interface de monitoring vidéo - <?php echo date('d/m/Y H:i:s'); ?></p>
        <div class="nav-buttons">
            <a href="proxmox_main_web_server.php" class="nav-btn back">🏠 Retour Hub</a>
            <a href="<?php echo Env::get('URL_GO2RTC', 'https://100.104.128.114:82'); ?>" target="_blank" class="nav-btn">🔧 Interface go2rtc</a>
            <button onclick="location.reload()" class="nav-btn">🔄 Actualiser</button>
        </div>
    </div>

    <div class="container">
        <div class="cameras-grid">
            <!-- Caméra 1 -->
            <div class="camera-card">
                <h2 class="camera-title">
                    <span class="status-indicator status-online"></span>
                    <?php echo htmlspecialchars($camera1_label); ?>
                </h2>
                
                <div class="stream-container">
                    <iframe 
                        class="stream-iframe" 
                        src="<?php echo Env::get('URL_GO2RTC', 'https://100.104.128.114:82'); ?>/stream.html?src=<?php echo $camera1_name; ?>&mode=mse"
                        allow="camera; microphone; autoplay"
                        loading="lazy">
                    </iframe>
                </div>

                <div class="stream-options">
                    <a href="<?php echo Env::get('URL_GO2RTC', 'https://100.104.128.114:82'); ?>/stream.html?src=<?php echo $camera1_name; ?>&mode=mse" 
                       target="_blank" class="stream-btn webrtc">
                        🎥 Flux Vidéo MSE
                    </a>
                    <a href="<?php echo Env::get('URL_GO2RTC', 'https://100.104.128.114:82'); ?>/api/frame.jpeg?src=<?php echo $camera1_name; ?>" 
                       target="_blank" class="stream-btn api">
                        📸 Photo Instantanée
                    </a>
                </div>
            </div>
        </div>

        <!-- Panneau d'informations -->
        <div class="info-panel">
            <h3>📊 Informations Système</h3>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">🖥️ Serveur go2rtc</span>
                    <span class="info-value"><?php echo Env::get('URL_GO2RTC', 'https://100.104.128.114:82'); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">� Mode Stream</span>
                    <span class="info-value">MSE (Media Source Extensions)</span>
                </div>
                <div class="info-item">
                    <span class="info-label">📹 Caméras</span>
                    <span class="info-value">1 Active</span>
                </div>
                <div class="info-item">
                    <span class="info-label">⏱️ Dernière MAJ</span>
                    <span class="info-value"><?php echo date('H:i:s'); ?></span>
                </div>
            </div>
        </div>

        <!-- Instructions -->
        <div class="info-panel">
            <h3>💡 Instructions d'utilisation</h3>
            <div style="color: #555; line-height: 1.6;">
                <p><strong>🎥 Flux Vidéo MSE :</strong> Stream en temps réel via WebSocket, compatible avec reverse proxy HTTPS</p>
                <p><strong>� Photo Instantanée :</strong> Image fixe de la caméra actualisée en temps réel</p>
                <p><strong>� Interface go2rtc :</strong> Configuration avancée et diagnostics du serveur vidéo</p>
                <p><strong>� URL directe :</strong> <?php echo Env::get('URL_GO2RTC', 'https://100.104.128.114:82'); ?>/stream.html?src=<?php echo $camera1_name; ?>&mode=mse</p>
            </div>
        </div>
    </div>

    <script>
        // Auto-refresh des frames toutes les 30 secondes pour maintenir la connexion
        setInterval(() => {
            const iframes = document.querySelectorAll('.stream-iframe');
            iframes.forEach(iframe => {
                // Vérifier si le stream est encore actif
                try {
                    iframe.contentWindow.postMessage('ping', '*');
                } catch (e) {
                    console.log('Stream refresh needed');
                }
            });
        }, 30000);

        // Animation au chargement
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.camera-card, .info-panel');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 200);
            });

            console.log('📹 Interface caméras chargée');
            console.log('🔧 go2rtc: <?php echo Env::get('URL_GO2RTC', 'https://100.104.128.114:82'); ?>');
        });
    </script>
</body>
</html>
