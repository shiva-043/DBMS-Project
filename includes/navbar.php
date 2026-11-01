<?php
/**
 * RailReserve - Global Top Navigation Bar
 */
if (!defined('BASE_PATH')) {
    require_once __DIR__ . '/../config/db.php';
}

$currentUser = current_user();
$currentScript = basename($_SERVER['SCRIPT_NAME']);
?>
<header class="navbar no-print">
    <div class="nav-container">
        <a href="<?= BASE_PATH ?>/index.php" class="brand">
            <span class="brand-icon">🚆</span>
            <span>RailReserve</span>
        </a>
        <nav>
            <ul class="nav-links">
                <?php if (!$currentUser): ?>
                    <!-- Public Navigation -->
                    <li><a href="<?= BASE_PATH ?>/index.php" class="<?= $currentScript === 'index.php' ? 'active' : '' ?>">Home</a></li>
                    <li><a href="<?= BASE_PATH ?>/login.php" class="<?= $currentScript === 'login.php' ? 'active' : '' ?>">Login</a></li>
                    <li><a href="<?= BASE_PATH ?>/register.php" class="<?= $currentScript === 'register.php' ? 'active' : '' ?>">Register</a></li>
                <?php elseif ($currentUser['role'] === 'ADMIN'): ?>
                    <!-- Admin Navigation -->
                    <li><a href="<?= BASE_PATH ?>/admin/dashboard.php" class="<?= $currentScript === 'dashboard.php' ? 'active' : '' ?>">Admin Dashboard</a></li>
                    <li><a href="<?= BASE_PATH ?>/admin/manage-trains.php" class="<?= $currentScript === 'manage-trains.php' ? 'active' : '' ?>">Manage Trains</a></li>
                    <li><a href="<?= BASE_PATH ?>/admin/manage-stations.php" class="<?= $currentScript === 'manage-stations.php' ? 'active' : '' ?>">Manage Stations</a></li>
                    <li><a href="<?= BASE_PATH ?>/admin/manage-bookings.php" class="<?= $currentScript === 'manage-bookings.php' ? 'active' : '' ?>">Manage Bookings</a></li>
                    <li><a href="<?= BASE_PATH ?>/admin/manage-users.php" class="<?= $currentScript === 'manage-users.php' ? 'active' : '' ?>">Manage Users</a></li>
                    <li>
                        <a href="<?= BASE_PATH ?>/logout.php">
                            Logout <span class="nav-user-badge">Admin</span>
                        </a>
                    </li>
                <?php else: ?>
                    <!-- Logged-in User Navigation -->
                    <li><a href="<?= BASE_PATH ?>/index.php" class="<?= $currentScript === 'index.php' ? 'active' : '' ?>">Home</a></li>
                    <li><a href="<?= BASE_PATH ?>/user/search-trains.php" class="<?= $currentScript === 'search-trains.php' ? 'active' : '' ?>">Search Trains</a></li>
                    <li><a href="<?= BASE_PATH ?>/user/my-bookings.php" class="<?= in_array($currentScript, ['my-bookings.php', 'booking-confirmation.php', 'cancellation-confirmation.php']) ? 'active' : '' ?>">My Bookings</a></li>
                    <li><a href="<?= BASE_PATH ?>/user/profile.php" class="<?= $currentScript === 'profile.php' ? 'active' : '' ?>">Profile</a></li>
                    <li>
                        <a href="<?= BASE_PATH ?>/logout.php">
                            Logout <span class="nav-user-badge"><?= htmlspecialchars(explode(' ', $currentUser['full_name'])[0]) ?></span>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</header>
