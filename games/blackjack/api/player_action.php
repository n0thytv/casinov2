<?php
/**
 * API pour les actions des joueurs (Hit, Stand, Double, Split)
 * LES ACTIONS NE S'EXÉCUTENT PAS - elles créent une demande que le croupier doit approuver
 */
require_once '../../../includes/init.php';
require_once '../../../includes/blackjack_functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    die(json_encode(['success' => false, 'error' => 'Non connecté']));
}

$tableId = intval($_POST['table_id'] ?? 0);
$action = $_POST['action'] ?? '';

if (!$tableId || !$action) {
    die(json_encode(['success' => false, 'error' => 'Paramètres invalides']));
}

// Valider l'action
if (!in_array($action, ['hit', 'stand', 'double', 'split'])) {
    die(json_encode(['success' => false, 'error' => 'Action invalide']));
}

$db = getDB();
$userId = $_SESSION['user_id'];
$twitchUsername = $_SESSION['twitch_username'];

// Récupérer l'état de la table
$table = getTableState($tableId, false);
if (!$table) {
    die(json_encode(['success' => false, 'error' => 'Table non trouvée']));
}

if ($table['status'] !== 'playing') {
    die(json_encode(['success' => false, 'error' => 'La partie n\'est pas en cours']));
}

// Vérifier si la distribution initiale est terminée
$playerCount = count($table['players']);
$totalCards = 0;
foreach ($table['players'] as $p) {
    $totalCards += count($p['cards']);
}
$totalCards += count($table['dealer_cards']);
$expectedCards = ($playerCount * 2) + 2;

if ($totalCards < $expectedCards) {
    die(json_encode(['success' => false, 'error' => 'La distribution n\'est pas encore terminée']));
}

// Récupérer le joueur
$stmt = $db->prepare("
    SELECT bp.*, u.twitch_username 
    FROM blackjack_players bp 
    JOIN users u ON bp.user_id = u.id 
    WHERE bp.table_id = ? AND bp.user_id = ?
");
$stmt->execute([$tableId, $userId]);
$player = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$player) {
    die(json_encode(['success' => false, 'error' => 'Vous n\'êtes pas à cette table']));
}

if ($player['status'] !== 'playing') {
    die(json_encode(['success' => false, 'error' => 'Ce n\'est pas votre tour ou vous avez déjà terminé']));
}

// Vérifier si le joueur a déjà une action en attente
if (!empty($player['pending_action'])) {
    die(json_encode(['success' => false, 'error' => 'Une action est déjà en attente']));
}

$cardsJson = $player['cards'] ?? '[]';
$cards = json_decode($cardsJson, true) ?? [];
$cardCount = count($cards);
$handValue = calculateHandValue($cards);

// Valider les conditions spécifiques pour chaque action
switch ($action) {
    case 'hit':
        if ($handValue >= 21) {
            die(json_encode(['success' => false, 'error' => 'Vous ne pouvez plus tirer de carte']));
        }
        break;

    case 'double':
        if ($cardCount !== 2) {
            die(json_encode(['success' => false, 'error' => 'Double uniquement possible avec 2 cartes']));
        }
        if ($player['doubled']) {
            die(json_encode(['success' => false, 'error' => 'Vous avez déjà doublé']));
        }
        break;

    case 'split':
        if (!canSplit($cards)) {
            die(json_encode(['success' => false, 'error' => 'Split impossible']));
        }
        if ($player['has_split']) {
            die(json_encode(['success' => false, 'error' => 'Vous avez déjà splitté']));
        }
        break;
}

// Enregistrer l'action en attente
$stmt = $db->prepare("UPDATE blackjack_players SET pending_action = ? WHERE id = ?");
$stmt->execute([$action, $player['id']]);

$actionLabels = [
    'hit' => '🃏 Demande une carte',
    'stand' => '✋ Reste',
    'double' => '💰 Double',
    'split' => '✌️ Split'
];

echo json_encode([
    'success' => true,
    'message' => $actionLabels[$action],
    'pending' => true,
    'action' => $action
]);
