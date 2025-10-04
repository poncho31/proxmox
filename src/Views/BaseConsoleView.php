<?php

namespace App\Views;

/**
 * Classe de base pour les vues console
 */
abstract class BaseConsoleView
{
    protected const COLOR_GREEN = "\033[32m";
    protected const COLOR_RED = "\033[31m";
    protected const COLOR_BLUE = "\033[34m";
    protected const COLOR_RESET = "\033[0m";

    /**
     * Affiche un message avec une couleur spécifique
     */
    protected function printColored(string $message, string $color): void
    {
        echo $color . $message . self::COLOR_RESET . "\n";
    }

    /**
     * Affiche un message de succès
     */
    protected function printSuccess(string $message): void
    {
        $this->printColored($message, self::COLOR_GREEN);
    }

    /**
     * Affiche un message d'erreur
     */
    protected function printError(string $message): void
    {
        $this->printColored($message, self::COLOR_RED);
    }

    /**
     * Affiche un message d'information
     */
    protected function printInfo(string $message): void
    {
        $this->printColored($message, self::COLOR_BLUE);
    }

    /**
     * Affiche un séparateur
     */
    protected function printSeparator(string $char = "=", int $length = 48): void
    {
        echo str_repeat($char, $length) . "\n";
    }

    /**
     * Affiche une ligne vide
     */
    protected function printNewLine(): void
    {
        echo "\n";
    }
}
