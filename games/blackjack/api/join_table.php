<?php
require_once '../../../includes/init.php';
require_once '../../../includes/blackjack_functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    die(json_encode(['success' => false, 'error' => 'Non connecté']));
}

$tableId = intval($_POST['table_id'] ?? 0);
$seatNumber = intval($_POST['seat_number'] ?? 0);

if (!$tableId || !$seatNumber || $seatNumber < 1 || $seatNumber > 5) {
    die(json_encode(['success' => false, 'error' => 'Paramètres invalides']));
}

$db = getDB();
$userId = $_SESSION['user_id'];

// Vérifier que la table existe et est en phase de mises
$stmt = $db->prepare("SELECT * FROM blackjack_tables WHERE id = ? AND status = 'betting'");
$stmt->execute([$tableId]);
$table = $stmt->fetch();

if (!$table) {
    die(json_encode(['success' => false, 'error' => 'Table non disponible']));
}

// Vérifier que le joueur n'est pas déjà à une table
$stmt = $db->prepare("
    SELECT bp.id FROM blackjack_players bp
    JOIN blackjack_tables bt ON bp.table_id = bt.id
    WHERE bp.user_id = ? AND bt.status IN ('betting', 'playing')
");
$stmt->execute([$userId]);
if ($stmt->fetch()) {
    die(json_encode(['success' => false, 'error' => 'Vous êtes déjà à une table']));
}

// Vérifier que le siège est libre
$stmt = $db->prepare("SELECT id FROM blackjack_players WHERE table_id = ? AND seat_number = ?");
$stmt->execute([$tableId, $seatNumber]);
if ($stmt->fetch()) {
    die(json_encode(['success' => false, 'error' => 'Ce siège est déjà pris']));
}

// Rejoindre la table
$stmt = $db->prepare("INSERT INTO blackjack_players (table_id, user_id, seat_number, status) VALUES (?, ?, ?, 'betting')");
$stmt->execute([$tableId, $userId, $seatNumber]);

header("Location: ../index.php?table=$tableId");
exit;
