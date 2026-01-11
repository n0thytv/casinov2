<?php
/**
 * API Admin: Forcer le statut d'un joueur splité à 'playing' 
 * pour débloquer une main splittée coincée
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
$stmt = $db->prepare("SELECT * FROM blackjack_players WHERE id = ? AND table_id = ?");
$stmt->execute([$playerId, $tableId]);
$player = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$player) {
    header("Location: ../manage_table.php?id=$tableId&error=player_not_found");
    exit;
}

// Vérifier que le joueur a bien splitté
if (!$player['has_split']) {
    header("Location: ../manage_table.php?id=$tableId&error=no_split");
    exit;
}

// Vérifier si la main splittée n'a qu'une seule carte (bug de l'ancien code)
$splitCards = json_decode($player['split_cards'], true) ?? [];
if (count($splitCards) === 1) {
    // Distribuer une carte supplémentaire
    $newCard = drawCardFromTable($tableId);
    $splitCards[] = $newCard;

    // Mettre à jour les split_cards
    $stmt = $db->prepare("UPDATE blackjack_players SET split_cards = ? WHERE id = ?");
    $stmt->execute([json_encode($splitCards), $playerId]);
}

// Forcer le statut à 'playing' et s'assurer que current_hand est 'split'
$stmt = $db->prepare("UPDATE blackjack_players SET status = 'playing', current_hand = 'split', pending_action = NULL WHERE id = ?");
$stmt->execute([$playerId]);

header("Location: ../manage_table.php?id=$tableId&success=split_unlocked");
exit;
