<?php
/**
 * N0thy Casino - Configuration
 * Casino virtuel pour Twitch avec intégration Wizebot
 */

// Mode debug (à désactiver en production)
define('DEBUG_MODE', true);

// Configuration de la base de données (WAMP)
define('DB_HOST', 'localhost');
define('DB_NAME', 'n0thy_casino');
define('DB_USER', 'root');
define('DB_PASS', ''); // WAMP utilise un mot de passe vide par défaut

// Clé API Wizebot - À REMPLACER PAR VOTRE CLÉ
define('WIZEBOT_API_KEY', '1ea3a56caf523a02dd210af19f7cda13389b75280fe1ab321a5af0fdb0df7c3a');

// URL de base de l'API Wizebot
define('WIZEBOT_API_URL', 'https://wapi.wizebot.tv/api/currency/' . WIZEBOT_API_KEY . '/get/');

// Configuration du site
define('SITE_NAME', 'N0thy Casino');
define('SITE_URL', 'http://localhost/casinov2');

// Configuration des sessions
define('SESSION_LIFETIME', 86400); // 24 heures

// Compte administrateur par défaut
define('ADMIN_EMAIL', 'admin@n0thy.tv');
define('ADMIN_PASSWORD', 'admin123'); // À CHANGER
