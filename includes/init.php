<?php
/**
 * N0thy Casino - Initialisation
 * Fichier principal à inclure dans toutes les pages
 */

// Démarrage de la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Chargement de la configuration et des fonctions
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
