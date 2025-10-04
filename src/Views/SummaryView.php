<?php

namespace App\Views;

use App\Models\UpdateStatistics;
use App\Models\Operation;
use App\Models\CommandResult;

/**
 * Vue pour l'affichage du résumé final
 */
class SummaryView extends BaseConsoleView
{
    /**
     * Affiche l'en-tête de démarrage
     */
    public function displayStartHeader(): void
    {
        $this->printNewLine();
        echo "🔄 Démarrage de la mise à jour du serveur Proxmox...\n";
        $this->printSeparator();
        $this->printNewLine();
    }

    /**
     * Affiche le résumé final
     */
    public function displayFinalSummary(array $operations, array $results, UpdateStatistics $statistics): void
    {
        $this->printSeparator();
        echo "📊 RÉSUMÉ DE LA MISE À JOUR\n";
        $this->printSeparator();

        foreach ($operations as $operation) {
            $key = $operation->getKey();
            $result = $results[$key] ?? null;
            
            if ($result) {
                $this->displayOperationSummaryLine($operation, $result);
            }
        }

        $this->printNewLine();
        echo "📈 Statistiques: {$statistics->getSuccessful()} réussies, {$statistics->getFailed()} échouées, {$statistics->getSkipped()} ignorées\n";

        $this->displayFinalStatus($statistics);
        $this->displayWarnings($statistics);
        $this->printNewLine();
    }

    /**
     * Affiche une ligne du résumé pour une opération
     */
    private function displayOperationSummaryLine(Operation $operation, CommandResult $result): void
    {
        $status = $result->isSuccess() ? '✅' : ($result->isSkipped() ? '⏭️' : '❌');
        $statusText = $result->isSuccess() ? 'RÉUSSI' : ($result->isSkipped() ? 'IGNORÉ' : 'ÉCHEC');
        
        echo sprintf("%-40s %s %s\n", $operation->getDescription(), $status, $statusText);
    }

    /**
     * Affiche le statut final
     */
    private function displayFinalStatus(UpdateStatistics $statistics): void
    {
        $status = $statistics->getOverallStatus();

        switch ($status) {
            case 'success':
                $this->printSuccess("🎉 Mise à jour terminée avec succès !");
                echo "Tous les services critiques ont été mis à jour correctement.\n";
                if ($statistics->getFailed() > 0) {
                    echo "Note: Certaines opérations optionnelles ont échoué mais n'affectent pas le fonctionnement.\n";
                }
                break;

            case 'partial':
                $this->printError("⚠️  Mise à jour partiellement réussie");
                echo "Certaines opérations critiques ont échoué mais les services principaux fonctionnent.\n";
                break;

            case 'failure':
                $this->printError("💥 Mise à jour échouée");
                echo "Plusieurs opérations critiques ont échoué.\n";
                break;
        }
    }

    /**
     * Affiche les avertissements
     */
    private function displayWarnings(UpdateStatistics $statistics): void
    {
        if ($statistics->hasWarnings()) {
            $this->printNewLine();
            echo "⚠️  Avertissements:\n";
            foreach ($statistics->getWarnings() as $warning) {
                echo "   • $warning\n";
            }
        }
    }
}
