# Architecture Refactorisée - Mise à jour Proxmox

## Vue d'ensemble

Le code a été refactorisé en suivant les principes SOLID et l'architecture MVC pour une meilleure organisation, maintenabilité et extensibilité.

## Structure du projet

```
src/
├── Config/
│   └── UpdateConfig.php          # Configuration centralisée
├── Models/
│   ├── CommandResult.php         # Modèle pour les résultats de commande
│   ├── Operation.php             # Modèle pour les opérations
│   └── UpdateStatistics.php      # Modèle pour les statistiques
├── Services/
│   ├── CommandExecutor.php       # Exécution des commandes système
│   ├── PhpServiceDetector.php    # Détection des services PHP
│   └── OperationFactory.php      # Factory pour créer les opérations
├── Views/
│   ├── BaseConsoleView.php       # Vue de base pour la console
│   ├── OperationView.php         # Vue pour l'affichage des opérations
│   └── SummaryView.php           # Vue pour le résumé final
└── Controllers/
    └── UpdateController.php      # Contrôleur principal

autoload.php                      # Autoloader PSR-4
update_refactored.php            # Point d'entrée principal
```

## Principes appliqués

### Single Responsibility Principle (SRP)
- Chaque classe a une responsabilité unique et bien définie
- `CommandExecutor` : Exécution de commandes
- `PhpServiceDetector` : Détection de services PHP
- `OperationFactory` : Création d'opérations
- Vues séparées par type d'affichage

### Open/Closed Principle (OCP)
- Les classes sont ouvertes à l'extension, fermées à la modification
- Utilisation d'héritage avec `BaseConsoleView`
- Factory pattern pour créer les opérations

### Dependency Inversion Principle (DIP)
- Les classes dépendent d'abstractions, pas de détails concrets
- Injection de dépendances dans les constructeurs

### Separation of Concerns
- **Config** : Configuration centralisée
- **Models** : Structures de données métier
- **Services** : Logique métier et interaction système
- **Views** : Présentation et affichage
- **Controllers** : Orchestration et coordination

## Avantages de la refactorisation

1. **Maintenabilité** : Code organisé et facile à comprendre
2. **Testabilité** : Classes isolées, facilement testables
3. **Extensibilité** : Ajout facile de nouvelles opérations ou vues
4. **Réutilisabilité** : Composants réutilisables
5. **Lisibilité** : Code auto-documenté avec des noms explicites

## Utilisation

```bash
php update_refactored.php
```

## Extension

### Ajouter une nouvelle opération
1. Ajouter la configuration dans `UpdateConfig.php`
2. Si besoin, étendre `OperationFactory` pour la logique spécifique

### Ajouter un nouveau type d'affichage
1. Créer une nouvelle vue héritant de `BaseConsoleView`
2. L'injecter dans `UpdateController`

### Ajouter de nouveaux services
1. Créer le service dans le dossier `Services/`
2. L'injecter dans les classes qui en ont besoin
