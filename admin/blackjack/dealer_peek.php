<?php
require_once '../../includes/init.php';
require_once '../../includes/blackjack_functions.php';

// Admin seulement
if (!isLoggedIn() || !isAdmin()) {
    die('Accès refusé');
}

$tableId = intval($_GET['id'] ?? 0);
if (!$tableId) {
    die('Table non trouvée');
}

$table = getTableState($tableId, true); // true = voir les vraies cartes
if (!$table) {
    die('Table non trouvée');
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dealer Peek - <?= htmlspecialchars($table['name']) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #0a0a0a;
            color: white;
            font-family: 'Segoe UI', sans-serif;
            padding: 20px;
            min-height: 100vh;
        }

        h1 {
            font-size: 1rem;
            color: #FFD700;
            margin-bottom: 15px;
        }

        .cards {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }

        .card {
            width: 50px;
            height: 70px;
            background: white;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            font-weight: bold;
            color: #333;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.5);
        }

        .card.hearts,
        .card.diamonds {
            color: #dc3545;
        }

        .value {
            font-size: 2rem;
            font-weight: bold;
            color: #FFD700;
            margin-top: 10px;
        }

        .refresh {
            color: #888;
            font-size: 0.8rem;
            margin-top: 20px;
        }
    </style>
</head>

<body>
    <h1>👁️ DEALER PEEK - <?= htmlspecialchars($table['name']) ?></h1>

    <div class="cards">
        <?php if (empty($table['dealer_cards'])): ?>
            <span style="color: #888;">Aucune carte</span>
        <?php else: ?>
            <?php foreach ($table['dealer_cards'] as $card): ?>
                <div class="card <?= $card['suit'] ?>">
                    <?= $card['value'] ?>        <?php
                              echo match ($card['suit']) {
                                  'hearts' => '♥',
                                  'diamonds' => '♦',
                                  'clubs' => '♣',
                                  'spades' => '♠',
                                  default => ''
                              };
                              ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="value">
        Total: <?= $table['dealer_value'] ?>
        <?php if ($table['dealer_value'] > 21): ?>
            <span style="color: #ff4444;">BUST!</span>
        <?php endif; ?>
    </div>

    <div class="refresh">
        Auto-refresh: 2s
    </div>

    <script>
        // Auto-refresh toutes les 2 secondes
        setTimeout(() => location.reload(), 2000);
    </script>
</body>

</html>