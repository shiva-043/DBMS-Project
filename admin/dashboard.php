<?php
require_once __DIR__ . '/../config/db.php';
require_admin();

// Fetch summary metrics
try {
    $trainsCount   = (int)$pdo->query("SELECT COUNT(*) FROM trains")->fetchColumn();
    $stationsCount = (int)$pdo->query("SELECT COUNT(*) FROM stations")->fetchColumn();
    $bookingsCount = (int)$pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
    $usersCount    = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'USER'")->fetchColumn();
} catch (PDOException $e) {
    $trainsCount = $stationsCount = $bookingsCount = $usersCount = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard — RailReserve</title>
    <link rel="stylesheet" href="<?= BASE_PATH ?>/css/style.css">
</head>
<body>
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="container">
        <div style="margin-bottom: 24px;">
            <h1>Administrator Dashboard</h1>
            <p style="color: var(--color-muted);">Welcome, <?= htmlspecialchars($_SESSION['full_name']) ?>. Manage railway stations, train routes, reservations, and users.</p>
        </div>

        <!-- Management Navigation Cards -->
        <div class="features-grid" style="grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));">
            <div class="feature-box">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                    <h3>Manage Trains</h3>
                    <span class="badge badge-admin"><?= $trainsCount ?> Active</span>
                </div>
                <p>Add new express routes, update timings, seat capacities, fares, or remove obsolete trains.</p>
                <div style="margin-top: 16px;">
                    <a href="<?= BASE_PATH ?>/admin/manage-trains.php" class="btn btn-primary btn-sm btn-block">Open Trains</a>
                </div>
            </div>

            <div class="feature-box">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                    <h3>Manage Stations</h3>
                    <span class="badge badge-admin"><?= $stationsCount ?> Stations</span>
                </div>
                <p>Register railway stations, configure unique 3-4 letter station codes, and set city locations.</p>
                <div style="margin-top: 16px;">
                    <a href="<?= BASE_PATH ?>/admin/manage-stations.php" class="btn btn-primary btn-sm btn-block">Open Stations</a>
                </div>
            </div>

            <div class="feature-box">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                    <h3>Manage Bookings</h3>
                    <span class="badge badge-admin"><?= $bookingsCount ?> Bookings</span>
                </div>
                <p>Inspect all passenger ticket reservations across routes and search directly by PNR code.</p>
                <div style="margin-top: 16px;">
                    <a href="<?= BASE_PATH ?>/admin/manage-bookings.php" class="btn btn-primary btn-sm btn-block">Open Bookings</a>
                </div>
            </div>

            <div class="feature-box">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                    <h3>Manage Users</h3>
                    <span class="badge badge-admin"><?= $usersCount ?> Users</span>
                </div>
                <p>View registered user accounts, contact information, and security roles across the system.</p>
                <div style="margin-top: 16px;">
                    <a href="<?= BASE_PATH ?>/admin/manage-users.php" class="btn btn-primary btn-sm btn-block">Open Users</a>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
