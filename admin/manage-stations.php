<?php
require_once __DIR__ . '/../config/db.php';
require_admin();

$errors = [];
$success = '';

// Determine current form action from GET params
$formAction = $_GET['action'] ?? '';  // 'add' or 'edit'
$editStationId = (int)($_GET['station_id'] ?? 0);
$editStation = null;

// If editing, fetch the station record to pre-fill
if ($formAction === 'edit' && $editStationId > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM stations WHERE station_id = :id LIMIT 1");
        $stmt->execute(['id' => $editStationId]);
        $editStation = $stmt->fetch();
        if (!$editStation) {
            $errors[] = "Station not found.";
            $formAction = '';
        }
    } catch (PDOException $e) {
        $errors[] = "Error loading station for edit.";
        $formAction = '';
    }
}

// Handle POST actions: save or delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $stationId = (int)($_POST['station_id'] ?? 0);
        if ($stationId <= 0) {
            $errors[] = "Invalid station ID.";
        } else {
            try {
                // Check if any train references this station
                $chk = $pdo->prepare("SELECT COUNT(*) FROM trains WHERE source_station_id = :id OR destination_station_id = :id");
                $chk->execute(['id' => $stationId]);
                $trainCount = (int)$chk->fetchColumn();

                if ($trainCount > 0) {
                    $errors[] = "Cannot delete station: referenced by {$trainCount} existing train route(s).";
                } else {
                    $delStmt = $pdo->prepare("DELETE FROM stations WHERE station_id = :id");
                    $delStmt->execute(['id' => $stationId]);
                    $success = "Station deleted successfully.";
                }
            } catch (PDOException $e) {
                $errors[] = "Failed to delete station: " . $e->getMessage();
            }
        }
    } elseif ($action === 'save') {
        $stationId   = (int)($_POST['station_id'] ?? 0);
        $stationName = trim($_POST['station_name'] ?? '');
        $stationCode = strtoupper(trim($_POST['station_code'] ?? ''));
        $city        = trim($_POST['city'] ?? '');

        // Validation
        if (empty($stationName) || empty($stationCode) || empty($city)) {
            $errors[] = "Station name, station code, and city are all required.";
        } elseif (strlen($stationCode) > 10) {
            $errors[] = "Station code must be 10 characters or fewer.";
        }

        // Unique station code validation
        if (empty($errors)) {
            try {
                if ($stationId > 0) {
                    $chk = $pdo->prepare("SELECT COUNT(*) FROM stations WHERE station_code = :code AND station_id != :id");
                    $chk->execute(['code' => $stationCode, 'id' => $stationId]);
                } else {
                    $chk = $pdo->prepare("SELECT COUNT(*) FROM stations WHERE station_code = :code");
                    $chk->execute(['code' => $stationCode]);
                }
                if ((int)$chk->fetchColumn() > 0) {
                    $errors[] = "Station code '{$stationCode}' is already registered.";
                }
            } catch (PDOException $e) {
                $errors[] = "Database check failed.";
            }
        }

        // Insert or Update
        if (empty($errors)) {
            try {
                if ($stationId > 0) {
                    $stmt = $pdo->prepare("
                        UPDATE stations 
                        SET station_name = :name, station_code = :code, city = :city 
                        WHERE station_id = :id
                    ");
                    $stmt->execute([
                        'name' => $stationName,
                        'code' => $stationCode,
                        'city' => $city,
                        'id'   => $stationId,
                    ]);
                    $success = "Station '{$stationName}' updated successfully.";
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO stations (station_name, station_code, city) 
                        VALUES (:name, :code, :city)
                    ");
                    $stmt->execute([
                        'name' => $stationName,
                        'code' => $stationCode,
                        'city' => $city,
                    ]);
                    $success = "Station '{$stationName}' created successfully.";
                }
                // Clear form on success
                $formAction = '';
                $editStation = null;
            } catch (PDOException $e) {
                $errors[] = "Database operation failed: " . $e->getMessage();
                // Keep form open with entered values
                $editStation = [
                    'station_id' => $stationId, 'station_name' => $stationName,
                    'station_code' => $stationCode, 'city' => $city,
                ];
                $formAction = $stationId > 0 ? 'edit' : 'add';
            }
        } else {
            // Validation failed — keep form open with entered values
            $editStation = [
                'station_id' => $stationId, 'station_name' => $stationName,
                'station_code' => $stationCode, 'city' => $city,
            ];
            $formAction = $stationId > 0 ? 'edit' : 'add';
        }
    }
}

