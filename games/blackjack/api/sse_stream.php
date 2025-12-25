<?php
/**
 * N0THY CASINO - Blackjack SSE (Server-Sent Events) Endpoint
 * Streams real-time game state updates to clients
 */

require_once '../../../includes/init.php';
require_once '../../../includes/blackjack_functions.php';

// Disable output buffering
if (ob_get_level())
    ob_end_clean();

// SSE Headers
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // For nginx

// Get table ID
$tableId = intval($_GET['table_id'] ?? 0);
if (!$tableId) {
    echo "event: error\n";
    echo "data: " . json_encode(['error' => 'Table ID required']) . "\n\n";
    exit;
}

// Keep track of last state to only send updates
$lastStateHash = '';

// Stream updates
while (true) {
    // Check if client disconnected
    if (connection_aborted()) {
        exit;
    }

    try {
        // Get current table state (without hidden cards for players)
        $table = getTableState($tableId, false);

        if (!$table) {
            echo "event: error\n";
            echo "data: " . json_encode(['error' => 'Table not found']) . "\n\n";
            flush();
            sleep(5);
            continue;
        }

        // Build game state
        $gameState = [
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
        ];

        // Create hash to detect changes
        $stateHash = md5(json_encode($gameState));

        // Only send if state changed
        if ($stateHash !== $lastStateHash) {
            echo "event: gamestate\n";
            echo "data: " . json_encode($gameState) . "\n\n";
            $lastStateHash = $stateHash;
        } else {
            // Send heartbeat to keep connection alive
            echo "event: heartbeat\n";
            echo "data: " . json_encode(['time' => time()]) . "\n\n";
        }

        flush();

    } catch (Exception $e) {
        echo "event: error\n";
        echo "data: " . json_encode(['error' => $e->getMessage()]) . "\n\n";
        flush();
    }

    // Wait before next check (2 seconds for balance of responsiveness and performance)
    sleep(2);
}
