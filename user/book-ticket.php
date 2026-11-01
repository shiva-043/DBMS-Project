<?php
require_once __DIR__ . '/../config/db.php';
require_user();

$errors = [];
$trainId = (int)($_GET['train_id'] ?? ($_POST['train_id'] ?? 0));
$date    = trim($_GET['journey_date'] ?? ($_POST['journey_date'] ?? ''));

// Validate base query params
if ($trainId <= 0 || empty($date)) {
    header("Location: " . BASE_PATH . "/user/search-trains.php");
    exit;
}

if ($date < date('Y-m-d')) {
    $errors[] = "You cannot book for a past date.";
}

// Fetch train details
$train = null;
$availableSeats = 0;
try {
    $stmt = $pdo->prepare("
        SELECT t.*, 
               s1.station_name AS source_name, s1.station_code AS source_code,
               s2.station_name AS dest_name, s2.station_code AS dest_code
        FROM trains t
        JOIN stations s1 ON t.source_station_id = s1.station_id
        JOIN stations s2 ON t.destination_station_id = s2.station_id
        WHERE t.train_id = :train_id
        LIMIT 1
    ");
    $stmt->execute(['train_id' => $trainId]);
    $train = $stmt->fetch();

    if (!$train) {
        header("Location: " . BASE_PATH . "/user/search-trains.php");
        exit;
    }

    $availableSeats = get_available_seats($pdo, $trainId, $date);
} catch (PDOException $e) {
    $errors[] = "Database error while fetching train information.";
}

// Determine passenger count and preserved data
$maxPassengers = min($availableSeats, 6);
$passengerCount = 1;
$savedNames   = [];
$savedAges    = [];
$savedGenders = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $savedNames   = $_POST['passenger_name'] ?? [];
    $savedAges    = $_POST['age'] ?? [];
    $savedGenders = $_POST['gender'] ?? [];

    // Handle Add Passenger button
    if (isset($_POST['add_passenger'])) {
        $passengerCount = count($savedNames) + 1;
        if ($passengerCount > $maxPassengers) {
            $passengerCount = $maxPassengers;
            $errors[] = "Cannot add more passengers. Maximum {$maxPassengers} allowed based on available seats.";
        }
    }
    // Handle Remove Passenger button
    elseif (isset($_POST['remove_passenger'])) {
        $removeIdx = (int)$_POST['remove_passenger'];
        if ($removeIdx >= 0 && $removeIdx < count($savedNames)) {
            array_splice($savedNames, $removeIdx, 1);
            array_splice($savedAges, $removeIdx, 1);
            array_splice($savedGenders, $removeIdx, 1);
        }
        $passengerCount = max(1, count($savedNames));
    }
    // Handle Confirm Booking submission
    elseif (isset($_POST['confirm_booking'])) {
        verify_csrf();

        $passengerCount = count($savedNames);
        $passengers = [];

        if ($passengerCount < 1) {
            $errors[] = "At least one passenger is required to make a booking.";
        } elseif ($passengerCount > $maxPassengers) {
            $errors[] = "Cannot book more passengers than available capacity.";
        }

        // Validate each passenger field
        for ($i = 0; $i < $passengerCount; $i++) {
            $pName   = trim($savedNames[$i] ?? '');
            $pAge    = trim($savedAges[$i] ?? '');
            $pGender = trim($savedGenders[$i] ?? '');

            if (empty($pName)) {
                $errors[] = "Passenger #" . ($i + 1) . ": Name is required.";
            }
            if ($pAge === '' || !is_numeric($pAge) || (int)$pAge < 0 || (int)$pAge > 120) {
                $errors[] = "Passenger #" . ($i + 1) . ": Please enter a valid age (0 - 120).";
            }
            if (!in_array($pGender, ['MALE', 'FEMALE', 'OTHER'])) {
                $errors[] = "Passenger #" . ($i + 1) . ": Please select a valid gender.";
            }

            $passengers[] = [
                'name'   => $pName,
                'age'    => (int)$pAge,
                'gender' => $pGender
            ];
        }

        if (empty($errors)) {
            try {
                // Concurrency-safe transaction with row-level lock
                $pdo->beginTransaction();

                // Lock train record for update
                $lockStmt = $pdo->prepare("SELECT total_seats, fare FROM trains WHERE train_id = :train_id FOR UPDATE");
                $lockStmt->execute(['train_id' => $trainId]);
                $lockedTrain = $lockStmt->fetch();

                if (!$lockedTrain) {
                    throw new Exception("Train not found.");
                }

                // Recalculate confirmed bookings capacity under lock
                $countStmt = $pdo->prepare("
                    SELECT COUNT(p.passenger_id) AS booked_count
                    FROM bookings b
                    JOIN passengers p ON b.booking_id = p.booking_id
                    WHERE b.train_id = :train_id 
                      AND b.journey_date = :journey_date 
                      AND b.booking_status = 'CONFIRMED'
                ");
                $countStmt->execute([
                    'train_id'     => $trainId,
                    'journey_date' => $date
                ]);
                $bookedSeats = (int)$countStmt->fetchColumn();
                $currentAvailable = $lockedTrain['total_seats'] - $bookedSeats;

                if ($passengerCount > $currentAvailable) {
                    $pdo->rollBack();
                    $errors[] = "Insufficient seats available. Only {$currentAvailable} seat(s) remain.";
                } else {
                    // Generate Unique PNR
                    $pnr = generate_pnr($pdo);

                    // Insert booking record (snapshots the current train fare)
                    $bStmt = $pdo->prepare("
                        INSERT INTO bookings (pnr, user_id, train_id, journey_date, booking_date, fare_per_passenger, booking_status)
                        VALUES (:pnr, :user_id, :train_id, :journey_date, NOW(), :fare, 'CONFIRMED')
                    ");
                    $bStmt->execute([
                        'pnr'          => $pnr,
                        'user_id'      => $_SESSION['user_id'],
                        'train_id'     => $trainId,
                        'journey_date' => $date,
                        'fare'         => $lockedTrain['fare']
                    ]);
                    $bookingId = (int)$pdo->lastInsertId();

                    // Insert passenger rows
                    $pStmt = $pdo->prepare("
                        INSERT INTO passengers (booking_id, passenger_name, age, gender)
                        VALUES (:booking_id, :passenger_name, :age, :gender)
                    ");
                    foreach ($passengers as $p) {
                        $pStmt->execute([
                            'booking_id'     => $bookingId,
                            'passenger_name' => $p['name'],
                            'age'            => $p['age'],
                            'gender'         => $p['gender']
                        ]);
                    }

                    // Commit the entire atomic unit
                    $pdo->commit();

                    // Store one-time confirmation context in session
                    $_SESSION['booking_confirmation'] = [
                        'booking_id' => $bookingId,
                        'pnr'        => $pnr
                    ];

                    header("Location: " . BASE_PATH . "/user/booking-confirmation.php");
                    exit;
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors[] = "Booking could not be completed: " . $e->getMessage();
            }
        }
    } else {
        // Regular page load from POST (shouldn't happen, but handle gracefully)
        $passengerCount = count($savedNames);
        if ($passengerCount < 1) $passengerCount = 1;
    }
} 

// Ensure passenger count is at least 1
if ($passengerCount < 1) $passengerCount = 1;

// Calculate fare for display
$totalFare = $passengerCount * (float)$train['fare'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Ticket — RailReserve</title>
    <link rel="stylesheet" href="<?= BASE_PATH ?>/css/style.css">
</head>
<body>
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="container-medium">
        <div style="margin-bottom: 20px;">
            <h1>Passenger Reservation</h1>
            <p style="color: var(--color-muted);">Enter passenger information to confirm your booking.</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $err): ?>
                    <div><?= htmlspecialchars($err) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Train Summary Card -->
        <section class="card">
            <div class="card-header">
                <h2>Journey Summary</h2>
                <span class="badge badge-confirmed">Daily Express</span>
            </div>

            <div class="ticket-grid">
                <div class="ticket-item">
                    Train
                    <strong><?= htmlspecialchars($train['train_number']) ?> — <?= htmlspecialchars($train['train_name']) ?></strong>
                </div>
                <div class="ticket-item">
                    Route
                    <strong><?= htmlspecialchars($train['source_name']) ?> &rarr; <?= htmlspecialchars($train['dest_name']) ?></strong>
                </div>
                <div class="ticket-item">
                    Journey Date
                    <strong><?= date('l, d M Y', strtotime($date)) ?></strong>
                </div>
                <div class="ticket-item">
                    Timings
                    <strong><?= format_time($train['departure_time']) ?> &rarr; <?= format_time($train['arrival_time']) ?></strong>
                </div>
                <div class="ticket-item">
                    Fare per Passenger
                    <strong>₹<?= number_format($train['fare'], 2) ?></strong>
                </div>
                <div class="ticket-item">
                    Available Seats
                    <strong style="color: <?= $availableSeats > 5 ? 'var(--color-success)' : 'var(--color-danger)' ?>;">
                        <?= $availableSeats ?> seats remaining
                    </strong>
                </div>
            </div>
        </section>

        <!-- Booking Form -->
        <section class="card">
            <div class="card-header">
                <h3>Passenger Details (<?= $passengerCount ?>)</h3>
            </div>

            <form action="<?= BASE_PATH ?>/user/book-ticket.php" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="train_id" value="<?= $trainId ?>">
                <input type="hidden" name="journey_date" value="<?= htmlspecialchars($date) ?>">

                <?php for ($i = 0; $i < $passengerCount; $i++): ?>
                    <div class="passenger-row">
                        <div style="flex: 2;">
                            <label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">Passenger <?= $i + 1 ?> Full Name</label>
                            <input type="text" name="passenger_name[]" class="form-control" placeholder="Full Name" 
                                   value="<?= htmlspecialchars($savedNames[$i] ?? '') ?>" required>
                        </div>
                        <div style="flex: 1;">
                            <label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">Age</label>
                            <input type="number" name="age[]" class="form-control" placeholder="Age" min="1" max="120" 
                                   value="<?= htmlspecialchars($savedAges[$i] ?? '') ?>" required>
                        </div>
                        <div style="flex: 1.2;">
                            <label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">Gender</label>
                            <select name="gender[]" class="form-control" required>
                                <option value="MALE" <?= (($savedGenders[$i] ?? '') === 'MALE') ? 'selected' : '' ?>>Male</option>
                                <option value="FEMALE" <?= (($savedGenders[$i] ?? '') === 'FEMALE') ? 'selected' : '' ?>>Female</option>
                                <option value="OTHER" <?= (($savedGenders[$i] ?? '') === 'OTHER') ? 'selected' : '' ?>>Other</option>
                            </select>
                        </div>
                        <div>
                            <?php if ($i > 0): ?>
                                <button type="submit" name="remove_passenger" value="<?= $i ?>" formnovalidate class="btn btn-danger btn-sm" title="Remove">&times;</button>
                            <?php else: ?>
                                <button type="button" class="btn btn-secondary btn-sm" disabled style="opacity: 0.3;">&times;</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endfor; ?>

                <!-- Add Passenger Button -->
                <div style="margin-bottom: 20px;">
                    <?php if ($passengerCount < $maxPassengers): ?>
                        <button type="submit" name="add_passenger" value="1" formnovalidate class="btn btn-secondary btn-sm">
                            + Add Passenger
                        </button>
                    <?php else: ?>
                        <button type="button" class="btn btn-secondary btn-sm" disabled>
                            + Add Passenger (Max reached)
                        </button>
                    <?php endif; ?>
                </div>

                <!-- Booking Summary Bar -->
                <div style="background: #F8FAFC; border: 1px solid var(--color-border); border-radius: var(--radius-sm); padding: 16px; margin: 24px 0; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <span style="color: var(--color-muted); font-size: 13px;">Passenger Count: </span>
                        <strong><?= $passengerCount ?></strong>
                    </div>
                    <div style="text-align: right;">
                        <span style="color: var(--color-muted); font-size: 13px;">Total Booking Fare: </span>
                        <span style="font-size: 1.4rem; font-weight: 700; color: var(--color-primary); margin-left: 8px;">
                            ₹<?= number_format($totalFare, 2) ?>
                        </span>
                    </div>
                </div>

                <div>
                    <button type="submit" name="confirm_booking" value="1" class="btn btn-primary btn-block" style="height: 46px; font-size: 15px;">
                        Confirm and Book Ticket
                    </button>
                </div>
            </form>
        </section>
    </main>
</body>
</html>
