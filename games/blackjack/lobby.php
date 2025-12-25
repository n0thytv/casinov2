<?php
require_once '../../includes/init.php';
require_once '../../includes/blackjack_functions.php';

// Utilisateur connecté requis
if (!isLoggedIn()) {
    redirect('../../pages/login.php');
}

$db = getDB();

// Récupérer les tables disponibles (en phase de mises)
$stmt = $db->query("
    SELECT bt.*, 
           COUNT(bp.id) as player_count
    FROM blackjack_tables bt
    LEFT JOIN blackjack_players bp ON bt.id = bp.table_id
    WHERE bt.status IN ('betting', 'playing')
    GROUP BY bt.id
    ORDER BY bt.created_at DESC
");
$tables = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Vérifier si le joueur est déjà dans une partie
$stmt = $db->prepare("
    SELECT bp.*, bt.name as table_name, bt.id as table_id
    FROM blackjack_players bp
    JOIN blackjack_tables bt ON bp.table_id = bt.id
    WHERE bp.user_id = ? AND bt.status IN ('betting', 'playing')
");
$stmt->execute([$_SESSION['user_id']]);
$currentGame = $stmt->fetch(PDO::FETCH_ASSOC);

require_once '../../includes/header.php';
?>

<main class="dashboard-main">
    <div class="container">

        <div style="margin-bottom: 40px;">
            <a href="../../dashboard.php" style="color: var(--text-muted);">← Retour au Dashboard</a>
            <h1 style="font-size: 2rem; margin-top: 10px;">🃏 BLACKJACK</h1>
            <p style="color: var(--text-muted);">Rejoignez une table et tentez votre chance !</p>
        </div>

        <?php if ($currentGame): ?>
            <!-- Déjà dans une partie -->
            <div class="card"
                style="text-align: center; padding: 40px; margin-bottom: 30px; border-color: var(--gold-main);">
                <h2 style="color: var(--gold-main); margin-bottom: 20px;">VOUS ÊTES EN JEU !</h2>
                <p style="margin-bottom: 20px;">Vous êtes à la table:
                    <strong><?= htmlspecialchars($currentGame['table_name']) ?></strong>
                </p>
                <a href="index.php?table=<?= $currentGame['table_id'] ?>" class="btn btn-gold"
                    style="font-size: 1rem; padding: 15px 30px;">
                    REJOINDRE LA TABLE
                </a>
            </div>
        <?php endif; ?>

        <!-- Liste des tables -->
        <section class="card">
            <h2 style="margin-bottom: 25px; border-bottom: 1px solid var(--violet-main); padding-bottom: 15px;">
                🎰 TABLES DISPONIBLES
            </h2>

            <?php if (empty($tables)): ?>
                <div style="text-align: center; padding: 60px; color: var(--text-muted);">
                    <div style="font-size: 4rem; margin-bottom: 20px;">🃏</div>
                    <p>Aucune table disponible pour le moment.</p>
                    <p style="font-size: 0.9rem;">Attendez qu'un croupier ouvre une partie !</p>
                </div>
            <?php else: ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px;">
                    <?php foreach ($tables as $table): ?>
                        <div class="card" style="padding: 25px; border-image: none; border-color: 
                            <?= $table['status'] === 'betting' ? 'var(--success)' : 'var(--warning)' ?>;">

                            <div
                                style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                <h3 style="font-size: 1.3rem; margin: 0;"><?= htmlspecialchars($table['name']) ?></h3>
                                <span
                                    style="
                                    padding: 5px 12px; 
                                    border-radius: 20px; 
                                    font-size: 0.7rem; 
                                    font-weight: bold;
                                    background: <?= $table['status'] === 'betting' ? 'rgba(0,255,127,0.2)' : 'rgba(255,183,0,0.2)' ?>;
                                    color: <?= $table['status'] === 'betting' ? 'var(--success)' : 'var(--warning)' ?>;">
                                    <?= $table['status'] === 'betting' ? 'OUVERT' : 'EN JEU' ?>
                                </span>
                            </div>

                            <div style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 20px;">
                                <div style="margin-bottom: 5px;">👥 <strong><?= $table['player_count'] ?>/5</strong> joueurs
                                </div>
                                <div>💰 Mises: <strong><?= number_format($table['min_bet']) ?></strong> -
                                    <strong><?= number_format($table['max_bet']) ?></strong> 🪙
                                </div>
                            </div>

                            <?php if ($table['status'] === 'betting' && $table['player_count'] < 5 && !$currentGame): ?>
                                <a href="index.php?table=<?= $table['id'] ?>" class="btn btn-gold" style="width: 100%;">
                                    REJOINDRE
                                </a>
                            <?php elseif ($table['status'] === 'playing'): ?>
                                <a href="index.php?table=<?= $table['id'] ?>" class="btn btn-outline" style="width: 100%;">
                                    OBSERVER
                                </a>
                            <?php elseif ($table['player_count'] >= 5): ?>
                                <button class="btn btn-outline" style="width: 100%; opacity: 0.5;" disabled>
                                    TABLE PLEINE
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

    </div>
</main>

<!-- Auto-refresh script to detect new tables -->
<script>
    // Store current table count to detect changes
    let currentTableCount = <?= count($tables) ?>;

    // Check for new tables every 5 seconds
    setInterval(function () {
        fetch('api/get_tables.php')
            .then(r => r.json())
            .then(data => {
                if (data.success && data.table_count !== currentTableCount) {
                    console.log('🎰 Tables changed! Refreshing...');
                    location.reload();
                }
            })
            .catch(() => { }); // Silent fail
    }, 5000);
</script>

<?php require_once '../../includes/footer.php'; ?>