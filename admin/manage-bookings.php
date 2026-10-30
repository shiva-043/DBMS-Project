<?php
require_once __DIR__ . '/../config/db.php';
require_admin();

$pnrQuery = trim($_GET['pnr'] ?? '');
$errors = [];
$bookings = [];

try {
    $sql = "
        SELECT b.booking_id, b.pnr, b.journey_date, b.booking_date, b.fare_per_passenger, b.booking_status,
               u.full_name AS user_name, u.email AS user_email,
               t.train_number, t.train_name,
               s1.station_name AS source_name, s1.station_code AS source_code,
               s2.station_name AS dest_name, s2.station_code AS dest_code,
               COUNT(p.passenger_id) AS passenger_count
        FROM bookings b
        JOIN users u ON b.user_id = u.user_id
        JOIN trains t ON b.train_id = t.train_id
        JOIN stations s1 ON t.source_station_id = s1.station_id
        JOIN stations s2 ON t.destination_station_id = s2.station_id
        LEFT JOIN passengers p ON b.booking_id = p.booking_id
    ";

    $params = [];
    if (!empty($pnrQuery)) {
        $sql .= " WHERE b.pnr LIKE :pnr ";
        $params['pnr'] = '%' . $pnrQuery . '%';
    }

    $sql .= " GROUP BY b.booking_id ORDER BY b.booking_date DESC ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $bookings = $stmt->fetchAll();
} catch (PDOException $e) {
    $errors[] = "Error loading bookings list: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Bookings — RailReserve</title>
    <link rel="stylesheet" href="<?= BASE_PATH ?>/css/style.css">
</head>
<body>
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="container">
        <div style="margin-bottom: 20px;">
            <h1>Manage Bookings</h1>
            <p style="color: var(--color-muted);">Inspect all system reservations. Search records by PNR identifier.</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $err): ?>
                    <div><?= htmlspecialchars($err) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- PNR Lookup Form -->
        <section class="card" style="padding: 16px 20px; margin-bottom: 20px;">
            <form action="<?= BASE_PATH ?>/admin/manage-bookings.php" method="GET">
                <div class="form-row">
                    <div class="form-col" style="flex: 2;">
                        <input type="text" name="pnr" class="form-control" 
                               placeholder="Search by PNR (e.g. RR12345678)" value="<?= htmlspecialchars($pnrQuery) ?>">
                    </div>
                    <div>
                        <button type="submit" class="btn btn-primary" style="height: 42px;">Search PNR</button>
                        <?php if (!empty($pnrQuery)): ?>
                            <a href="<?= BASE_PATH ?>/admin/manage-bookings.php" class="btn btn-secondary" style="height: 42px;">Reset</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </section>

        <!-- Bookings Data Table -->
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>PNR</th>
                        <th>User</th>
                        <th>Train</th>
                        <th>Route</th>
                        <th>Journey Date</th>
                        <th>Passengers</th>
                        <th>Total Fare</th>
                        <th>Booking Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bookings)): ?>
                        <tr>
                            <td colspan="9" class="empty-state">
                                <?= !empty($pnrQuery) ? 'No bookings found matching PNR "' . htmlspecialchars($pnrQuery) . '".' : 'No reservations found in database.' ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($bookings as $b): ?>
                            <?php 
                                $totalFare = (int)$b['passenger_count'] * (float)$b['fare_per_passenger']; 
                            ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($b['pnr']) ?></strong>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($b['user_name']) ?></strong><br>
                                    <span style="font-size: 12px; color: var(--color-muted);"><?= htmlspecialchars($b['user_email']) ?></span>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($b['train_number']) ?></strong><br>
                                    <span style="font-size: 13px; color: var(--color-muted);"><?= htmlspecialchars($b['train_name']) ?></span>
                                </td>
                                <td>
                                    <?= htmlspecialchars($b['source_code']) ?> &rarr; <?= htmlspecialchars($b['dest_code']) ?><br>
                                    <span style="font-size: 12px; color: var(--color-muted);"><?= htmlspecialchars($b['source_name']) ?> to <?= htmlspecialchars($b['dest_name']) ?></span>
                                </td>
                                <td>
                                    <?= date('d M Y', strtotime($b['journey_date'])) ?>
                                </td>
                                <td>
                                    <?= (int)$b['passenger_count'] ?> passenger<?= (int)$b['passenger_count'] > 1 ? 's' : '' ?>
                                </td>
                                <td>
                                    <strong>₹<?= number_format($totalFare, 2) ?></strong>
                                </td>
                                <td>
                                    <?= date('d M Y, h:i A', strtotime($b['booking_date'])) ?>
                                </td>
                                <td>
                                    <?php if ($b['booking_status'] === 'CONFIRMED'): ?>
                                        <span class="badge badge-confirmed">Confirmed</span>
                                    <?php else: ?>
                                        <span class="badge badge-cancelled">Cancelled</span>
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
