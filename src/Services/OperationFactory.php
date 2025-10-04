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
    private SslCertificateService $sslCertificateService;

    public function __construct(PhpServiceDetector $phpServiceDetector, SslCertificateService $sslCertificateService)
    {
        $this->phpServiceDetector = $phpServiceDetector;
        $this->sslCertificateService = $sslCertificateService;
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

        // Gestion spéciale pour SSL - optimiser si le certificat existe déjà et est valide
        if ($key === 'ssl_cert') {
            if ($this->sslCertificateService->certificateExists() && $this->sslCertificateService->isCertificateValid()) {
                // Certificat valide, juste mettre à jour les permissions
                $command = 'chmod 600 /etc/ssl/private/nginx-selfsigned.key && chmod 644 /etc/ssl/certs/nginx-selfsigned.crt';
                $config['success_message'] = 'Certificat SSL existant et permissions mises à jour';
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
