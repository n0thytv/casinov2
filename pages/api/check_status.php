<?php
/**
 * N0THY CASINO - Check Account Status API
 * Returns if a pending account has been approved
 */
require_once '../../includes/init.php';

header('Content-Type: application/json');

$username = $_GET['username'] ?? '';

if (empty($username)) {
    die(json_encode(['approved' => false, 'error' => 'Username required']));
}

$db = getDB();

// Check if user exists and is approved
$stmt = $db->prepare("SELECT status FROM users WHERE twitch_username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die(json_encode(['approved' => false, 'error' => 'User not found']));
}

// Check if status is 'active' (approved)
$approved = ($user['status'] === 'active');

echo json_encode(['approved' => $approved]);
