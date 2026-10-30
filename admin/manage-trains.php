<?php
require_once __DIR__ . '/../config/db.php';
require_admin();

$errors = [];
$success = '';

// Fetch all stations for the select dropdowns
$stations = [];
try {
    $stations = $pdo->query("SELECT station_id, station_name, station_code, city FROM stations ORDER BY station_name ASC")->fetchAll();
} catch (PDOException $e) {
    $errors[] = "Error loading stations.";
}

// Handle POST actions: add, edit, delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $trainId = (int)($_POST['train_id'] ?? 0);
        if ($trainId <= 0) {
            $errors[] = "Invalid train ID.";
        } else {
            try {
                // Check if bookings exist for this train
                $chk = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE train_id = :train_id");
                $chk->execute(['train_id' => $trainId]);
                $bookingCount = (int)$chk->fetchColumn();

                if ($bookingCount > 0) {
                    $errors[] = "Cannot delete train: active or historical bookings exist for this train ({$bookingCount} booking(s)).";
                } else {
                    $delStmt = $pdo->prepare("DELETE FROM trains WHERE train_id = :train_id");
                    $delStmt->execute(['train_id' => $trainId]);
                    $success = "Train deleted successfully.";
                }
            } catch (PDOException $e) {
                $errors[] = "Failed to delete train: " . $e->getMessage();
            }
        }
    } elseif ($action === 'save') {
        $trainId   = (int)($_POST['train_id'] ?? 0);
        $trainNum  = trim($_POST['train_number'] ?? '');
        $trainName = trim($_POST['train_name'] ?? '');
        $sourceId  = (int)($_POST['source_station_id'] ?? 0);
        $destId    = (int)($_POST['destination_station_id'] ?? 0);
        $depTime   = trim($_POST['departure_time'] ?? '');
        $arrTime   = trim($_POST['arrival_time'] ?? '');
        $seats     = (int)($_POST['total_seats'] ?? 0);
        $fare      = (float)($_POST['fare'] ?? 0.0);

        // Validation
        if (empty($trainNum) || empty($trainName) || empty($sourceId) || empty($destId) || empty($depTime) || empty($arrTime)) {
            $errors[] = "All train fields are required.";
        } elseif ($sourceId === $destId) {
            $errors[] = "Source and Destination stations cannot be identical.";
        } elseif ($seats <= 0) {
            $errors[] = "Total seats capacity must be greater than zero.";
        } elseif ($fare < 0) {
            $errors[] = "Train fare cannot be negative.";
        }

        // Unique train number validation
        if (empty($errors)) {
            try {
                if ($trainId > 0) {
                    $chk = $pdo->prepare("SELECT COUNT(*) FROM trains WHERE train_number = :num AND train_id != :id");
                    $chk->execute(['num' => $trainNum, 'id' => $trainId]);
                } else {
                    $chk = $pdo->prepare("SELECT COUNT(*) FROM trains WHERE train_number = :num");
                    $chk->execute(['num' => $trainNum]);
                }
                if ((int)$chk->fetchColumn() > 0) {
                    $errors[] = "A train with number '{$trainNum}' already exists.";
                }
            } catch (PDOException $e) {
                $errors[] = "Database check failed.";
            }
        }

        // Insert or Update
        if (empty($errors)) {
            try {
                if ($trainId > 0) {
                    // Update
                    $stmt = $pdo->prepare("
                        UPDATE trains 
                        SET train_number = :train_num, train_name = :train_name,
                            source_station_id = :source_id, destination_station_id = :dest_id,
                            departure_time = :dep_time, arrival_time = :arr_time,
                            total_seats = :seats, fare = :fare
                        WHERE train_id = :train_id
                    ");
                    $stmt->execute([
                        'train_num'  => $trainNum,
                        'train_name' => $trainName,
                        'source_id'  => $sourceId,
                        'dest_id'    => $destId,
                        'dep_time'   => $depTime,
                        'arr_time'   => $arrTime,
                        'seats'      => $seats,
                        'fare'       => $fare,
                        'train_id'   => $trainId,
                    ]);
                    $success = "Train #{$trainNum} updated successfully.";
                } else {
                    // Insert
                    $stmt = $pdo->prepare("
                        INSERT INTO trains (train_number, train_name, source_station_id, destination_station_id, departure_time, arrival_time, total_seats, fare)
                        VALUES (:train_num, :train_name, :source_id, :dest_id, :dep_time, :arr_time, :seats, :fare)
                    ");
                    $stmt->execute([
                        'train_num'  => $trainNum,
                        'train_name' => $trainName,
                        'source_id'  => $sourceId,
                        'dest_id'    => $destId,
                        'dep_time'   => $depTime,
                        'arr_time'   => $arrTime,
                        'seats'      => $seats,
                        'fare'       => $fare,
                    ]);
                    $success = "Train #{$trainNum} created successfully.";
                }
            } catch (PDOException $e) {
                $errors[] = "Database operation failed: " . $e->getMessage();
            }
        }
    }
}

// Fetch all trains with station names
$trains = [];
try {
    $stmt = $pdo->query("
        SELECT t.*, 
               s1.station_name AS source_name, s1.station_code AS source_code,
               s2.station_name AS dest_name, s2.station_code AS dest_code
        FROM trains t
        JOIN stations s1 ON t.source_station_id = s1.station_id
        JOIN stations s2 ON t.destination_station_id = s2.station_id
        ORDER BY t.train_number ASC
    ");
    $trains = $stmt->fetchAll();
} catch (PDOException $e) {
    $errors[] = "Error loading trains list.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Trains — RailReserve</title>
    <link rel="stylesheet" href="<?= BASE_PATH ?>/css/style.css">
</head>
<body>
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="container">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px;">
            <div>
                <h1>Manage Trains</h1>
                <p style="color: var(--color-muted);">Add, edit, or configure daily express trains and capacities.</p>
            </div>
            <button type="button" class="btn btn-primary" onclick="openAddTrainModal()">
                + Add Train
            </button>
        </div>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $err): ?>
                    <div><?= htmlspecialchars($err) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Trains Data Table -->
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Train No.</th>
                        <th>Train Name</th>
                        <th>Source</th>
                        <th>Destination</th>
                        <th>Departure</th>
                        <th>Arrival</th>
                        <th>Total Seats</th>
                        <th>Fare</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($trains)): ?>
                        <tr>
                            <td colspan="9" class="empty-state">No trains configured in database.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($trains as $tr): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($tr['train_number']) ?></strong></td>
                                <td><?= htmlspecialchars($tr['train_name']) ?></td>
                                <td><?= htmlspecialchars($tr['source_name']) ?> (<?= htmlspecialchars($tr['source_code']) ?>)</td>
                                <td><?= htmlspecialchars($tr['dest_name']) ?> (<?= htmlspecialchars($tr['dest_code']) ?>)</td>
                                <td><?= format_time($tr['departure_time']) ?></td>
                                <td><?= format_time($tr['arrival_time']) ?></td>
                                <td><?= (int)$tr['total_seats'] ?> seats</td>
                                <td>₹<?= number_format($tr['fare'], 2) ?></td>
                                <td>
                                    <div style="display:flex; gap: 8px;">
                                        <button type="button" class="btn btn-secondary btn-sm" 
                                                onclick='openEditTrainModal(<?= json_encode($tr) ?>)'>
                                            Edit
                                        </button>
                                        <form action="<?= BASE_PATH ?>/admin/manage-trains.php" method="POST" 
                                              onsubmit="return confirm('Are you sure you want to delete Train <?= htmlspecialchars($tr['train_number']) ?>?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="train_id" value="<?= $tr['train_id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <!-- Add/Edit Train Modal -->
    <div class="modal-backdrop" id="trainModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3 id="modalTitle">Add Train</h3>
                <button type="button" class="modal-close" onclick="closeTrainModal()">&times;</button>
            </div>
            <form action="<?= BASE_PATH ?>/admin/manage-trains.php" method="POST" id="trainForm">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="train_id" id="modalTrainId" value="0">

                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="modalTrainNumber">Train Number <span style="color:var(--color-danger)">*</span></label>
                                <input type="text" name="train_number" id="modalTrainNumber" class="form-control" required placeholder="e.g. 12951">
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="modalTrainName">Train Name <span style="color:var(--color-danger)">*</span></label>
                                <input type="text" name="train_name" id="modalTrainName" class="form-control" required placeholder="e.g. Rajdhani Express">
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="modalSourceId">Source Station <span style="color:var(--color-danger)">*</span></label>
                                <select name="source_station_id" id="modalSourceId" class="form-control" required>
                                    <option value="">-- Select Source --</option>
                                    <?php foreach ($stations as $st): ?>
                                        <option value="<?= $st['station_id'] ?>">
                                            <?= htmlspecialchars($st['station_name']) ?> (<?= htmlspecialchars($st['station_code']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="modalDestId">Destination Station <span style="color:var(--color-danger)">*</span></label>
                                <select name="destination_station_id" id="modalDestId" class="form-control" required>
                                    <option value="">-- Select Destination --</option>
                                    <?php foreach ($stations as $st): ?>
                                        <option value="<?= $st['station_id'] ?>">
                                            <?= htmlspecialchars($st['station_name']) ?> (<?= htmlspecialchars($st['station_code']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="modalDepTime">Departure Time <span style="color:var(--color-danger)">*</span></label>
                                <input type="time" name="departure_time" id="modalDepTime" class="form-control" required>
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="modalArrTime">Arrival Time <span style="color:var(--color-danger)">*</span></label>
                                <input type="time" name="arrival_time" id="modalArrTime" class="form-control" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-col">
                            <div class="form-group">
                                <label for="modalSeats">Total Seats Capacity <span style="color:var(--color-danger)">*</span></label>
                                <input type="number" name="total_seats" id="modalSeats" class="form-control" min="1" required placeholder="e.g. 60">
                            </div>
                        </div>
                        <div class="form-col">
                            <div class="form-group">
                                <label for="modalFare">Fare per Passenger (₹) <span style="color:var(--color-danger)">*</span></label>
                                <input type="number" step="0.01" name="fare" id="modalFare" class="form-control" min="0" required placeholder="e.g. 1850.00">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeTrainModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="modalSubmitBtn">Save Train</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('trainModal');
        const form = document.getElementById('trainForm');
        const title = document.getElementById('modalTitle');
        const trainIdInput = document.getElementById('modalTrainId');

        function openAddTrainModal() {
            form.reset();
            trainIdInput.value = '0';
            title.innerText = 'Add New Train';
            modal.classList.add('active');
        }

        function openEditTrainModal(tr) {
            form.reset();
            trainIdInput.value = tr.train_id;
            document.getElementById('modalTrainNumber').value = tr.train_number;
            document.getElementById('modalTrainName').value = tr.train_name;
            document.getElementById('modalSourceId').value = tr.source_station_id;
            document.getElementById('modalDestId').value = tr.destination_station_id;
            document.getElementById('modalDepTime').value = tr.departure_time;
            document.getElementById('modalArrTime').value = tr.arrival_time;
            document.getElementById('modalSeats').value = tr.total_seats;
            document.getElementById('modalFare').value = tr.fare;
            title.innerText = 'Edit Train #' + tr.train_number;
            modal.classList.add('active');
        }

        function closeTrainModal() {
            modal.classList.remove('active');
        }

        // Validate stations not identical on form submission
        form.addEventListener('submit', function(e) {
            const src = document.getElementById('modalSourceId').value;
            const dst = document.getElementById('modalDestId').value;
            if (src && dst && src === dst) {
                e.preventDefault();
                alert('Source station and Destination station cannot be the same.');
            }
        });
    </script>
</body>
</html>
