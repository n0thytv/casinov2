<?php
/**
 * N0THY CASINO - Get Table Status API
 * Returns the current game state for AJAX polling
 */
require_once '../../../includes/init.php';
require_once '../../../includes/blackjack_functions.php';

header('Content-Type: application/json');

$tableId = intval($_GET['table_id'] ?? 0);
if (!$tableId) {
    die(json_encode(['success' => false, 'error' => 'Table ID manquant']));
}

// Get table state (without hidden cards for players)
$table = getTableState($tableId, false);

if (!$table) {
    die(json_encode(['success' => false, 'error' => 'Table non trouvée']));
}

// Build game state for 3D rendering
echo json_encode([
    'success' => true,
    'status' => $table['status'],
    'dealer_cards_visible' => $table['dealer_cards_visible'] ?? [],
    'dealer_value' => $table['dealer_value'] ?? 0,
    'current_player_index' => $table['current_player_index'] ?? null,
    'players' => array_map(function ($p) {
        return [
            'id' => $p['id'],
            'user_id' => $p['user_id'],
            'twitch_username' => $p['twitch_username'],
            'seat_number' => $p['seat_number'],
            'bet_amount' => $p['bet_amount'],
            'cards' => $p['cards'],
            'status' => $p['status'],
            'hand_value' => $p['hand_value']
        ];
    }, $table['players'] ?? [])
]);
