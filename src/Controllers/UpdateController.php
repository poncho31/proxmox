<?php

namespace App\Controllers;

use App\Services\CommandExecutor;
use App\Services\PhpServiceDetector;
use App\Services\OperationFactory;
use App\Views\OperationView;
use App\Views\SummaryView;
use App\Models\UpdateStatistics;
use App\Models\CommandResult;
use App\Config\UpdateConfig;

/**
 * Contrôleur principal pour la gestion des mises à jour
 */
class UpdateController
{
    private CommandExecutor $commandExecutor;
    private PhpServiceDetector $phpServiceDetector;
    private OperationFactory $operationFactory;
    private OperationView $operationView;
    private SummaryView $summaryView;
    private UpdateStatistics $statistics;

    public function __construct()
    {
        $this->commandExecutor = new CommandExecutor();
        $this->phpServiceDetector = new PhpServiceDetector($this->commandExecutor);
        $this->operationFactory = new OperationFactory($this->phpServiceDetector);
        $this->operationView = new OperationView();
        $this->summaryView = new SummaryView();
        $this->statistics = new UpdateStatistics();
    }

    /**
     * Exécute le processus de mise à jour complet
     */
    public function run(): void
    {
        // Configuration initiale
        set_time_limit(UpdateConfig::getTimeLimit());

        // Affichage de l'en-tête
        $this->summaryView->displayStartHeader();

        // Création et exécution des opérations
        $operations = $this->operationFactory->createOperations();
        $results = $this->executeOperations($operations);

        // Affichage du résumé final
        $this->summaryView->displayFinalSummary($operations, $results, $this->statistics);
    }

    /**
     * Exécute toutes les opérations
     */
    private function executeOperations(array $operations): array
    {
        $results = [];

        foreach ($operations as $operation) {
            $result = $this->executeOperation($operation);
            $results[$operation->getKey()] = $result;
            $this->updateStatistics($operation, $result);
            echo "\n";
        }

        return $results;
    }

    /**
     * Exécute une opération spécifique
     */
    private function executeOperation($operation): CommandResult
    {
        $this->operationView->displayOperationStart($operation);

        // Gestion spéciale pour PHP si pas de service détecté
        if ($operation->getKey() === 'php' && !$this->phpServiceDetector->hasPhpService()) {
            $result = new CommandResult(false, 'Aucun service PHP-FPM détecté sur ce système', 1, true);
            $this->operationView->displayOperationSkipped($operation, 'Aucun service PHP-FPM détecté sur ce système');
            return $result;
        }

        // Vérifier si la commande est disponible
        if (empty($operation->getCommand())) {
            $result = new CommandResult(false, 'Command not available', 1, true);
            $this->operationView->displayOperationUnavailable($operation);
            return $result;
        }

        // Exécuter la commande
        $result = $this->commandExecutor->execute($operation->getCommand());

        // Afficher le résultat
        if ($result->isSuccess()) {
            $this->operationView->displayOperationSuccess($operation, $result);
        } else {
            $this->operationView->displayOperationFailure($operation, $result);
        }

        return $result;
    }

    /**
     * Met à jour les statistiques en fonction du résultat
     */
    private function updateStatistics($operation, CommandResult $result): void
    {
        if ($result->isSuccess()) {
            $this->statistics->incrementSuccessful();
        } elseif ($result->isSkipped()) {
            $this->statistics->incrementSkipped();
        } else {
            $this->statistics->incrementFailed();
            
            if ($operation->isOptional()) {
                $this->statistics->addWarning($operation->getDescription() . " (optionnel)");
            } else {
                $this->statistics->incrementCriticalFailed();
                $this->statistics->addError($operation->getDescription());
            }
        }
    }
}
