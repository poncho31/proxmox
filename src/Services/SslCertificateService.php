<?php

namespace App\Services;

use App\Models\CommandResult;

/**
 * Service pour la gestion des certificats SSL
 */
class SslCertificateService
{
    private CommandExecutor $commandExecutor;

    public function __construct(CommandExecutor $commandExecutor)
    {
        $this->commandExecutor = $commandExecutor;
    }

    /**
     * Vérifie si un certificat SSL existe déjà
     */
    public function certificateExists(): bool
    {
        $result = $this->commandExecutor->execute('test -f /etc/ssl/certs/nginx-selfsigned.crt && test -f /etc/ssl/private/nginx-selfsigned.key');
        return $result->isSuccess();
    }

    /**
     * Vérifie si le certificat SSL est encore valide (pas expiré)
     */
    public function isCertificateValid(): bool
    {
        if (!$this->certificateExists()) {
            return false;
        }

        $result = $this->commandExecutor->execute('openssl x509 -in /etc/ssl/certs/nginx-selfsigned.crt -checkend 86400 -noout');
        return $result->isSuccess();
    }

    /**
     * Génère un nouveau certificat SSL auto-signé
     */
    public function generateSelfSignedCertificate(): CommandResult
    {
        $command = 'mkdir -p /etc/ssl/private /etc/ssl/certs && ' .
                   'openssl req -x509 -nodes -days 365 -newkey rsa:2048 ' .
                   '-keyout /etc/ssl/private/nginx-selfsigned.key ' .
                   '-out /etc/ssl/certs/nginx-selfsigned.crt ' .
                   '-subj "/C=FR/ST=France/L=Paris/O=Proxmox/OU=IT Department/CN=localhost"';

        return $this->commandExecutor->execute($command);
    }

    /**
     * Met à jour les permissions du certificat pour sécuriser l'accès
     */
    public function secureCertificatePermissions(): CommandResult
    {
        $command = 'chmod 600 /etc/ssl/private/nginx-selfsigned.key && ' .
                   'chmod 644 /etc/ssl/certs/nginx-selfsigned.crt && ' .
                   'chown root:root /etc/ssl/private/nginx-selfsigned.key /etc/ssl/certs/nginx-selfsigned.crt';

        return $this->commandExecutor->execute($command);
    }

    /**
     * Obtient des informations sur le certificat actuel
     */
    public function getCertificateInfo(): array
    {
        if (!$this->certificateExists()) {
            return ['exists' => false];
        }

        $result = $this->commandExecutor->execute('openssl x509 -in /etc/ssl/certs/nginx-selfsigned.crt -dates -subject -noout');
        
        if (!$result->isSuccess()) {
            return ['exists' => true, 'error' => 'Cannot read certificate info'];
        }

        return [
            'exists' => true,
            'valid' => $this->isCertificateValid(),
            'info' => $result->getOutput()
        ];
    }
}
