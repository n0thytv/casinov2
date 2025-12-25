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

// Distribution initiale
if ($action === 'initial_deal') {
    $deck = createDeck();
    $deckIndex = 0;

    // 2 cartes pour chaque joueur
    $stmt = $db->prepare("SELECT id FROM blackjack_players WHERE table_id = ? ORDER BY seat_number");
    $stmt->execute([$tableId]);
    $players = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($players as $player) {
        $playerCards = [$deck[$deckIndex++], $deck[$deckIndex++]];
        $cardsJson = json_encode($playerCards);

        // Vérifier blackjack
        $status = isBlackjack($playerCards) ? 'blackjack' : 'playing';

        $stmt2 = $db->prepare("UPDATE blackjack_players SET cards = ?, status = ? WHERE id = ?");
        $stmt2->execute([$cardsJson, $status, $player['id']]);
    }

    // 2 cartes pour le croupier
    $dealerCards = [$deck[$deckIndex++], $deck[$deckIndex++]];
    $stmt = $db->prepare("UPDATE blackjack_tables SET dealer_cards = ?, current_player_seat = 1 WHERE id = ?");
    $stmt->execute([json_encode($dealerCards), $tableId]);

    header("Location: ../manage_table.php?id=$tableId");
    exit;
}

// Tirer une carte pour le croupier
if ($target === 'dealer') {
    $dealerCards = $table['dealer_cards'];
    $newCard = createDeck()[0]; // Nouvelle carte aléatoire
    $dealerCards[] = $newCard;

    $stmt = $db->prepare("UPDATE blackjack_tables SET dealer_cards = ? WHERE id = ?");
    $stmt->execute([json_encode($dealerCards), $tableId]);

    header("Location: ../manage_table.php?id=$tableId");
    exit;
}

// Tirer une carte pour un joueur
if ($target === 'player' && $playerId) {
    // Récupérer le joueur
    $stmt = $db->prepare("SELECT * FROM blackjack_players WHERE id = ? AND table_id = ?");
    $stmt->execute([$playerId, $tableId]);
    $player = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$player) {
        header("Location: ../manage_table.php?id=$tableId&error=player_not_found");
        exit;
    }

    $cards = json_decode($player['cards'], true) ?? [];
    $newCard = createDeck()[0];
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
