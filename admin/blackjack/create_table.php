<?php
require_once '../../includes/init.php';
require_once '../../includes/blackjack_functions.php';

// Admin seulement
if (!isLoggedIn() || !isAdmin()) {
    redirect('../../pages/login.php');
}

$error = '';
$success = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $minBet = intval($_POST['min_bet'] ?? 100);
    $maxBet = intval($_POST['max_bet'] ?? 10000);

    if (empty($name)) {
        $error = 'Le nom de la table est requis.';
    } elseif ($minBet < 10) {
        $error = 'La mise minimum doit être d\'au moins 10.';
    } elseif ($maxBet <= $minBet) {
        $error = 'La mise maximum doit être supérieure à la mise minimum.';
    } else {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO blackjack_tables (name, min_bet, max_bet, status, created_by) VALUES (?, ?, ?, 'betting', ?)");
        $stmt->execute([$name, $minBet, $maxBet, $_SESSION['user_id']]);

        $tableId = $db->lastInsertId();
        header("Location: manage_table.php?id=$tableId");
        exit;
    }
}

require_once '../../includes/header.php';
?>

<main class="dashboard-main">
    <div class="container" style="max-width: 600px;">

        <div style="margin-bottom: 40px;">
            <a href="index.php" style="color: var(--text-muted); text-decoration: none;">← Retour au Dashboard</a>
        </div>

        <div class="card">
            <h1 style="font-size: 1.8rem; margin-bottom: 30px; text-align: center;">➕ CRÉER UNE TABLE</h1>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>NOM DE LA TABLE</label>
                    <input type="text" name="name" class="form-control" placeholder="Ex: Table VIP, Table 1..." required
                        value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label>MISE MINIMUM</label>
                        <input type="number" name="min_bet" class="form-control"
                            value="<?= intval($_POST['min_bet'] ?? 100) ?>" min="10" step="10">
                    </div>

                    <div class="form-group">
                        <label>MISE MAXIMUM</label>
                        <input type="number" name="max_bet" class="form-control"
                            value="<?= intval($_POST['max_bet'] ?? 10000) ?>" min="100" step="100">
                    </div>
                </div>

                <div style="margin-top: 30px;">
                    <button type="submit" class="btn btn-gold" style="width: 100%; font-size: 1rem; padding: 15px;">
                        CRÉER LA TABLE
                    </button>
                </div>
            </form>
        </div>

    </div>
</main>

<?php require_once '../../includes/footer.php'; ?>