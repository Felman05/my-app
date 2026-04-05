<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireRole('admin');
$pageTitle = 'Manage Users';
$additionalCSS = '<link rel="stylesheet" href="/doon-app/assets/css/dashboard.css"><link rel="stylesheet" href="/doon-app/assets/css/components.css">';
try {
    $stmt = $pdo->query('SELECT id, name, email, role, is_active, created_at FROM users ORDER BY created_at DESC');
    $users = $stmt->fetchAll();
} catch (Exception $e) {
    $users = [];
}
?>
<?php include '../includes/header.php'; ?>
<div class="dashboard-layout">
<?php include '../includes/sidebar.php'; ?>
<main class="main-content">
<h1>Manage Users</h1>
<div style="overflow-x: auto;">
<table style="width: 100%; border-collapse: collapse;">
<thead>
<tr style="border-bottom: 2px solid var(--bd);">
<th style="padding: var(--sp2); text-align: left;">Name</th>
<th style="padding: var(--sp2); text-align: left;">Email</th>
<th style="padding: var(--sp2); text-align: left;">Role</th>
<th style="padding: var(--sp2); text-align: left;">Status</th>
<th style="padding: var(--sp2); text-align: left;">Joined</th>
</tr>
</thead>
<tbody>
<?php foreach ($users as $user): ?>
<tr style="border-bottom: 1px solid var(--bd);">
<td style="padding: var(--sp2);"><?php echo escape($user['name']); ?></td>
<td style="padding: var(--sp2);"><?php echo escape($user['email']); ?></td>
<td style="padding: var(--sp2);"><span class="badge badge-primary"><?php echo ucfirst($user['role']); ?></span></td>
<td style="padding: var(--sp2);"><span class="badge <?php echo $user['is_active'] ? 'badge-success' : 'badge-danger'; ?>"><?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?></span></td>
<td style="padding: var(--sp2);"><?php echo formatDate($user['created_at']); ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</main>
</div>
<link rel="stylesheet" href="/doon-app/assets/css/main.css">
<script src="/doon-app/assets/js/main.js"></script>
<?php include '../includes/footer.php'; ?>
