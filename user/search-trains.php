<?php
require_once __DIR__ . '/../config/db.php';
require_user();

$errors = [];
$sourceId = trim($_GET['source_station_id'] ?? '');
$destId   = trim($_GET['destination_station_id'] ?? '');
$date     = trim($_GET['journey_date'] ?? date('Y-m-d'));

// Fetch stations for the modification form
$stations = [];
try {
    $stStmt = $pdo->query("SELECT station_id, station_name, station_code, city FROM stations ORDER BY station_name ASC");
    $stations = $stStmt->fetchAll();
} catch (PDOException $e) {
    $errors[] = "Error fetching stations.";
}

// Map station names for summary
$stationsById = [];
foreach ($stations as $s) {
    $stationsById[$s['station_id']] = $s;
}

$trains = [];
$searched = false;

if (!empty($sourceId) && !empty($destId)) {
    $searched = true;
    if ($sourceId === $destId) {
        $errors[] = "Departure and Arrival stations cannot be identical.";
    } elseif ($date < date('Y-m-d')) {
        $errors[] = "Journey date cannot be in the past.";
    } else {
        try {
            $stmt = $pdo->prepare("
                SELECT t.train_id, t.train_number, t.train_name, 
                       t.source_station_id, t.destination_station_id,
                       t.departure_time, t.arrival_time, t.total_seats, t.fare,
                       s1.station_name AS source_name, s1.station_code AS source_code,
                       s2.station_name AS dest_name, s2.station_code AS dest_code
                FROM trains t
                JOIN stations s1 ON t.source_station_id = s1.station_id
                JOIN stations s2 ON t.destination_station_id = s2.station_id
                WHERE t.source_station_id = :source_id AND t.destination_station_id = :dest_id
                ORDER BY t.departure_time ASC
            ");
            $stmt->execute([
                'source_id' => $sourceId,
                'dest_id'   => $destId,
            ]);
            $trains = $stmt->fetchAll();

            // Calculate live availability for each train
            foreach ($trains as &$train) {
                $train['available_seats'] = get_available_seats($pdo, $train['train_id'], $date);
                $train['duration']        = calculate_duration($train['departure_time'], $train['arrival_time']);
            }
            unset($train);
        } catch (PDOException $e) {
            $errors[] = "Failed to search trains.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Trains — RailReserve</title>
    <link rel="stylesheet" href="<?= BASE_PATH ?>/css/style.css">
</head>
<body>
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="container">
        <div style="margin-bottom: 20px;">
            <h1>Train Search</h1>
            <p style="color: var(--color-muted);">Search direct express trains and check real-time availability.</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $err): ?>
                    <div><?= htmlspecialchars($err) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Search Modification Form -->
        <section class="card" style="padding: 18px 20px;">
            <form action="<?= BASE_PATH ?>/user/search-trains.php" method="GET">
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="source_station_id">From</label>
                            <select name="source_station_id" id="source_station_id" class="form-control" required>
                                <option value="">-- Select Origin --</option>
                                <?php foreach ($stations as $st): ?>
                                    <option value="<?= $st['station_id'] ?>" <?= (string)$sourceId === (string)$st['station_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($st['station_name']) ?> (<?= htmlspecialchars($st['station_code']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-col">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="destination_station_id">To</label>
                            <select name="destination_station_id" id="destination_station_id" class="form-control" required>
                                <option value="">-- Select Destination --</option>
                                <?php foreach ($stations as $st): ?>
                                    <option value="<?= $st['station_id'] ?>" <?= (string)$destId === (string)$st['station_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($st['station_name']) ?> (<?= htmlspecialchars($st['station_code']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-col">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="journey_date">Date of Journey</label>
                            <input type="date" name="journey_date" id="journey_date" class="form-control" 
                                   min="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($date) ?>" required>
                        </div>
                    </div>

                    <div>
                        <button type="submit" class="btn btn-primary" style="height: 42px;">
                            Modify Search
                        </button>
                    </div>
                </div>
            </form>
        </section>

        <!-- Search Results Section -->
        <?php if ($searched && empty($errors)): ?>
            <div style="margin: 20px 0 14px 0; display:flex; justify-content:space-between; align-items:center;">
                <h3 style="margin:0;">
                    Available Trains: 
                    <?= htmlspecialchars($stationsById[$sourceId]['station_name'] ?? 'Origin') ?> 
                    &rarr; 
                    <?= htmlspecialchars($stationsById[$destId]['station_name'] ?? 'Destination') ?>
                </h3>
                <span style="font-size: 13px; color: var(--color-muted); font-weight: 600;">
                    Journey Date: <?= date('d M Y', strtotime($date)) ?>
                </span>
            </div>

            <?php if (empty($trains)): ?>
                <div class="card empty-state">
                    No trains found
                </div>
            <?php else: ?>
                <?php foreach ($trains as $tr): ?>
                    <?php 
                        $availPercent = $tr['total_seats'] > 0 ? round(($tr['available_seats'] / $tr['total_seats']) * 100) : 0;
                        $isSoldOut = ($tr['available_seats'] <= 0);
                    ?>
                    <div class="train-card">
                        <div class="train-main">
                            <div class="train-name-row">
                                <span class="train-number"><?= htmlspecialchars($tr['train_number']) ?></span>
                                <span class="train-title"><?= htmlspecialchars($tr['train_name']) ?></span>
                            </div>

                            <div class="train-timing-row">
                                <div class="time-box">
                                    <strong><?= format_time($tr['departure_time']) ?></strong>
                                    <span><?= htmlspecialchars($tr['source_code']) ?></span>
                                </div>

                                <div class="duration-line">
                                    <?= htmlspecialchars($tr['duration']) ?>
                                </div>

                                <div class="time-box">
                                    <strong><?= format_time($tr['arrival_time']) ?></strong>
                                    <span><?= htmlspecialchars($tr['dest_code']) ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="train-fare-availability">
                            <div class="fare-display">
                                ₹<?= number_format($tr['fare'], 2) ?>
                            </div>

                            <div class="seat-availability-info">
                                Available: <strong><?= $tr['available_seats'] ?></strong> / <?= $tr['total_seats'] ?> seats
                            </div>

                            <div class="seat-bar" title="<?= $availPercent ?>% seats available">
                                <div class="seat-progress" style="width: <?= $availPercent ?>%; <?= $availPercent < 20 ? 'background-color: var(--color-danger);' : '' ?>"></div>
                            </div>

                            <?php if ($isSoldOut): ?>
                                <button class="btn btn-secondary btn-sm disabled" disabled>Sold Out</button>
                            <?php else: ?>
                                <a href="<?= BASE_PATH ?>/user/book-ticket.php?train_id=<?= $tr['train_id'] ?>&journey_date=<?= urlencode($date) ?>" class="btn btn-primary btn-sm">
                                    Book Now
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endif; ?>
    </main>
</body>
</html>
