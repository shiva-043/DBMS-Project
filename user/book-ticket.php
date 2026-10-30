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

// Process Booking Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $names   = $_POST['passenger_name'] ?? [];
    $ages    = $_POST['age'] ?? [];
    $genders = $_POST['gender'] ?? [];

    $passengers = [];
    $passengerCount = count($names);

    if ($passengerCount < 1) {
        $errors[] = "At least one passenger is required to make a booking.";
    } elseif ($passengerCount > 6) {
        $errors[] = "A maximum of 6 passengers can be booked per ticket.";
    }

    // Validate each passenger field
    for ($i = 0; $i < $passengerCount; $i++) {
        $pName   = trim($names[$i] ?? '');
        $pAge    = trim($ages[$i] ?? '');
        $pGender = trim($genders[$i] ?? '');

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
}
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
                    <strong>₹<span id="farePerPassenger"><?= number_format($train['fare'], 2, '.', '') ?></span></strong>
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
                <h3>Passenger Details</h3>
                <button type="button" id="addPassengerBtn" class="btn btn-secondary btn-sm" <?= $availableSeats <= 1 ? 'disabled' : '' ?>>
                    + Add Passenger
                </button>
            </div>

            <form action="<?= BASE_PATH ?>/user/book-ticket.php" method="POST" id="bookingForm">
                <?= csrf_field() ?>
                <input type="hidden" name="train_id" value="<?= $trainId ?>">
                <input type="hidden" name="journey_date" value="<?= htmlspecialchars($date) ?>">

                <div id="passengerContainer">
                    <!-- Default Passenger 1 -->
                    <div class="passenger-row" id="row-0">
                        <div style="flex: 2;">
                            <label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">Passenger 1 Full Name</label>
                            <input type="text" name="passenger_name[]" class="form-control" placeholder="Full Name" required>
                        </div>
                        <div style="flex: 1;">
                            <label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">Age</label>
                            <input type="number" name="age[]" class="form-control" placeholder="Age" min="1" max="120" required>
                        </div>
                        <div style="flex: 1.2;">
                            <label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">Gender</label>
                            <select name="gender[]" class="form-control" required>
                                <option value="MALE">Male</option>
                                <option value="FEMALE">Female</option>
                                <option value="OTHER">Other</option>
                            </select>
                        </div>
                        <div>
                            <!-- Cannot remove first passenger -->
                            <button type="button" class="btn btn-secondary btn-sm" disabled style="opacity: 0.3;">&times;</button>
                        </div>
                    </div>
                </div>

                <!-- Booking Summary Bar -->
                <div style="background: #F8FAFC; border: 1px solid var(--color-border); border-radius: var(--radius-sm); padding: 16px; margin: 24px 0; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <span style="color: var(--color-muted); font-size: 13px;">Passenger Count: </span>
                        <strong id="passengerCountDisplay">1</strong>
                    </div>
                    <div style="text-align: right;">
                        <span style="color: var(--color-muted); font-size: 13px;">Total Booking Fare: </span>
                        <span style="font-size: 1.4rem; font-weight: 700; color: var(--color-primary); margin-left: 8px;">
                            ₹<span id="totalFareDisplay"><?= number_format($train['fare'], 2) ?></span>
                        </span>
                    </div>
                </div>

                <div>
                    <button type="submit" class="btn btn-primary btn-block" style="height: 46px; font-size: 15px;">
                        Confirm and Book Ticket
                    </button>
                </div>
            </form>
        </section>
    </main>

    <script>
        const maxCapacity = Math.min(<?= (int)$availableSeats ?>, 6);
        const farePerPassenger = parseFloat(document.getElementById('farePerPassenger').innerText);
        const container = document.getElementById('passengerContainer');
        const countDisplay = document.getElementById('passengerCountDisplay');
        const totalFareDisplay = document.getElementById('totalFareDisplay');
        const addBtn = document.getElementById('addPassengerBtn');

        function updateSummary() {
            const rows = container.querySelectorAll('.passenger-row');
            const count = rows.length;
            countDisplay.innerText = count;
            totalFareDisplay.innerText = (count * farePerPassenger).toFixed(2);

            if (count >= maxCapacity) {
                addBtn.disabled = true;
            } else {
                addBtn.disabled = false;
            }
        }

        addBtn.addEventListener('click', function() {
            const rows = container.querySelectorAll('.passenger-row');
            const newIndex = rows.length + 1;
            if (newIndex > maxCapacity) {
                alert('Cannot add more passengers than available capacity (Max ' + maxCapacity + ').');
                return;
            }

            const row = document.createElement('div');
            row.className = 'passenger-row';
            row.innerHTML = `
                <div style="flex: 2;">
                    <label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">Passenger ${newIndex} Full Name</label>
                    <input type="text" name="passenger_name[]" class="form-control" placeholder="Full Name" required>
                </div>
                <div style="flex: 1;">
                    <label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">Age</label>
                    <input type="number" name="age[]" class="form-control" placeholder="Age" min="1" max="120" required>
                </div>
                <div style="flex: 1.2;">
                    <label style="font-size: 12px; font-weight: 600; display: block; margin-bottom: 4px;">Gender</label>
                    <select name="gender[]" class="form-control" required>
                        <option value="MALE">Male</option>
                        <option value="FEMALE">Female</option>
                        <option value="OTHER">Other</option>
                    </select>
                </div>
                <div>
                    <button type="button" class="btn btn-danger btn-sm remove-passenger-btn" title="Remove">&times;</button>
                </div>
            `;
            container.appendChild(row);
            updateSummary();
        });

        container.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-passenger-btn')) {
                const row = e.target.closest('.passenger-row');
                if (row) {
                    row.remove();
                    // Re-label headers
                    const rows = container.querySelectorAll('.passenger-row');
                    rows.forEach((r, idx) => {
                        const lbl = r.querySelector('label');
                        if (lbl) lbl.innerText = `Passenger ${idx + 1} Full Name`;
                    });
                    updateSummary();
                }
            }
        });
    </script>
</body>
</html>
