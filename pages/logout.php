<?php
require_once '../includes/init.php';

// Détruire la session
$_SESSION = [];
session_destroy();

// Rediriger vers la page de connexion
redirect('login.php');
