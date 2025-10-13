<?php

class Env
{
    private static $variables = [];
    private static $loaded = false;

    /**
     * Charge les variables d'environnement depuis le fichier .env
     */
    public static function load($envPath = null)
    {
        if (self::$loaded) {
            return;
        }

        if ($envPath === null) {
            // Essayer plusieurs chemins possibles pour le fichier .env
            $possibleEnvPaths = [
                __DIR__ . '/../.env',
                dirname(__DIR__) . '/.env',
                realpath(__DIR__ . '/../.env')
            ];
            
            foreach ($possibleEnvPaths as $path) {
                if ($path && file_exists($path)) {
                    $envPath = $path;
                    break;
                }
            }
            
            if (!$envPath) {
                $envPath = __DIR__ . '/../.env'; // Fallback au chemin par défaut
            }
        }

        if (!file_exists($envPath)) {
            throw new Exception("Fichier .env non trouvé : {$envPath}");
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Ignorer les commentaires
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            // Parser la ligne key=value
            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $key = trim($parts[0]);
                $value = trim($parts[1]);
                
                // Supprimer les guillemets si présents
                if ((strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) ||
                    (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1)) {
                    $value = substr($value, 1, -1);
                }
                
                self::$variables[$key] = $value;
                
                // Définir aussi comme variable d'environnement système
                putenv("{$key}={$value}");
            }
        }

        self::$loaded = true;
    }

    /**
     * Récupère une variable d'environnement
     */
    public static function get($key, $default = null)
    {
        // S'assurer que les variables sont chargées
        if (!self::$loaded) {
            self::load();
        }

        // Priorité : variable système > fichier .env > défaut
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }

        return self::$variables[$key] ?? $default;
    }

    /**
     * Vérifie si une variable existe
     */
    public static function has($key)
    {
        if (!self::$loaded) {
            self::load();
        }

        return getenv($key) !== false || isset(self::$variables[$key]);
    }

    /**
     * Récupère toutes les variables chargées
     */
    public static function all()
    {
        if (!self::$loaded) {
            self::load();
        }

        return self::$variables;
    }

    /**
     * Parse une URL de base de données et retourne les composants
     */
    public static function parseDatabaseUrl($url = null)
    {
        if ($url === null) {
            $url = self::get('DATABASE_URL');
        }

        if (!$url) {
            throw new Exception("DATABASE_URL non trouvée dans les variables d'environnement");
        }

        $parsed = parse_url($url);
        
        if (!$parsed) {
            throw new Exception("Format DATABASE_URL invalide : {$url}");
        }

        return [
            'scheme' => $parsed['scheme'] ?? 'mysql',
            'host' => $parsed['host'] ?? 'localhost',
            'port' => $parsed['port'] ?? 3306,
            'dbname' => ltrim($parsed['path'] ?? '', '/'),
            'username' => $parsed['user'] ?? '',
            'password' => $parsed['pass'] ?? ''
        ];
    }

    /**
     * Récupère la configuration de base de données prête à utiliser
     */
    public static function getDatabaseConfig()
    {
        $config = self::parseDatabaseUrl();
        
        if (empty($config['dbname'])) {
            throw new Exception("Nom de base de données manquant dans DATABASE_URL");
        }

        return $config;
    }

    /**
     * Génère un DSN pour PDO
     */
    public static function getDatabaseDsn()
    {
        $config = self::getDatabaseConfig();
        return "{$config['scheme']}:host={$config['host']};port={$config['port']};dbname={$config['dbname']};charset=utf8mb4";
    }
}
