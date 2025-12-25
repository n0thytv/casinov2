<?php
require_once '../../includes/init.php';
require_once '../../includes/blackjack_functions.php';

// Admin seulement
if (!isLoggedIn() || !isAdmin()) {
    redirect('../../pages/login.php');
}

$db = getDB();

// Récupérer les tables actives
$stmt = $db->query("
    SELECT bt.*, 
           COUNT(bp.id) as player_count,
           u.twitch_username as created_by_name
    FROM blackjack_tables bt
    LEFT JOIN blackjack_players bp ON bt.id = bp.table_id
    LEFT JOIN users u ON bt.created_by = u.id
    WHERE bt.status != 'finished'
    GROUP BY bt.id
    ORDER BY bt.created_at DESC
");
$activeTables = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les tables terminées récemment
$stmt = $db->query("
    SELECT bt.*, COUNT(bp.id) as player_count
    FROM blackjack_tables bt
    LEFT JOIN blackjack_players bp ON bt.id = bp.table_id
    WHERE bt.status = 'finished'
    GROUP BY bt.id
    ORDER BY bt.closed_at DESC
    LIMIT 10
");
$finishedTables = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Solde du casino
$casinoBalance = getCasinoBalance();

require_once '../../includes/header.php';
?>

<main class="dashboard-main">
    <div class="container">

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px;">
            <div>
                <h1 style="font-size: 2rem; margin-bottom: 5px;">🃏 BLACKJACK - ADMIN</h1>
                <p style="color: var(--text-muted);">Gérez les tables et les parties</p>
            </div>
            <div style="text-align: right;">
                <div style="font-size: 0.8rem; color: var(--gold-main); letter-spacing: 1px;">BANQUE CASINO</div>
                <div
                    style="font-size: 2rem; font-weight: 800; color: <?= $casinoBalance < 0 ? 'var(--danger)' : 'var(--success)' ?>;">
                    <?= number_format($casinoBalance, 0, ',', ' ') ?> 🪙
                </div>
            </div>
        </div>

        <!-- Bouton Créer Table -->
        <div style="margin-bottom: 40px;">
            <a href="create_table.php" class="btn btn-gold" style="font-size: 1rem; padding: 15px 30px;">
                ➕ CRÉER UNE TABLE
            </a>
        </div>

        <!-- Tables Actives -->
        <section class="card" style="margin-bottom: 40px;">
            <h2 style="margin-bottom: 25px; border-bottom: 1px solid var(--violet-main); padding-bottom: 15px;">
                🎰 TABLES ACTIVES
            </h2>

            <?php if (empty($activeTables)): ?>
                <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                    Aucune table active. Créez-en une !
                </div>
            <?php else: ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">
                    <?php foreach ($activeTables as $table): ?>
                        <div class="card" style="padding: 20px; border-image: none; border-color: 
                            <?php
                            echo match ($table['status']) {
                                'waiting' => 'var(--text-muted)',
                                'betting' => 'var(--warning)',
                                'playing' => 'var(--success)',
                                default => 'var(--text-muted)'
                            };
                            ?>;">
                            <div
                                style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                <h3 style="font-size: 1.2rem; margin: 0;"><?= htmlspecialchars($table['name']) ?></h3>
                                <span style="
                                    padding: 5px 10px; 
                                    border-radius: 4px; 
                                    font-size: 0.7rem; 
                                    background: <?php
                                    echo match ($table['status']) {
                                        'waiting' => 'rgba(255,255,255,0.1)',
                                        'betting' => 'rgba(255,183,0,0.2)',
                                        'playing' => 'rgba(0,255,127,0.2)',
                                        default => 'rgba(255,255,255,0.1)'
                                    };
                                    ?>;
                                    color: <?php
                                    echo match ($table['status']) {
                                        'waiting' => 'var(--text-muted)',
                                        'betting' => 'var(--warning)',
                                        'playing' => 'var(--success)',
                                        default => 'var(--text-muted)'
                                    };
                                    ?>;">
                                    <?= strtoupper($table['status']) ?>
                                </span>
                            </div>

                            <div style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 15px;">
                                <div>👥 <?= $table['player_count'] ?>/5 joueurs</div>
                                <div>💰 Mises: <?= number_format($table['min_bet']) ?> - <?= number_format($table['max_bet']) ?>
                                </div>
                                <?php if ($table['streamer_mode']): ?>
                                    <div style="color: var(--violet-glow);">📺 Mode Streameur</div>
                                <?php endif; ?>
                            </div>

                            <a href="manage_table.php?id=<?= $table['id'] ?>" class="btn btn-primary" style="width: 100%;">
                                GÉRER
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- Tables Terminées -->
        <section class="card">
            <h2 style="margin-bottom: 25px; border-bottom: 1px solid var(--violet-main); padding-bottom: 15px;">
                📜 HISTORIQUE RÉCENT
            </h2>

            <?php if (empty($finishedTables)): ?>
                <div style="text-align: center; padding: 20px; color: var(--text-muted);">
                    Aucune partie terminée.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>TABLE</th>
                                <th>JOUEURS</th>
                                <th>TERMINÉE LE</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($finishedTables as $table): ?>
                                <tr>
                                    <td><?= htmlspecialchars($table['name']) ?></td>
                                    <td><?= $table['player_count'] ?></td>
                                    <td style="color: var(--text-muted);">
                                        <?= date('d/m H:i', strtotime($table['closed_at'])) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

    </div>
</main>

<?php require_once '../../includes/footer.php'; ?>