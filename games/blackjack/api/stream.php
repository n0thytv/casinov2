<?php
/**
 * Server-Sent Events (SSE) Stream for Blackjack Table
 * Provides real-time updates to connected clients
 */

require_once '../../../includes/init.php';
require_once '../../../includes/blackjack_functions.php';

// Configuration SSE
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Pour Nginx

// Désactiver le buffering PHP
if (ob_get_level())
    ob_end_clean();

$tableId = intval($_GET['table_id'] ?? 0);
if (!$tableId) {
    echo "event: error\n";
    echo "data: {\"error\": \"Table ID manquant\"}\n\n";
    exit;
}

// Vérifier la connexion (optionnel pour observers)
$isAdmin = isLoggedIn() && isAdmin();
$includeHiddenCards = $isAdmin;

// Heartbeat pour maintenir la connexion
$lastUpdate = '';
$timeout = 30; // Secondes avant déconnexion
$startTime = time();

while (true) {
    // Vérifier le timeout
    if (time() - $startTime > $timeout) {
        echo "event: timeout\n";
        echo "data: {\"message\": \"Connexion expirée\"}\n\n";
        break;
    }

    // Récupérer l'état de la table
    $table = getTableState($tableId, $includeHiddenCards);

    if (!$table) {
        echo "event: error\n";
        echo "data: {\"error\": \"Table non trouvée\"}\n\n";
        break;
    }

    // Créer un hash pour détecter les changements
    $tableJson = json_encode($table);
    $currentHash = md5($tableJson);

    // Envoyer seulement si changement
    if ($currentHash !== $lastUpdate) {
        $lastUpdate = $currentHash;

        echo "event: update\n";
        echo "data: " . $tableJson . "\n\n";
    } else {
        // Heartbeat
        echo ": heartbeat\n\n";
    }

    // Flush le buffer
    if (ob_get_level())
        ob_flush();
    flush();

    // Pause avant prochaine vérification
    sleep(1);

    // Vérifier si le client est toujours connecté
    if (connection_aborted()) {
        break;
    }
}
