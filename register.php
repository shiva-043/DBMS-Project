<?php
require_once __DIR__ . '/config/db.php';

// Redirect if already logged in
if (is_logged_in()) {
    header("Location: " . BASE_PATH . "/user/search-trains.php");
    exit;
}

$errors = [];
$fullName = trim($_POST['full_name'] ?? '');
$email    = trim($_POST['email'] ?? '');
$phone    = trim($_POST['phone'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password        = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($fullName)) {
        $errors['full_name'] = 'Full name is required.';
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'A valid email address is required.';
    }
    if (empty($password)) {
        $errors['password'] = 'Password is required.';
    } elseif (strlen($password) < 4) {
        $errors['password'] = 'Password must be at least 4 characters long.';
    }
    if ($password !== $confirmPassword) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    // Check unique email if format is valid
    if (empty($errors['email'])) {
        try {
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = :email LIMIT 1");
            $stmt->execute(['email' => $email]);
            if ($stmt->fetch()) {
                $errors['email'] = 'This email address is already registered.';
            }
        } catch (PDOException $e) {
            $errors['general'] = 'Database error. Please try again.';
        }
    }

    // If no errors, create user
    if (empty($errors)) {
        try {
            // Store password as plain text as requested
            $stmt = $pdo->prepare("
                INSERT INTO users (full_name, email, password, phone, role) 
                VALUES (:full_name, :email, :password, :phone, 'USER')
            ");
            $stmt->execute([
                'full_name' => $fullName,
                'email'     => $email,
                'password'  => $password,
                'phone'     => !empty($phone) ? $phone : null,
            ]);

            header("Location: " . BASE_PATH . "/login.php?registered=1");
            exit;
        } catch (PDOException $e) {
            $errors['general'] = 'Registration failed. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — RailReserve</title>
    <link rel="stylesheet" href="<?= BASE_PATH ?>/css/style.css">
</head>
<body>
    <?php require_once __DIR__ . '/includes/navbar.php'; ?>

    <main class="container-narrow">
        <div class="card">
            <div class="card-header">
                <h2>Create an Account</h2>
            </div>

            <?php if (!empty($errors['general'])): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($errors['general']) ?>
                </div>
            <?php endif; ?>

            <form action="<?= BASE_PATH ?>/register.php" method="POST">
                <div class="form-group">
                    <label for="full_name">Full Name <span style="color:var(--color-danger)">*</span></label>
                    <input type="text" name="full_name" id="full_name" class="form-control" 
                           value="<?= htmlspecialchars($fullName) ?>" placeholder="e.g. John Doe" required>
                    <?php if (!empty($errors['full_name'])): ?>
                        <div class="field-error"><?= htmlspecialchars($errors['full_name']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="email">Email Address <span style="color:var(--color-danger)">*</span></label>
                    <input type="email" name="email" id="email" class="form-control" 
                           value="<?= htmlspecialchars($email) ?>" placeholder="e.g. john@example.com" required>
                    <?php if (!empty($errors['email'])): ?>
                        <div class="field-error"><?= htmlspecialchars($errors['email']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="phone">Phone Number (Optional)</label>
                    <input type="tel" name="phone" id="phone" class="form-control" 
                           value="<?= htmlspecialchars($phone) ?>" placeholder="e.g. 9876543210">
                </div>

                <div class="form-group">
                    <label for="password">Password <span style="color:var(--color-danger)">*</span></label>
                    <input type="password" name="password" id="password" class="form-control" 
                           placeholder="Create a password" required>
                    <?php if (!empty($errors['password'])): ?>
                        <div class="field-error"><?= htmlspecialchars($errors['password']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password <span style="color:var(--color-danger)">*</span></label>
                    <input type="password" name="confirm_password" id="confirm_password" class="form-control" 
                           placeholder="Re-enter your password" required>
                    <?php if (!empty($errors['confirm_password'])): ?>
                        <div class="field-error"><?= htmlspecialchars($errors['confirm_password']) ?></div>
                    <?php endif; ?>
                </div>

                <div style="margin-top: 24px;">
                    <button type="submit" class="btn btn-primary btn-block">Register Account</button>
                </div>
            </form>

            <div style="margin-top: 20px; text-align: center; font-size: 13px; color: var(--color-muted);">
                Already have an account? <a href="<?= BASE_PATH ?>/login.php">Login here</a>
            </div>
        </div>
    </main>
</body>
</html>
