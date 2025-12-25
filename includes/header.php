<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= defined('SITE_NAME') ? SITE_NAME : 'N0thy Casino' ?></title>

    <!-- CSS et Fonts -->
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css?v=<?= time() ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
</head>

<body>

    <?php if (isLoggedIn()): ?>
        <!-- Navigation Principale (pour utilisateurs connectés) -->
        <header class="main-header">
            <div class="container header-inner">
                <a href="<?= SITE_URL ?>/dashboard.php" class="logo-container">
                    <span class="logo-text">N0THY CASINO</span>
                </a>

                <nav class="nav-links">
                    <!-- Liens de navigation futurs -->
                </nav>

                <div class="nav-user">
                    <?php if (isAdmin()): ?>
                        <a href="<?= SITE_URL ?>/admin/" class="btn btn-outline btn-sm" style="font-size: 0.7rem;">ADMIN
                            PANEL</a>
                    <?php endif; ?>

                    <div class="user-badge">
                        <div class="user-avatar">
                            <?= strtoupper(substr($_SESSION['twitch_username'] ?? 'U', 0, 1)) ?>
                        </div>
                        <span><?= htmlspecialchars($_SESSION['twitch_username'] ?? 'User') ?></span>
                    </div>

                    <a href="<?= SITE_URL ?>/pages/logout.php" class="btn btn-logout" title="Déconnexion">
                        <span style="font-size: 1.2rem; vertical-align: middle;">⏻</span>
                    </a>
                </div>
            </div>
        </header>
    <?php endif; ?>