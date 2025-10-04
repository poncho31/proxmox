<?php

namespace App\Views;

use App\Models\Operation;
use App\Models\CommandResult;

/**
 * Vue pour l'affichage des opérations en cours
 */
class OperationView extends BaseConsoleView
{
    /**
     * Affiche le début d'une opération
     */
    public function displayOperationStart(Operation $operation): void
    {
        $this->printInfo($operation->getIcon() . " " . $operation->getDescription() . "...");
    }

    /**
     * Affiche le résultat d'une opération réussie
     */
    public function displayOperationSuccess(Operation $operation, CommandResult $result): void
    {
        $this->printSuccess("✅ " . $operation->getSuccessMessage());

        $output = trim($result->getOutput());
        if (!empty($output) && !$operation->shouldSkipOutput($output)) {
            echo "   📄 " . $output . "\n";
        }
    }

    /**
     * Affiche le résultat d'une opération échouée
     */
    public function displayOperationFailure(Operation $operation, CommandResult $result): void
    {
        $this->printError("❌ " . $operation->getErrorMessage());

        if (!empty($result->getOutput())) {
            echo "   💬 " . $result->getOutput() . "\n";
        }
    }

    /**
     * Affiche une opération ignorée
     */
    public function displayOperationSkipped(Operation $operation, string $reason): void
    {
        $this->printError("❌ $reason");
    }

    /**
     * Affiche une opération non disponible
     */
    public function displayOperationUnavailable(Operation $operation): void
    {
        $this->printError("⚠️  Opération ignorée");
    }
}
