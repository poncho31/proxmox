<?php

namespace App\Config;

/**
 * Configuration pour les opérations de mise à jour
 */
class UpdateConfig
{
    private static array $operations = [
        'git_reset' => [
            'description' => 'Réinitialisation des fichiers modifiés',
            'command' => 'cd /var/www/html/php && git reset --hard',
            'icon' => '🔄',
            'success_message' => 'Fichiers locaux réinitialisés',
            'error_message' => 'Échec de la réinitialisation Git',
            'skip_output_patterns' => ['HEAD is now at']
        ],
        'git' => [
            'description' => 'Mise à jour du code depuis Git',
            'command' => 'cd /var/www/html/php && git pull origin main',
            'icon' => '📥',
            'success_message' => 'Code mis à jour depuis le dépôt Git',
            'error_message' => 'Échec de la mise à jour Git',
            'skip_output_patterns' => ['Already up to date.', 'FETCH_HEAD']
        ],
        'php' => [
            'description' => 'Redémarrage du service PHP-FPM',
            'command' => 'dynamic', // Sera résolu dynamiquement
            'icon' => '🔄',
            'success_message' => 'Service PHP-FPM redémarré avec succès',
            'error_message' => 'Échec du redémarrage de PHP-FPM'
        ],
        'ssl_cert' => [
            'description' => 'Génération du certificat SSL auto-signé',
            'command' => 'mkdir -p /etc/ssl/private /etc/ssl/certs && openssl req -x509 -nodes -days 365 -newkey rsa:2048 -keyout /etc/ssl/private/nginx-selfsigned.key -out /etc/ssl/certs/nginx-selfsigned.crt -subj "/C=FR/ST=France/L=Paris/O=Proxmox/OU=IT Department/CN=localhost"',
            'icon' => '🔒',
            'success_message' => 'Certificat SSL auto-signé généré avec succès',
            'error_message' => 'Échec de la génération du certificat SSL',
            'skip_output_patterns' => ['Generating a RSA private key', 'writing new private key']
        ],
        'nginx_config' => [
            'description' => 'Mise à jour de la configuration Nginx',
            'command' => 'cp /var/www/html/php/config/nginx.conf /etc/nginx/nginx.conf && nginx -t',
            'icon' => '⚙️',
            'success_message' => 'Configuration Nginx mise à jour et validée',
            'error_message' => 'Échec de la mise à jour de la configuration Nginx',
            'skip_output_patterns' => ['syntax is ok', 'test is successful']
        ],
        'nginx' => [
            'description' => 'Rechargement de la configuration Nginx',
            'command' => 'systemctl reload nginx',
            'icon' => '🌐',
            'success_message' => 'Configuration Nginx rechargée',
            'error_message' => 'Échec du rechargement de Nginx'
        ],
        'permissions' => [
            'description' => 'Mise à jour des permissions des fichiers',
            'command' => 'chown -R www-data:www-data /var/www/html/php && chmod -R 755 /var/www/html/php',
            'icon' => '🔐',
            'success_message' => 'Permissions des fichiers mises à jour',
            'error_message' => 'Échec de la mise à jour des permissions'
        ],
        'cache' => [
            'description' => 'Nettoyage du cache système',
            'command' => 'sync && echo 3 > /proc/sys/vm/drop_caches 2>/dev/null || echo "Cache clearing not available"',
            'icon' => '🧹',
            'success_message' => 'Cache système nettoyé',
            'error_message' => 'Nettoyage du cache non disponible (système en lecture seule)',
            'optional' => true
        ]
    ];

    private static array $phpServices = [
        'php8.2-fpm',
        'php8.1-fpm',
        'php8.0-fpm',
        'php7.4-fpm',
        'php-fpm'
    ];

    public static function getOperations(): array
    {
        return self::$operations;
    }

    public static function getOperation(string $key): ?array
    {
        return self::$operations[$key] ?? null;
    }

    public static function getPhpServices(): array
    {
        return self::$phpServices;
    }

    public static function getTimeLimit(): int
    {
        return 60;
    }
}
