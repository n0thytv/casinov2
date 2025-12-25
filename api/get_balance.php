<?php
require_once '../includes/init.php';

header('Content-Type: application/json');

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Non authentifié']);
    exit;
}

$twitch_username = $_SESSION['twitch_username'] ?? '';

if (empty($twitch_username)) {
    echo json_encode(['success' => false, 'error' => 'Pseudo Twitch non trouvé']);
    exit;
}

// Récupérer le solde via Wizebot
$result = getN0thyCoins($twitch_username);

if ($result !== null && isset($result['currency'])) {
    echo json_encode([
        'success' => true,
        'balance' => intval($result['currency']),
        'username' => $twitch_username
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Impossible de récupérer le solde',
        'balance' => 0
    ]);
}
