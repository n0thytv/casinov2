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
 * Tirer une carte unique (non déjà distribuée) pour une table
 * @param int $tableId ID de la table
 * @return array La carte tirée
 */
function drawCardFromTable($tableId)
{
    $table = getTableState($tableId, true);
    if (!$table) {
        return createDeck()[0]; // Fallback
    }

    // Collecter toutes les cartes déjà en jeu
    $usedCards = [];

    // Cartes du croupier
    foreach ($table['dealer_cards'] ?? [] as $card) {
        if ($card['suit'] !== 'hidden') {
            $usedCards[] = $card['suit'] . '-' . $card['value'];
        }
    }

    // Cartes des joueurs
    foreach ($table['players'] ?? [] as $player) {
        foreach ($player['cards'] ?? [] as $card) {
            $usedCards[] = $card['suit'] . '-' . $card['value'];
        }
    }

    // Créer un deck et exclure les cartes utilisées
    $fullDeck = createDeck();
    $availableCards = [];

    foreach ($fullDeck as $card) {
        $cardKey = $card['suit'] . '-' . $card['value'];
        if (!in_array($cardKey, $usedCards)) {
            $availableCards[] = $card;
        }
    }

    // Si le deck est vide (improbable), refaire un shuffle complet
    if (empty($availableCards)) {
        return createDeck()[0];
    }

    // Retourner une carte aléatoire parmi les disponibles
    return $availableCards[array_rand($availableCards)];
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

    // Décoder les cartes du croupier (gérer le cas null)
    $dealerCardsJson = $table['dealer_cards'] ?? '[]';
    $table['dealer_cards'] = json_decode($dealerCardsJson, true) ?? [];

    // En mode streameur et pas d'accès aux cartes cachées
    // SAUF si tous les joueurs ont terminé (stand, bust, blackjack) - alors révéler les cartes
    $allPlayersFinished = false; // Par défaut, cacher les cartes

    if ($table['status'] === 'playing') {
        // Vérifier si tous les joueurs ont terminé (à partir des données de la DB directement)
        $stmtCheck = $db->prepare("
            SELECT COUNT(*) as count 
            FROM blackjack_players 
            WHERE table_id = ? AND status IN ('playing', 'betting', 'waiting')
        ");
        $stmtCheck->execute([$tableId]);
        $checkResult = $stmtCheck->fetch();
        $allPlayersFinished = ($checkResult['count'] == 0);
    } else if ($table['status'] === 'finished' || $table['status'] === 'completed') {
        $allPlayersFinished = true; // Partie terminée, révéler
    } else if ($table['status'] === 'betting') {
        $allPlayersFinished = false; // Mises en cours, cacher
    }

    // Cacher les cartes seulement si en mode streameur, pas d'accès admin, ET joueurs pas encore terminés
    if ($table['streamer_mode'] && !$includeHiddenCards && count($table['dealer_cards']) > 1 && !$allPlayersFinished) {
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
        $cardsJson = $player['cards'] ?? '[]';
        $player['cards'] = json_decode($cardsJson, true) ?? [];
        $player['hand_value'] = calculateHandValue($player['cards']);

        // Décoder les cartes splittées si elles existent
        if ($player['has_split']) {
            $splitCardsJson = $player['split_cards'] ?? '[]';
            $player['split_cards'] = json_decode($splitCardsJson, true) ?? [];
        }
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
        $twitchUsername = $player['twitch_username'];
        $userId = $player['user_id'];
        $betAmount = $player['bet_amount'];
        $totalWinAmount = 0;

        // Si le joueur a splitté, traiter les deux mains séparément
        if ($player['has_split']) {
            // Traiter la main principale (peut être déjà un array si venant de getTableState)
            $mainCards = is_array($player['cards']) ? $player['cards'] : (json_decode($player['cards'], true) ?? []);
            $mainValue = calculateHandValue($mainCards);
            $mainBust = $mainValue > 21;

            $mainWinAmount = 0;
            if (!$mainBust) {
                if (isBlackjack($mainCards)) {
                    $mainWinAmount = intval($betAmount * 2.5); // Blackjack paie 3:2
                } elseif ($dealerBust || $mainValue > $dealerValue) {
                    $mainWinAmount = $betAmount * 2;
                } elseif ($mainValue === $dealerValue) {
                    $mainWinAmount = $betAmount; // Push
                }
            }

            // Traiter la main splittée (peut être déjà un array si venant de getTableState)
            $splitCards = is_array($player['split_cards']) ? $player['split_cards'] : (json_decode($player['split_cards'], true) ?? []);
            $splitValue = calculateHandValue($splitCards);
            $splitBust = $splitValue > 21;

            $splitWinAmount = 0;
            if (!$splitBust) {
                if (isBlackjack($splitCards)) {
                    $splitWinAmount = intval($betAmount * 2.5); // Blackjack paie 3:2
                } elseif ($dealerBust || $splitValue > $dealerValue) {
                    $splitWinAmount = $betAmount * 2;
                } elseif ($splitValue === $dealerValue) {
                    $splitWinAmount = $betAmount; // Push
                }
            }

            $totalWinAmount = $mainWinAmount + $splitWinAmount;

            // Déterminer le statut global
            if ($mainBust && $splitBust) {
                $newStatus = 'lose';
            } elseif ($mainWinAmount > $betAmount || $splitWinAmount > $betAmount) {
                $newStatus = 'win';
            } elseif ($totalWinAmount > 0) {
                $newStatus = 'push';
            } else {
                $newStatus = 'lose';
            }

            // Payer le joueur
            if ($totalWinAmount > 0) {
                addN0thyCoins($twitchUsername, $totalWinAmount);
                updateCasinoBalance(-$totalWinAmount, $tableId, $userId, $newStatus, "Gain BJ Split: $totalWinAmount");
            }

        } else {
            // Pas de split - comportement normal
            if ($player['status'] === 'bust') {
                // Déjà traité - joueur a perdu
                $newStatus = 'lose';
            } else {
                $playerValue = $player['hand_value'];
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

                // Payer le joueur
                if ($winAmount > 0) {
                    addN0thyCoins($twitchUsername, $winAmount);
                    updateCasinoBalance(-$winAmount, $tableId, $userId, $newStatus, "Gain BJ: $winAmount");
                }
            }
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

/**
 * Vérifier si tous les joueurs ont terminé (stand ou bust)
 */
function isAllPlayersFinished($tableId)
{
    $db = getDB();

    // Compter les joueurs qui n'ont pas encore terminé
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM blackjack_players 
        WHERE table_id = ? AND status IN ('playing', 'betting', 'waiting')
    ");
    $stmt->execute([$tableId]);
    $result = $stmt->fetch();

    return $result['count'] == 0;
}

/**
 * Vérifier et clore automatiquement la manche si tous les joueurs ont terminé
 */
function checkAndAutoCloseRound($tableId)
{
    if (!isAllPlayersFinished($tableId)) {
        return false;
    }

    $db = getDB();
    $table = getTableState($tableId, true);

    if (!$table || $table['status'] !== 'playing') {
        return false;
    }

    // Vérifier si tous les joueurs ont bust - dans ce cas, le dealer gagne automatically
    $allPlayersBusted = true;
    foreach ($table['players'] as $player) {
        if ($player['status'] !== 'bust') {
            $allPlayersBusted = false;
            break;
        }
    }

    // Si tous les joueurs ont bust, résoudre immédiatement (dealer gagne)
    if ($allPlayersBusted) {
        return resolveRound($tableId);
    }

    // Sinon, vérifier si le croupier a au moins 17 (condition pour terminer)
    $dealerValue = $table['dealer_value'];
    if ($dealerValue < 17) {
        // Le croupier doit encore tirer
        return false;
    }

    // Résoudre automatiquement la manche
    return resolveRound($tableId);
}

/**
 * Vérifier si la distribution initiale est terminée
 */
function isDealingComplete($tableId)
{
    $table = getTableState($tableId, true);
    if (!$table)
        return false;

    $playerCount = count($table['players']);
    if ($playerCount == 0)
        return false;

    $totalCards = 0;
    foreach ($table['players'] as $p) {
        $totalCards += count($p['cards']);
    }
    $totalCards += count($table['dealer_cards']);
    $expectedCards = ($playerCount * 2) + 2;

    return $totalCards >= $expectedCards;
}
