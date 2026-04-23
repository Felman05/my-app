<?php
/**
 * Sidebar for dashboard pages
 */
$currentUser = getCurrentUser();
$userRole = $currentUser['role'] ?? 'tourist';

function isActive($page) {
    return $page === basename($_SERVER['PHP_SELF']) ? 'active' : '';
}
?>

<aside class="d-sidebar">
    <div class="sb-logo">Doon<b>.</b><sub><?php echo strtoupper($userRole); ?></sub></div>
    <div class="sb-user">
        <div class="sb-ava"><?php echo strtoupper(substr($currentUser['name'] ?? 'User', 0, 1)); ?></div>
        <div>
            <div class="sb-name"><?php echo escape($currentUser['name'] ?? 'User'); ?></div>
            <div class="sb-role"><?php echo ucfirst($userRole); ?></div>
        </div>
    </div>

    <?php if ($userRole === 'tourist'): ?>
        <nav class="sb-nav">
            <div class="sb-section">Main</div>
            <a href="/doon-app/tourist/dashboard.php" class="sb-item <?php echo isActive('dashboard.php'); ?>"><span class="sb-ico"><i class="fa-solid fa-gauge-high"></i></span>Dashboard</a>
            <a href="/doon-app/tourist/discover.php" class="sb-item <?php echo isActive('discover.php'); ?>"><span class="sb-ico"><i class="fa-solid fa-compass"></i></span>Discover</a>

            <div class="sb-section">Tools</div>
            <a href="/doon-app/tourist/recommend.php" class="sb-item <?php echo isActive('recommend.php'); ?>"><span class="sb-ico"><i class="fa-solid fa-wand-magic-sparkles"></i></span>Recommendations</a>
            <a href="/doon-app/tourist/map.php" class="sb-item <?php echo isActive('map.php'); ?>"><span class="sb-ico"><i class="fa-solid fa-map"></i></span>Map</a>
            <a href="/doon-app/tourist/itinerary.php" class="sb-item <?php echo isActive('itinerary.php'); ?>"><span class="sb-ico"><i class="fa-solid fa-route"></i></span>Itineraries</a>
            <a href="/doon-app/tourist/favorites.php" class="sb-item <?php echo isActive('favorites.php'); ?>"><span class="sb-ico"><i class="fa-solid fa-heart"></i></span>Favorites</a>
            <a href="/doon-app/tourist/directory.php" class="sb-item <?php echo isActive('directory.php'); ?>"><span class="sb-ico"><i class="fa-solid fa-book"></i></span>Directory</a>
            <a href="/doon-app/tourist/chatbot.php" class="sb-item <?php echo isActive('chatbot.php'); ?>"><span class="sb-ico"><i class="fa-solid fa-comments"></i></span>Chatbot</a>

            <div class="sb-section">Account</div>
            <a href="/doon-app/tourist/profile.php" class="sb-item <?php echo isActive('profile.php'); ?>"><span class="sb-ico"><i class="fa-solid fa-user"></i></span>Profile</a>
            <a href="/doon-app/api/auth.php?action=logout" class="sb-item"><span class="sb-ico"><i class="fa-solid fa-right-from-bracket"></i></span>Logout</a>
        </nav>
    <?php elseif ($userRole === 'local'): ?>
        <nav class="sb-nav">
            <div class="sb-section">Main</div>
            <a href="/doon-app/local/dashboard.php" class="sb-item <?php echo isActive('dashboard.php'); ?>"><span class="sb-ico"><i class="fa-solid fa-gauge-high"></i></span>Dashboard</a>

            <div class="sb-section">Management</div>
            <a href="/doon-app/local/listings.php" class="sb-item <?php echo isActive('listings.php'); ?>"><span class="sb-ico"><i class="fa-solid fa-list"></i></span>My Listings</a>
            <a href="/doon-app/local/listing-create.php" class="sb-item <?php echo isActive('listing-create.php'); ?>"><span class="sb-ico"><i class="fa-solid fa-plus-circle"></i></span>New Listing</a>
            <a href="/doon-app/local/analytics.php" class="sb-item <?php echo isActive('analytics.php'); ?>"><span class="sb-ico"><i class="fa-solid fa-chart-line"></i></span>Analytics</a>

            <div class="sb-section">Account</div>
            <a href="/doon-app/local/profile.php" class="sb-item <?php echo isActive('profile.php'); ?>"><span class="sb-ico"><i class="fa-solid fa-user"></i></span>Profile</a>
            <a href="/doon-app/api/auth.php?action=logout" class="sb-item"><span class="sb-ico"><i class="fa-solid fa-right-from-bracket"></i></span>Logout</a>
        </nav>
    <?php else: ?>
        <nav class="sb-nav">
            <div class="sb-section">Main</div>
            <a href="/doon-app/admin/dashboard.php" class="sb-item <?php echo isActive('dashboard.php'); ?>"><span class="sb-ico"><i class="fa-solid fa-gauge-high"></i></span>Dashboard</a>

            <div class="sb-section">Management</div>
            <a href="/doon-app/admin/destinations.php" class="sb-item <?php echo isActive('destinations.php'); ?>"><span class="sb-ico"><i class="fa-solid fa-umbrella-beach"></i></span>Destinations</a>
            <a href="/doon-app/admin/providers.php" class="sb-item <?php echo isActive('providers.php'); ?>"><span class="sb-ico"><i class="fa-solid fa-handshake"></i></span>Providers</a>
            <a href="/doon-app/admin/users.php" class="sb-item <?php echo isActive('users.php'); ?>"><span class="sb-ico"><i class="fa-solid fa-users"></i></span>Users</a>
            <a href="/doon-app/admin/reviews.php" class="sb-item <?php echo isActive('reviews.php'); ?>"><span class="sb-ico"><i class="fa-solid fa-star"></i></span>Reviews</a>

            <div class="sb-section">Analytics</div>
            <a href="/doon-app/admin/analytics.php" class="sb-item <?php echo isActive('analytics.php'); ?>"><span class="sb-ico"><i class="fa-solid fa-chart-line"></i></span>Analytics</a>
            <a href="/doon-app/admin/reports.php" class="sb-item <?php echo isActive('reports.php'); ?>"><span class="sb-ico"><i class="fa-solid fa-file-lines"></i></span>Reports</a>

            <div class="sb-section">Account</div>
            <a href="/doon-app/api/auth.php?action=logout" class="sb-item"><span class="sb-ico"><i class="fa-solid fa-right-from-bracket"></i></span>Logout</a>
        </nav>
    <?php endif; ?>

    <div class="sb-foot">Doon Smart Tourism Platform</div>
</aside>
