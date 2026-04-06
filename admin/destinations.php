<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireRole('admin');
$pageTitle = 'Manage Destinations';
$additionalCSS = '<link rel="stylesheet" href="/doon-app/assets/css/dashboard.css"><link rel="stylesheet" href="/doon-app/assets/css/components.css">';

$message = '';
$error   = '';

// ── Toggle active / featured ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle'])) {
    $tid   = (int) ($_POST['dest_id'] ?? 0);
    $field = $_POST['toggle'] === 'featured' ? 'is_featured' : 'is_active';
    if ($tid) {
        try {
            $pdo->prepare("UPDATE destinations SET $field = 1 - $field, updated_at = NOW() WHERE id = ?")->execute([$tid]);
            $message = 'Updated.';
        } catch (Exception $e) { $error = 'Update failed.'; }
    }
    header('Location: /doon-app/admin/destinations.php');
    exit;
}

// ── Add new destination ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_dest'])) {
    $name        = trim($_POST['name'] ?? '');
    $provinceId  = (int) ($_POST['province_id'] ?? 0);
    $categoryId  = (int) ($_POST['category_id'] ?? 0);
    $shortDesc   = trim($_POST['short_description'] ?? '');
    $desc        = trim($_POST['description'] ?? '');
    $address     = trim($_POST['address'] ?? '');
    $lat         = ($_POST['latitude'] ?? '') !== '' ? (float) $_POST['latitude'] : null;
    $lng         = ($_POST['longitude'] ?? '') !== '' ? (float) $_POST['longitude'] : null;
    $priceLabel  = $_POST['price_label'] ?: null;
    $contact     = trim($_POST['contact_number'] ?? '');
    $isActive    = isset($_POST['is_active']) ? 1 : 0;
    $isFeatured  = isset($_POST['is_featured']) ? 1 : 0;

    if (!$name) {
        $error = 'Name is required.';
    } elseif (!$provinceId || !$categoryId) {
        $error = 'Province and category are required.';
    } else {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name)) . '-' . time();
        try {
            $pdo->prepare(
                'INSERT INTO destinations
                    (province_id, category_id, name, slug, short_description, description, address,
                     latitude, longitude, price_label, contact_number, is_active, is_featured, is_verified, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW(), NOW())'
            )->execute([$provinceId, $categoryId, $name, $slug, $shortDesc, $desc, $address, $lat, $lng, $priceLabel, $contact, $isActive, $isFeatured]);
            $message = 'Destination "' . htmlspecialchars($name) . '" added.';
        } catch (Exception $e) {
            $error = 'Failed to add destination: ' . $e->getMessage();
        }
    }
}

