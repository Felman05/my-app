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
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM provider_listings pl
         JOIN local_provider_profiles lpp ON pl.provider_id = lpp.id
         WHERE lpp.user_id = ?'
    );
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
    <div class="dc-head"><div><div class="dc-title">Recent Listings</div></div><a class="s-btn" href="/doon-app/local/analytics.php">Full Analytics</a></div>
    <div class="dest-list" style="margin-top:8px;">
      <?php if ($profile): ?>
      <?php
        try {
            $stmt2 = $pdo->prepare(
                'SELECT pl.listing_title, pl.status, pl.listing_type FROM provider_listings pl
                 JOIN local_provider_profiles lpp ON pl.provider_id = lpp.id
                 WHERE lpp.user_id = ? ORDER BY pl.created_at DESC LIMIT 5'
            );
            $stmt2->execute([$currentUser['id']]);
            $recentListings = $stmt2->fetchAll();
        } catch (Exception $e) { $recentListings = []; }
        foreach ($recentListings as $rl):
      ?>
      <div class="dest-row">
        <div class="dest-ico">L</div>
        <div>
          <div class="dest-name"><?php echo escape($rl['listing_title']); ?></div>
          <div class="dest-meta"><?php echo ucfirst(str_replace('_', ' ', $rl['listing_type'])); ?></div>
        </div>
        <span class="badge <?php echo $rl['status'] === 'active' ? 'badge-success' : ($rl['status'] === 'rejected' ? 'badge-danger' : 'badge-primary'); ?>">
          <?php echo ucfirst($rl['status']); ?>
        </span>
      </div>
      <?php endforeach; ?>
      <?php if (empty($recentListings ?? [])): ?>
      <div class="dest-row" style="opacity:.5;">No listings yet. <a href="/doon-app/local/listing-create.php">Create one.</a></div>
      <?php endif; ?>
      <?php endif; ?>
    </div>
  </section>
</main>
</div>
<script src="/doon-app/assets/js/main.js"></script>
<?php include '../includes/footer.php'; ?>
