<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireRole('tourist');
$currentUser = getCurrentUser();
$pageTitle = 'Business Directory';
$additionalCSS = '<link rel="stylesheet" href="/doon-app/assets/css/dashboard.css"><link rel="stylesheet" href="/doon-app/assets/css/components.css">';

$typeFilter   = $_GET['type'] ?? '';
$provinceFilter = (int) ($_GET['province_id'] ?? 0);
$search       = trim($_GET['search'] ?? '');
$page         = max(1, (int) ($_GET['page'] ?? 1));
$perPage      = 12;
$offset       = ($page - 1) * $perPage;

$validTypes = ['accommodation','tour_package','restaurant','transport','event','other'];

$where  = ['pl.status = "active"'];
$params = [];

if ($typeFilter && in_array($typeFilter, $validTypes)) {
    $where[]  = 'pl.listing_type = ?';
    $params[] = $typeFilter;
}
if ($provinceFilter) {
    $where[]  = 'lpp.province = (SELECT name FROM provinces WHERE id = ? LIMIT 1)';
    $params[] = $provinceFilter;
}
if ($search !== '') {
    $where[]  = '(pl.listing_title LIKE ? OR pl.description LIKE ? OR lpp.business_name LIKE ?)';
    $s = "%{$search}%";
    $params[] = $s; $params[] = $s; $params[] = $s;
}

$whereSQL = 'WHERE ' . implode(' AND ', $where);

try {
    $countStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM provider_listings pl
         JOIN local_provider_profiles lpp ON pl.provider_id = lpp.id
         $whereSQL"
    );
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();
    $totalPages = max(1, (int) ceil($total / $perPage));

    $stmt = $pdo->prepare(
        "SELECT pl.id, pl.listing_title, pl.listing_type, pl.description, pl.price, pl.price_label,
                pl.contact_number, pl.capacity, pl.availability,
                lpp.business_name, lpp.municipality, lpp.province, lpp.website_url, lpp.facebook_url
         FROM provider_listings pl
         JOIN local_provider_profiles lpp ON pl.provider_id = lpp.id
         $whereSQL
         ORDER BY pl.created_at DESC
         LIMIT $perPage OFFSET $offset"
    );
    $stmt->execute($params);
    $listings = $stmt->fetchAll();

    $provinces = $pdo->query('SELECT id, name FROM provinces ORDER BY name')->fetchAll();
} catch (Exception $e) {
    $listings = [];
    $provinces = [];
    $total = 0;
    $totalPages = 1;
}

