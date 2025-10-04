<?php

namespace App\Models;

/**
 * Modèle pour représenter les statistiques de mise à jour
 */
class UpdateStatistics
{
    private int $successful;
    private int $failed;
    private int $skipped;
    private int $criticalFailed;
    private array $warnings;
    private array $errors;

    public function __construct()
    {
        $this->successful = 0;
        $this->failed = 0;
        $this->skipped = 0;
        $this->criticalFailed = 0;
        $this->warnings = [];
        $this->errors = [];
    }

    public function incrementSuccessful(): void
    {
        $this->successful++;
    }

    public function incrementFailed(): void
    {
        $this->failed++;
    }

    public function incrementSkipped(): void
    {
        $this->skipped++;
    }

    public function incrementCriticalFailed(): void
    {
        $this->criticalFailed++;
    }

    public function addWarning(string $warning): void
    {
        $this->warnings[] = $warning;
    }

    public function addError(string $error): void
    {
        $this->errors[] = $error;
    }

    public function getSuccessful(): int
    {
        return $this->successful;
    }

    public function getFailed(): int
    {
        return $this->failed;
    }

    public function getSkipped(): int
    {
        return $this->skipped;
    }

    public function getCriticalFailed(): int
    {
        return $this->criticalFailed;
    }

    public function getWarnings(): array
    {
        return $this->warnings;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function hasWarnings(): bool
    {
        return !empty($this->warnings);
    }

    public function getOverallStatus(): string
    {
        if ($this->criticalFailed === 0) {
            return 'success';
        } elseif ($this->criticalFailed > 0 && $this->successful > 0) {
            return 'partial';
        } else {
            return 'failure';
        }
    }
}
