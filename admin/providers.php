<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireRole('admin');
$pageTitle = 'Manage Providers';
$additionalCSS = '<link rel="stylesheet" href="/doon-app/assets/css/dashboard.css"><link rel="stylesheet" href="/doon-app/assets/css/components.css">';

// Handle approve / reject POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $listingId = (int) ($_POST['listing_id'] ?? 0);
    $action    = $_POST['action'] ?? '';

    if ($listingId && in_array($action, ['approve', 'reject'])) {
        $newStatus = $action === 'approve' ? 'active' : 'rejected';
        $reason    = $action === 'reject' ? trim($_POST['rejection_reason'] ?? 'Does not meet listing standards.') : null;
        try {
            $stmt = $pdo->prepare(
                'UPDATE provider_listings
                 SET status = ?, rejection_reason = ?, reviewed_by = ?, reviewed_at = NOW(), updated_at = NOW()
                 WHERE id = ?'
            );
            $stmt->execute([$newStatus, $reason, $_SESSION['user_id'], $listingId]);
        } catch (Exception $e) {}
    }
    header('Location: /doon-app/admin/providers.php');
    exit;
}

try {
    $pending = $pdo->query(
        'SELECT pl.id, pl.listing_title, pl.listing_type, pl.description, pl.price_label, pl.status, pl.created_at,
                u.name AS provider_name, u.email AS provider_email
         FROM provider_listings pl
         JOIN local_provider_profiles lpp ON pl.provider_id = lpp.id
         JOIN users u ON lpp.user_id = u.id
         WHERE pl.status = "pending"
         ORDER BY pl.created_at DESC'
    )->fetchAll();
} catch (Exception $e) {
    $pending = [];
}
?>
<?php include '../includes/header.php'; ?>
<div class="d-wrap">
<?php include '../includes/sidebar.php'; ?>
<main class="d-main">
  <div class="d-topbar">
    <div><h1 class="d-page-title">Pending Provider Listings</h1><p class="d-page-sub"><?php echo count($pending); ?> awaiting review.</p></div>
  </div>

  <section class="dc">
    <div class="dest-list">
      <?php foreach ($pending as $p): ?>
      <div class="dest-row" style="flex-wrap:wrap;gap:12px;align-items:flex-start;">
        <div class="dest-ico">P</div>
        <div style="flex:1;min-width:200px;">
          <div class="dest-name"><?php echo escape($p['listing_title']); ?></div>
          <div class="dest-meta"><?php echo ucfirst(str_replace('_', ' ', $p['listing_type'])); ?> &mdash; by <?php echo escape($p['provider_name']); ?> (<?php echo escape($p['provider_email']); ?>)</div>
          <?php if (!empty($p['description'])): ?>
          <div class="dest-meta" style="margin-top:4px;"><?php echo escape(mb_strimwidth($p['description'], 0, 120, '...')); ?></div>
          <?php endif; ?>
        </div>
        <div style="display:flex;gap:8px;align-items:center;">
          <form method="POST" style="display:inline;">
            <input type="hidden" name="listing_id" value="<?php echo (int) $p['id']; ?>">
            <input type="hidden" name="action" value="approve">
            <button class="s-btn green" type="submit">Approve</button>
          </form>
          <form method="POST" style="display:inline;" onsubmit="return confirmReject(this);">
            <input type="hidden" name="listing_id" value="<?php echo (int) $p['id']; ?>">
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="rejection_reason" class="reject-reason" value="Does not meet listing standards.">
            <button class="s-btn dark" type="submit">Reject</button>
          </form>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if (empty($pending)): ?>
      <div class="dest-row"><div>No pending listings.</div></div>
      <?php endif; ?>
    </div>
  </section>
</main>
</div>
<script>
function confirmReject(form) {
  var reason = prompt('Rejection reason (optional):', 'Does not meet listing standards.');
  if (reason === null) return false;
  form.querySelector('.reject-reason').value = reason || 'Does not meet listing standards.';
  return true;
}
</script>
<script src="/doon-app/assets/js/main.js"></script>
<?php include '../includes/footer.php'; ?>
