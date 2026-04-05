<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireRole('tourist');
$currentUser = getCurrentUser();
$pageTitle = 'Profile';
$additionalCSS = '<link rel="stylesheet" href="/doon-app/assets/css/dashboard.css"><link rel="stylesheet" href="/doon-app/assets/css/components.css">';
?>
<?php include '../includes/header.php'; ?>
<div class="dashboard-layout">
<?php include '../includes/sidebar.php'; ?>
<main class="main-content">
<h1>My Profile</h1>
<div class="card" style="max-width: 600px;">
<p>Name: <strong><?php echo escape($currentUser['name']); ?></strong></p>
<p>Email: <strong><?php echo escape($currentUser['email']); ?></strong></p>
<p>Role: <strong><?php echo ucfirst($currentUser['role']); ?></strong></p>
<a href="/doon-app/api/auth.php?action=logout" class="btn btn-secondary">Logout</a>
</div>
</main>
</div>
<script src="/doon-app/assets/js/main.js"></script>
<?php include '../includes/footer.php'; ?>
