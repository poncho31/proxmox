<?php

namespace App\Models;

/**
 * Modèle pour représenter une opération de mise à jour
 */
class Operation
{
    private string $key;
    private string $description;
    private string $command;
    private string $icon;
    private string $successMessage;
    private string $errorMessage;
    private array $skipOutputPatterns;
    private bool $optional;

    public function __construct(
        string $key,
        string $description,
        string $command,
        string $icon,
        string $successMessage,
        string $errorMessage,
        array $skipOutputPatterns = [],
        bool $optional = false
    ) {
        $this->key = $key;
        $this->description = $description;
        $this->command = $command;
        $this->icon = $icon;
        $this->successMessage = $successMessage;
        $this->errorMessage = $errorMessage;
        $this->skipOutputPatterns = $skipOutputPatterns;
        $this->optional = $optional;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getCommand(): string
    {
        return $this->command;
    }

    public function setCommand(string $command): void
    {
        $this->command = $command;
    }

    public function getIcon(): string
    {
        return $this->icon;
    }

    public function getSuccessMessage(): string
    {
        return $this->successMessage;
    }

    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }

    public function getSkipOutputPatterns(): array
    {
        return $this->skipOutputPatterns;
    }

    public function isOptional(): bool
    {
        return $this->optional;
    }

    public function shouldSkipOutput(string $output): bool
    {
        foreach ($this->skipOutputPatterns as $pattern) {
            if (strpos($output, $pattern) !== false) {
                return true;
            }
        }
        return false;
    }
}
