<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireRole('admin');
$pageTitle = 'Manage Providers';
$additionalCSS = '<link rel="stylesheet" href="/doon-app/assets/css/dashboard.css"><link rel="stylesheet" href="/doon-app/assets/css/components.css">';
try {
    $stmt = $pdo->query('SELECT pl.*, u.name, u.email FROM provider_listings pl JOIN users u ON pl.user_id = u.id WHERE pl.status = "pending" ORDER BY pl.created_at DESC');
    $pending = $stmt->fetchAll();
} catch (Exception $e) {
    $pending = [];
}
?>
<?php include '../includes/header.php'; ?>
<div class="dashboard-layout">
<?php include '../includes/sidebar.php'; ?>
<main class="main-content">
<h1>Pending Provider Approvals</h1>
<div class="grid grid-2">
<?php foreach ($pending as $p): ?>
<div class="card">
<h3 style="margin-top: 0;"><?php echo escape($p['title']); ?></h3>
<p>By: <strong><?php echo escape($p['name']); ?></strong></p>
<p style="color: var(--i3);"><?php echo escape($p['email']); ?></p>
<div style="display: flex; gap: var(--sp2);">
<button class="btn btn-accent btn-small" onclick="approveProvider(<?php echo $p['id']; ?>)">Approve</button>
<button class="btn btn-secondary btn-small" onclick="rejectProvider(<?php echo $p['id']; ?>)">Reject</button>
</div>
</div>
<?php endforeach; ?>
</div>
<?php if (empty($pending)): ?>
<p style="color: var(--i3);">No pending approvals.</p>
<?php endif; ?>
</main>
</div>
<script>
function approveProvider(id) { if (confirm('Approve this provider?')) { console.log('Approving provider ' + id); } }
function rejectProvider(id) { if (confirm('Reject this provider?')) { console.log('Rejecting provider ' + id); } }
</script>
<link rel="stylesheet" href="/doon-app/assets/css/main.css">
<script src="/doon-app/assets/js/main.js"></script>
<?php include '../includes/footer.php'; ?>
