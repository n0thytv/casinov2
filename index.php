<?php
/**
 * N0thy Casino - Page d'accueil
 * Redirige vers le dashboard ou la connexion
 */
require_once 'includes/init.php';

if (isLoggedIn()) {
    if (isAdmin()) {
        redirect('admin/');
    } else {
        redirect('dashboard.php');
    }
} else {
    redirect('pages/login.php');
}
