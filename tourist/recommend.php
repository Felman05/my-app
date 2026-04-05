<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireRole('tourist');
$currentUser = getCurrentUser();
$pageTitle = 'Recommendations';
$additionalCSS = '<link rel="stylesheet" href="/doon-app/assets/css/dashboard.css"><link rel="stylesheet" href="/doon-app/assets/css/components.css">';
$recommendations = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $budget = $_POST['budget'] ?? 'mid_range';
    $province_id = $_POST['province_id'] ?? null;
    $category_id = $_POST['category_id'] ?? null;
    try {
        $where = ['d.is_active = 1'];
        $params = [];
        if ($budget && in_array($budget, ['free', 'budget', 'mid_range', 'luxury'])) {
          $where[] = 'd.price_label = ?'; $params[] = $budget;
        }
        if ($province_id) { $where[] = 'd.province_id = ?'; $params[] = $province_id; }
        if ($category_id) { $where[] = 'd.category_id = ?'; $params[] = $category_id; }
        $sql = 'SELECT d.*, p.name as province_name FROM destinations d LEFT JOIN provinces p ON d.province_id = p.id WHERE ' . implode(' AND ', $where) . ' ORDER BY d.avg_rating DESC LIMIT 10';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $recommendations = $stmt->fetchAll();
    } catch (Exception $e) {}
}
$provinces = $pdo->query('SELECT * FROM provinces ORDER BY name')->fetchAll();
$categories = $pdo->query('SELECT * FROM activity_categories ORDER BY name')->fetchAll();
?>
<?php include '../includes/header.php'; ?>
<div class="d-wrap">
<?php include '../includes/sidebar.php'; ?>
<main class="d-main">
  <div class="d-topbar"><div><h1 class="d-page-title">Recommendations</h1><p class="d-page-sub">Filter based on your travel preferences.</p></div></div>
  <div class="g31">
    <section class="dc">
      <div class="dc-title mb16">Preference Form</div>
      <form method="POST" class="rec-form" style="grid-template-columns:1fr 1fr 1fr auto;">
        <div class="rf-g"><label class="rf-lbl">Budget</label><select class="rf-ctrl" name="budget"><option value="free">Free</option><option value="budget" selected>Budget</option><option value="mid_range">Mid range</option><option value="luxury">Luxury</option></select></div>
        <div class="rf-g"><label class="rf-lbl">Province</label><select class="rf-ctrl" name="province_id"><option value="">All</option><?php foreach ($provinces as $p): ?><option value="<?php echo $p['id']; ?>"><?php echo escape($p['name']); ?></option><?php endforeach; ?></select></div>
        <div class="rf-g"><label class="rf-lbl">Category</label><select class="rf-ctrl" name="category_id"><option value="">All</option><?php foreach ($categories as $c): ?><option value="<?php echo $c['id']; ?>"><?php echo escape($c['name']); ?></option><?php endforeach; ?></select></div>
        <button class="rf-go" type="submit">Get Picks</button>
      </form>
    </section>
    <section class="dc">
      <div class="dc-title">Packing Suggestions</div>
      <div class="sub-lbl" style="margin-top:12px;">Based on selected budget and weather</div>
      <div class="pack-row"><span class="pack-chip ess">Water</span><span class="pack-chip ess">Cashless card</span><span class="pack-chip">Light jacket</span><span class="pack-chip">Umbrella</span></div>
    </section>
  </div>

  <section class="dc" style="margin-top:16px;">
    <div class="dc-head"><div><div class="dc-title">Results</div><div class="dc-sub"><?php echo count($recommendations); ?> destination(s)</div></div></div>
    <div class="dest-list">
      <?php foreach ($recommendations as $rec): ?>
      <a href="/doon-app/tourist/destination.php?id=<?php echo $rec['id']; ?>" class="dest-row">
        <div class="dest-ico">R</div>
        <div><div class="dest-name"><?php echo escape($rec['name']); ?></div><div class="dest-meta"><?php echo escape($rec['province_name']); ?></div></div>
        <div class="dest-rating">? <?php echo number_format((float) ($rec['avg_rating'] ?? 0), 1); ?></div>
      </a>
      <?php endforeach; ?>
      <?php if (empty($recommendations)): ?><div class="dest-row">Fill in the form and submit to get recommendations.</div><?php endif; ?>
    </div>
  </section>
</main>
</div>
<script src="/doon-app/assets/js/main.js"></script>
<?php include '../includes/footer.php'; ?>
