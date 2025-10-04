"# 🚀 Proxmox Update Script

Script de mise à jour automatique pour serveur Proxmox avec support SSL.

## 📁 Structure

```
├── update.php              # Script principal avec diagnostic intégré
├── config/nginx.conf       # Configuration Nginx avec HTTPS
└── public/                 # Fichiers web du serveur
```

## 🔧 Utilisation

### Mise à jour complète avec diagnostic
```bash
sudo php update.php
```

Le script effectue automatiquement :
- ✅ Vérification préliminaire du système
- 🔄 Mise à jour complète
- 🔗 Test de connectivité final
- 🔧 Conseils de dépannage si nécessaire

## 🔒 Fonctionnalités SSL

- Génération automatique de certificat SSL auto-signé
- Configuration HTTPS automatique pour Nginx
- Redirection HTTP → HTTPS
- Vérification de validité des certificats

## 🌐 Accès Web

Après mise à jour, le serveur est accessible sur :
- **HTTPS**: https://192.168.0.50 (recommandé)
- **HTTP**: http://192.168.0.50 (redirige vers HTTPS)

## 🔍 Opérations effectuées

1. 🔄 Réinitialisation Git (reset --hard)
2. 📥 Mise à jour du code (git pull)
3. 🔒 Génération/vérification certificat SSL
4. ⚙️ Mise à jour configuration Nginx
5. 🔄 Redémarrage PHP-FPM
6. 🌐 Rechargement Nginx
7. 🔐 Permissions des fichiers
8. 🧹 Nettoyage cache (optionnel)
9. 🔍 Vérification services (optionnel)

## 🆘 Dépannage

Si le serveur n'est pas accessible :

1. Exécuter le diagnostic : `sudo php debug.php`
2. Vérifier les services : `systemctl status nginx php-fpm`
3. Vérifier les logs : `journalctl -u nginx -f`
4. Tester la config : `nginx -t`

## 📝 Logs utiles

```bash
# Logs Nginx
tail -f /var/log/nginx/error.log

# Logs PHP-FPM
journalctl -u php8.2-fpm -f

# Test configuration
nginx -t && echo "OK"
```" 
