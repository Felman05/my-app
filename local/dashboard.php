<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireRole('local');
$currentUser = getCurrentUser();
$pageTitle = 'Provider Dashboard';
$additionalCSS = '<link rel="stylesheet" href="/doon-app/assets/css/dashboard.css"><link rel="stylesheet" href="/doon-app/assets/css/components.css">';
try {
    $stmt = $pdo->prepare('SELECT * FROM local_provider_profiles WHERE user_id = ?');
    $stmt->execute([$currentUser['id']]);
    $profile = $stmt->fetch();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM provider_listings WHERE user_id = ?');
    $stmt->execute([$currentUser['id']]);
    $listingCount = $stmt->fetchColumn();
} catch (Exception $e) {
    $profile = null;
    $listingCount = 0;
}
?>
<?php include '../includes/header.php'; ?>
<div class="d-wrap">
<?php include '../includes/sidebar.php'; ?>
<main class="d-main">
  <div class="d-topbar"><div><h1 class="d-page-title">Provider Dashboard</h1><p class="d-page-sub">Manage listings and monitor performance.</p></div><a class="s-btn green" href="/doon-app/local/listing-create.php">Add Listing</a></div>
  <section class="kpi-row c3">
    <article class="kpi"><div class="kpi-lbl">My Listings</div><div class="kpi-val"><?php echo (int) $listingCount; ?></div><div class="kpi-sub">Active portfolio</div></article>
    <article class="kpi"><div class="kpi-lbl">Profile Status</div><div class="kpi-val"><?php echo $profile && $profile['is_verified'] ? 'OK' : 'PENDING'; ?></div><div class="kpi-sub"><?php echo $profile && $profile['is_verified'] ? 'Verified account' : 'Verification in progress'; ?></div></article>
    <article class="kpi"><div class="kpi-lbl">Quick Link</div><div class="kpi-val">OPEN</div><div class="kpi-sub"><a href="/doon-app/local/listings.php">Manage listings</a></div></article>
  </section>
  <section class="dc">
    <div class="dc-title">Performance Snapshot</div>
    <div class="bar-list" style="margin-top:12px;">
      <div class="bar-row"><div class="bar-lbl">Views</div><div class="bar-bg"><div class="bar-f ac" style="width:72%"></div></div><div class="bar-val">72</div></div>
      <div class="bar-row"><div class="bar-lbl">Bookings</div><div class="bar-bg"><div class="bar-f" style="width:38%"></div></div><div class="bar-val">38</div></div>
    </div>
  </section>
</main>
</div>
<script src="/doon-app/assets/js/main.js"></script>
<?php include '../includes/footer.php'; ?>
