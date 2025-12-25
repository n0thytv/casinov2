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

// Résoudre la manche et payer les joueurs
$result = resolveRound($tableId);

if ($result) {
    header("Location: ../manage_table.php?id=$tableId&success=round_ended");
} else {
    header("Location: ../manage_table.php?id=$tableId&error=end_failed");
}
exit;
