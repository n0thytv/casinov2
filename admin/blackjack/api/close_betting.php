<?php
require_once '../../../includes/init.php';
require_once '../../../includes/blackjack_functions.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !isAdmin()) {
    die(json_encode(['success' => false, 'error' => 'Non autorisé']));
}

$tableId = intval($_POST['table_id'] ?? 0);
if (!$tableId) {
    die(json_encode(['success' => false, 'error' => 'Table non trouvée']));
}

$db = getDB();

// Vérifier que la table est en phase de mises
$stmt = $db->prepare("SELECT status FROM blackjack_tables WHERE id = ?");
$stmt->execute([$tableId]);
$table = $stmt->fetch();

if (!$table || $table['status'] !== 'betting') {
    die(json_encode(['success' => false, 'error' => 'La table n\'est pas en phase de mises']));
}

// Vérifier qu'il y a au moins 1 joueur
$stmt = $db->prepare("SELECT COUNT(*) as count FROM blackjack_players WHERE table_id = ? AND bet_amount > 0");
$stmt->execute([$tableId]);
$players = $stmt->fetch();

if ($players['count'] < 1) {
    header("Location: ../manage_table.php?id=$tableId&error=no_players");
    exit;
}

// Collecter les mises et les débiter des joueurs
$stmt = $db->prepare("
    SELECT bp.*, u.twitch_username 
    FROM blackjack_players bp 
    JOIN users u ON bp.user_id = u.id 
    WHERE bp.table_id = ? AND bp.bet_amount > 0
");
$stmt->execute([$tableId]);
$players = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($players as $player) {
    // Débiter le joueur via Wizebot
    $result = removeN0thyCoins($player['twitch_username'], $player['bet_amount'], 0);

    if (!$result || !($result['success'] ?? false)) {
        // Échec - rembourser si nécessaire et annuler
        header("Location: ../manage_table.php?id=$tableId&error=payment_failed&user=" . urlencode($player['twitch_username']));
        exit;
    }

    // Créditer le casino
    updateCasinoBalance($player['bet_amount'], $tableId, $player['user_id'], 'bet', "Mise BJ: {$player['bet_amount']}");

    // Mettre à jour le statut du joueur
    $stmt2 = $db->prepare("UPDATE blackjack_players SET status = 'waiting' WHERE id = ?");
    $stmt2->execute([$player['id']]);
}

// Passer la table en mode jeu
$stmt = $db->prepare("UPDATE blackjack_tables SET status = 'playing' WHERE id = ?");
$stmt->execute([$tableId]);

header("Location: ../manage_table.php?id=$tableId");
exit;
