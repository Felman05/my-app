<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireRole('local');
$currentUser = getCurrentUser();
$pageTitle = 'My Listings';
$additionalCSS = '<link rel="stylesheet" href="/doon-app/assets/css/dashboard.css"><link rel="stylesheet" href="/doon-app/assets/css/components.css">';

try {
    $stmt = $pdo->prepare(
        'SELECT pl.* FROM provider_listings pl
         JOIN local_provider_profiles lpp ON pl.provider_id = lpp.id
         WHERE lpp.user_id = ?
         ORDER BY pl.created_at DESC'
    );
    $stmt->execute([$currentUser['id']]);
    $listings = $stmt->fetchAll();
} catch (Exception $e) {
    $listings = [];
}

$statusLabels = [
    'pending'  => 'Pending Review',
    'active'   => 'Active',
    'inactive' => 'Inactive',
    'rejected' => 'Rejected',
];
?>
<?php include '../includes/header.php'; ?>
<div class="d-wrap">
<?php include '../includes/sidebar.php'; ?>
<main class="d-main">
  <div class="d-topbar">
    <div><h1 class="d-page-title">My Listings</h1><p class="d-page-sub">All your submitted listings.</p></div>
    <a href="/doon-app/local/listing-create.php" class="s-btn green">+ New Listing</a>
  </div>

  <?php if (isset($_GET['updated'])): ?>
  <div class="alert ok" style="margin-bottom:12px;">Listing updated successfully.</div>
  <?php endif; ?>

  <section class="dc">
    <div class="dest-list">
      <?php foreach ($listings as $list): ?>
      <div class="dest-row">
        <div class="dest-ico">L</div>
        <div>
          <div class="dest-name"><?php echo escape($list['listing_title']); ?></div>
          <div class="dest-meta"><?php echo ucfirst(str_replace('_', ' ', $list['listing_type'])); ?> &mdash; <?php echo $statusLabels[$list['status']] ?? ucfirst($list['status']); ?></div>
          <?php if ($list['status'] === 'rejected' && !empty($list['rejection_reason'])): ?>
          <div class="dest-meta" style="color:var(--err);">Reason: <?php echo escape($list['rejection_reason']); ?></div>
          <?php endif; ?>
        </div>
        <a href="/doon-app/local/listing-edit.php?id=<?php echo (int) $list['id']; ?>" class="s-btn dark">Edit</a>
      </div>
      <?php endforeach; ?>
      <?php if (empty($listings)): ?>
      <div class="dest-row"><div>No listings yet. <a href="/doon-app/local/listing-create.php">Create one</a></div></div>
      <?php endif; ?>
    </div>
  </section>
</main>
</div>
<script src="/doon-app/assets/js/main.js"></script>
<?php include '../includes/footer.php'; ?>
