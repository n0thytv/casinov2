<?php
require_once '../../includes/init.php';
require_once '../../includes/blackjack_functions.php';

// Utilisateur connecté requis
if (!isLoggedIn()) {
    redirect('../../pages/login.php');
}

$tableId = intval($_GET['table'] ?? 0);

// Si pas de table spécifiée, rediriger vers le lobby
if (!$tableId) {
    redirect('lobby.php');
}

$table = getTableState($tableId, false); // false = ne pas voir les cartes cachées
if (!$table) {
    redirect('lobby.php');
}

$db = getDB();
$userId = $_SESSION['user_id'];
$twitchUsername = $_SESSION['twitch_username'];

// Vérifier si le joueur est déjà à cette table
$stmt = $db->prepare("SELECT * FROM blackjack_players WHERE table_id = ? AND user_id = ?");
$stmt->execute([$tableId, $userId]);
$myPlayer = $stmt->fetch(PDO::FETCH_ASSOC);

// Récupérer le solde du joueur
$balanceData = getN0thyCoins($twitchUsername);
$myBalance = ($balanceData && isset($balanceData['currency'])) ? intval($balanceData['currency']) : 0;

require_once '../../includes/header.php';
?>

<!-- CSS 3D Blackjack Styles -->
<link rel="stylesheet" href="../../assets/css/blackjack3d.css?v=<?= time() ?>">