// Fetch all stations for the table
$stations = [];
try {
    $stations = $pdo->query("SELECT * FROM stations ORDER BY station_name ASC")->fetchAll();
} catch (PDOException $e) {
    $errors[] = "Error loading stations.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Stations — RailReserve</title>
    <link rel="stylesheet" href="<?= BASE_PATH ?>/css/style.css">
</head>
<body>
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="container">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px;">
            <div>
                <h1>Manage Stations</h1>
                <p style="color: var(--color-muted);">Configure railway junction stops, station codes, and cities.</p>
            </div>
            <?php if ($formAction !== 'add'): ?>
                <a href="<?= BASE_PATH ?>/admin/manage-stations.php?action=add" class="btn btn-primary">
                    + Add Station
                </a>
            <?php endif; ?>
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

        <!-- Inline Add/Edit Form -->
        <?php if ($formAction === 'add' || $formAction === 'edit'): ?>
            <?php
                $f = $editStation ?? [];
                $isEdit = ($formAction === 'edit' && !empty($f['station_id']));
            ?>
            <section class="card" style="border-left: 3px solid var(--color-primary);">
                <div class="card-header">
                    <h3><?= $isEdit ? 'Edit Station ' . htmlspecialchars($f['station_code'] ?? '') : 'Add New Station' ?></h3>
                    <a href="<?= BASE_PATH ?>/admin/manage-stations.php" class="btn btn-secondary btn-sm">Cancel</a>
                </div>

                <form action="<?= BASE_PATH ?>/admin/manage-stations.php" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="station_id" value="<?= (int)($f['station_id'] ?? 0) ?>">

                    <div class="form-group">
                        <label for="station_code">Station Code <span style="color:var(--color-danger)">*</span></label>
                        <input type="text" name="station_code" id="station_code" class="form-control" 
                               maxlength="10" placeholder="e.g. NDLS" style="text-transform:uppercase;" required
                               value="<?= htmlspecialchars($f['station_code'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="station_name">Station Full Name <span style="color:var(--color-danger)">*</span></label>
                        <input type="text" name="station_name" id="station_name" class="form-control" 
                               placeholder="e.g. New Delhi Railway Station" required
                               value="<?= htmlspecialchars($f['station_name'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="city">City <span style="color:var(--color-danger)">*</span></label>
                        <input type="text" name="city" id="city" class="form-control" 
                               placeholder="e.g. New Delhi" required
                               value="<?= htmlspecialchars($f['city'] ?? '') ?>">
                    </div>

                    <div style="margin-top: 10px; display: flex; gap: 10px; justify-content: flex-end;">
                        <a href="<?= BASE_PATH ?>/admin/manage-stations.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Update Station' : 'Save Station' ?></button>
                    </div>
                </form>
            </section>
        <?php endif; ?>

        <!-- Stations Table -->
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Station Code</th>
                        <th>Station Name</th>
                        <th>City</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($stations)): ?>
                        <tr>
                            <td colspan="4" class="empty-state">No stations registered yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($stations as $st): ?>
                            <tr>
                                <td>
                                    <span class="train-number"><?= htmlspecialchars($st['station_code']) ?></span>
                                </td>
                                <td><strong><?= htmlspecialchars($st['station_name']) ?></strong></td>
                                <td><?= htmlspecialchars($st['city']) ?></td>
                                <td>
                                    <div style="display:flex; gap: 8px;">
                                        <a href="<?= BASE_PATH ?>/admin/manage-stations.php?action=edit&station_id=<?= $st['station_id'] ?>" class="btn btn-secondary btn-sm">
                                            Edit
                                        </a>
                                        <form action="<?= BASE_PATH ?>/admin/manage-stations.php" method="POST">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="station_id" value="<?= $st['station_id'] ?>">
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
</body>
</html>
