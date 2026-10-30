<?php
require_once __DIR__ . '/../config/db.php';
require_user();

$userId = (int)$_SESSION['user_id'];
$errors = [];
$success = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $fullName = trim($_POST['full_name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');

    // Validation
    if (empty($fullName)) {
        $errors['full_name'] = 'Full name is required.';
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'A valid email address is required.';
    }

    // Check unique email (excluding current user)
    if (empty($errors['email'])) {
        try {
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = :email AND user_id != :user_id LIMIT 1");
            $stmt->execute([
                'email'   => $email,
                'user_id' => $userId
            ]);
            if ($stmt->fetch()) {
                $errors['email'] = 'This email address is already in use by another account.';
            }
        } catch (PDOException $e) {
            $errors['general'] = 'Database error verifying email.';
        }
    }

    // Update if valid
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET full_name = :full_name, email = :email, phone = :phone 
                WHERE user_id = :user_id
            ");
            $stmt->execute([
                'full_name' => $fullName,
                'email'     => $email,
                'phone'     => !empty($phone) ? $phone : null,
                'user_id'   => $userId
            ]);

            // Update session display variables
            $_SESSION['full_name'] = $fullName;
            $_SESSION['email']     = $email;

            $success = 'Profile details updated successfully.';
        } catch (PDOException $e) {
            $errors['general'] = 'Failed to update profile. Please try again.';
        }
    }
}

// Fetch current user details
try {
    $stmt = $pdo->prepare("SELECT user_id, full_name, email, phone, role FROM users WHERE user_id = :user_id LIMIT 1");
    $stmt->execute(['user_id' => $userId]);
    $user = $stmt->fetch();

    if (!$user) {
        header("Location: " . BASE_PATH . "/logout.php");
        exit;
    }
} catch (PDOException $e) {
    die("Database error loading user profile.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile — RailReserve</title>
    <link rel="stylesheet" href="<?= BASE_PATH ?>/css/style.css">
</head>
<body>
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="container-narrow">
        <div class="card">
            <div class="card-header">
                <h2>Account Profile</h2>
            </div>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors['general'])): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($errors['general']) ?>
                </div>
            <?php endif; ?>

            <form action="<?= BASE_PATH ?>/user/profile.php" method="POST">
                <?= csrf_field() ?>

                <div class="form-group">
                    <label for="full_name">Full Name <span style="color:var(--color-danger)">*</span></label>
                    <input type="text" name="full_name" id="full_name" class="form-control" 
                           value="<?= htmlspecialchars($user['full_name']) ?>" required>
                    <?php if (!empty($errors['full_name'])): ?>
                        <div class="field-error"><?= htmlspecialchars($errors['full_name']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="email">Email Address <span style="color:var(--color-danger)">*</span></label>
                    <input type="email" name="email" id="email" class="form-control" 
                           value="<?= htmlspecialchars($user['email']) ?>" required>
                    <?php if (!empty($errors['email'])): ?>
                        <div class="field-error"><?= htmlspecialchars($errors['email']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" name="phone" id="phone" class="form-control" 
                           value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="e.g. 9876543210">
                </div>

                <div class="form-group">
                    <label>Account Role</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($user['role']) ?>" readonly>
                </div>

                <div style="margin-top: 24px;">
                    <button type="submit" class="btn btn-primary btn-block">Save Changes</button>
                </div>
            </form>
        </div>
    </main>
</body>
</html>
