<?php
require_once __DIR__ . '/../config/db.php';
require_user();

$userId = (int)$_SESSION['user_id'];
$filter = trim($_GET['status'] ?? 'CONFIRMED');
if (!in_array($filter, ['CONFIRMED', 'CANCELLED'])) {
    $filter = 'CONFIRMED';
}

$errors = [];

// Handle Cancellation POST Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel') {
    verify_csrf();
    $bookingIdToCancel = (int)($_POST['booking_id'] ?? 0);

    if ($bookingIdToCancel <= 0) {
        $errors[] = "Invalid booking reference.";
    } else {
        try {
            // Verify booking ownership and confirmed status
            $stmt = $pdo->prepare("
                SELECT b.booking_id, b.pnr, b.journey_date, b.booking_status, t.train_name, t.train_number
                FROM bookings b
                JOIN trains t ON b.train_id = t.train_id
                WHERE b.booking_id = :booking_id AND b.user_id = :user_id
                LIMIT 1
            ");
            $stmt->execute([
                'booking_id' => $bookingIdToCancel,
                'user_id'    => $userId,
            ]);
            $targetBooking = $stmt->fetch();

            if (!$targetBooking) {
                $errors[] = "Booking not found or permission denied.";
            } elseif ($targetBooking['booking_status'] !== 'CONFIRMED') {
                $errors[] = "This booking has already been cancelled.";
            } else {
                // Update booking_status to CANCELLED within a transaction
                $pdo->beginTransaction();

                $updateStmt = $pdo->prepare("
                    UPDATE bookings 
                    SET booking_status = 'CANCELLED' 
                    WHERE booking_id = :booking_id AND user_id = :user_id
                ");
                $updateStmt->execute([
                    'booking_id' => $bookingIdToCancel,
                    'user_id'    => $userId,
                ]);

                $pdo->commit();

                // Set one-time cancellation context
                $_SESSION['cancellation_confirmation'] = [
                    'pnr'          => $targetBooking['pnr'],
                    'train_name'   => $targetBooking['train_number'] . ' — ' . $targetBooking['train_name'],
                    'journey_date' => $targetBooking['journey_date'],
                ];

                header("Location: " . BASE_PATH . "/user/cancellation-confirmation.php");
                exit;
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = "Could not cancel booking: " . $e->getMessage();
        }
    }
}

// Fetch user's bookings with passenger count and total fare
$bookings = [];
try {
    $stmt = $pdo->prepare("
        SELECT b.booking_id, b.pnr, b.journey_date, b.booking_date, b.fare_per_passenger, b.booking_status,
               t.train_number, t.train_name,
               s1.station_name AS source_name, s1.station_code AS source_code,
               s2.station_name AS dest_name, s2.station_code AS dest_code,
               COUNT(p.passenger_id) AS passenger_count
        FROM bookings b
        JOIN trains t ON b.train_id = t.train_id
        JOIN stations s1 ON t.source_station_id = s1.station_id
        JOIN stations s2 ON t.destination_station_id = s2.station_id
        LEFT JOIN passengers p ON b.booking_id = p.booking_id
        WHERE b.user_id = :user_id AND b.booking_status = :status
        GROUP BY b.booking_id
        ORDER BY b.booking_date DESC
    ");
    $stmt->execute([
        'user_id' => $userId,
        'status'  => $filter,
    ]);
    $bookings = $stmt->fetchAll();
} catch (PDOException $e) {
    $errors[] = "Error loading booking history.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings — RailReserve</title>
    <link rel="stylesheet" href="<?= BASE_PATH ?>/css/style.css">
</head>
<body>
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="container">
        <div style="margin-bottom: 20px;">
            <h1>My Bookings</h1>
            <p style="color: var(--color-muted);">Review your reservation history and manage existing bookings.</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $err): ?>
                    <div><?= htmlspecialchars($err) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Filter Controls -->
        <div class="filter-tabs no-print">
            <a href="<?= BASE_PATH ?>/user/my-bookings.php?status=CONFIRMED" 
               class="filter-tab <?= $filter === 'CONFIRMED' ? 'active' : '' ?>">
                Confirmed Bookings
            </a>
            <a href="<?= BASE_PATH ?>/user/my-bookings.php?status=CANCELLED" 
               class="filter-tab <?= $filter === 'CANCELLED' ? 'active' : '' ?>">
                Cancelled Bookings
            </a>
        </div>

        <!-- Bookings Data Table -->
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>PNR</th>
                        <th>Train</th>
                        <th>Route</th>
                        <th>Journey Date</th>
                        <th>Passengers</th>
                        <th>Total Fare</th>
                        <th>Status</th>
                        <?php if ($filter === 'CONFIRMED'): ?>
                            <th class="no-print">Action</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bookings)): ?>
                        <tr>
                            <td colspan="<?= $filter === 'CONFIRMED' ? '8' : '7' ?>" class="empty-state">
                                No <?= strtolower($filter) ?> bookings found.
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
                                    <?= htmlspecialchars($b['passenger_count']) ?> passenger<?= (int)$b['passenger_count'] > 1 ? 's' : '' ?>
                                </td>
                                <td>
                                    <strong>₹<?= number_format($totalFare, 2) ?></strong>
                                </td>
                                <td>
                                    <?php if ($b['booking_status'] === 'CONFIRMED'): ?>
                                        <span class="badge badge-confirmed">Confirmed</span>
                                    <?php else: ?>
                                        <span class="badge badge-cancelled">Cancelled</span>
                                    <?php endif; ?>
                                </td>
                                <?php if ($filter === 'CONFIRMED'): ?>
                                    <td class="no-print">
                                        <form action="<?= BASE_PATH ?>/user/my-bookings.php" method="POST" 
                                              onsubmit="return confirm('Are you sure you want to cancel booking PNR <?= htmlspecialchars($b['pnr']) ?>? This action cannot be reversed.');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="cancel">
                                            <input type="hidden" name="booking_id" value="<?= $b['booking_id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">
                                                Cancel
                                            </button>
                                        </form>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>
