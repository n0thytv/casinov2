<?php
/**
 * N0THY CASINO - Get Available Tables API
 * Returns the count of available tables for lobby auto-refresh
 */
require_once '../../../includes/init.php';

header('Content-Type: application/json');

$db = getDB();

// Count available tables (betting or playing)
$stmt = $db->query("
    SELECT COUNT(*) as count 
    FROM blackjack_tables 
    WHERE status IN ('betting', 'playing')
");
$result = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'table_count' => intval($result['count'])
]);
