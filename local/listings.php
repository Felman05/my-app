<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireRole('local');
$currentUser = getCurrentUser();
$pageTitle = 'My Listings';
$additionalCSS = '<link rel="stylesheet" href="/doon-app/assets/css/dashboard.css"><link rel="stylesheet" href="/doon-app/assets/css/components.css">';
try {
    $stmt = $pdo->prepare('SELECT * FROM provider_listings WHERE user_id = ? ORDER BY created_at DESC');
    $stmt->execute([$currentUser['id']]);
    $listings = $stmt->fetchAll();
} catch (Exception $e) {
    $listings = [];
}
?>
<?php include '../includes/header.php'; ?>
<div class="dashboard-layout">
<?php include '../includes/sidebar.php'; ?>
<main class="main-content">
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--sp4);">
<h1 style="margin: 0;">My Listings</h1>
<a href="/doon-app/local/listing-create.php" class="btn btn-accent">+ New Listing</a>
</div>
<div class="grid grid-2">
<?php foreach ($listings as $list): ?>
<div class="card">
<h3 style="margin-top: 0;"><?php echo escape($list['title']); ?></h3>
<p style="color: var(--i3);">Status: <strong><?php echo ucfirst($list['status']); ?></strong></p>
<a href="/doon-app/local/listing-edit.php?id=<?php echo $list['id']; ?>" class="btn btn-secondary btn-small">Edit</a>
</div>
<?php endforeach; ?>
</div>
<?php if (empty($listings)): ?>
<p style="color: var(--i3);">No listings yet. <a href="/doon-app/local/listing-create.php">Create one</a></p>
<?php endif; ?>
</main>
</div>
<link rel="stylesheet" href="/doon-app/assets/css/main.css">
<script src="/doon-app/assets/js/main.js"></script>
<?php include '../includes/footer.php'; ?>
