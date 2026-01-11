<?php
/**
 * API Admin: Exécuter l'action demandée par un joueur
 * Le croupier approuve et distribue les cartes si nécessaire
 */
require_once '../../../includes/init.php';
require_once '../../../includes/blackjack_functions.php';

if (!isLoggedIn() || !isAdmin()) {
    header("Location: ../../pages/login.php");
    exit;
}

$tableId = intval($_POST['table_id'] ?? 0);
$playerId = intval($_POST['player_id'] ?? 0);

if (!$tableId || !$playerId) {
    header("Location: ../manage_table.php?id=$tableId&error=missing_params");
    exit;
}

$db = getDB();

// Récupérer le joueur
$stmt = $db->prepare("
    SELECT bp.*, u.twitch_username 
    FROM blackjack_players bp 
    JOIN users u ON bp.user_id = u.id 
    WHERE bp.id = ? AND bp.table_id = ?
");
$stmt->execute([$playerId, $tableId]);
$player = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$player) {
    header("Location: ../manage_table.php?id=$tableId&error=player_not_found");
    exit;
}

$action = $player['pending_action'];
if (!$action) {
    header("Location: ../manage_table.php?id=$tableId&error=no_pending_action");
    exit;
}

$cardsJson = $player['cards'] ?? '[]';
$cards = json_decode($cardsJson, true) ?? [];
$userId = $player['user_id'];
$twitchUsername = $player['twitch_username'];

switch ($action) {
    case 'hit':
        // Distribuer une carte
        $newCard = drawCardFromTable($tableId);

        // Si le joueur a splitté, déterminer quelle main doit recevoir la carte
        if ($player['has_split']) {
            $currentHand = $player['current_hand'] ?? 'main';

            if ($currentHand === 'main') {
                // Tirer sur la main principale
                $cards[] = $newCard;
                $handValue = calculateHandValue($cards);

                // Si bust ou 21 sur main principale, passer à la main splittée
                if ($handValue >= 21) {
                    $splitCards = json_decode($player['split_cards'], true) ?? [];
                    $stmt = $db->prepare("UPDATE blackjack_players SET cards = ?, current_hand = 'split', status = 'playing', pending_action = NULL WHERE id = ?");
                    $stmt->execute([json_encode($cards), $playerId]);
                } else {
                    $stmt = $db->prepare("UPDATE blackjack_players SET cards = ?, status = 'playing', pending_action = NULL WHERE id = ?");
                    $stmt->execute([json_encode($cards), $playerId]);
                }
            } else {
                // Tirer sur la main splittée
                $splitCards = json_decode($player['split_cards'], true) ?? [];
                $splitCards[] = $newCard;
                $handValue = calculateHandValue($splitCards);
                $status = $handValue > 21 ? 'bust' : ($handValue === 21 ? 'stand' : 'playing');

                $stmt = $db->prepare("UPDATE blackjack_players SET split_cards = ?, status = ?, pending_action = NULL WHERE id = ?");
                $stmt->execute([json_encode($splitCards), $status, $playerId]);
            }
        } else {
            // Pas de split, comportement normal
            $cards[] = $newCard;
            $handValue = calculateHandValue($cards);
            $status = $handValue > 21 ? 'bust' : 'playing';

            $stmt = $db->prepare("UPDATE blackjack_players SET cards = ?, status = ?, pending_action = NULL WHERE id = ?");
            $stmt->execute([json_encode($cards), $status, $playerId]);
        }
        break;

    case 'stand':
        // Si le joueur a splitté et est sur la main principale, passer à la main splittée
        if ($player['has_split'] && $player['current_hand'] === 'main') {
            // Passer à la main splittée - GARDER le statut 'playing'
            $stmt = $db->prepare("UPDATE blackjack_players SET current_hand = 'split', status = 'playing', pending_action = NULL WHERE id = ?");
            $stmt->execute([$playerId]);
        } else {
            // Sinon, stand normal
            $stmt = $db->prepare("UPDATE blackjack_players SET status = 'stand', pending_action = NULL WHERE id = ?");
            $stmt->execute([$playerId]);
        }
        break;

    case 'double':
        $newBet = $player['bet_amount'] * 2;

        // Débiter le joueur
        $result = removeN0thyCoins($twitchUsername, $player['bet_amount'], 0);
        if (!$result || !($result['success'] ?? false)) {
            // Annuler l'action si pas assez de fonds
            $stmt = $db->prepare("UPDATE blackjack_players SET pending_action = NULL WHERE id = ?");
            $stmt->execute([$playerId]);
            header("Location: ../manage_table.php?id=$tableId&error=insufficient_funds");
            exit;
        }

        // Créditer le casino
        updateCasinoBalance($player['bet_amount'], $tableId, $userId, 'double', "Double: {$player['bet_amount']}");

        // Tirer une carte et stand automatiquement
        $newCard = drawCardFromTable($tableId);
        $cards[] = $newCard;
        $handValue = calculateHandValue($cards);
        $status = $handValue > 21 ? 'bust' : 'stand';

        $stmt = $db->prepare("UPDATE blackjack_players SET bet_amount = ?, cards = ?, status = ?, doubled = TRUE, pending_action = NULL WHERE id = ?");
        $stmt->execute([$newBet, json_encode($cards), $status, $playerId]);
        break;

    case 'split':
        // Débiter pour la 2ème main
        $result = removeN0thyCoins($twitchUsername, $player['bet_amount'], 0);
        if (!$result || !($result['success'] ?? false)) {
            $stmt = $db->prepare("UPDATE blackjack_players SET pending_action = NULL WHERE id = ?");
            $stmt->execute([$playerId]);
            header("Location: ../manage_table.php?id=$tableId&error=insufficient_funds");
            exit;
        }

        updateCasinoBalance($player['bet_amount'], $tableId, $userId, 'bet', "Split: {$player['bet_amount']}");

        // Séparer les cartes et distribuer une nouvelle carte sur chaque main
        $mainHand = [$cards[0]];
        $splitHand = [$cards[1]];

        // Distribuer une carte sur la main principale
        $newCardMain = drawCardFromTable($tableId);
        $mainHand[] = $newCardMain;

        // Distribuer une carte sur la main splittée
        $newCardSplit = drawCardFromTable($tableId);
        $splitHand[] = $newCardSplit;

        // Vérifier si blackjack sur la main principale
        $mainHandValue = calculateHandValue($mainHand);
        $isMainBlackjack = isBlackjack($mainHand);

        // Si la main principale a 21 (blackjack), passer directement à la main splittée
        if ($isMainBlackjack || $mainHandValue >= 21) {
            $currentHand = 'split';
            $newStatus = 'playing'; // Continuer sur la main splittée
        } else {
            $currentHand = 'main';
            $newStatus = 'playing'; // Jouer la main principale
        }

        $stmt = $db->prepare("UPDATE blackjack_players SET cards = ?, split_cards = ?, has_split = TRUE, current_hand = ?, status = ?, pending_action = NULL WHERE id = ?");
        $stmt->execute([json_encode($mainHand), json_encode($splitHand), $currentHand, $newStatus, $playerId]);
        break;
}

// Vérifier si tous les joueurs ont terminé et clore automatiquement
checkAndAutoCloseRound($tableId);

header("Location: ../manage_table.php?id=$tableId");
exit;