// ── Load data ─────────────────────────────────────────────────────────────
try {
    $destinations = $pdo->query(
        'SELECT d.id, d.name, d.province_id, d.is_active, d.is_featured,
                p.name as province_name, ac.name as category_name
         FROM destinations d
         LEFT JOIN provinces p ON d.province_id = p.id
         LEFT JOIN activity_categories ac ON d.category_id = ac.id
         ORDER BY d.created_at DESC LIMIT 100'
    )->fetchAll();

    $provinces  = $pdo->query('SELECT id, name FROM provinces ORDER BY name')->fetchAll();
    $categories = $pdo->query('SELECT id, name FROM activity_categories ORDER BY name')->fetchAll();
} catch (Exception $e) {
    $destinations = [];
    $provinces    = [];
    $categories   = [];
}
?>
<?php include '../includes/header.php'; ?>
<div class="d-wrap">
<?php include '../includes/sidebar.php'; ?>
<main class="d-main">
  <div class="d-topbar">
    <div><h1 class="d-page-title">Manage Destinations</h1><p class="d-page-sub">Add, activate, feature, or deactivate destination listings.</p></div>
    <button class="s-btn dark" onclick="document.getElementById('add-form').style.display=document.getElementById('add-form').style.display==='none'?'block':'none'">+ Add Destination</button>
  </div>

  <?php if ($message): ?><div class="alert ok" style="margin-bottom:12px;"><?php echo escape($message); ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert err" style="margin-bottom:12px;"><?php echo escape($error); ?></div><?php endif; ?>

  <!-- Add form (hidden by default) -->
  <section class="dc" id="add-form" style="display:none;margin-bottom:16px;">
    <div class="dc-title" style="margin-bottom:12px;">New Destination</div>
    <form method="POST">
      <input type="hidden" name="add_dest" value="1">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div class="rf-g"><label class="rf-lbl">Name *</label><input class="rf-ctrl" name="name" required></div>
        <div class="rf-g"><label class="rf-lbl">Province *</label>
          <select class="rf-ctrl" name="province_id" required>
            <option value="">Select</option>
            <?php foreach ($provinces as $p): ?><option value="<?php echo $p['id']; ?>"><?php echo escape($p['name']); ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="rf-g"><label class="rf-lbl">Category *</label>
          <select class="rf-ctrl" name="category_id" required>
            <option value="">Select</option>
            <?php foreach ($categories as $c): ?><option value="<?php echo $c['id']; ?>"><?php echo escape($c['name']); ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="rf-g"><label class="rf-lbl">Price Label</label>
          <select class="rf-ctrl" name="price_label">
            <option value="">—</option>
            <option value="free">Free</option><option value="budget">Budget</option>
            <option value="mid_range">Mid Range</option><option value="luxury">Luxury</option>
          </select>
        </div>
        <div class="rf-g" style="grid-column:1/-1;"><label class="rf-lbl">Short Description</label><input class="rf-ctrl" name="short_description" maxlength="500"></div>
        <div class="rf-g" style="grid-column:1/-1;"><label class="rf-lbl">Full Description</label><textarea class="rf-ctrl" name="description"></textarea></div>
        <div class="rf-g" style="grid-column:1/-1;"><label class="rf-lbl">Address</label><input class="rf-ctrl" name="address"></div>
        <div class="rf-g"><label class="rf-lbl">Latitude</label><input class="rf-ctrl" type="number" name="latitude" step="any" placeholder="e.g., 13.7565"></div>
        <div class="rf-g"><label class="rf-lbl">Longitude</label><input class="rf-ctrl" type="number" name="longitude" step="any" placeholder="e.g., 121.0583"></div>
        <div class="rf-g"><label class="rf-lbl">Contact Number</label><input class="rf-ctrl" name="contact_number"></div>
        <div class="rf-g" style="display:flex;align-items:center;gap:16px;margin-top:20px;">
          <label style="display:flex;align-items:center;gap:6px;"><input type="checkbox" name="is_active" checked> Active</label>
          <label style="display:flex;align-items:center;gap:6px;"><input type="checkbox" name="is_featured"> Featured</label>
        </div>
      </div>
      <button class="rf-go" type="submit" style="margin-top:12px;">Save Destination</button>
    </form>
  </section>

  <section class="dc">
    <div style="overflow-x:auto;">
      <table class="d-table">
        <thead>
          <tr>
            <th>Name</th>
            <th>Province</th>
            <th>Category</th>
            <th>Featured</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($destinations as $d): ?>
          <tr>
            <td><?php echo escape($d['name']); ?></td>
            <td><?php echo escape($d['province_name']); ?></td>
            <td><?php echo escape($d['category_name']); ?></td>
            <td>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="dest_id" value="<?php echo $d['id']; ?>">
                <input type="hidden" name="toggle" value="featured">
                <button class="s-btn" type="submit" style="padding:2px 8px;font-size:.78rem;">
                  <?php echo $d['is_featured'] ? '★ Unfeature' : '☆ Feature'; ?>
                </button>
              </form>
            </td>
            <td>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="dest_id" value="<?php echo $d['id']; ?>">
                <input type="hidden" name="toggle" value="active">
                <button class="s-btn <?php echo $d['is_active'] ? '' : 'dark'; ?>" type="submit" style="padding:2px 8px;font-size:.78rem;">
                  <?php echo $d['is_active'] ? 'Deactivate' : 'Activate'; ?>
                </button>
              </form>
            </td>
            <td>
              <a class="s-btn" href="/doon-app/tourist/destination.php?id=<?php echo $d['id']; ?>" target="_blank" style="font-size:.78rem;">View</a>
            </td>
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
