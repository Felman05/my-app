<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireRole('admin');
$pageTitle = 'Reports';
$additionalCSS = '<link rel="stylesheet" href="/doon-app/assets/css/dashboard.css"><link rel="stylesheet" href="/doon-app/assets/css/components.css">';
?>
<?php include '../includes/header.php'; ?>
<div class="dashboard-layout">
<?php include '../includes/sidebar.php'; ?>
<main class="main-content">
<h1>Reports</h1>
<div class="card">
<p>Monthly LGU reports feature coming soon.</p>
</div>
</main>
</div>
<link rel="stylesheet" href="/doon-app/assets/css/main.css">
<script src="/doon-app/assets/js/main.js"></script>
<?php include '../includes/footer.php'; ?>
