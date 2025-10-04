<?php

namespace App\Services;

use App\Config\UpdateConfig;

/**
 * Service pour détecter et gérer les services PHP
 */
class PhpServiceDetector
{
    private CommandExecutor $commandExecutor;

    public function __construct(CommandExecutor $commandExecutor)
    {
        $this->commandExecutor = $commandExecutor;
    }

    /**
     * Détecte le service PHP-FPM actif
     */
    public function detectPhpService(): ?string
    {
        $services = UpdateConfig::getPhpServices();
        
        foreach ($services as $service) {
            if ($this->commandExecutor->isServiceActive($service)) {
                return $service;
            }
        }
        
        return null;
    }

    /**
     * Génère la commande de redémarrage pour le service PHP détecté
     */
    public function getRestartCommand(): ?string
    {
        $service = $this->detectPhpService();
        return $service ? "systemctl restart $service" : null;
    }

    /**
     * Vérifie si un service PHP-FPM est disponible
     */
    public function hasPhpService(): bool
    {
        return $this->detectPhpService() !== null;
    }
}
