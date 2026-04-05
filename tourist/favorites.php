<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireRole('tourist');
$currentUser = getCurrentUser();
$pageTitle = 'Favorites';
$additionalCSS = '<link rel="stylesheet" href="/doon-app/assets/css/dashboard.css"><link rel="stylesheet" href="/doon-app/assets/css/components.css">';
try {
    $stmt = $pdo->prepare('SELECT d.*, p.name as province_name FROM destinations d JOIN favorites f ON d.id = f.destination_id LEFT JOIN provinces p ON d.province_id = p.id WHERE f.user_id = ? ORDER BY f.created_at DESC');
    $stmt->execute([$currentUser['id']]);
    $favorites = $stmt->fetchAll();
} catch (Exception $e) {
    $favorites = [];
}
?>
<?php include '../includes/header.php'; ?>
<div class="d-wrap">
<?php include '../includes/sidebar.php'; ?>
<main class="d-main">
  <div class="d-topbar"><div><h1 class="d-page-title">Saved Destinations</h1><p class="d-page-sub">Your personal shortlist.</p></div></div>
  <section class="dest-list">
    <?php foreach ($favorites as $fav): ?>
    <a href="/doon-app/tourist/destination.php?id=<?php echo $fav['id']; ?>" class="dest-row">
      <div class="dest-ico">S</div>
      <div><div class="dest-name"><?php echo escape($fav['name']); ?></div><div class="dest-meta"><?php echo escape($fav['province_name']); ?></div></div>
    </a>
    <?php endforeach; ?>
    <?php if (empty($favorites)): ?><div class="dest-row">No saved destinations yet.</div><?php endif; ?>
  </section>
</main>
</div>
<script src="/doon-app/assets/js/main.js"></script>
<?php include '../includes/footer.php'; ?>
