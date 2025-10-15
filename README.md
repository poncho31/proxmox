# 🖥️ Système de Monitoring Proxmox

Ce système de monitoring vous permet de surveiller les performances de votre serveur Proxmox VE et de gérer l'équilibrage des charges.

## 📁 Structure du Projet

```
proxmox/
├── public/
│   ├── proxmox_main_web_server.php  # Hub principal avec liens vers services
│   ├── info_server.php              # Page de monitoring détaillée
│   └── todo_list.php               # Application TODO
├── src/
│   ├── env.php                     # Gestionnaire des variables d'environnement
│   └── database.php                # Connexion base de données
├── config/
│   ├── Caddyfile                   # Configuration Caddy
│   └── nginx.conf                  # Configuration Nginx
├── .env                            # Variables d'environnement
├── monitoring_system.sh            # Script bash de monitoring
└── proxmox_caddy_config.md         # Configuration automatisée Caddy
```

## 🚀 Accès aux Services

### Interface Web
- **Hub Principal** : `http://votre-ip/` (port 80)
- **Monitoring Système** : `http://votre-ip/info_server.php`
- **TODO List** : `http://votre-ip:81/`
- **Application Rust** : `http://votre-ip:82/`
- **Interface Proxmox** : `https://votre-ip:8006`

### Variables d'Environnement (.env)

```bash
# Base de données
DATABASE_URL=mysql://user:password@host:port/database

# Adresses Proxmox
IP_PROXMOX_PUBLIC=https://votre-ip-publique:8006
IP_PROXMOX_LOCAL=https://votre-ip-locale:8006

# Connexions SSH Tailscale
PROXMOX_SSH_TAILSCALE_CONNEXION=ssh -p 22 root@tailscale-ip
ANDROID_SSH_TAILSCALE_CONNEXION=ssh -p 8022 user@tailscale-ip
DESKTOP_SSH_TAILSCALE_CONNEXION=ssh -p 22 root@tailscale-ip
```

## 📊 Monitoring des Performances

### Interface Web (`info_server.php`)

L'interface web de monitoring affiche :

- **Score de Charge Global** : Indicateur synthétique de la santé du système
- **CPU** : Utilisation, température, load average
- **Mémoire** : RAM utilisée/libre, swap
- **Stockage** : Espace disque utilisé/libre
- **VMs/Conteneurs** : Nombre d'instances actives/arrêtées
- **Services** : État des services Proxmox
- **Réseau** : Trafic des interfaces
- **Recommandations** : Suggestions d'optimisation automatiques

#### Fonctionnalités
- Auto-refresh toutes les 30 secondes
- Barres de progression visuelles
- Alertes couleur selon les seuils
- Interface responsive (mobile/desktop)

### Script Bash (`monitoring_system.sh`)

```bash
# Exécution unique
./monitoring_system.sh

# Monitoring continu (refresh 60s)
./monitoring_system.sh --continuous

# Aide
./monitoring_system.sh --help
```

#### Informations affichées :
- État CPU (modèle, cœurs, utilisation, température)
- Mémoire (RAM, swap)
- Stockage (partitions, pools ZFS)
- Réseau (interfaces, trafic)
- Services (Proxmox, Caddy, Nginx)
- VMs/Conteneurs (actifs/arrêtés)
- Score global de charge
- Recommandations d'équilibrage

## ⚖️ Équilibrage des Charges

### Seuils d'Alerte

| Métrique | Faible | Modérée | Élevée |
|----------|--------|---------|--------|
| Score Global | < 30% | 30-70% | > 70% |
| CPU | < 60% | 60-80% | > 80% |
| Mémoire | < 70% | 70-85% | > 85% |
| Disque | < 80% | 80-90% | > 90% |

### Recommandations Automatiques

Le système génère automatiquement des recommandations :

#### CPU Surchargé (> 80%)
- Migrer des VMs vers d'autres nœuds
- Réduire le nombre de VMs actives
- Optimiser les processus consommateurs

#### Mémoire Critique (> 85%)
- Arrêter des VMs non essentielles
- Augmenter la RAM du serveur
- Optimiser l'allocation mémoire

#### Stockage Critique (> 90%)
- Nettoyer les snapshots anciens
- Migrer des VMs vers autre stockage
- Étendre l'espace disque

#### Load Average Élevé
- Vérifier les processus consommateurs
- Optimiser les services actifs
- Répartir la charge

## 🔧 Configuration Caddy

### Installation Automatique

Le script `proxmox_caddy_config.md` automatise :
- Installation de Caddy sur Proxmox
- Configuration HTTPS avec authentification
- Création de conteneurs LXC
- Configuration des services

### Variables de Configuration

```bash
# Dans proxmox_caddy_config.md
PASSWORD="MOTDEPASSE"              # Mot de passe auth Basic
MAIN_IP="IP_PVE"                   # IP du serveur Proxmox
APP_GIT="https://github.com/USER/REPO.git"
APP_DOMAIN="app.domaine.tld"

# Allocation des ressources
APP_RAM=6144          # 6 Go pour l'application
APP_CPU=2
DB_RAM=3072           # 3 Go pour la base de données
DB_CPU=1
```

## 🐳 Architecture des Conteneurs

### Conteneur Application (ID: 101)
- **RAM** : 6 Go
- **CPU** : 2 cœurs
- **Disque** : 80 Go
- **Services** : Caddy, PHP-FPM, Git
- **Port** : 80, 81

### Conteneur Base de Données (ID: 102)
- **RAM** : 3 Go
- **CPU** : 1 cœur
- **Disque** : 40 Go
- **Service** : MariaDB
- **Port** : 3306

## 🌐 Intégration Tailscale

### Connexions SSH Configurées

```bash
# Proxmox
ssh -p 22 root@tailscale-ip-proxmox

# Android (Termux)
ssh -p 8022 user@tailscale-ip-android

# Desktop
ssh -p 22 root@tailscale-ip-desktop
```

### Avantages
- Accès sécurisé à distance
- Réseau privé virtuel
- Connexions P2P chiffrées
- Gestion centralisée des accès

## 📱 Utilisation Mobile

L'interface est optimisée pour mobile :
- Design responsive
- Navigation tactile
- Métriques adaptées
- Alertes visuelles

## 🔒 Sécurité

- Authentification HTTP Basic sur Caddy
- Variables d'environnement pour les credentials
- HTTPS automatique avec Let's Encrypt
- Isolation des conteneurs LXC
- Accès VPN via Tailscale

## 🚨 Dépannage

### Problèmes Courants

1. **Page de monitoring inaccessible**
   ```bash
   # Vérifier les services
   systemctl status nginx caddy php-fpm
   
   # Vérifier les permissions
   ls -la /var/www/html/info_server.php
   ```

2. **Variables d'environnement non chargées**
   ```bash
   # Vérifier le fichier .env
   cat .env
   
   # Tester le chargement
   php -r "require 'src/env.php'; Env::load(); var_dump(Env::get('DATABASE_URL'));"
   ```

3. **Métriques système incorrectes**
   ```bash
   # Tester les commandes système
   top -bn1
   free -h
   df -h
   ```

### Logs Utiles

```bash
# Logs Caddy
journalctl -u caddy -f

# Logs Nginx
tail -f /var/log/nginx/error.log

# Logs Proxmox
tail -f /var/log/pve/tasks/active
```

## 📈 Optimisations Futures

- Intégration Prometheus/Grafana
- Alertes par email/Slack
- API REST pour les métriques
- Dashboard temps réel WebSocket
- Historique des performances
- Prédictions de charge

---

*Dernière mise à jour : Octobre 2025*
