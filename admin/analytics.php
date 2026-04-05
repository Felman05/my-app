<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireRole('admin');
$pageTitle = 'Analytics';
$additionalCSS = '<link rel="stylesheet" href="/doon-app/assets/css/dashboard.css"><link rel="stylesheet" href="/doon-app/assets/css/components.css">';
try {
    $viewCount = $pdo->query('SELECT COUNT(*) FROM analytics_events WHERE event_type = "view_destination"')->fetchColumn();
    $recCount = $pdo->query('SELECT COUNT(*) FROM recommendation_requests')->fetchColumn();
} catch (Exception $e) {
    $viewCount = $recCount = 0;
}
?>
<?php include '../includes/header.php'; ?>
<div class="dashboard-layout">
<?php include '../includes/sidebar.php'; ?>
<main class="main-content">
<h1>Analytics</h1>
<div class="dashboard-grid">
<div class="card">
<div class="stat-card-label">Destination Views</div>
<div class="stat-card-value" style="color: var(--ac);"><?php echo $viewCount; ?></div>
</div>
<div class="card">
<div class="stat-card-label">Recommendations Given</div>
<div class="stat-card-value" style="color: var(--ac);"><?php echo $recCount; ?></div>
</div>
</div>
<div class="card">
<h2>Charts Coming Soon</h2>
<p>Analytics visualizations will be available here.</p>
</div>
</main>
</div>
<link rel="stylesheet" href="/doon-app/assets/css/main.css">
<script src="/doon-app/assets/js/main.js"></script>
<?php include '../includes/footer.php'; ?>
