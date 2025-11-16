<?php

namespace ComfyUI\Workflows;

/**
 * Interface pour tous les workflows ComfyUI
 */
interface WorkflowInterface
{
    /**
     * Construit le workflow JSON
     */
    public function build(): array;

    /**
     * Retourne le nom du workflow
     */
    public function getName(): string;

    /**
     * Valide les paramètres du workflow
     */
    public function validate(): bool;
}
