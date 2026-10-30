<?php
require_once __DIR__ . '/../config/db.php';
require_user();

// One-time session context check
if (empty($_SESSION['cancellation_confirmation']['pnr'])) {
    header("Location: " . BASE_PATH . "/user/my-bookings.php");
    exit;
}

$cancellationData = $_SESSION['cancellation_confirmation'];

// Clear one-time context immediately
unset($_SESSION['cancellation_confirmation']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cancellation Confirmed — RailReserve</title>
    <link rel="stylesheet" href="<?= BASE_PATH ?>/css/style.css">
</head>
<body>
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="container-narrow">
        <div class="card" style="text-align: center; padding: 36px 24px;">
            <div style="font-size: 40px; margin-bottom: 12px;">✅</div>
            <span class="badge badge-cancelled" style="font-size: 13px; padding: 6px 14px;">Booking Cancelled</span>
            
            <h2 style="margin-top: 16px;">Cancellation Successful</h2>
            <p style="color: var(--color-muted);">
                Your reservation has been cancelled and seat capacity has been released.
            </p>

            <div class="pnr-box" style="margin: 20px auto; border-color: #F5C6CB; background-color: var(--color-danger-bg); color: var(--color-danger);">
                PNR: <?= htmlspecialchars($cancellationData['pnr']) ?>
            </div>

            <div style="background: #F8FAFC; border: 1px solid var(--color-border); border-radius: var(--radius-sm); padding: 16px; margin: 20px 0; text-align: left; font-size: 14px;">
                <div style="margin-bottom: 6px;">
                    <span style="color: var(--color-muted);">Train:</span> 
                    <strong><?= htmlspecialchars($cancellationData['train_name']) ?></strong>
                </div>
                <div>
                    <span style="color: var(--color-muted);">Journey Date:</span> 
                    <strong><?= date('l, d M Y', strtotime($cancellationData['journey_date'])) ?></strong>
                </div>
            </div>

            <div style="margin-top: 24px;">
                <a href="<?= BASE_PATH ?>/user/my-bookings.php" class="btn btn-primary">
                    Return to My Bookings
                </a>
            </div>
        </div>
    </main>
</body>
</html>
