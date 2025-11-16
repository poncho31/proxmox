<?php

namespace ComfyUI\Workflows;

/**
 * Classe de base simplifiée pour tous les workflows ComfyUI
 */
abstract class BaseWorkflow implements WorkflowInterface
{
    protected array $apiFormat = [];

    /**
     * Crée un noeud pour l'API ComfyUI
     */
    protected function createNode(string $classType, array $inputs): array
    {
        return [
            'class_type' => $classType,
            'inputs' => $inputs
        ];
    }

    /**
     * Crée une référence vers la sortie d'un autre noeud
     */
    protected function nodeOutput(string $nodeId, int $outputIndex = 0): array
    {
        return [$nodeId, $outputIndex];
    }

    /**
     * Construit le workflow au format API ComfyUI
     */
    protected function buildApiFormat(array $nodes): void
    {
        $this->apiFormat = [
            'prompt' => $nodes,
            'client_id' => 'php-' . uniqid()
        ];
    }

    /**
     * Build final workflow structure
     */
    public function build(): array
    {
        return $this->apiFormat;
    }

    /**
     * Validation par défaut
     */
    public function validate(): bool
    {
        return !empty($this->apiFormat);
    }
}
