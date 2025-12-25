<?php
require_once '../../../includes/init.php';
require_once '../../../includes/blackjack_functions.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !isAdmin()) {
    die(json_encode(['success' => false, 'error' => 'Non autorisé']));
}

$tableId = intval($_POST['table_id'] ?? 0);
$playerId = intval($_POST['player_id'] ?? 0);
$action = $_POST['action'] ?? '';

if (!$tableId || !$playerId || !$action) {
    header("Location: ../manage_table.php?id=$tableId&error=invalid_params");
    exit;
}

$db = getDB();

// Récupérer le joueur
$stmt = $db->prepare("
    SELECT bp.*, u.twitch_username 
    FROM blackjack_players bp 
    JOIN users u ON bp.user_id = u.id 
    WHERE bp.id = ? AND bp.table_id = ?
");
$stmt->execute([$playerId, $tableId]);
$player = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$player) {
    header("Location: ../manage_table.php?id=$tableId&error=player_not_found");
    exit;
}

$cards = json_decode($player['cards'], true) ?? [];

switch ($action) {
    case 'stand':
        // Le joueur reste
        $stmt = $db->prepare("UPDATE blackjack_players SET status = 'stand' WHERE id = ?");
        $stmt->execute([$playerId]);
        break;

    case 'double':
        // Doubler la mise
        $newBet = $player['bet_amount'] * 2;

        // Débiter le joueur du montant supplémentaire
        $result = removeN0thyCoins($player['twitch_username'], $player['bet_amount'], 0);

        if (!$result || !($result['success'] ?? false)) {
            header("Location: ../manage_table.php?id=$tableId&error=double_failed");
            exit;
        }

        // Créditer le casino
        updateCasinoBalance($player['bet_amount'], $tableId, $player['user_id'], 'double', "Double BJ: {$player['bet_amount']}");

        // Tirer une carte
        $newCard = drawCardFromTable($tableId);
        $cards[] = $newCard;
        $handValue = calculateHandValue($cards);
        $status = $handValue > 21 ? 'bust' : 'stand'; // Après double, on s'arrête automatiquement

        $stmt = $db->prepare("UPDATE blackjack_players SET bet_amount = ?, cards = ?, status = ?, doubled = TRUE WHERE id = ?");
        $stmt->execute([$newBet, json_encode($cards), $status, $playerId]);
        break;

    case 'split':
        // Splitter les cartes
        if (!canSplit($cards)) {
            header("Location: ../manage_table.php?id=$tableId&error=cannot_split");
            exit;
        }

        // Débiter le joueur du montant supplémentaire pour la 2ème main
        $result = removeN0thyCoins($player['twitch_username'], $player['bet_amount'], 0);

        if (!$result || !($result['success'] ?? false)) {
            header("Location: ../manage_table.php?id=$tableId&error=split_failed");
            exit;
        }

        // Créditer le casino
        updateCasinoBalance($player['bet_amount'], $tableId, $player['user_id'], 'bet', "Split BJ: {$player['bet_amount']}");

        // Séparer les cartes
        $mainHand = [$cards[0]];
        $splitHand = [$cards[1]];

        $stmt = $db->prepare("UPDATE blackjack_players SET cards = ?, split_cards = ?, has_split = TRUE, current_hand = 'main' WHERE id = ?");
        $stmt->execute([json_encode($mainHand), json_encode($splitHand), $playerId]);
        break;
}

header("Location: ../manage_table.php?id=$tableId");
exit;
