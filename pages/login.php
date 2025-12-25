<?php
require_once '../includes/init.php';

// Rediriger si déjà connecté
if (isLoggedIn()) {
    redirect('../dashboard.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ... Logique de traitement inchangée ...
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Veuillez remplir tous les champs.';
    } else {
        try {
            $db = getDB();
            $stmt = $db->prepare('SELECT id, email, password_hash, twitch_username, status, role FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password_hash'])) {
                $error = 'Identifiants incorrects.';
            } elseif ($user['status'] !== 'approved') {
                if ($user['status'] === 'pending')
                    $error = 'Compte en attente d\'approbation.';
                else
                    $error = 'Inscription refusée.';
            } else {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['twitch_username'] = $user['twitch_username'];
                $_SESSION['role'] = $user['role'];
                redirect($user['role'] === 'admin' ? '../admin/' : '../dashboard.php');
            }
        } catch (PDOException $e) {
            $error = 'Erreur système.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?= time() ?>">
</head>

<body>

    <div class="auth-wrapper">
        <!-- Background Elements -->
        <div class="auth-bg-anim">
            <div class="auth-orb orb-1"></div>
            <div class="auth-orb orb-2"></div>
        </div>

        <div class="auth-box card">
            <div class="auth-header">
                <h1 class="logo-text">N0THY CASINO</h1>
                <p style="color: var(--text-muted); letter-spacing: 1px;">ACCÈS MEMBRE</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" placeholder="user@example.com" required>
                </div>

                <div class="form-group">
                    <label>Mot de passe</label>
                    <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 20px;">
                    SE CONNECTER
                </button>
            </form>

            <div style="text-align: center; margin-top: 30px; font-size: 0.9rem;">
                <a href="register.php" style="color: var(--text-muted);">Pas encore membre ? <span
                        style="color: var(--primary-glow);">Créer un compte</span></a>
            </div>
        </div>
    </div>

</body>

</html>