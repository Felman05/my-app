<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireRole('admin');
$pageTitle = 'Manage Destinations';
$additionalCSS = '<link rel="stylesheet" href="/doon-app/assets/css/dashboard.css"><link rel="stylesheet" href="/doon-app/assets/css/components.css">';

try {
    $stmt = $pdo->query('SELECT d.id, d.name, d.province_id, d.is_active, d.is_featured, p.name as province_name FROM destinations d LEFT JOIN provinces p ON d.province_id = p.id ORDER BY d.created_at DESC LIMIT 50');
    $destinations = $stmt->fetchAll();
} catch (Exception $e) {
    $destinations = [];
}
?>
<?php include '../includes/header.php'; ?>
<div class="d-wrap">
<?php include '../includes/sidebar.php'; ?>
<main class="d-main">
  <div class="d-topbar"><div><h1 class="d-page-title">Manage Destinations</h1><p class="d-page-sub">All active and inactive destination listings.</p></div></div>
  <section class="dc">
  <div style="overflow-x: auto;">
<table style="width: 100%; border-collapse: collapse;">
<thead>
<tr style="border-bottom: 2px solid var(--bd);">
<th style="padding: var(--sp2); text-align: left;">Name</th>
<th style="padding: var(--sp2); text-align: left;">Province</th>
<th style="padding: var(--sp2); text-align: left;">Featured</th>
<th style="padding: var(--sp2); text-align: left;">Active</th>
</tr>
</thead>
<tbody>
<?php foreach ($destinations as $d): ?>
<tr style="border-bottom: 1px solid var(--bd);">
<td style="padding: var(--sp2);"><?php echo escape($d['name']); ?></td>
<td style="padding: var(--sp2);"><?php echo escape($d['province_name']); ?></td>
<td style="padding: var(--sp2);"><?php echo $d['is_featured'] ? '⭐' : '-'; ?></td>
<td style="padding: var(--sp2);"><span class="badge <?php echo $d['is_active'] ? 'badge-success' : 'badge-danger'; ?>"><?php echo $d['is_active'] ? 'Active' : 'Inactive'; ?></span></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
  </section>
</main>
</div>

<script src="/doon-app/assets/js/main.js"></script>
<?php include '../includes/footer.php'; ?>
