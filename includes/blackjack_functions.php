<?php
/**
 * N0THY CASINO - Blackjack Helper Functions
 */

// =============================================
// WIZEBOT API FUNCTIONS
// =============================================

/**
 * Ajouter des N0thyCoins à un utilisateur
 */
function addN0thyCoins($twitchUsername, $amount)
{
    $apiKey = WIZEBOT_API_KEY;
    $username = urlencode($twitchUsername);
    $url = "https://wapi.wizebot.tv/api/currency/{$apiKey}/action/add/{$username}/{$amount}";

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_SSL_VERIFYPEER => false, // Pour WAMP
    ]);

    $response = curl_exec($curl);
    $error = curl_error($curl);
    curl_close($curl);

    if ($error) {
        return ['success' => false, 'error' => $error];
    }

    return json_decode($response, true) ?? ['success' => false, 'error' => 'Invalid response'];
}

/**
 * Retirer des N0thyCoins d'un utilisateur
 * @param int $allowNegative 0 = ne pas permettre solde négatif, 1 = permettre
 */
function removeN0thyCoins($twitchUsername, $amount, $allowNegative = 0)
{
    $apiKey = WIZEBOT_API_KEY;
    $username = urlencode($twitchUsername);
    $url = "https://wapi.wizebot.tv/api/currency/{$apiKey}/action/remove/{$username}/{$amount}/{$allowNegative}";

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_SSL_VERIFYPEER => false,
    ]);

    $response = curl_exec($curl);
    $error = curl_error($curl);
    curl_close($curl);

    if ($error) {
        return ['success' => false, 'error' => $error];
    }

    return json_decode($response, true) ?? ['success' => false, 'error' => 'Invalid response'];
}

// =============================================
// CASINO BANK FUNCTIONS
// =============================================

/**
 * Obtenir le solde du casino
 */
function getCasinoBalance()
{
    $db = getDB();
    $stmt = $db->query("SELECT balance FROM casino_bank LIMIT 1");
    $row = $stmt->fetch();
    return $row ? intval($row['balance']) : 0;
}

/**
 * Modifier le solde du casino
 */
