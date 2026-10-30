<?php
require_once __DIR__ . '/../config/db.php';
require_user();

// One-time session context check
if (empty($_SESSION['booking_confirmation']['booking_id'])) {
    header("Location: " . BASE_PATH . "/user/my-bookings.php");
    exit;
}

$bookingId = (int)$_SESSION['booking_confirmation']['booking_id'];

// Fetch booking details ensuring ownership by logged-in user
try {
    $stmt = $pdo->prepare("
        SELECT b.*, 
               t.train_number, t.train_name, t.departure_time, t.arrival_time,
               s1.station_name AS source_name, s1.station_code AS source_code,
               s2.station_name AS dest_name, s2.station_code AS dest_code
        FROM bookings b
        JOIN trains t ON b.train_id = t.train_id
        JOIN stations s1 ON t.source_station_id = s1.station_id
        JOIN stations s2 ON t.destination_station_id = s2.station_id
        WHERE b.booking_id = :booking_id AND b.user_id = :user_id
        LIMIT 1
    ");
    $stmt->execute([
        'booking_id' => $bookingId,
        'user_id'    => $_SESSION['user_id']
    ]);
    $booking = $stmt->fetch();

    if (!$booking) {
        unset($_SESSION['booking_confirmation']);
        header("Location: " . BASE_PATH . "/user/my-bookings.php");
        exit;
    }

    // Fetch passengers for this booking
    $pStmt = $pdo->prepare("SELECT * FROM passengers WHERE booking_id = :booking_id ORDER BY passenger_id ASC");
    $pStmt->execute(['booking_id' => $bookingId]);
    $passengers = $pStmt->fetchAll();

    $passengerCount = count($passengers);
    $totalFare = $passengerCount * (float)$booking['fare_per_passenger'];

    // Clear one-time context immediately so reload safely redirects to My Bookings
    unset($_SESSION['booking_confirmation']);
} catch (PDOException $e) {
    unset($_SESSION['booking_confirmation']);
    header("Location: " . BASE_PATH . "/user/my-bookings.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Confirmed — PNR <?= htmlspecialchars($booking['pnr']) ?> — RailReserve</title>
    <link rel="stylesheet" href="<?= BASE_PATH ?>/css/style.css">
</head>
<body>
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="container">
        <div class="confirmation-card">
            <div class="confirmation-header">
                <span class="badge badge-confirmed" style="font-size: 14px; padding: 6px 14px;">Booking Confirmed</span>
                <h1 style="margin-top: 14px; font-size: 1.6rem;">Reservation Receipt</h1>
                <p style="color: var(--color-muted); font-size: 14px;">Your tickets have been reserved successfully.</p>
                <div class="pnr-box">
                    PNR: <?= htmlspecialchars($booking['pnr']) ?>
                </div>
            </div>

            <div class="ticket-grid">
                <div class="ticket-item">
                    Train
                    <strong><?= htmlspecialchars($booking['train_number']) ?> — <?= htmlspecialchars($booking['train_name']) ?></strong>
                </div>
                <div class="ticket-item">
                    Journey Date
                    <strong><?= date('l, d M Y', strtotime($booking['journey_date'])) ?></strong>
                </div>
                <div class="ticket-item">
                    Origin Station
                    <strong><?= htmlspecialchars($booking['source_name']) ?> (<?= htmlspecialchars($booking['source_code']) ?>)</strong>
                </div>
                <div class="ticket-item">
                    Destination Station
                    <strong><?= htmlspecialchars($booking['dest_name']) ?> (<?= htmlspecialchars($booking['dest_code']) ?>)</strong>
                </div>
                <div class="ticket-item">
                    Departure Time
                    <strong><?= format_time($booking['departure_time']) ?></strong>
                </div>
                <div class="ticket-item">
                    Arrival Time
                    <strong><?= format_time($booking['arrival_time']) ?></strong>
                </div>
                <div class="ticket-item">
                    Fare per Passenger
                    <strong>₹<?= number_format($booking['fare_per_passenger'], 2) ?></strong>
                </div>
                <div class="ticket-item">
                    Total Booking Fare
                    <strong style="color: var(--color-primary); font-size: 1.15rem;">₹<?= number_format($totalFare, 2) ?></strong>
                </div>
            </div>

            <!-- Passenger Details Table -->
            <div style="margin: 20px 0;">
                <h3 style="margin-bottom: 10px;">Passenger List (<?= $passengerCount ?>)</h3>
                <div class="table-container" style="box-shadow: none; margin-bottom: 0;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Passenger Name</th>
                                <th>Age</th>
                                <th>Gender</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($passengers as $idx => $p): ?>
                                <tr>
                                    <td><?= $idx + 1 ?></td>
                                    <td><strong><?= htmlspecialchars($p['passenger_name']) ?></strong></td>
                                    <td><?= htmlspecialchars($p['age']) ?> yrs</td>
                                    <td><?= htmlspecialchars($p['gender']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="no-print" style="margin-top: 24px; display: flex; gap: 12px; justify-content: flex-end;">
                <button type="button" onclick="window.print()" class="btn btn-secondary">
                    Print Ticket
                </button>
                <a href="<?= BASE_PATH ?>/user/my-bookings.php" class="btn btn-primary">
                    My Bookings
                </a>
            </div>
        </div>
    </main>
</body>
</html>
