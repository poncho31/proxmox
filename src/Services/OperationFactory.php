<?php

namespace App\Services;

use App\Config\UpdateConfig;
use App\Models\Operation;

/**
 * Service pour créer et gérer les opérations
 */
class OperationFactory
{
    private PhpServiceDetector $phpServiceDetector;

    public function __construct(PhpServiceDetector $phpServiceDetector)
    {
        $this->phpServiceDetector = $phpServiceDetector;
    }

    /**
     * Crée toutes les opérations configurées
     */
    public function createOperations(): array
    {
        $operations = [];
        $config = UpdateConfig::getOperations();

        foreach ($config as $key => $operationConfig) {
            $operation = $this->createOperation($key, $operationConfig);
            if ($operation) {
                $operations[] = $operation;
            }
        }

        return $operations;
    }

    /**
     * Crée une opération spécifique
     */
    private function createOperation(string $key, array $config): ?Operation
    {
        $command = $config['command'];

        // Gestion spéciale pour PHP
        if ($key === 'php') {
            $command = $this->phpServiceDetector->getRestartCommand();
            if (!$command) {
                return null; // Pas de service PHP détecté
            }
        }

        return new Operation(
            $key,
            $config['description'],
            $command,
            $config['icon'],
            $config['success_message'],
            $config['error_message'],
            $config['skip_output_patterns'] ?? [],
            $config['optional'] ?? false
        );
    }
}
