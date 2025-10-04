<?php

namespace App\Services;

use App\Models\CommandResult;

/**
 * Service pour exécuter des commandes système
 */
class CommandExecutor
{
    /**
     * Exécute une commande et retourne le résultat
     */
    public function execute(string $command): CommandResult
    {
        $output = [];
        $returnCode = 0;
        exec($command . " 2>&1", $output, $returnCode);
        
        return new CommandResult(
            $returnCode === 0,
            implode("\n", $output),
            $returnCode
        );
    }

    /**
     * Vérifie si un service est actif
     */
    public function isServiceActive(string $service): bool
    {
        $result = $this->execute("systemctl is-active $service");
        return $result->isSuccess() || strpos($result->getOutput(), 'inactive') !== false;
    }
}
