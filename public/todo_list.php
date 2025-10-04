<?php
session_start();

// Initialiser la liste des tâches si elle n'existe pas
if (!isset($_SESSION['todos'])) {
    $_SESSION['todos'] = [];
}

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add':
            $task = trim($_POST['task'] ?? '');
            if (!empty($task)) {
                $_SESSION['todos'][] = [
                    'id' => uniqid(),
                    'task' => htmlspecialchars($task),
                    'completed' => false,
                    'created_at' => date('Y-m-d H:i:s')
                ];
            }
            break;
            
        case 'toggle':
            $id = $_POST['id'] ?? '';
            foreach ($_SESSION['todos'] as &$todo) {
                if ($todo['id'] === $id) {
                    $todo['completed'] = !$todo['completed'];
                    break;
                }
            }
            break;
            
        case 'delete':
            $id = $_POST['id'] ?? '';
            $_SESSION['todos'] = array_filter($_SESSION['todos'], function($todo) use ($id) {
                return $todo['id'] !== $id;
            });
            $_SESSION['todos'] = array_values($_SESSION['todos']); // Réindexer
            break;
            
        case 'clear_completed':
            $_SESSION['todos'] = array_filter($_SESSION['todos'], function($todo) {
                return !$todo['completed'];
            });
            $_SESSION['todos'] = array_values($_SESSION['todos']);
            break;
    }
    
    // Redirection pour éviter la resoumission du formulaire
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Statistiques
$total_tasks = count($_SESSION['todos']);
$completed_tasks = count(array_filter($_SESSION['todos'], function($todo) {
    return $todo['completed'];
}));
$pending_tasks = $total_tasks - $completed_tasks;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📝 Todo List - Proxmox</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            color: #333;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .header h1 {
            color: #764ba2;
            font-size: 2.5rem;
            margin-bottom: 10px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .stat-card.pending {
            background: linear-gradient(135deg, #FF6B6B 0%, #4ECDC4 100%);
        }

        .stat-card.completed {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .add-form {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
        }

        .task-input {
            flex: 1;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .task-input:focus {
            outline: none;
            border-color: #764ba2;
        }

        .btn {
            padding: 15px 25px;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-danger {
            background: linear-gradient(135deg, #FF6B6B 0%, #EE5A24 100%);
            color: white;
        }

        .btn-success {
            background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .todo-list {
            margin-bottom: 30px;
        }

        .todo-item {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 15px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .todo-item:hover {
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        .todo-item.completed {
            opacity: 0.7;
            background: #f8f9fa;
        }

        .todo-checkbox {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .todo-text {
            flex: 1;
            font-size: 1.1rem;
        }

        .todo-text.completed {
            text-decoration: line-through;
            color: #888;
        }

        .todo-date {
            font-size: 0.9rem;
            color: #666;
            margin-right: 10px;
        }

        .todo-actions {
            display: flex;
            gap: 10px;
        }

        .btn-small {
            padding: 8px 12px;
            font-size: 0.9rem;
        }

        .actions-bar {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        .empty-state h3 {
            margin-bottom: 10px;
            color: #764ba2;
        }

        @media (max-width: 768px) {
            .container {
                padding: 20px;
                margin: 10px;
            }

            .header h1 {
                font-size: 2rem;
            }

            .add-form {
                flex-direction: column;
            }

            .todo-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .todo-actions {
                align-self: flex-end;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- En-tête -->
        <div class="header">
            <h1>📝 Todo List</h1>
            <p>Gestionnaire de tâches - <?php echo date('d/m/Y H:i:s'); ?></p>
        </div>

        <!-- Statistiques -->
        <div class="stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_tasks; ?></div>
                <div class="stat-label">Total</div>
            </div>
            <div class="stat-card pending">
                <div class="stat-number"><?php echo $pending_tasks; ?></div>
                <div class="stat-label">En cours</div>
            </div>
            <div class="stat-card completed">
                <div class="stat-number"><?php echo $completed_tasks; ?></div>
                <div class="stat-label">Terminées</div>
            </div>
        </div>

        <!-- Formulaire d'ajout -->
        <form class="add-form" method="POST">
            <input type="hidden" name="action" value="add">
            <input type="text" name="task" class="task-input" 
                   placeholder="Ajouter une nouvelle tâche..." 
                   required maxlength="255" autofocus>
            <button type="submit" class="btn btn-primary">
                ➕ Ajouter
            </button>
        </form>

        <!-- Liste des tâches -->
        <div class="todo-list">
            <?php if (empty($_SESSION['todos'])): ?>
                <div class="empty-state">
                    <h3>🎉 Aucune tâche !</h3>
                    <p>Ajoutez votre première tâche ci-dessus pour commencer.</p>
                </div>
            <?php else: ?>
                <?php foreach ($_SESSION['todos'] as $todo): ?>
                    <div class="todo-item <?php echo $todo['completed'] ? 'completed' : ''; ?>">
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?php echo $todo['id']; ?>">
                            <input type="checkbox" class="todo-checkbox" 
                                   <?php echo $todo['completed'] ? 'checked' : ''; ?>
                                   onchange="this.form.submit()">
                        </form>
                        
                        <div class="todo-text <?php echo $todo['completed'] ? 'completed' : ''; ?>">
                            <?php echo $todo['task']; ?>
                        </div>
                        
                        <div class="todo-date">
                            <?php echo date('d/m H:i', strtotime($todo['created_at'])); ?>
                        </div>
                        
                        <div class="todo-actions">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $todo['id']; ?>">
                                <button type="submit" class="btn btn-danger btn-small" 
                                        onclick="return confirm('Supprimer cette tâche ?')">
                                    🗑️ Supprimer
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Actions -->
        <?php if (!empty($_SESSION['todos'])): ?>
            <div class="actions-bar">
                <?php if ($completed_tasks > 0): ?>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="clear_completed">
                        <button type="submit" class="btn btn-danger" 
                                onclick="return confirm('Supprimer toutes les tâches terminées ?')">
                            🧹 Nettoyer terminées
                        </button>
                    </form>
                <?php endif; ?>
                
                <a href="/" class="btn btn-success">
                    🏠 Retour au Hub
                </a>
                
                <button onclick="location.reload()" class="btn btn-primary">
                    🔄 Actualiser
                </button>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Animation au chargement
        document.addEventListener('DOMContentLoaded', function() {
            const items = document.querySelectorAll('.todo-item, .stat-card');
            items.forEach((item, index) => {
                item.style.opacity = '0';
                item.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    item.style.transition = 'all 0.5s ease';
                    item.style.opacity = '1';
                    item.style.transform = 'translateY(0)';
                }, index * 100);
            });

            console.log('📝 Todo List chargée');
            console.log('📊 Stats: <?php echo $total_tasks; ?> total, <?php echo $pending_tasks; ?> en cours, <?php echo $completed_tasks; ?> terminées');
        });

        // Auto-focus sur le champ de saisie
        document.querySelector('.task-input').focus();
    </script>
</body>
</html>