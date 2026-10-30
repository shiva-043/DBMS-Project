<?php
require_once __DIR__ . '/config/db.php';

// Redirect if already logged in
if (is_logged_in()) {
    if ($_SESSION['role'] === 'ADMIN') {
        header("Location: " . BASE_PATH . "/admin/dashboard.php");
        exit;
    } else {
        header("Location: " . BASE_PATH . "/user/search-trains.php");
        exit;
    }
}

$error = '';
$success = '';
$email = trim($_POST['email'] ?? '');

if (isset($_GET['registered'])) {
    $success = 'Registration successful! You can now log in with your email and password.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $error = 'Please enter both your email address and password.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT user_id, full_name, email, password, role FROM users WHERE email = :email LIMIT 1");
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch();

            // Plain text password comparison as requested (with fallback for existing hashes if any)
            if ($user && ($user['password'] === $password || password_verify($password, $user['password']))) {
                // Prevent session fixation
                session_regenerate_id(true);

                $_SESSION['user_id']   = (int)$user['user_id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['email']     = $user['email'];
                $_SESSION['role']      = $user['role'];

                // Handle role-based destination
                if ($user['role'] === 'ADMIN') {
                    // Admin always goes to dashboard
                    header("Location: " . BASE_PATH . "/admin/dashboard.php");
                    exit;
                } else {
                    // Check if user had a preserved pending search
                    if (!empty($_SESSION['pending_search'])) {
                        $searchParams = http_build_query($_SESSION['pending_search']);
                        unset($_SESSION['pending_search']);
                        header("Location: " . BASE_PATH . "/user/search-trains.php?" . $searchParams);
                        exit;
                    } else {
                        header("Location: " . BASE_PATH . "/user/search-trains.php");
                        exit;
                    }
                }
            } else {
                $error = 'Invalid email address or password.';
            }
        } catch (PDOException $e) {
            $error = 'A database error occurred. Please verify MySQL connection.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — RailReserve</title>
    <link rel="stylesheet" href="<?= BASE_PATH ?>/css/style.css">
</head>
<body>
    <?php require_once __DIR__ . '/includes/navbar.php'; ?>

    <main class="container-narrow">
        <div class="card">
            <div class="card-header">
                <h2>Account Login</h2>
            </div>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['redirect_to']) && $_GET['redirect_to'] === 'search'): ?>
                <div class="alert alert-info">
                    Please log in to continue with your train reservation.
                </div>
            <?php endif; ?>

            <form action="<?= BASE_PATH ?>/login.php" method="POST">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" name="email" id="email" class="form-control" 
                           value="<?= htmlspecialchars($email) ?>" placeholder="e.g. user@railway.com" required autofocus>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" name="password" id="password" class="form-control" 
                           placeholder="Enter your password" required>
                </div>

                <div style="margin-top: 20px;">
                    <button type="submit" class="btn btn-primary btn-block">Log In</button>
                </div>
            </form>

            <div style="margin-top: 20px; text-align: center; font-size: 13px; color: var(--color-muted);">
                Don't have an account? <a href="<?= BASE_PATH ?>/register.php">Register here</a>
            </div>
        </div>
    </main>
</body>
</html>
