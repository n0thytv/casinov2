<?php
require_once '../includes/init.php';

// Rediriger les admins connectés
if (isLoggedIn() && isAdmin()) {
    redirect('../admin/');
}

$username = $_SESSION['pending_username'] ?? 'Utilisateur';
// Ne pas unset tout de suite pour qu'ils puissent rafraîchir la page si besoin
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>En Attente - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= time() ?>">
</head>

<body>

    <div class="auth-wrapper">
        <div class="auth-bg-anim">
            <!-- Background orb removed - was causing visual issues -->
        </div>

        <div class="auth-box card" style="text-align: center;">
            <div style="font-size: 4rem; margin-bottom: 20px;">⏳</div>

            <h1 style="font-family: 'Orbitron'; color: var(--warning); margin-bottom: 20px;">COMPTE EN ATTENTE</h1>

            <p style="color: var(--text-muted); margin-bottom: 15px;">
                Bienvenue <strong><?= htmlspecialchars($username) ?></strong> !
            </p>

            <p style="margin-bottom: 30px; line-height: 1.6;">
                Votre demande d'accès est en cours d'examen par nos modérateurs.<br>
                Vous pourrez vous connecter une fois l'accès validé.
            </p>

            <div id="status-message" style="color: var(--success); margin-bottom: 20px; display: none;">
                ✅ Compte approuvé ! Redirection en cours...
            </div>

            <a href="login.php" class="btn btn-outline">
                ← RETOUR À L'ACCUEIL
            </a>
        </div>
    </div>

    <!-- Auto-check for account approval -->
    <script>
        const username = '<?= htmlspecialchars($username) ?>';
        
        // Check every 3 seconds if account was approved
        setInterval(function() {
            fetch('api/check_status.php?username=' + encodeURIComponent(username))
                .then(r => r.json())
                .then(data => {
                    if (data.approved) {
                        document.getElementById('status-message').style.display = 'block';
                        setTimeout(() => {
                            window.location.href = 'login.php?approved=1';
                        }, 1500);
                    }
                })
                .catch(() => {});
        }, 3000);
    </script>

</body>

</html>