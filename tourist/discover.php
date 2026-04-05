<?php
/**
 * Discover Destinations Page
 */

require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$pageTitle = 'Discover';
$additionalCSS = '<link rel="stylesheet" href="/doon-app/assets/css/dashboard.css"><link rel="stylesheet" href="/doon-app/assets/css/components.css">';

requireRole('tourist');

$currentUser = getCurrentUser();

// Get filter parameters
$provinceId = $_GET['province_id'] ?? null;
$categoryId = $_GET['category_id'] ?? null;
$priceRange = $_GET['price_range'] ?? null;
$search = trim($_GET['search'] ?? '');
$page = (int) ($_GET['page'] ?? 1);
$perPage = 12;
$offset = ($page - 1) * $perPage;

// Build query
$where = ['d.is_active = 1'];
$params = [];

if ($provinceId) {
    $where[] = 'd.province_id = ?';
    $params[] = $provinceId;
}

if ($categoryId) {
    $where[] = 'd.category_id = ?';
    $params[] = $categoryId;
}

if ($priceRange && in_array($priceRange, ['free', 'budget', 'mid_range', 'luxury'])) {
  $where[] = 'd.price_label = ?';
    $params[] = $priceRange;
}

if ($search) {
    $where[] = '(d.name LIKE ? OR d.short_description LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

$whereClause = 'WHERE ' . implode(' AND ', $where);

try {
    // Get total count
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM destinations d $whereClause");
    $countStmt->execute($params);
    $total = $countStmt->fetchColumn();
    $totalPages = ceil($total / $perPage);

    // Get destinations
    $stmt = $pdo->prepare(
        "SELECT d.*, ac.name as category_name, p.name as province_name
         FROM destinations d
         LEFT JOIN activity_categories ac ON d.category_id = ac.id
         LEFT JOIN provinces p ON d.province_id = p.id
         $whereClause
         ORDER BY d.avg_rating DESC, d.view_count DESC
         LIMIT $perPage OFFSET $offset"
    );
    $stmt->execute($params);
    $destinations = $stmt->fetchAll();

    // Get filter options
    $categories = $pdo->query('SELECT DISTINCT id, name, slug FROM activity_categories ORDER BY name')->fetchAll();
    $provinces = $pdo->query('SELECT DISTINCT id, name FROM provinces ORDER BY name')->fetchAll();

} catch (PDOException $e) {
    $destinations = [];
    $categories = [];
    $provinces = [];
    $total = 0;
    $totalPages = 1;
}
?>

<?php include '../includes/header.php'; ?>
<div class="d-wrap">
<?php include '../includes/sidebar.php'; ?>
<main class="d-main">
  <div class="d-topbar"><div><h1 class="d-page-title">Discover Destinations</h1><p class="d-page-sub"><?php echo (int) $total; ?> results found</p></div></div>

  <section class="dc mb20">
    <form method="GET" class="rec-form" style="grid-template-columns:2fr 1fr 1fr 1fr auto;">
      <div class="rf-g"><label class="rf-lbl">Search</label><input class="rf-ctrl" type="text" name="search" value="<?php echo escape($search); ?>" placeholder="Search destinations"></div>
      <div class="rf-g"><label class="rf-lbl">Province</label><select class="rf-ctrl" name="province_id"><option value="">All</option><?php foreach ($provinces as $prov): ?><option value="<?php echo $prov['id']; ?>" <?php echo $provinceId == $prov['id'] ? 'selected' : ''; ?>><?php echo escape($prov['name']); ?></option><?php endforeach; ?></select></div>
      <div class="rf-g"><label class="rf-lbl">Category</label><select class="rf-ctrl" name="category_id"><option value="">All</option><?php foreach ($categories as $cat): ?><option value="<?php echo $cat['id']; ?>" <?php echo $categoryId == $cat['id'] ? 'selected' : ''; ?>><?php echo escape($cat['name']); ?></option><?php endforeach; ?></select></div>
      <div class="rf-g"><label class="rf-lbl">Price</label><select class="rf-ctrl" name="price_range"><option value="">All</option><option value="free" <?php echo $priceRange === 'free' ? 'selected' : ''; ?>>Free</option><option value="budget" <?php echo $priceRange === 'budget' ? 'selected' : ''; ?>>Budget</option><option value="mid_range" <?php echo $priceRange === 'mid_range' ? 'selected' : ''; ?>>Mid range</option><option value="luxury" <?php echo $priceRange === 'luxury' ? 'selected' : ''; ?>>Luxury</option></select></div>
      <button class="rf-go" type="submit">Apply</button>
    </form>
  </section>

  <section class="dest-list">
    <?php foreach ($destinations as $dest): ?>
      <a class="dest-row" href="/doon-app/tourist/destination.php?id=<?php echo $dest['id']; ?>">
        <div class="dest-ico">D</div>
        <div>
          <div class="dest-name"><?php echo escape($dest['name']); ?></div>
          <div class="dest-meta"><?php echo escape($dest['province_name']); ?>  -  <?php echo escape($dest['category_name']); ?>  -  <?php echo escape($dest['price_label'] ?? 'Price on request'); ?></div>
        </div>
        <div class="dest-rating">? <?php echo number_format((float) ($dest['avg_rating'] ?? 0), 1); ?></div>
      </a>
    <?php endforeach; ?>
    <?php if (empty($destinations)): ?><div class="dest-row"><div>No destinations matched your filters.</div></div><?php endif; ?>
  </section>
</main>
</div>
<script src="/doon-app/assets/js/main.js"></script>
<?php include '../includes/footer.php'; ?>

