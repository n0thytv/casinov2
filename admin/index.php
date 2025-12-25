<?php
require_once '../includes/init.php';

// Vérifier si l'utilisateur est connecté et admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('../pages/login.php');
}

$db = getDB();

// -- Logique inchangée pour les datas --
$stats = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'total' => 0];
$stmt = $db->query("SELECT status, COUNT(*) as count FROM users GROUP BY status");
while ($row = $stmt->fetch()) {
    $stats[$row['status']] = $row['count'];
    $stats['total'] += $row['count'];
}

$stmt = $db->query("SELECT id, email, twitch_username, created_at FROM users WHERE status = 'pending' ORDER BY created_at DESC");
$pendingUsers = $stmt->fetchAll();

$stmt = $db->query("SELECT id, email, twitch_username, status, created_at, updated_at FROM users WHERE status != 'pending' AND role = 'user' ORDER BY updated_at DESC LIMIT 10");
$recentUsers = $stmt->fetchAll();

$flash = getFlashMessage();

require_once '../includes/header.php';
?>

<main class="dashboard-main">
    <div class="container">

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px;">
            <div>
                <h1 style="font-size: 2rem; margin-bottom: 5px;">PANEL ADMIN</h1>
                <p style="color: var(--text-muted); font-family: var(--font-body); font-size: 0.9rem;">Gestion des accès
                    et utilisateurs</p>
            </div>
            <div style="text-align: right;">
                <div style="font-size: 0.8rem; color: var(--gold-main); letter-spacing: 1px;">TOTAL UTILISATEURS</div>
                <div
                    style="font-size: 2.5rem; font-weight: 800; color: var(--text-main); text-shadow: 0 0 10px var(--violet-main);">
                    <?= $stats['total'] ?>
                </div>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-<?= $flash['type'] ?>"><?= htmlspecialchars($flash['message']) ?></div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div
            style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 50px;">
            <div class="card"
                style="padding: 25px; text-align: center; border-image: none; border-color: var(--warning);">
                <h3 style="color: var(--warning); font-size: 0.8rem; margin-bottom: 5px;">EN ATTENTE</h3>
                <div style="font-size: 2.5rem; font-weight: bold; color: #fff;"><?= $stats['pending'] ?></div>
            </div>
            <div class="card"
                style="padding: 25px; text-align: center; border-image: none; border-color: var(--success);">
                <h3 style="color: var(--success); font-size: 0.8rem; margin-bottom: 5px;">APPROUVÉS</h3>
                <div style="font-size: 2.5rem; font-weight: bold; color: #fff;"><?= $stats['approved'] ?></div>
            </div>
            <div class="card"
                style="padding: 25px; text-align: center; border-image: none; border-color: var(--danger);">
                <h3 style="color: var(--danger); font-size: 0.8rem; margin-bottom: 5px;">REFUSÉS</h3>
                <div style="font-size: 2.5rem; font-weight: bold; color: #fff;"><?= $stats['rejected'] ?></div>
            </div>
        </div>

        <!-- Inscriptions en attente -->
        <section class="card" style="margin-bottom: 40px;">
            <h2 style="margin-bottom: 25px; border-bottom: 1px solid var(--violet-main); padding-bottom: 15px;">
                ⏳ EXAMEN DES DEMANDES
            </h2>

            <?php if (empty($pendingUsers)): ?>
                <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                    Aucune demande en attente pour le moment.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr style="color: var(--text-muted);">
                                <th>PSEUDO TWITCH</th>
                                <th>EMAIL</th>
                                <th>DATE</th>
                                <th style="text-align: right;">ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingUsers as $user): ?>
                                <tr>
                                    <td><strong
                                            style="color: white; font-size: 1.1rem;"><?= htmlspecialchars($user['twitch_username']) ?></strong>
                                    </td>
                                    <td style="color: var(--text-muted);"><?= htmlspecialchars($user['email']) ?></td>
                                    <td><?= date('d/m H:i', strtotime($user['created_at'])) ?></td>
                                    <td style="text-align: right;">
                                        <div style="display: inline-flex; gap: 10px;">
                                            <form action="approve.php" method="POST">
                                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                <button type="submit" class="btn btn-success"
                                                    style="padding: 5px 15px; font-size: 0.8rem;"
                                                    onclick="return confirm('Accepter ?');">
                                                    ACCEPTER
                                                </button>
                                            </form>
                                            <form action="reject.php" method="POST">
                                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                <button type="submit" class="btn btn-danger"
                                                    style="padding: 5px 15px; font-size: 0.8rem;"
                                                    onclick="return confirm('Refuser ?');">
                                                    REFUSER
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <!-- Derniers inscrits -->
        <section class="card">
            <h2 style="margin-bottom: 25px; border-bottom: 1px solid var(--violet-main); padding-bottom: 15px;">
                👥 DERNIERS MEMBRES
            </h2>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>PSEUDO</th>
                            <th>STATUT</th>
                            <th>MODIFIÉ LE</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentUsers as $user): ?>
                            <tr>
                                <td><?= htmlspecialchars($user['twitch_username']) ?></td>
                                <td>
                                    <?php if ($user['status'] == 'approved'): ?>
                                        <span style="color: var(--success); font-weight: 600;">● Approuvé</span>
                                    <?php else: ?>
                                        <span style="color: var(--danger); font-weight: 600;">● Refusé</span>
                                    <?php endif; ?>
                                </td>
                                <td style="color: var(--text-muted);">
                                    <?= date('d/m/Y H:i', strtotime($user['updated_at'])) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

    </div>
</main>

<?php require_once '../includes/footer.php'; ?>