$typeLabels = [
    'accommodation' => 'Accommodation',
    'tour_package'  => 'Tour Package',
    'restaurant'    => 'Restaurant / Food',
    'transport'     => 'Transport',
    'event'         => 'Event',
    'other'         => 'Other',
];
$priceLabels = ['free' => 'Free', 'budget' => 'Budget', 'mid_range' => 'Mid Range', 'luxury' => 'Luxury'];
?>
<?php include '../includes/header.php'; ?>
<div class="d-wrap">
<?php include '../includes/sidebar.php'; ?>
<main class="d-main">
  <div class="d-topbar">
    <div><h1 class="d-page-title">Business Directory</h1><p class="d-page-sub">Local accommodations, tours, restaurants, and services.</p></div>
  </div>

  <section class="dc" style="margin-bottom:16px;">
    <form method="GET" style="display:flex;flex-wrap:wrap;gap:8px;align-items:flex-end;">
      <div class="rf-g" style="flex:1;min-width:180px;">
        <label class="rf-lbl">Search</label>
        <input class="rf-ctrl" type="text" name="search" value="<?php echo escape($search); ?>" placeholder="Business or listing name...">
      </div>
      <div class="rf-g" style="min-width:150px;">
        <label class="rf-lbl">Type</label>
        <select class="rf-ctrl" name="type">
          <option value="">All Types</option>
          <?php foreach ($typeLabels as $val => $lbl): ?>
          <option value="<?php echo $val; ?>" <?php echo $typeFilter === $val ? 'selected' : ''; ?>><?php echo $lbl; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="rf-g" style="min-width:150px;">
        <label class="rf-lbl">Province</label>
        <select class="rf-ctrl" name="province_id">
          <option value="">All Provinces</option>
          <?php foreach ($provinces as $p): ?>
          <option value="<?php echo $p['id']; ?>" <?php echo $provinceFilter == $p['id'] ? 'selected' : ''; ?>><?php echo escape($p['name']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="rf-go" type="submit" style="margin-bottom:0;">Filter</button>
      <?php if ($search || $typeFilter || $provinceFilter): ?>
      <a class="s-btn" href="/doon-app/tourist/directory.php" style="align-self:flex-end;">Clear</a>
      <?php endif; ?>
    </form>
  </section>

  <div style="margin-bottom:12px;opacity:.6;font-size:.82rem;"><?php echo number_format($total); ?> listing<?php echo $total !== 1 ? 's' : ''; ?> found</div>

  <?php if (empty($listings)): ?>
  <section class="dc"><div class="dest-row" style="opacity:.5;">No listings found. Try adjusting your filters.</div></section>
  <?php else: ?>
  <div class="dest-grid">
    <?php foreach ($listings as $l):
        $avail = $l['availability'] ? json_decode($l['availability'], true) : null;
        $openTime  = $avail['open_time']  ?? null;
        $closeTime = $avail['close_time'] ?? null;
        $hours = ($openTime && $closeTime) ? "{$openTime} – {$closeTime}" : null;
        $imgs  = !empty($l['images']) ? json_decode($l['images'], true) : [];
        $thumb = $imgs[0] ?? null;
    ?>
    <div class="dest-card">
      <?php if ($thumb): ?>
      <div style="height:160px;overflow:hidden;border-radius:var(--r2) var(--r2) 0 0;background:var(--bg2);">
        <img src="<?php echo escape($thumb); ?>" alt="<?php echo escape($l['listing_title']); ?>"
             style="width:100%;height:100%;object-fit:cover;display:block;">
      </div>
      <?php endif; ?>
      <div class="dest-card-body">
        <div class="dest-card-type"><?php echo $typeLabels[$l['listing_type']] ?? $l['listing_type']; ?></div>
        <div class="dest-card-name"><?php echo escape($l['listing_title']); ?></div>
        <div class="dest-card-meta"><?php echo escape($l['business_name']); ?> &mdash; <?php echo escape($l['municipality'] . ', ' . $l['province']); ?></div>
        <?php if ($l['description']): ?>
        <p class="dest-card-desc"><?php echo escape(mb_strimwidth($l['description'], 0, 120, '...')); ?></p>
        <?php endif; ?>
        <div class="dest-card-footer">
          <?php if ($l['price'] !== null): ?>
          <span class="badge badge-primary">₱<?php echo number_format((float) $l['price'], 2); ?></span>
          <?php elseif ($l['price_label']): ?>
          <span class="badge badge-primary"><?php echo $priceLabels[$l['price_label']] ?? $l['price_label']; ?></span>
          <?php endif; ?>
          <?php if ($l['capacity']): ?>
          <span class="badge">Up to <?php echo (int) $l['capacity']; ?> pax</span>
          <?php endif; ?>
          <?php if ($hours): ?>
          <span class="badge"><?php echo escape($hours); ?></span>
          <?php endif; ?>
        </div>
        <?php if ($l['contact_number']): ?>
        <div style="margin-top:8px;font-size:.8rem;opacity:.7;"><?php echo escape($l['contact_number']); ?></div>
        <?php endif; ?>
        <?php if ($l['website_url'] || $l['facebook_url']): ?>
        <div style="margin-top:6px;display:flex;gap:8px;">
          <?php if ($l['website_url']): ?><a class="s-btn" href="<?php echo escape($l['website_url']); ?>" target="_blank" rel="noopener">Website</a><?php endif; ?>
          <?php if ($l['facebook_url']): ?><a class="s-btn" href="<?php echo escape($l['facebook_url']); ?>" target="_blank" rel="noopener">Facebook</a><?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <?php if ($totalPages > 1): ?>
  <div style="display:flex;gap:8px;margin-top:16px;flex-wrap:wrap;">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
    <a class="s-btn <?php echo $i === $page ? 'dark' : ''; ?>"
       href="?page=<?php echo $i; ?>&type=<?php echo urlencode($typeFilter); ?>&province_id=<?php echo $provinceFilter; ?>&search=<?php echo urlencode($search); ?>">
      <?php echo $i; ?>
    </a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</main>
</div>
<script src="/doon-app/assets/js/main.js"></script>
<?php include '../includes/footer.php'; ?>
