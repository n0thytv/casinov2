<?php
require_once '../../../includes/init.php';
require_once '../../../includes/blackjack_functions.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !isAdmin()) {
    die(json_encode(['success' => false, 'error' => 'Non autorisé']));
}

$tableId = intval($_POST['table_id'] ?? 0);
$target = $_POST['target'] ?? ''; // 'dealer' ou 'player'
$playerId = intval($_POST['player_id'] ?? 0);
$action = $_POST['action'] ?? '';

if (!$tableId) {
    die(json_encode(['success' => false, 'error' => 'Table non trouvée']));
}

$db = getDB();

// Récupérer l'état de la table
$table = getTableState($tableId, true);
if (!$table || $table['status'] !== 'playing') {
    header("Location: ../manage_table.php?id=$tableId&error=not_playing");
    exit;
}

// Distribution initiale : une seule carte à la fois
if ($action === 'initial_deal') {
    // Compter les cartes déjà distribuées
    $totalCards = 0;
    foreach ($table['players'] as $player) {
        $totalCards += count($player['cards']);
    }
    $totalCards += count($table['dealer_cards']);

    $playerCount = count($table['players']);
    $expectedCards = ($playerCount * 2) + 2; // 2 cartes par joueur + 2 pour le croupier

    if ($totalCards >= $expectedCards) {
        header("Location: ../manage_table.php?id=$tableId&error=deal_complete");
        exit;
    }

    // Déterminer à qui donner la prochaine carte
    // Ordre: Joueur1, Joueur2, ..., Croupier, Joueur1, Joueur2, ..., Croupier
    $round = intval($totalCards / ($playerCount + 1)); // 0 ou 1
    $position = $totalCards % ($playerCount + 1);

    $newCard = drawCardFromTable($tableId);

    if ($position < $playerCount) {
        // Donner au joueur
        $targetPlayer = $table['players'][$position];
        $cards = $targetPlayer['cards'];
        $cards[] = $newCard;

        // Vérifier blackjack après 2 cartes
        $status = (count($cards) === 2 && isBlackjack($cards)) ? 'blackjack' : 'playing';

        $stmt = $db->prepare("UPDATE blackjack_players SET cards = ?, status = ? WHERE id = ?");
        $stmt->execute([json_encode($cards), $status, $targetPlayer['id']]);
    } else {
        // Donner au croupier
        $dealerCards = $table['dealer_cards'];
        $dealerCards[] = $newCard;

        $stmt = $db->prepare("UPDATE blackjack_tables SET dealer_cards = ? WHERE id = ?");
        $stmt->execute([json_encode($dealerCards), $tableId]);
    }

    header("Location: ../manage_table.php?id=$tableId");
    exit;
}

// Tirer une carte pour le croupier
if ($target === 'dealer') {
    // Vérifier si la distribution initiale est terminée
    if (!isDealingComplete($tableId)) {
        header("Location: ../manage_table.php?id=$tableId&error=dealing_not_complete");
        exit;
    }

    // Vérifier si tous les joueurs ont terminé leur tour
    if (!isAllPlayersFinished($tableId)) {
        header("Location: ../manage_table.php?id=$tableId&error=players_not_finished");
        exit;
    }

    $dealerCards = $table['dealer_cards'];
    $dealerValue = calculateHandValue($dealerCards);

    // Vérifier si le croupier peut encore tirer (< 17)
    if ($dealerValue >= 17) {
        header("Location: ../manage_table.php?id=$tableId&error=dealer_must_stand");
        exit;
    }

    $newCard = drawCardFromTable($tableId);
    $dealerCards[] = $newCard;

    $stmt = $db->prepare("UPDATE blackjack_tables SET dealer_cards = ? WHERE id = ?");
    $stmt->execute([json_encode($dealerCards), $tableId]);

    // Vérifier si le croupier a >= 17, si oui, auto-clore la manche
    $newValue = calculateHandValue($dealerCards);
    if ($newValue >= 17) {
        checkAndAutoCloseRound($tableId);
    }

    header("Location: ../manage_table.php?id=$tableId");
    exit;
}

// Tirer une carte pour un joueur (admin distribue)
if ($target === 'player' && $playerId) {
    $stmt = $db->prepare("SELECT * FROM blackjack_players WHERE id = ? AND table_id = ?");
    $stmt->execute([$playerId, $tableId]);
    $player = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$player) {
        header("Location: ../manage_table.php?id=$tableId&error=player_not_found");
        exit;
    }

    $cardsJson = $player['cards'] ?? '[]';
    $cards = json_decode($cardsJson, true) ?? [];
    $newCard = drawCardFromTable($tableId);
    $cards[] = $newCard;

    $handValue = calculateHandValue($cards);
    $status = $handValue > 21 ? 'bust' : 'playing';

    $stmt = $db->prepare("UPDATE blackjack_players SET cards = ?, status = ? WHERE id = ?");
    $stmt->execute([json_encode($cards), $status, $playerId]);

    header("Location: ../manage_table.php?id=$tableId");
    exit;
}

header("Location: ../manage_table.php?id=$tableId");
exit;
