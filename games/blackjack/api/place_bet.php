<?php
require_once '../../../includes/init.php';
require_once '../../../includes/blackjack_functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    die(json_encode(['success' => false, 'error' => 'Non connecté']));
}

$tableId = intval($_POST['table_id'] ?? 0);
$amount = intval($_POST['amount'] ?? 0);

if (!$tableId || $amount < 0) {
    die(json_encode(['success' => false, 'error' => 'Paramètres invalides']));
}

$db = getDB();
$userId = $_SESSION['user_id'];
$twitchUsername = $_SESSION['twitch_username'];

// Vérifier que la table existe et est en phase de mises
$stmt = $db->prepare("SELECT * FROM blackjack_tables WHERE id = ? AND status = 'betting'");
$stmt->execute([$tableId]);
$table = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$table) {
    die(json_encode(['success' => false, 'error' => 'Table non disponible']));
}

// Vérifier les limites de mise
if ($amount < $table['min_bet']) {
    die(json_encode(['success' => false, 'error' => 'Mise minimum: ' . $table['min_bet']]));
}
if ($amount > $table['max_bet']) {
    die(json_encode(['success' => false, 'error' => 'Mise maximum: ' . $table['max_bet']]));
}

// Vérifier que le joueur est à cette table
$stmt = $db->prepare("SELECT * FROM blackjack_players WHERE table_id = ? AND user_id = ?");
$stmt->execute([$tableId, $userId]);
$player = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$player) {
    die(json_encode(['success' => false, 'error' => 'Vous n\'êtes pas à cette table']));
}

// Vérifier le solde du joueur
$balanceData = getN0thyCoins($twitchUsername);
$balance = ($balanceData && isset($balanceData['currency'])) ? intval($balanceData['currency']) : 0;

if ($amount > $balance) {
    die(json_encode(['success' => false, 'error' => 'Solde insuffisant']));
}

// Enregistrer la mise (pas encore débitée - sera fait à la clôture des mises par l'admin)
$stmt = $db->prepare("UPDATE blackjack_players SET bet_amount = ? WHERE id = ?");
$stmt->execute([$amount, $player['id']]);

echo json_encode(['success' => true, 'bet' => $amount]);
