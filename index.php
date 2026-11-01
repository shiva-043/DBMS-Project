<?php
require_once __DIR__ . '/config/db.php';

$errors = [];
$selectedSource = $_POST['source_station_id'] ?? ($_GET['source_station_id'] ?? '');
$selectedDest   = $_POST['destination_station_id'] ?? ($_GET['destination_station_id'] ?? '');
$selectedDate   = $_POST['journey_date'] ?? ($_GET['journey_date'] ?? date('Y-m-d'));

// Fetch stations for dropdowns
try {
    $stmt = $pdo->query("SELECT station_id, station_name, station_code, city FROM stations ORDER BY station_name ASC");
    $stations = $stmt->fetchAll();
} catch (PDOException $e) {
    $stations = [];
    $errors[] = "Could not load stations. Please ensure database is initialized.";
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sourceId = trim($_POST['source_station_id'] ?? '');
    $destId   = trim($_POST['destination_station_id'] ?? '');
    $date     = trim($_POST['journey_date'] ?? '');

    // Server-side validation
    if (empty($sourceId) || empty($destId) || empty($date)) {
        $errors[] = "Please select departure station, arrival station, and journey date.";
    } elseif ($sourceId === $destId) {
        $errors[] = "Departure and Arrival stations cannot be identical.";
    } elseif ($date < date('Y-m-d')) {
        $errors[] = "Journey date cannot be in the past.";
    }

    if (empty($errors)) {
        $searchParams = http_build_query([
            'source_station_id'      => $sourceId,
            'destination_station_id' => $destId,
            'journey_date'           => $date,
        ]);

        if (is_logged_in()) {
            if ($_SESSION['role'] === 'ADMIN') {
                $errors[] = "Administrators manage records via Admin Dashboard and cannot perform passenger bookings.";
            } else {
                header("Location: " . BASE_PATH . "/user/search-trains.php?" . $searchParams);
                exit;
            }
        } else {
            // Preserve search criteria for logged-out visitor
            $_SESSION['pending_search'] = [
                'source_station_id'      => $sourceId,
                'destination_station_id' => $destId,
                'journey_date'           => $date,
            ];
            header("Location: " . BASE_PATH . "/login.php?redirect_to=search");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RailReserve — Railway Reservation Management System</title>
    <link rel="stylesheet" href="<?= BASE_PATH ?>/css/style.css">
</head>
<body>
    <?php require_once __DIR__ . '/includes/navbar.php'; ?>

    <main class="container">
        <!-- Hero Section -->
        <section class="hero">
            <h1>Railway Reservation System</h1>
            <p>Search daily direct express trains, check live seat availability, and book your tickets smoothly.</p>
        </section>

        <!-- Train Search Form Card -->
        <section class="card">
            <div class="card-header">
                <h2>Search Daily Express Trains</h2>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $err): ?>
                        <div><?= htmlspecialchars($err) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form action="<?= BASE_PATH ?>/index.php" method="POST" id="searchForm">
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="source_station_id">From Station</label>
                            <select name="source_station_id" id="source_station_id" class="form-control" required>
                                <option value="">-- Select Departure Station --</option>
                                <?php foreach ($stations as $st): ?>
                                    <option value="<?= $st['station_id'] ?>" <?= (string)$selectedSource === (string)$st['station_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($st['station_name']) ?> (<?= htmlspecialchars($st['station_code']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-col">
                        <div class="form-group">
                            <label for="destination_station_id">To Station</label>
                            <select name="destination_station_id" id="destination_station_id" class="form-control" required>
                                <option value="">-- Select Arrival Station --</option>
                                <?php foreach ($stations as $st): ?>
                                    <option value="<?= $st['station_id'] ?>" <?= (string)$selectedDest === (string)$st['station_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($st['station_name']) ?> (<?= htmlspecialchars($st['station_code']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-col">
                        <div class="form-group">
                            <label for="journey_date">Journey Date</label>
                            <input type="date" name="journey_date" id="journey_date" class="form-control" 
                                   min="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($selectedDate) ?>" required>
                        </div>
                    </div>

                    <div style="margin-bottom: 16px;">
                        <button type="submit" class="btn btn-primary" style="height: 42px;">
                            Search Trains
                        </button>
                    </div>
                </div>
            </form>
        </section>

        <!-- Informational Section on Booking Process -->
        <section class="features-grid">
            <div class="feature-box">
                <h3>1. Search Routes</h3>
                <p>Choose your origin, destination station, and journey date to instantly view available daily express trains.</p>
            </div>
            <div class="feature-box">
                <h3>2. Check Capacity</h3>
                <p>Transparent seat availability computed dynamically from live database bookings for your chosen date.</p>
            </div>
            <div class="feature-box">
                <h3>3. Instant PNR Ticket</h3>
                <p>Add passenger details, book your seats securely in one transaction, and print your official PNR reservation.</p>
            </div>
        </section>
    </main>


</body>
</html>