<style>
    /* Main game layout */
    .game-container {
        display: grid;
        grid-template-columns: 1fr 250px;
        gap: 15px;
        align-items: start;
        max-width: 1600px;
        margin: 0 auto;
    }

    @media (max-width: 1200px) {
        .game-container {
            grid-template-columns: 1fr;
        }
    }

    /* Left column with table and controls */
    .game-main {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    /* Hide card displays in table-zone (replaced by CSS 3D) but keep action buttons */
    .table-zone .dealer-area,
    .table-zone .cards-row,
    .table-zone .playing-card {
        display: none !important;
    }

    .table-zone {
        /* Keep visible for action buttons */
        background: transparent;
        border: none;
        padding: 0;
        min-height: auto;
    }

    .dealer-area,
    .players-area {
        margin-bottom: 30px;
    }

    .cards-row {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin: 15px 0;
    }

    .playing-card {
        width: 55px;
        height: 80px;
        background: white;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        font-weight: bold;
        color: #333;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.4);
        transition: transform 0.3s ease;
    }

    .playing-card:hover {
        transform: translateY(-5px);
    }

    .playing-card.hearts,
    .playing-card.diamonds {
        color: #dc3545;
    }

    .playing-card.hidden {
        background: linear-gradient(135deg, #4b0082, #1a0b2e);
        color: #FFD700;
        font-size: 2rem;
    }

    .player-slot {
        background: rgba(0, 0, 0, 0.4);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 15px;
    }

    .player-slot.active {
        border-color: var(--gold-main);
        box-shadow: 0 0 20px rgba(255, 215, 0, 0.2);
    }

    .player-slot.mine {
        border-color: var(--violet-main);
    }

    /* Chips */
    .chips-selector {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        justify-content: center;
        margin: 20px 0;
    }

    .chip {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 0.9rem;
        cursor: pointer;
        transition: all 0.2s ease;
        border: 3px dashed rgba(255, 255, 255, 0.3);
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.4);
    }

    .chip:hover {
        transform: scale(1.1);
    }

    .chip:active {
        transform: scale(0.95);
    }

    .chip-10 {
        background: #3b82f6;
        color: white;
    }

    .chip-25 {
        background: #22c55e;
        color: white;
    }

    .chip-50 {
        background: #ef4444;
        color: white;
    }

    .chip-100 {
        background: #1f2937;
        color: white;
        border-color: #FFD700;
    }

    .chip-500 {
        background: #7c3aed;
        color: white;
    }

    .chip-1000 {
        background: linear-gradient(135deg, #FFD700, #B8860B);
        color: #000;
    }

    .bet-display {
        text-align: center;
        padding: 20px;
        background: rgba(0, 0, 0, 0.3);
        border-radius: 12px;
        margin-bottom: 20px;
    }

    .bet-amount {
        font-size: 3rem;
        font-weight: 800;
        color: var(--gold-main);
        font-family: 'Orbitron', sans-serif;
    }

    .sidebar {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    /* Animations de résultats */
    .winner-glow {
        animation: winnerPulse 1.5s ease-in-out infinite;
        border-color: var(--success) !important;
    }

    @keyframes winnerPulse {

        0%,
        100% {
            box-shadow: 0 0 10px rgba(0, 255, 127, 0.3);
        }

        50% {
            box-shadow: 0 0 30px rgba(0, 255, 127, 0.6), 0 0 60px rgba(0, 255, 127, 0.3);
        }
    }

    .loser-fade {
        opacity: 0.5;
        border-color: var(--danger) !important;
    }

    .result-badge {
        margin-left: 10px;
        font-weight: bold;
        padding: 4px 10px;
        border-radius: 4px;
        display: inline-block;
    }

    .bust-anim {
        color: var(--danger);
        animation: shake 0.5s ease-in-out;
    }

    @keyframes shake {

        0%,
        100% {
            transform: translateX(0);
        }

        25% {
            transform: translateX(-5px);
        }

        75% {
            transform: translateX(5px);
        }
    }

    .bj-anim,
    .win-anim {
        color: var(--gold-main);
        animation: celebrate 0.8s ease-out;
    }

    @keyframes celebrate {
        0% {
            transform: scale(1);
        }

        50% {
            transform: scale(1.3);
        }

        100% {
            transform: scale(1);
        }
    }

    .lose-anim {
        color: var(--danger);
        opacity: 0.8;
    }

    .push-anim {
        color: var(--text-muted);
        background: rgba(255, 255, 255, 0.1);
    }

    .player-actions button {
        transition: all 0.2s ease;
    }

    .player-actions button:hover {
        transform: scale(1.05);
    }

    /* Sidebar styling */
    .sidebar {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .sidebar .card {
        background: rgba(0, 0, 0, 0.6);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 12px;
    }
</style>

<main class="dashboard-main">
    <div class="container">

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
            <div>
                <a href="lobby.php" style="color: var(--text-muted);">← Retour au Lobby</a>
                <h1 style="font-size: 1.8rem; margin-top: 10px;"><?= htmlspecialchars($table['name']) ?></h1>
            </div>
            <div style="text-align: right;">
                <div style="font-size: 0.8rem; color: var(--text-muted);">VOTRE SOLDE</div>
                <div style="font-size: 1.5rem; font-weight: bold; color: var(--gold-main);">
                    <?= number_format($myBalance, 0, ',', ' ') ?> 🪙
                </div>
            </div>
        </div>

        <!-- HUD Overlay for game info -->
        <div style="
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            padding: 15px 20px;
            background: rgba(0,0,0,0.5);
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.1);
        ">
            <div>
                <span style="color: var(--text-muted);">🎩 Croupier:</span>
                <strong style="color: white; margin-left: 10px;">
                    <?= count($table['dealer_cards_visible'] ?? []) ?> cartes
                </strong>
            </div>
            <div>
                <span style="color: var(--text-muted);">📊 Statut:</span>
                <strong
                    style="margin-left: 10px; color: <?= $table['status'] === 'playing' ? 'var(--success)' : 'var(--warning)' ?>;">
                    <?= strtoupper($table['status']) ?>
                </strong>
            </div>
            <div>
                <span style="color: var(--text-muted);">👥 Joueurs:</span>
                <strong style="color: white; margin-left: 10px;"><?= count($table['players']) ?>/5</strong>
            </div>
        </div>

        <div class="game-container">
            <!-- Left column: Table and game controls -->
            <div class="game-main">
                <!-- 3D Game Table -->
                <div id="game3d-container" style="
                    width: 100%;
                    height: 450px;
                    border-radius: 16px;
                    overflow: hidden;
                    border: 2px solid var(--violet-main);
                    position: relative;
                    background: #0a0a1a;
                ">
                    <div id="game3d-loading" style="
                        position: absolute;
                        top: 50%;
                        left: 50%;
                        transform: translate(-50%, -50%);
                        text-align: center;
                        color: var(--gold-main);
                    ">
                        <div style="font-size: 3rem; margin-bottom: 10px;">🎰</div>
                        <div>Chargement de la table 3D...</div>
                    </div>
                </div>

                <!-- Game finished summary -->
                <?php if ($table['status'] === 'finished'): ?>
                    <div style="grid-column: 1 / -1; margin-bottom: 30px;">
                        <div class="card" style="padding: 30px; text-align: center; border: 2px solid var(--gold-main);">
                            <h2 style="color: var(--gold-main); margin-bottom: 20px;">🏆 PARTIE TERMINÉE</h2>

                            <div style="display: flex; flex-wrap: wrap; gap: 20px; justify-content: center;">
                                <?php foreach ($table['players'] as $player):
                                    $isWin = in_array($player['status'], ['win', 'blackjack']);
                                    $isLose = in_array($player['status'], ['lose', 'bust']);
                                    $isPush = $player['status'] === 'push';

                                    // Calculer le gain/perte
                                    $betAmount = $player['bet_amount'];
                                    if ($player['status'] === 'blackjack') {
                                        $gain = intval($betAmount * 1.5); // Gain net (3:2 - mise)
                                    } elseif ($isWin) {
                                        $gain = $betAmount; // Gain net
                                    } elseif ($isPush) {
                                        $gain = 0;
                                    } else {
                                        $gain = -$betAmount; // Perte
                                    }
                                    ?>
                                    <div style="
                                    padding: 20px 30px;
                                    border-radius: 12px;
                                    min-width: 180px;
                                    background: <?= $isWin ? 'rgba(0,255,127,0.15)' : ($isLose ? 'rgba(255,0,64,0.15)' : 'rgba(255,255,255,0.05)') ?>;
                                    border: 2px solid <?= $isWin ? 'var(--success)' : ($isLose ? 'var(--danger)' : 'var(--text-muted)') ?>;
                                    <?= $isWin ? 'animation: winnerPulse 1.5s ease-in-out infinite;' : '' ?>
                                ">
                                        <div style="font-size: 2rem; margin-bottom: 10px;">
                                            <?= $isWin ? '🏆' : ($isLose ? '💔' : '🤝') ?>
                                        </div>
                                        <div style="font-weight: bold; font-size: 1.1rem; margin-bottom: 5px;">
                                            <?= htmlspecialchars($player['twitch_username']) ?>
                                        </div>
                                        <div style="
                                        font-size: 1.3rem;
                                        font-weight: bold;
                                        color: <?= $gain > 0 ? 'var(--success)' : ($gain < 0 ? 'var(--danger)' : 'var(--text-muted)') ?>;
                                    ">
                                            <?= $gain > 0 ? '+' : '' ?>         <?= number_format($gain, 0, ',', ' ') ?> 🪙
                                        </div>
                                        <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 5px;">
                                            <?php
                                            echo match ($player['status']) {
                                                'blackjack' => '🎉 BLACKJACK!',
                                                'win' => '✅ Gagné',
                                                'push' => '↔️ Égalité',
                                                'bust' => '💥 Bust',
                                                'lose' => '❌ Perdu',
                                                default => strtoupper($player['status'])
                                            };
                                            ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <a href="lobby.php" class="btn btn-gold" style="margin-top: 30px; padding: 15px 40px;">
                                🔄 RETOUR AU LOBBY
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Zone de jeu principale -->
                <div class="table-zone">

                    <!-- Croupier -->
                    <div class="dealer-area">
                        <h3 style="color: var(--gold-main); margin-bottom: 10px;">🎩 CROUPIER</h3>
                        <div class="cards-row">
                            <?php if (empty($table['dealer_cards_visible'])): ?>
                                <span style="color: var(--text-muted);">En attente...</span>
                            <?php else: ?>
                                <?php foreach ($table['dealer_cards_visible'] as $card): ?>
                                    <div class="playing-card <?= $card['suit'] ?>">
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
                    </div>

                    <!-- Joueurs -->
                    <div class="players-area">
                        <h3 style="color: var(--text-muted); margin-bottom: 15px;">JOUEURS</h3>

                        <?php if (empty($table['players'])): ?>
                            <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                                Aucun joueur pour le moment
                            </div>
                        <?php else: ?>
                            <?php
                            // Calculer si la distribution initiale est terminée
                            $playerCount = count($table['players']);
                            $totalCards = 0;
                            foreach ($table['players'] as $p) {
                                $totalCards += count($p['cards']);
                            }
                            $totalCards += count($table['dealer_cards_visible']);
                            $expectedCards = ($playerCount * 2) + 2; // 2 cartes par joueur + 2 pour le croupier
                            $dealingComplete = $totalCards >= $expectedCards;
                            ?>

                            <?php if (!$dealingComplete && $table['status'] === 'playing'): ?>
                                <div
                                    style="text-align: center; padding: 20px; margin-bottom: 15px; background: rgba(255,183,0,0.1); border-radius: 8px;">
                                    <div style="color: var(--warning); font-weight: bold;">📦 Distribution en cours...</div>
                                    <div style="color: var(--text-muted); font-size: 0.9rem;"><?= $totalCards ?> /
                                        <?= $expectedCards ?> cartes
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php foreach ($table['players'] as $player):
                                $isMe = $player['user_id'] == $userId;
                                $isPlaying = $player['status'] === 'playing';

                                // Déterminer quelle main est active
                                $hasSplit = $player['has_split'] ?? false;
                                $currentHand = $player['current_hand'] ?? 'main';

                                // Si le joueur a splitté et joue la main splittée
                                if ($hasSplit && $currentHand === 'split') {
                                    // split_cards est déjà décodé par getTableState()
                                    $splitCards = is_array($player['split_cards']) ? $player['split_cards'] : (json_decode($player['split_cards'], true) ?? []);
                                    $handValue = calculateHandValue($splitCards);
                                    $cardCount = count($splitCards);
                                } else {
                                    // Main principale (ou pas de split)
                                    $handValue = $player['hand_value'];
                                    $cardCount = count($player['cards']);
                                }

                                $pendingAction = $player['pending_action'] ?? null;
                                $hasPendingAction = !empty($pendingAction);
                                // Actions seulement possibles si la distribution est terminée ET pas d'action en attente
                                $canAct = $dealingComplete && $isPlaying && !$hasPendingAction;
                                $canHit = $canAct && $handValue < 21;
                                $canDouble = $canAct && $cardCount === 2 && !$player['doubled'] && !$hasSplit;
                                $canSplit = $canAct && $cardCount === 2 && !$hasSplit && canSplit($player['cards']);
                                $isWinner = in_array($player['status'], ['win', 'blackjack']);
                                $isLoser = in_array($player['status'], ['lose', 'bust']);
                                ?>
                                <div
                                    class="player-slot <?= $isMe ? 'mine' : '' ?> <?= $isPlaying ? 'active' : '' ?> <?= $isWinner ? 'winner-glow' : '' ?> <?= $isLoser ? 'loser-fade' : '' ?>">
                                    <div
                                        style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                        <div>
                                            <strong><?= htmlspecialchars($player['twitch_username']) ?></strong>
                                            <?php if ($isMe): ?>
                                                <span style="color: var(--violet-glow); margin-left: 5px;">(Vous)</span>
                                            <?php endif; ?>
                                            <span
                                                style="color: var(--text-muted); margin-left: 10px;">#<?= $player['seat_number'] ?></span>
                                        </div>
                                        <div>
                                            <span style="color: var(--gold-main); font-weight: bold;">💰
                                                <?= number_format($player['bet_amount']) ?></span>
                                            <?php if ($player['doubled']): ?>
                                                <span style="color: var(--warning); margin-left: 5px;">x2</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <?php if ($hasSplit): ?>
                                        <!-- Affichage des deux mains en cas de split -->
                                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                            <!-- Main 1 -->
                                            <div>
                                                <div
                                                    style="margin-bottom: 5px; font-size: 0.8rem; font-weight: bold; color: <?= $currentHand === 'main' ? 'var(--gold-main)' : 'var(--text-muted)' ?>;">
                                                    Main 1 <?= $currentHand === 'main' ? '👈' : '' ?>
                                                </div>
                                                <div class="cards-row" style="flex-wrap: nowrap; overflow-x: auto;">
                                                    <?php foreach ($player['cards'] as $card): ?>
                                                        <div class="playing-card <?= $card['suit'] ?>" style="min-width: 45px;">
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
                                                </div>
                                                <div style="margin-top: 5px; font-size: 0.85rem; color: var(--text-muted);">
                                                    <?= $player['hand_value'] ?>
                                                </div>
                                            </div>

                                            <!-- Main 2 (Splittée) -->
                                            <div>
                                                <div
                                                    style="margin-bottom: 5px; font-size: 0.8rem; font-weight: bold; color: <?= $currentHand === 'split' ? 'var(--gold-main)' : 'var(--text-muted)' ?>;">
                                                    Main 2 <?= $currentHand === 'split' ? '👈' : '' ?>
                                                </div>
                                                <div class="cards-row" style="flex-wrap: nowrap; overflow-x: auto;">
                                                    <?php
                                                    $splitCardsDisplay = [];
                                                    if (isset($player['split_cards'])) {
                                                        $splitCardsDisplay = is_array($player['split_cards']) ? $player['split_cards'] : (json_decode($player['split_cards'], true) ?? []);
                                                    }
                                                    foreach ($splitCardsDisplay as $card): ?>
                                                        <div class="playing-card <?= $card['suit'] ?>" style="min-width: 45px;">
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
                                                </div>
                                                <div style="margin-top: 5px; font-size: 0.85rem; color: var(--text-muted);">
                                                    <?= !empty($splitCardsDisplay) ? calculateHandValue($splitCardsDisplay) : 0 ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <!-- Affichage normal sans split -->
                                        <div class="cards-row">
                                            <?php if (empty($player['cards'])): ?>
                                                <span style="color: var(--text-muted); font-size: 0.9rem;">En attente des
                                                    cartes...</span>
                                            <?php else: ?>
                                                <?php foreach ($player['cards'] as $card): ?>
                                                    <div class="playing-card <?= $card['suit'] ?>">
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
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($player['cards'])): ?>
                                        <div style="margin-top: 10px; color: var(--text-muted);">
                                            Total: <strong style="color: white;"><?= $handValue ?></strong>
                                            <?php if ($handValue > 21): ?>
                                                <span class="result-badge bust-anim">💥 BUST!</span>
                                            <?php elseif (isBlackjack($player['cards'])): ?>
                                                <span class="result-badge bj-anim">🎉 BLACKJACK!</span>
                                            <?php elseif ($isWinner): ?>
                                                <span class="result-badge win-anim">🏆 GAGNÉ!</span>
                                            <?php elseif ($player['status'] === 'lose'): ?>
                                                <span class="result-badge lose-anim">❌ PERDU</span>
                                            <?php elseif ($player['status'] === 'push'): ?>
                                                <span class="result-badge push-anim">🤝 ÉGALITÉ</span>
                                            <?php endif; ?>

                                            <span style="float: right; padding: 3px 10px; border-radius: 4px; font-size: 0.8rem;
                                            background: <?php echo match ($player['status']) {
                                                'betting' => 'rgba(255,183,0,0.2)',
                                                'playing' => 'rgba(0,255,127,0.2)',
                                                'stand' => 'rgba(100,100,100,0.2)',
                                                'bust', 'lose' => 'rgba(255,0,64,0.2)',
                                                'blackjack', 'win' => 'rgba(0,255,127,0.3)',
                                                'push' => 'rgba(255,255,255,0.1)',
                                                default => 'transparent'
                                            }; ?>;
                                            color: <?php echo match ($player['status']) {
                                                'betting' => 'var(--warning)',
                                                'playing' => 'var(--success)',
                                                'bust', 'lose' => 'var(--danger)',
                                                'blackjack', 'win' => 'var(--success)',
                                                default => 'var(--text-muted)'
                                            }; ?>;">
                                                <?= strtoupper($player['status']) ?>
                                            </span>
                                        </div>

                                        <!-- Boutons d'action pour le joueur lui-même (seulement si distribution terminée) -->
                                        <?php if ($isMe && $canAct): ?>
                                            <div class="player-actions"
                                                style="margin-top: 15px; display: flex; gap: 10px; flex-wrap: wrap;">
                                                <?php if ($canHit): ?>
                                                    <button onclick="playerAction('hit')" class="btn btn-primary"
                                                        style="padding: 10px 20px;">
                                                        🃏 HIT
                                                    </button>
                                                <?php endif; ?>

                                                <button onclick="playerAction('stand')" class="btn btn-outline"
                                                    style="padding: 10px 20px;">
                                                    ✋ STAND
                                                </button>

                                                <?php if ($canDouble): ?>
                                                    <button onclick="playerAction('double')" class="btn btn-gold"
                                                        style="padding: 10px 20px;">
                                                        💰 DOUBLE
                                                    </button>
                                                <?php endif; ?>

                                                <?php if ($canSplit): ?>
                                                    <button onclick="playerAction('split')" class="btn btn-primary"
                                                        style="padding: 10px 20px; background: var(--violet-main);">
                                                        ✌️ SPLIT
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        <?php elseif ($isMe && $isPlaying && $hasPendingAction): ?>
                                            <?php
                                            $actionLabels = [
                                                'hit' => '🃏 Demande une carte',
                                                'stand' => '✋ Reste',
                                                'double' => '💰 Double',
                                                'split' => '✌️ Split'
                                            ];
                                            ?>
                                            <div
                                                style="margin-top: 10px; padding: 15px; background: rgba(255,215,0,0.15); border: 1px solid var(--gold-main); border-radius: 6px; text-align: center;">
                                                <div style="color: var(--gold-main); font-weight: bold;">
                                                    <?= $actionLabels[$pendingAction] ?>
                                                    <?php if ($hasSplit): ?>
                                                        (<?= $currentHand === 'main' ? 'Main 1' : 'Main 2' ?>)
                                                    <?php endif; ?>
                                                </div>
                                                <div style="color: var(--text-muted); font-size: 0.85rem; margin-top: 5px;">
                                                    ⏳ En attente du croupier...
                                                </div>
                                            </div>
                                        <?php elseif ($isMe && $isPlaying && !$dealingComplete): ?>
                                            <div
                                                style="margin-top: 10px; padding: 10px; background: rgba(255,183,0,0.1); border-radius: 6px; text-align: center;">
                                                <span style="color: var(--warning); font-size: 0.85rem;">
                                                    ⏳ En attente de la distribution...
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                </div>

            </div> <!-- End game-main -->

            <!-- Sidebar -->
            <div class="sidebar">

                <!-- Statut -->
                <div class="card" style="padding: 20px; text-align: center;">
                    <div style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 5px;">STATUT</div>
                    <div style="font-size: 1.2rem; font-weight: bold; color: 
                        <?= $table['status'] === 'betting' ? 'var(--success)' : 'var(--warning)' ?>;">
                        <?= $table['status'] === 'betting' ? '🟢 MISES OUVERTES' : '🎰 EN JEU' ?>
                    </div>
                </div>

                <?php if (!$myPlayer && $table['status'] === 'betting' && count($table['players']) < 5): ?>
                    <!-- Rejoindre la table -->
                    <div class="card" style="padding: 20px;">
                        <h3 style="margin-bottom: 20px; text-align: center;">REJOINDRE</h3>
                        <form method="POST" action="api/join_table.php">
                            <input type="hidden" name="table_id" value="<?= $tableId ?>">

                            <div class="form-group">
                                <label>CHOISIR UN SIÈGE</label>
                                <select name="seat_number" class="form-control" required>
                                    <?php
                                    $takenSeats = array_column($table['players'], 'seat_number');
                                    for ($i = 1; $i <= 5; $i++):
                                        if (!in_array($i, $takenSeats)):
                                            ?>
                                            <option value="<?= $i ?>">Siège #<?= $i ?></option>
                                            <?php
                                        endif;
                                    endfor;
                                    ?>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-gold" style="width: 100%;">
                                S'ASSEOIR
                            </button>
                        </form>
                    </div>

                <?php elseif ($myPlayer && $table['status'] === 'betting'): ?>
                    <!-- Miser - Interactive chip system -->
                    <div class="card" style="padding: 20px;">
                        <h3 style="margin-bottom: 15px; text-align: center;">VOTRE MISE</h3>

                        <div class="bet-display">
                            <div class="bet-amount" id="bet-amount"><?= number_format($myPlayer['bet_amount']) ?></div>
                            <div style="color: var(--text-muted); font-size: 0.9rem;">N0thyCoins</div>
                        </div>

                        <div style="font-size: 0.8rem; color: var(--text-muted); text-align: center; margin-bottom: 15px;">
                            Min: <?= number_format($table['min_bet']) ?> | Max: <?= number_format($table['max_bet']) ?>
                        </div>

                        <!-- Chip selector - click to add to bet -->
                        <div class="chips-selector" style="margin-bottom: 15px;">
                            <div class="chip chip-10" onclick="addChipToBet(10)" title="Cliquer pour ajouter">10</div>
                            <div class="chip chip-25" onclick="addChipToBet(25)" title="Cliquer pour ajouter">25</div>
                            <div class="chip chip-50" onclick="addChipToBet(50)" title="Cliquer pour ajouter">50</div>
                            <div class="chip chip-100" onclick="addChipToBet(100)" title="Cliquer pour ajouter">100</div>
                            <div class="chip chip-500" onclick="addChipToBet(500)" title="Cliquer pour ajouter">500</div>
                            <div class="chip chip-1000" onclick="addChipToBet(1000)" title="Cliquer pour ajouter">1K</div>
                        </div>

                        <!-- Chips on table area -->
                        <div id="bet-chips-area" style="
                            min-height: 80px;
                            background: rgba(0, 100, 0, 0.2);
                            border: 2px dashed rgba(255, 215, 0, 0.3);
                            border-radius: 12px;
                            padding: 10px;
                            display: flex;
                            flex-wrap: wrap;
                            gap: 5px;
                            justify-content: center;
                            align-items: center;
                        ">
                            <!-- Chips will be added here dynamically -->
                            <div id="bet-chips-placeholder"
                                style="color: var(--text-muted); font-size: 0.8rem; text-align: center;">
                                Cliquez sur les jetons ci-dessus<br>pour placer votre mise
                            </div>
                        </div>

                        <div style="font-size: 0.75rem; color: var(--text-muted); text-align: center; margin-top: 10px;">
                            💡 Cliquez sur un jeton placé pour le retirer
                        </div>
                    </div>

                <?php elseif ($myPlayer && $table['status'] === 'playing'): ?>
                    <!-- En jeu - Afficher les actions possibles -->
                    <div class="card" style="padding: 20px; text-align: center;">
                        <h3 style="margin-bottom: 15px;">EN JEU</h3>
                        <p style="color: var(--text-muted);">
                            Le croupier contrôle la partie.<br>
                            Observez l'écran principal.
                        </p>
                    </div>
                <?php endif; ?>

                <!-- Info table -->
                <div class="card" style="padding: 20px;">
                    <h4 style="margin-bottom: 15px; color: var(--text-muted);">INFOS</h4>
                    <div style="font-size: 0.9rem; color: var(--text-muted);">
                        <div style="margin-bottom: 8px;">👥 <?= count($table['players']) ?>/5 joueurs</div>
                        <div style="margin-bottom: 8px;">💰 Mises: <?= number_format($table['min_bet']) ?> -
                            <?= number_format($table['max_bet']) ?>
                        </div>
                        <div>🃏 Blackjack paie 3:2</div>
                    </div>
                </div>

            </div>

        </div>

    </div>
</main>

<script>
    let currentBet = <?= $myPlayer ? $myPlayer['bet_amount'] : 0 ?>;
    const minBet = <?= $table['min_bet'] ?>;
    const maxBet = <?= $table['max_bet'] ?>;
    const myBalance = <?= $myBalance ?>;
    const tableId = <?= $tableId ?>;
    const tableStatus = '<?= $table['status'] ?>';
    const isBetting = <?= ($myPlayer && $table['status'] === 'betting') ? 'true' : 'false' ?>;

    // Array to track individual chips placed
    let placedChips = [];

    function addChipToBet(value) {
        // Check limits
        if (currentBet + value > maxBet) {
            showToast('Mise maximum atteinte !', 'warning');
            return;
        }
        if (currentBet + value > myBalance) {
            showToast('Solde insuffisant !', 'warning');
            return;
        }

        // Add chip to array
        placedChips.push(value);
        currentBet += value;

        // Update display
        renderPlacedChips();
        updateBetDisplay();

        // Save to server immediately
        saveBetToServer();
    }

    function removeChip(index) {
        if (index >= 0 && index < placedChips.length) {
            const value = placedChips[index];
            placedChips.splice(index, 1);
            currentBet -= value;

            // Update display
            renderPlacedChips();
            updateBetDisplay();

            // Save to server immediately
            saveBetToServer();
        }
    }

    function renderPlacedChips() {
        const area = document.getElementById('bet-chips-area');
        const placeholder = document.getElementById('bet-chips-placeholder');

        if (!area) return;

        // Clear area but keep placeholder hidden if chips exist
        area.innerHTML = '';

        if (placedChips.length === 0) {
            area.innerHTML = '<div id="bet-chips-placeholder" style="color: var(--text-muted); font-size: 0.8rem; text-align: center;">Cliquez sur les jetons ci-dessus<br>pour placer votre mise</div>';
            return;
        }

        // Render each chip
        placedChips.forEach((value, idx) => {
            const chip = document.createElement('div');
            chip.className = 'chip chip-' + value + ' placed-chip';
            chip.textContent = value >= 1000 ? (value / 1000) + 'K' : value;
            chip.title = 'Cliquer pour retirer';
            chip.style.cssText = 'cursor: pointer; transform: scale(0.8); transition: transform 0.2s;';
            chip.onclick = () => removeChip(idx);
            chip.onmouseenter = () => chip.style.transform = 'scale(0.9)';
            chip.onmouseleave = () => chip.style.transform = 'scale(0.8)';
            area.appendChild(chip);
        });
    }

    function updateBetDisplay() {
        const display = document.getElementById('bet-amount');
        if (display) {
            display.textContent = currentBet.toLocaleString('fr-FR');
        }
    }

    function saveBetToServer() {
        fetch('api/place_bet.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'table_id=' + tableId + '&amount=' + currentBet
        })
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    console.error('Bet save error:', data.error);
                }
            })
            .catch(err => console.error('Bet save failed:', err));
    }

    function showToast(message, type = 'info') {
        // Simple toast notification
        const toast = document.createElement('div');
        toast.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: ${type === 'warning' ? '#f59e0b' : '#3b82f6'};
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            z-index: 9999;
            animation: slideIn 0.3s ease;
        `;
        toast.textContent = message;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }

    // Actions du joueur (Hit, Stand, Double, Split)
    function playerAction(action) {
        // Désactiver les boutons pendant le traitement
        const buttons = document.querySelectorAll('.player-actions button');
        buttons.forEach(btn => btn.disabled = true);

        fetch('api/player_action.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'table_id=' + tableId + '&action=' + action
        })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    // Afficher le résultat
                    if (data.status === 'bust') {
                        alert('💥 BUST! Vous avez dépassé 21');
                    } else if (data.status === 'stand') {
                        alert('✋ Vous restez sur votre main');
                    } else if (action === 'double') {
                        alert('💰 Double! Nouvelle mise: ' + data.new_bet);
                    } else if (action === 'split') {
                        alert('✌️ Split! Vos cartes ont été séparées');
                    }
                    // Recharger pour voir les changements
                    location.reload();
                } else {
                    alert('Erreur: ' + (data.error || 'Inconnue'));
                    buttons.forEach(btn => btn.disabled = false);
                }
            })
            .catch(err => {
                alert('Erreur de connexion');
                buttons.forEach(btn => btn.disabled = false);
            });
    }

    // Auto-refresh désactivé pour éviter le clignotement de la table 3D
    // L'état du jeu est maintenant géré via AJAX sans rechargement complet
    /*
    if (!isBetting) {
        setInterval(() => {
            location.reload();
        }, 5000);
    } else {
        setInterval(() => {
            fetch('api/get_status.php?table_id=' + tableId)
                .then(r => r.json())
                .then(data => {
                    if (data.status && data.status !== 'betting') {
                        location.reload();
                    }
                })
                .catch(() => { });
        }, 3000);
    }
    */
</script>

<!-- Blackjack CSS 3D Engine (no Three.js needed) -->
<script src="../../assets/js/blackjack3d_v2.js?v=<?= time() ?>"></script>

<!-- Initialize CSS 3D Game -->
<script>
    // Game state from PHP
    const gameState = <?= json_encode([
        'status' => $table['status'],
        'dealer_cards_visible' => $table['dealer_cards_visible'] ?? [],
        'dealer_value' => $table['dealer_value'] ?? 0,
        'players' => array_map(function ($p) {
        return [
            'id' => $p['id'],
            'user_id' => $p['user_id'],
            'twitch_username' => $p['twitch_username'],
            'seat_number' => $p['seat_number'],
            'bet_amount' => $p['bet_amount'],
            'cards' => $p['cards'],
            'status' => $p['status'],
            'hand_value' => $p['hand_value'],
            'has_split' => $p['has_split'] ?? false,
            'split_cards' => $p['split_cards'] ?? [],
            'current_hand' => $p['current_hand'] ?? 'main'
        ];
    }, $table['players'] ?? [])
    ]) ?>;

    // Initialize CSS 3D when DOM is ready
    document.addEventListener('DOMContentLoaded', function () {
        try {
            // Hide loading message
            const loadingEl = document.getElementById('game3d-loading');
            if (loadingEl) loadingEl.style.display = 'none';

            // Create CSS 3D game instance
            window.game3d = new Blackjack3D('game3d-container', gameState);
            console.log('🎰 Blackjack CSS 3D initialized!');

            // Simple AJAX polling instead of SSE for better performance
            // Poll every 3 seconds to check for game state updates
            setInterval(function () {
                fetch('api/get_status.php?table_id=<?= $tableId ?>')
                    .then(r => r.json())
                    .then(data => {
                        if (data.success && window.game3d) {
                            window.game3d.updateGameState(data);
                        }
                    })
                    .catch(() => { }); // Silent fail
            }, 3000);

        } catch (error) {
            console.error('3D init error:', error);
            const loadingEl = document.getElementById('game3d-loading');
            if (loadingEl) {
                loadingEl.innerHTML =
                    '<div style="color: var(--danger);">❌ Erreur 3D</div>' +
                    '<div style="font-size: 0.8rem; color: var(--text-muted);">' + error.message + '</div>';
            }
        }
    });
</script>

<?php require_once '../../includes/footer.php'; ?>