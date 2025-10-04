<?php

namespace App\Models;

/**
 * Modèle pour représenter le résultat d'une commande
 */
class CommandResult
{
    private bool $success;
    private string $output;
    private int $code;
    private bool $skipped;

    public function __construct(bool $success, string $output, int $code, bool $skipped = false)
    {
        $this->success = $success;
        $this->output = $output;
        $this->code = $code;
        $this->skipped = $skipped;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getOutput(): string
    {
        return $this->output;
    }

    public function getCode(): int
    {
        return $this->code;
    }

    public function isSkipped(): bool
    {
        return $this->skipped;
    }

    public function setSkipped(bool $skipped): void
    {
        $this->skipped = $skipped;
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'output' => $this->output,
            'code' => $this->code,
            'skipped' => $this->skipped
        ];
    }
}
