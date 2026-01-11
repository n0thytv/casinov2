<?php
require_once '../../includes/init.php';
require_once '../../includes/blackjack_functions.php';

// Admin seulement
if (!isLoggedIn() || !isAdmin()) {
    redirect('../../pages/login.php');
}

$tableId = intval($_GET['id'] ?? 0);
if (!$tableId) {
    redirect('index.php');
}

$table = getTableState($tableId, true); // true = voir les cartes cachées
if (!$table) {
    redirect('index.php');
}

require_once '../../includes/header.php';
?>

<style>
    .dealer-zone,
    .player-zone {
        background: rgba(0, 0, 0, 0.3);
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 20px;
    }

    .cards-display {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin: 15px 0;
    }

    .card-item {
        width: 60px;
        height: 85px;
        background: white;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        font-weight: bold;
        color: #333;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
    }

    .card-item.hearts,
    .card-item.diamonds {
        color: #dc3545;
    }

    .card-item.clubs,
    .card-item.spades {
        color: #333;
    }

    .card-item.hidden {
        background: linear-gradient(135deg, #1a0b2e, #2a1b3e);
        color: white;
    }

    .player-seat {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 15px;
    }

    .player-seat.active {
        border-color: var(--gold-main);
        box-shadow: 0 0 15px rgba(255, 215, 0, 0.2);
    }

    .player-seat.bust {
        opacity: 0.5;
    }

    .action-buttons {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: 15px;
    }

    .status-badge {
        padding: 3px 8px;
        border-radius: 4px;
        font-size: 0.7rem;
        font-weight: bold;
    }
</style>

<main class="dashboard-main">
    <div class="container">

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
            <div>
                <a href="index.php" style="color: var(--text-muted);">← Dashboard</a>
                <h1 style="font-size: 1.8rem; margin-top: 10px;"><?= htmlspecialchars($table['name']) ?></h1>
            </div>
            <div style="display: flex; gap: 15px; align-items: center;">
                <!-- Mode Streameur Toggle -->
                <form method="POST" action="api/toggle_streamer.php" style="display: inline;">
                    <input type="hidden" name="table_id" value="<?= $tableId ?>">
                    <button type="submit" class="btn <?= $table['streamer_mode'] ? 'btn-gold' : 'btn-outline' ?>"
                        style="padding: 10px 15px;">
                        📺 Mode Streameur <?= $table['streamer_mode'] ? 'ON' : 'OFF' ?>
                    </button>
                </form>

                <?php if ($table['streamer_mode']): ?>
                    <button
                        onclick="window.open('dealer_peek.php?id=<?= $tableId ?>', 'DealerPeek', 'width=450,height=350')"
                        class="btn btn-outline" style="padding: 10px 15px;">
                        👁️ Dealer Peek
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Statut de la table -->
        <div style="margin-bottom: 30px; display: flex; gap: 20px; align-items: center;">
            <span style="color: var(--text-muted);">STATUT:</span>
            <span class="status-badge" style="
                background: <?php echo match ($table['status']) {
                    'waiting' => 'rgba(255,255,255,0.1)',
                    'betting' => 'rgba(255,183,0,0.3)',
                    'playing' => 'rgba(0,255,127,0.3)',
                    'finished' => 'rgba(255,0,64,0.3)',
                    default => 'rgba(255,255,255,0.1)'
                }; ?>;
                color: <?php echo match ($table['status']) {
                    'betting' => 'var(--warning)',
                    'playing' => 'var(--success)',
                    'finished' => 'var(--danger)',
                    default => 'var(--text-muted)'
                }; ?>;
                font-size: 1rem; padding: 8px 15px;">
                <?= strtoupper($table['status']) ?>
            </span>

            <?php if ($table['status'] === 'betting'): ?>
                <form method="POST" action="api/close_betting.php" style="display: inline;">
                    <input type="hidden" name="table_id" value="<?= $tableId ?>">
                    <button type="submit" class="btn btn-primary"
                        onclick="return confirm('Clôturer les mises et démarrer la partie ?');">
                        🚫 CLÔTURER LES MISES
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">

            <!-- Zone Croupier -->
            <div class="dealer-zone">
                <h2 style="font-size: 1.2rem; margin-bottom: 15px; color: var(--gold-main);">🎩 CROUPIER</h2>

                <div class="cards-display">
                    <?php if (empty($table['dealer_cards'])): ?>
                        <span style="color: var(--text-muted);">Aucune carte</span>
                    <?php else: ?>
                        <?php foreach ($table['dealer_cards'] as $card): ?>
                            <div class="card-item <?= $card['suit'] ?>">
                                <?php if ($card['suit'] === 'hidden'): ?>
                                    ?
                                <?php else: ?>
                                    <?= $card['value'] ?>             <?php
                                                   echo match ($card['suit']) {
                                                       'hearts' => '♥',
                                                       'diamonds' => '♦',
                                                       'clubs' => '♣',
                                                       'spades' => '♠',
                                                       default => ''
                                                   };
                                                   ?>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div style="color: var(--text-muted);">
                    Valeur: <strong style="color: white;"><?= $table['dealer_value'] ?></strong>
                    <?php if ($table['dealer_value'] >= 17): ?>
                        <span style="color: var(--warning); margin-left: 10px;">⚠️ DOIT RESTER</span>
                    <?php endif; ?>
                </div>

                <?php if ($table['status'] === 'playing'): ?>
                    <div class="action-buttons">
                        <?php if ($table['dealer_value'] < 17): ?>
                            <form method="POST" action="api/deal_cards.php" style="display: inline;">
                                <input type="hidden" name="table_id" value="<?= $tableId ?>">
                                <input type="hidden" name="target" value="dealer">
                                <button type="submit" class="btn btn-outline">🃏 Tirer une carte</button>
                            </form>
                        <?php else: ?>
                            <button class="btn btn-outline" disabled style="opacity: 0.5;">🃏 ≥17 - Ne peut plus tirer</button>
                        <?php endif; ?>

                        <form method="POST" action="api/end_round.php" style="display: inline;">
                            <input type="hidden" name="table_id" value="<?= $tableId ?>">
                            <button type="submit" class="btn btn-gold"
                                onclick="return confirm('Terminer la manche et calculer les gains ?');">
                                ✅ TERMINER LA MANCHE
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Zone Contrôles -->
            <div class="dealer-zone">
                <h2 style="font-size: 1.2rem; margin-bottom: 15px; color: var(--gold-main);">🎮 CONTRÔLES</h2>

                <?php
                // Calculer la progression de la distribution
                $playerCount = count($table['players']);
                $totalCards = 0;
                foreach ($table['players'] as $p) {
                    $totalCards += count($p['cards']);
                }
                $totalCards += count($table['dealer_cards']);
                $expectedCards = ($playerCount * 2) + 2;
                $dealingComplete = $totalCards >= $expectedCards;
                ?>

                <?php if ($table['status'] === 'playing' && !$dealingComplete && $playerCount > 0): ?>
                    <div style="margin-bottom: 15px; padding: 15px; background: rgba(255,183,0,0.1); border-radius: 8px;">
                        <div style="color: var(--warning); font-weight: bold; margin-bottom: 10px;">
                            📦 DISTRIBUTION: <?= $totalCards ?> / <?= $expectedCards ?> cartes
                        </div>
                        <div style="background: rgba(0,0,0,0.3); border-radius: 4px; height: 8px; overflow: hidden;">
                            <div
                                style="width: <?= ($totalCards / $expectedCards) * 100 ?>%; height: 100%; background: var(--gold-main);">
                            </div>
                        </div>
                    </div>

                    <form method="POST" action="api/deal_cards.php">
                        <input type="hidden" name="table_id" value="<?= $tableId ?>">
                        <input type="hidden" name="action" value="initial_deal">
                        <button type="submit" class="btn btn-gold" style="width: 100%; margin-bottom: 15px;">
                            🃏 DISTRIBUER LA PROCHAINE CARTE
                        </button>
                    </form>
                <?php elseif ($table['status'] === 'playing' && $dealingComplete): ?>
                    <div
                        style="padding: 15px; background: rgba(0,255,127,0.1); border-radius: 8px; text-align: center; margin-bottom: 15px;">
                        <div style="color: var(--success); font-weight: bold;">✅ Distribution terminée</div>
                        <div style="color: var(--text-muted); font-size: 0.9rem;">
                            Les joueurs choisissent leurs actions (HIT/STAND/DOUBLE/SPLIT)
                        </div>
                    </div>
                <?php elseif ($table['status'] === 'playing' && $playerCount === 0): ?>
                    <div style="padding: 15px; background: rgba(255,0,64,0.1); border-radius: 8px; text-align: center;">
                        <div style="color: var(--danger);">❌ Aucun joueur à la table</div>
                    </div>
                <?php endif; ?>

                <div style="background: rgba(0,0,0,0.3); padding: 15px; border-radius: 8px;">
                    <div style="color: var(--text-muted); font-size: 0.9rem;">BANQUE CASINO</div>
                    <div
                        style="font-size: 1.5rem; font-weight: bold; color: <?= $table['casino_balance'] >= 0 ? 'var(--success)' : 'var(--danger)' ?>;">
                        <?= number_format($table['casino_balance'], 0, ',', ' ') ?> 🪙
                    </div>
                </div>
            </div>
        </div>

        <!-- Zone Joueurs -->
        <div class="card" style="margin-top: 30px;">
            <h2 style="font-size: 1.2rem; margin-bottom: 20px;">👥 JOUEURS (<?= count($table['players']) ?>/5)</h2>

            <?php if (empty($table['players'])): ?>
                <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                    Aucun joueur n'a rejoint la table.
                </div>
            <?php else: ?>
                <?php foreach ($table['players'] as $player): ?>
                    <div
                        class="player-seat <?= $player['seat_number'] == $table['current_player_seat'] ? 'active' : '' ?> <?= $player['status'] === 'bust' ? 'bust' : '' ?>">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong style="font-size: 1.1rem;"><?= htmlspecialchars($player['twitch_username']) ?></strong>
                                <span style="color: var(--text-muted); margin-left: 10px;">Siège
                                    #<?= $player['seat_number'] ?></span>
                            </div>
                            <div>
                                <span style="color: var(--gold-main); font-weight: bold;">💰
                                    <?= number_format($player['bet_amount']) ?></span>
                                <?php if ($player['doubled']): ?>
                                    <span style="color: var(--warning); margin-left: 10px;">x2</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($player['has_split']): ?>
                            <div style="margin-bottom: 10px;">
                                <strong style="color: var(--violet-glow);">Main Principale
                                    <?= ($player['current_hand'] ?? 'main') === 'main' ? '👈 ACTIVE' : '' ?></strong>
                            </div>
                        <?php endif; ?>

                        <div class="cards-display">
                            <?php if (empty($player['cards'])): ?>
                                <span style="color: var(--text-muted);">Pas encore de cartes</span>
                            <?php else: ?>
                                <?php foreach ($player['cards'] as $card): ?>
                                    <div class="card-item <?= $card['suit'] ?>">
                                        <?= $card['value'] ?>                 <?php
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

                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="color: var(--text-muted);">
                                Valeur: <strong style="color: white;"><?= $player['hand_value'] ?></strong>
                                <?php if ($player['hand_value'] > 21): ?>
                                    <span style="color: var(--danger); margin-left: 10px;">BUST!</span>
                                <?php elseif (isBlackjack($player['cards'])): ?>
                                    <span style="color: var(--gold-main); margin-left: 10px;">BLACKJACK!</span>
                                <?php endif; ?>
                            </span>

                            <span class="status-badge" style="
                                background: <?php echo match ($player['status']) {
                                    'betting' => 'rgba(255,183,0,0.2)',
                                    'playing' => 'rgba(0,255,127,0.2)',
                                    'stand' => 'rgba(100,100,100,0.2)',
                                    'bust' => 'rgba(255,0,64,0.2)',
                                    'blackjack' => 'rgba(255,215,0,0.3)',
                                    'win' => 'rgba(0,255,127,0.3)',
                                    'lose' => 'rgba(255,0,64,0.2)',
                                    'push' => 'rgba(255,255,255,0.1)',
                                    default => 'rgba(255,255,255,0.1)'
                                }; ?>;
                                color: <?php echo match ($player['status']) {
                                    'betting' => 'var(--warning)',
                                    'playing' => 'var(--success)',
                                    'stand' => 'var(--text-muted)',
                                    'bust', 'lose' => 'var(--danger)',
                                    'blackjack', 'win' => 'var(--success)',
                                    default => 'var(--text-muted)'
                                }; ?>;">
                                <?= strtoupper($player['status']) ?>
                            </span>
                        </div>

                        <?php if ($table['status'] === 'playing' && $player['status'] === 'playing'): ?>
                            <?php
                            $pendingAction = $player['pending_action'] ?? null;
                            $actionLabels = [
                                'hit' => '🃏 DEMANDE UNE CARTE',
                                'stand' => '✋ VEUT RESTER',
                                'double' => '💰 VEUT DOUBLER',
                                'split' => '✌️ VEUT SPLIT'
                            ];
                            ?>
                            <?php if ($pendingAction): ?>
                                <div
                                    style="margin-top: 10px; padding: 15px; background: rgba(255,215,0,0.2); border: 2px solid var(--gold-main); border-radius: 8px; text-align: center;">
                                    <div style="color: var(--gold-main); font-weight: bold; font-size: 1.1rem; margin-bottom: 10px;">
                                        <?= $actionLabels[$pendingAction] ?>
                                    </div>
                                    <form method="POST" action="api/execute_action.php" style="display: inline;">
                                        <input type="hidden" name="table_id" value="<?= $tableId ?>">
                                        <input type="hidden" name="player_id" value="<?= $player['id'] ?>">
                                        <button type="submit" class="btn btn-gold" style="padding: 10px 30px;">
                                            ✅ EXÉCUTER
                                        </button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <div
                                    style="margin-top: 10px; padding: 10px; background: rgba(100,100,100,0.2); border-radius: 6px; text-align: center;">
                                    <span style="color: var(--text-muted); font-size: 0.85rem;">
                                        ⏳ En attente du choix du joueur...
                                    </span>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php if ($player['has_split']): ?>
                            <!-- Afficher la main splittée -->
                            <div style="margin-top: 15px; padding-top: 15px; border-top: 1px dashed rgba(255,255,255,0.1);">
                                <strong style="color: var(--violet-glow);">Main Splittée
                                    <?= ($player['current_hand'] ?? 'main') === 'split' ? '👈 ACTIVE' : '' ?></strong>
                                <div class="cards-display">
                                    <?php
                                    // split_cards est déjà décodé par getTableState()
                                    $splitCards = is_array($player['split_cards']) ? $player['split_cards'] : (json_decode($player['split_cards'], true) ?? []);
                                    if (!empty($splitCards)):
                                        foreach ($splitCards as $card): ?>
                                            <div class="card-item <?= $card['suit'] ?>">
                                                <?= $card['value'] ?>                     <?php
                                                                       echo match ($card['suit']) {
                                                                           'hearts' => '♥',
                                                                           'diamonds' => '♦',
                                                                           'clubs' => '♣',
                                                                           'spades' => '♠',
                                                                           default => ''
                                                                       };
                                                                       ?>
                                            </div>
                                        <?php endforeach;
                                    else: ?>
                                        <span style="color: var(--text-muted);">Aucune carte</span>
                                    <?php endif; ?>
                                </div>
                                <span style="color: var(--text-muted);">
                                    Valeur: <strong
                                        style="color: white;"><?= !empty($splitCards) ? calculateHandValue($splitCards) : 0 ?></strong>
                                    <?php if (!empty($splitCards) && calculateHandValue($splitCards) > 21): ?>
                                        <span style="color: var(--danger); margin-left: 10px;">BUST!</span>
                                    <?php endif; ?>
                                </span>

                                <?php // Bouton pour débloquer une main splittée coincée
                                            if ($player['status'] !== 'playing' && ($player['current_hand'] ?? 'main') === 'split'): ?>
                                    <form method="POST" action="api/unlock_split.php" style="margin-top: 10px;">
                                        <input type="hidden" name="table_id" value="<?= $tableId ?>">
                                        <input type="hidden" name="player_id" value="<?= $player['id'] ?>">
                                        <button type="submit" class="btn btn-gold btn-sm" style="font-size: 0.75rem; padding: 5px 10px;"
                                            onclick="return confirm('Débloquer la main splittée et remettre le joueur en mode playing ?');">
                                            🔓 Débloquer Split
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>
</main>

<script>
    // Auto-refresh toutes les 3 secondes pour voir les actions des joueurs
    setInterval(() => {
        location.reload();
    }, 3000);
</script>

<?php require_once '../../includes/footer.php'; ?>