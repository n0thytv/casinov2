<?php
require_once 'includes/init.php';

// Vérifier si l'utilisateur est connecté
if (!isLoggedIn()) {
    redirect('pages/login.php');
}

// Récupérer le solde
$twitch_username = $_SESSION['twitch_username'] ?? 'Utilisateur';
$balance = getN0thyCoins($twitch_username);
$balanceAmount = 0;

if ($balance !== null && isset($balance['success']) && $balance['success'] === true) {
    $balanceAmount = intval($balance['currency'] ?? 0);
}

// Inclure le layout
require_once 'includes/header.php';
?>

<main class="dashboard-main">
    <div class="container">

        <!-- Section Solde Principal -->
        <section class="balance-showcase">
            <div class="balance-title">Votre Solde Actuel</div>
            <div class="balance-value-container">
                <div class="balance-value" id="balance-amount">
                    <?php
                    $formatted = number_format($balanceAmount, 0, ',', ' ');
                    $chars = str_split($formatted);
                    foreach ($chars as $char) {
                        if ($char === ' ') {
                            echo '<span class="balance-sep">&nbsp;</span>';
                        } else {
                            echo '<span class="balance-digit">' . $char . '</span>';
                        }
                    }
                    ?>
                </div>
                <div class="currency-symbol">🪙</div>
            </div>

            <div style="margin-top: 30px;">
                <button id="refresh-btn" class="btn btn-primary" onclick="refreshBalance()">
                    RAFRAÎCHIR LE SOLDE
                </button>
            </div>
        </section>



    </div>
</main>

<?php require_once 'includes/footer.php'; ?>