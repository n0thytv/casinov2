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

// Toggle streamer mode
$stmt = $db->prepare("UPDATE blackjack_tables SET streamer_mode = NOT streamer_mode WHERE id = ?");
$stmt->execute([$tableId]);

header("Location: ../manage_table.php?id=$tableId");
exit;
