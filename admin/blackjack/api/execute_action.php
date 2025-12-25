<?php
/**
 * API Admin: Exécuter l'action demandée par un joueur
 * Le croupier approuve et distribue les cartes si nécessaire
 */
require_once '../../../includes/init.php';
require_once '../../../includes/blackjack_functions.php';

if (!isLoggedIn() || !isAdmin()) {
    header("Location: ../../pages/login.php");
    exit;
}

$tableId = intval($_POST['table_id'] ?? 0);
$playerId = intval($_POST['player_id'] ?? 0);

if (!$tableId || !$playerId) {
    header("Location: ../manage_table.php?id=$tableId&error=missing_params");
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

$action = $player['pending_action'];
if (!$action) {
    header("Location: ../manage_table.php?id=$tableId&error=no_pending_action");
    exit;
}

$cardsJson = $player['cards'] ?? '[]';
$cards = json_decode($cardsJson, true) ?? [];
$userId = $player['user_id'];
$twitchUsername = $player['twitch_username'];

switch ($action) {
    case 'hit':
        // Distribuer une carte
        $newCard = drawCardFromTable($tableId);
        $cards[] = $newCard;
        $handValue = calculateHandValue($cards);
        $status = $handValue > 21 ? 'bust' : 'playing';

        $stmt = $db->prepare("UPDATE blackjack_players SET cards = ?, status = ?, pending_action = NULL WHERE id = ?");
        $stmt->execute([json_encode($cards), $status, $playerId]);
        break;

    case 'stand':
        $stmt = $db->prepare("UPDATE blackjack_players SET status = 'stand', pending_action = NULL WHERE id = ?");
        $stmt->execute([$playerId]);
        break;

    case 'double':
        $newBet = $player['bet_amount'] * 2;

        // Débiter le joueur
        $result = removeN0thyCoins($twitchUsername, $player['bet_amount'], 0);
        if (!$result || !($result['success'] ?? false)) {
            // Annuler l'action si pas assez de fonds
            $stmt = $db->prepare("UPDATE blackjack_players SET pending_action = NULL WHERE id = ?");
            $stmt->execute([$playerId]);
            header("Location: ../manage_table.php?id=$tableId&error=insufficient_funds");
            exit;
        }

        // Créditer le casino
        updateCasinoBalance($player['bet_amount'], $tableId, $userId, 'double', "Double: {$player['bet_amount']}");

        // Tirer une carte et stand automatiquement
        $newCard = drawCardFromTable($tableId);
        $cards[] = $newCard;
        $handValue = calculateHandValue($cards);
        $status = $handValue > 21 ? 'bust' : 'stand';

        $stmt = $db->prepare("UPDATE blackjack_players SET bet_amount = ?, cards = ?, status = ?, doubled = TRUE, pending_action = NULL WHERE id = ?");
        $stmt->execute([$newBet, json_encode($cards), $status, $playerId]);
        break;

    case 'split':
        // Débiter pour la 2ème main
        $result = removeN0thyCoins($twitchUsername, $player['bet_amount'], 0);
        if (!$result || !($result['success'] ?? false)) {
            $stmt = $db->prepare("UPDATE blackjack_players SET pending_action = NULL WHERE id = ?");
            $stmt->execute([$playerId]);
            header("Location: ../manage_table.php?id=$tableId&error=insufficient_funds");
            exit;
        }

        updateCasinoBalance($player['bet_amount'], $tableId, $userId, 'bet', "Split: {$player['bet_amount']}");

        // Séparer les cartes
        $mainHand = [$cards[0]];
        $splitHand = [$cards[1]];

        $stmt = $db->prepare("UPDATE blackjack_players SET cards = ?, split_cards = ?, has_split = TRUE, current_hand = 'main', pending_action = NULL WHERE id = ?");
        $stmt->execute([json_encode($mainHand), json_encode($splitHand), $playerId]);
        break;
}

// Vérifier si tous les joueurs ont terminé et clore automatiquement
checkAndAutoCloseRound($tableId);

header("Location: ../manage_table.php?id=$tableId");
exit;
