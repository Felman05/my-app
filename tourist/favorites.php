<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireRole('tourist');
$currentUser = getCurrentUser();
$pageTitle = 'Favorites';
$additionalCSS = '<link rel="stylesheet" href="/doon-app/assets/css/dashboard.css"><link rel="stylesheet" href="/doon-app/assets/css/components.css">';

// Handle remove
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_dest'])) {
    $removeId = (int) $_POST['remove_dest'];
    try {
        $stmt = $pdo->prepare('DELETE FROM favorites WHERE user_id = ? AND destination_id = ?');
        $stmt->execute([$currentUser['id'], $removeId]);
    } catch (Exception $e) {}
    header('Location: /doon-app/tourist/favorites.php');
    exit;
}

try {
    $stmt = $pdo->prepare(
        'SELECT d.id, d.name, d.short_description, d.avg_rating, d.view_count,
                p.name AS province_name, ac.name AS category_name, f.created_at AS saved_at
         FROM favorites f
         JOIN destinations d ON f.destination_id = d.id
         LEFT JOIN provinces p ON d.province_id = p.id
         LEFT JOIN activity_categories ac ON d.category_id = ac.id
         WHERE f.user_id = ?
         ORDER BY f.created_at DESC'
    );
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
  <div class="d-topbar">
    <div><h1 class="d-page-title">Favorites</h1><p class="d-page-sub">Your saved destinations (<?php echo count($favorites); ?>).</p></div>
    <a class="s-btn dark" href="/doon-app/tourist/discover.php">Discover More</a>
  </div>

  <?php if (empty($favorites)): ?>
  <section class="dc">
    <div class="dest-row" style="opacity:.5;">No saved destinations yet. Browse <a href="/doon-app/tourist/discover.php">Discover</a> and tap Save on any destination.</div>
  </section>
  <?php else: ?>
  <div class="dest-grid">
    <?php foreach ($favorites as $d): ?>
    <div class="dest-card">
      <div class="dest-card-body">
        <div class="dest-card-type"><?php echo escape($d['category_name'] ?? 'Destination'); ?></div>
        <div class="dest-card-name"><?php echo escape($d['name']); ?></div>
        <div class="dest-card-meta"><?php echo escape($d['province_name']); ?></div>
        <?php if ($d['short_description']): ?>
        <p class="dest-card-desc"><?php echo escape(mb_strimwidth($d['short_description'], 0, 100, '...')); ?></p>
        <?php endif; ?>
        <div class="dest-card-footer">
          <?php if ($d['avg_rating']): ?>
          <span class="badge badge-primary"><?php echo number_format((float) $d['avg_rating'], 1); ?> / 5</span>
          <?php endif; ?>
          <span class="badge"><?php echo number_format((int) $d['view_count']); ?> views</span>
        </div>
        <div style="display:flex;gap:8px;margin-top:10px;">
          <a class="s-btn dark" href="/doon-app/tourist/destination.php?id=<?php echo $d['id']; ?>" style="flex:1;text-align:center;">View</a>
          <form method="POST" style="flex:1;" onsubmit="return confirm('Remove from favorites?');">
            <input type="hidden" name="remove_dest" value="<?php echo $d['id']; ?>">
            <button class="s-btn" type="submit" style="width:100%;">Remove</button>
          </form>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</main>
</div>
<script src="/doon-app/assets/js/main.js"></script>
<?php include '../includes/footer.php'; ?>
