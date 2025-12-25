<?php
require_once '../includes/init.php';

// Vérifier si l'utilisateur est connecté et admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('../pages/login.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

$user_id = intval($_POST['user_id'] ?? 0);

if ($user_id <= 0) {
    setFlashMessage('error', 'ID utilisateur invalide.');
    redirect('index.php');
}

try {
    $db = getDB();

    // Vérifier que l'utilisateur existe et est en attente
    $stmt = $db->prepare('SELECT twitch_username, status FROM users WHERE id = ?');
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        setFlashMessage('error', 'Utilisateur non trouvé.');
    } elseif ($user['status'] !== 'pending') {
        setFlashMessage('warning', 'Cet utilisateur a déjà été traité.');
    } else {
        // Supprimer l'utilisateur pour libérer l'email
        $stmt = $db->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$user_id]);

        setFlashMessage('success', 'L\'inscription de "' . $user['twitch_username'] . '" a été refusée et supprimée.');
    }
} catch (PDOException $e) {
    if (DEBUG_MODE) {
        setFlashMessage('error', 'Erreur: ' . $e->getMessage());
    } else {
        setFlashMessage('error', 'Une erreur est survenue.');
    }
}

redirect('index.php');
