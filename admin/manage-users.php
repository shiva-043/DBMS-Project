<?php
require_once __DIR__ . '/../config/db.php';
require_admin();

$errors = [];
$users = [];

try {
    $stmt = $pdo->query("SELECT user_id, full_name, email, phone, role FROM users ORDER BY user_id ASC");
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    $errors[] = "Error loading users: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users — RailReserve</title>
    <link rel="stylesheet" href="<?= BASE_PATH ?>/css/style.css">
</head>
<body>
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="container">
        <div style="margin-bottom: 20px;">
            <h1>Manage Users</h1>
            <p style="color: var(--color-muted);">Registered passenger and administrator accounts in the system.</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $err): ?>
                    <div><?= htmlspecialchars($err) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Users Data Table -->
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>User ID</th>
                        <th>Full Name</th>
                        <th>Email Address</th>
                        <th>Phone</th>
                        <th>Role</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="5" class="empty-state">No users registered yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td>#<?= htmlspecialchars($u['user_id']) ?></td>
                                <td><strong><?= htmlspecialchars($u['full_name']) ?></strong></td>
                                <td><?= htmlspecialchars($u['email']) ?></td>
                                <td><?= !empty($u['phone']) ? htmlspecialchars($u['phone']) : '<span style="color:var(--color-muted);">Not provided</span>' ?></td>
                                <td>
                                    <?php if ($u['role'] === 'ADMIN'): ?>
                                        <span class="badge badge-admin">Admin</span>
                                    <?php else: ?>
                                        <span class="badge badge-user">User</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>
