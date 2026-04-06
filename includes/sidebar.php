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
            <a href="/doon-app/tourist/dashboard.php" class="sb-item <?php echo isActive('dashboard.php'); ?>"><span class="sb-ico">D</span>Dashboard</a>
            <a href="/doon-app/tourist/discover.php" class="sb-item <?php echo isActive('discover.php'); ?>"><span class="sb-ico">F</span>Discover</a>

            <div class="sb-section">Tools</div>
            <a href="/doon-app/tourist/recommend.php" class="sb-item <?php echo isActive('recommend.php'); ?>"><span class="sb-ico">R</span>Recommendations</a>
            <a href="/doon-app/tourist/map.php" class="sb-item <?php echo isActive('map.php'); ?>"><span class="sb-ico">M</span>Map</a>
            <a href="/doon-app/tourist/itinerary.php" class="sb-item <?php echo isActive('itinerary.php'); ?>"><span class="sb-ico">I</span>Itineraries</a>
            <a href="/doon-app/tourist/favorites.php" class="sb-item <?php echo isActive('favorites.php'); ?>"><span class="sb-ico">S</span>Favorites</a>
            <a href="/doon-app/tourist/directory.php" class="sb-item <?php echo isActive('directory.php'); ?>"><span class="sb-ico">B</span>Directory</a>
            <a href="/doon-app/tourist/chatbot.php" class="sb-item <?php echo isActive('chatbot.php'); ?>"><span class="sb-ico">C</span>Chatbot</a>
            <a href="/doon-app/tourist/weather.php" class="sb-item <?php echo isActive('weather.php'); ?>"><span class="sb-ico">W</span>Weather</a>

            <div class="sb-section">Account</div>
            <a href="/doon-app/tourist/profile.php" class="sb-item <?php echo isActive('profile.php'); ?>"><span class="sb-ico">P</span>Profile</a>
            <a href="/doon-app/api/auth.php?action=logout" class="sb-item"><span class="sb-ico">L</span>Logout</a>
        </nav>
    <?php elseif ($userRole === 'local'): ?>
        <nav class="sb-nav">
            <div class="sb-section">Main</div>
            <a href="/doon-app/local/dashboard.php" class="sb-item <?php echo isActive('dashboard.php'); ?>"><span class="sb-ico">D</span>Dashboard</a>

            <div class="sb-section">Management</div>
            <a href="/doon-app/local/listings.php" class="sb-item <?php echo isActive('listings.php'); ?>"><span class="sb-ico">L</span>My Listings</a>
            <a href="/doon-app/local/listing-create.php" class="sb-item <?php echo isActive('listing-create.php'); ?>"><span class="sb-ico">N</span>New Listing</a>
            <a href="/doon-app/local/analytics.php" class="sb-item <?php echo isActive('analytics.php'); ?>"><span class="sb-ico">A</span>Analytics</a>

            <div class="sb-section">Account</div>
            <a href="/doon-app/local/profile.php" class="sb-item <?php echo isActive('profile.php'); ?>"><span class="sb-ico">P</span>Profile</a>
            <a href="/doon-app/api/auth.php?action=logout" class="sb-item"><span class="sb-ico">L</span>Logout</a>
        </nav>
    <?php else: ?>
        <nav class="sb-nav">
            <div class="sb-section">Main</div>
            <a href="/doon-app/admin/dashboard.php" class="sb-item <?php echo isActive('dashboard.php'); ?>"><span class="sb-ico">D</span>Dashboard</a>

            <div class="sb-section">Management</div>
            <a href="/doon-app/admin/destinations.php" class="sb-item <?php echo isActive('destinations.php'); ?>"><span class="sb-ico">T</span>Destinations</a>
            <a href="/doon-app/admin/providers.php" class="sb-item <?php echo isActive('providers.php'); ?>"><span class="sb-ico">V</span>Providers</a>
            <a href="/doon-app/admin/users.php" class="sb-item <?php echo isActive('users.php'); ?>"><span class="sb-ico">U</span>Users</a>
            <a href="/doon-app/admin/reviews.php" class="sb-item <?php echo isActive('reviews.php'); ?>"><span class="sb-ico">R</span>Reviews</a>

            <div class="sb-section">Analytics</div>
            <a href="/doon-app/admin/analytics.php" class="sb-item <?php echo isActive('analytics.php'); ?>"><span class="sb-ico">A</span>Analytics</a>
            <a href="/doon-app/admin/reports.php" class="sb-item <?php echo isActive('reports.php'); ?>"><span class="sb-ico">R</span>Reports</a>

            <div class="sb-section">Account</div>
            <a href="/doon-app/api/auth.php?action=logout" class="sb-item"><span class="sb-ico">L</span>Logout</a>
        </nav>
    <?php endif; ?>

    <div class="sb-foot">Doon Smart Tourism Platform</div>
</aside>