function updateCasinoBalance($amount, $tableId = null, $userId = null, $type = 'bet', $description = '')
{
    $db = getDB();

    // Mettre à jour le solde
    $db->exec("UPDATE casino_bank SET balance = balance + ($amount)");

    // Récupérer le nouveau solde
    $newBalance = getCasinoBalance();

    // Enregistrer la transaction
    $stmt = $db->prepare("INSERT INTO casino_transactions (table_id, user_id, type, amount, balance_after, description) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$tableId, $userId, $type, $amount, $newBalance, $description]);

    return $newBalance;
}

// =============================================
// BLACKJACK GAME FUNCTIONS
// =============================================

/**
 * Créer un deck de cartes standard
 */
function createDeck()
{
    $suits = ['hearts', 'diamonds', 'clubs', 'spades'];
    $values = ['2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K', 'A'];

    $deck = [];
    foreach ($suits as $suit) {
        foreach ($values as $value) {
            $deck[] = ['suit' => $suit, 'value' => $value];
        }
    }

    shuffle($deck);
    return $deck;
}

/**
 * Calculer la valeur d'une main
 */
function calculateHandValue($cards)
{
    if (!$cards || !is_array($cards))
        return 0;

    $value = 0;
    $aces = 0;

    foreach ($cards as $card) {
        $cardValue = $card['value'];
        if (in_array($cardValue, ['J', 'Q', 'K'])) {
            $value += 10;
        } elseif ($cardValue === 'A') {
            $value += 11;
            $aces++;
        } else {
            $value += intval($cardValue);
        }
    }

    // Ajuster les As si on dépasse 21
    while ($value > 21 && $aces > 0) {
        $value -= 10;
        $aces--;
    }

    return $value;
}

/**
 * Vérifier si c'est un Blackjack (21 avec 2 cartes)
 */
function isBlackjack($cards)
{
    return count($cards) === 2 && calculateHandValue($cards) === 21;
}

/**
 * Vérifier si le joueur peut splitter (2 cartes de même valeur)
 */
function canSplit($cards)
{
    if (count($cards) !== 2)
        return false;

    $val1 = $cards[0]['value'];
    $val2 = $cards[1]['value'];

    // Les figures (J, Q, K) comptent comme 10, donc peuvent être splittées ensemble
    $getNumericValue = function ($v) {
        if (in_array($v, ['J', 'Q', 'K']))
            return 10;
        if ($v === 'A')
            return 11;
        return intval($v);
    };

    return $getNumericValue($val1) === $getNumericValue($val2);
}

/**
 * Obtenir l'état complet d'une table
 */
function getTableState($tableId, $includeHiddenCards = false)
{
    $db = getDB();

    // Récupérer la table
    $stmt = $db->prepare("SELECT * FROM blackjack_tables WHERE id = ?");
    $stmt->execute([$tableId]);
    $table = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$table)
        return null;

    // Décoder les cartes du croupier
    $table['dealer_cards'] = json_decode($table['dealer_cards'], true) ?? [];

    // En mode streameur et pas d'accès aux cartes cachées
    if ($table['streamer_mode'] && !$includeHiddenCards && count($table['dealer_cards']) > 1) {
        // Cacher toutes les cartes sauf la première
        $visibleCards = [$table['dealer_cards'][0]];
        for ($i = 1; $i < count($table['dealer_cards']); $i++) {
            $visibleCards[] = ['suit' => 'hidden', 'value' => 'hidden'];
        }
        $table['dealer_cards_visible'] = $visibleCards;
    } else {
        $table['dealer_cards_visible'] = $table['dealer_cards'];
    }

    $table['dealer_value'] = calculateHandValue($table['dealer_cards']);

    // Récupérer les joueurs
    $stmt = $db->prepare("
        SELECT bp.*, u.twitch_username 
        FROM blackjack_players bp 
        JOIN users u ON bp.user_id = u.id 
        WHERE bp.table_id = ? 
        ORDER BY bp.seat_number
    ");
    $stmt->execute([$tableId]);
    $players = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($players as &$player) {
        $player['cards'] = json_decode($player['cards'], true) ?? [];
        $player['hand_value'] = calculateHandValue($player['cards']);
    }

    $table['players'] = $players;
    $table['casino_balance'] = getCasinoBalance();

    return $table;
}

/**
 * Résoudre la fin d'une manche
 */
function resolveRound($tableId)
{
    $db = getDB();
    $table = getTableState($tableId, true);

    if (!$table)
        return false;

    $dealerValue = $table['dealer_value'];
    $dealerBust = $dealerValue > 21;

    foreach ($table['players'] as $player) {
        if ($player['status'] === 'bust') {
            // Déjà traité - joueur a perdu
            continue;
        }

        $playerValue = $player['hand_value'];
        $betAmount = $player['bet_amount'];
        $twitchUsername = $player['twitch_username'];
        $userId = $player['user_id'];

        $newStatus = 'lose';
        $winAmount = 0;

        if ($player['status'] === 'blackjack') {
            // Blackjack paie 3:2
            $winAmount = intval($betAmount * 2.5);
            $newStatus = 'blackjack';
        } elseif ($dealerBust || $playerValue > $dealerValue) {
            // Joueur gagne
            $winAmount = $betAmount * 2;
            $newStatus = 'win';
        } elseif ($playerValue === $dealerValue) {
            // Égalité (push) - remboursement
            $winAmount = $betAmount;
            $newStatus = 'push';
        }
        // Sinon lose - pas de gain

        // Payer le joueur
        if ($winAmount > 0) {
            addN0thyCoins($twitchUsername, $winAmount);
            updateCasinoBalance(-$winAmount, $tableId, $userId, $newStatus, "Gain BJ: $winAmount");
        }

        // Mettre à jour le statut du joueur
        $stmt = $db->prepare("UPDATE blackjack_players SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $player['id']]);
    }

    // Marquer la table comme terminée
    $stmt = $db->prepare("UPDATE blackjack_tables SET status = 'finished', closed_at = NOW() WHERE id = ?");
    $stmt->execute([$tableId]);

    return true;
}
