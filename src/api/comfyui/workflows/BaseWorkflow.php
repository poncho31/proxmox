<?php

namespace ComfyUI\Workflows;

/**
 * Classe de base pour tous les workflows ComfyUI
 */
abstract class BaseWorkflow implements WorkflowInterface
{
    protected array $nodes = [];
    protected array $links = [];
    protected int $lastNodeId = 0;
    protected int $lastLinkId = 0;
    protected array $apiFormat = []; // Format pour l'API ComfyUI

    /**
     * Crée un noeud
     */
    protected function createNode(
        int $id,
        string $type,
        array $pos,
        array $size,
        array $inputs = [],
        array $outputs = [],
        array $properties = [],
        array $widgetsValues = []
    ): array {
        return [
            'id' => $id,
            'type' => $type,
            'pos' => $pos,
            'size' => $size,
            'flags' => [],
            'order' => $id,
            'mode' => 0,
            'inputs' => $inputs,
            'outputs' => $outputs,
            'properties' => $properties,
            'widgets_values' => $widgetsValues
        ];
    }

    /**
     * Crée un lien entre deux noeuds
     */
    protected function createLink(
        int $id,
        int $sourceNodeId,
        int $sourceSlot,
        int $targetNodeId,
        int $targetSlot,
        string $type
    ): array {
        return [
            $id,
            $sourceNodeId,
            $sourceSlot,
            $targetNodeId,
            $targetSlot,
            $type
        ];
    }

    /**
     * Crée un input pour un noeud
     */
    protected function createInput(
        string $name,
        string $type,
        ?int $link = null,
        ?array $widget = null
    ): array {
        $input = [
            'name' => $name,
            'type' => $type
        ];

        if ($link !== null) {
            $input['link'] = $link;
        }

        if ($widget !== null) {
            $input['widget'] = $widget;
        }

        return $input;
    }

    /**
     * Crée un output pour un noeud
     */
    protected function createOutput(
        string $name,
        string $type,
        array $links = [],
        ?int $slotIndex = null
    ): array {
        $output = [
            'name' => $name,
            'type' => $type,
            'links' => $links
        ];

        if ($slotIndex !== null) {
            $output['slot_index'] = $slotIndex;
        }

        return $output;
    }

    /**
     * Build final workflow structure
     */
    public function build(): array
    {
        // Si le format API est défini, l'utiliser directement
        if (!empty($this->apiFormat)) {
            return $this->apiFormat;
        }

        // Sinon, utiliser le format UI classique
        return [
            'last_node_id' => $this->lastNodeId,
            'last_link_id' => $this->lastLinkId,
            'nodes' => $this->nodes,
            'links' => $this->links,
            'groups' => [],
            'config' => [],
            'extra' => []
        ];
    }

    /**
     * Validation par défaut
     */
    public function validate(): bool
    {
        return !empty($this->apiFormat);
    }
}
