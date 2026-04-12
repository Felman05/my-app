<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/env.php';
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
    $email       = trim($_POST['email'] ?? '');
    $website     = trim($_POST['website_url'] ?? '');
    $facebook    = trim($_POST['facebook_url'] ?? '');
    $openTime    = trim($_POST['opening_time'] ?? '') ?: null;
    $closeTime   = trim($_POST['closing_time'] ?? '') ?: null;
    $openDays    = $_POST['open_days'] ?? [];
    $openDaysJson = !empty($openDays) ? json_encode([...$openDays]) : null;
    $isActive    = isset($_POST['is_active']) ? 1 : 0;
    $isFeatured  = isset($_POST['is_featured']) ? 1 : 0;

    if (!$name) {
        $error = 'Name is required.';
    } elseif (!$provinceId || !$categoryId) {
        $error = 'Province and category are required.';
    } else {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name)) . '-' . time();
        try {
            $newImages  = uploadImages('destinations', 10);
            $coverImage = $newImages[0] ?? null;
            $imagesJson = !empty($newImages) ? json_encode($newImages) : null;
            $pdo->prepare(
                'INSERT INTO destinations
                    (province_id, category_id, name, slug, short_description, description, address,
                     latitude, longitude, price_label, contact_number, email, website_url, facebook_url,
                     opening_time, closing_time, open_days, cover_image, images,
                     is_active, is_featured, is_verified, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW(), NOW())'
            )->execute([$provinceId, $categoryId, $name, $slug, $shortDesc, $desc, $address, $lat, $lng, $priceLabel, $contact, $email ?: null, $website ?: null, $facebook ?: null, $openTime, $closeTime, $openDaysJson, $coverImage, $imagesJson, $isActive, $isFeatured]);
            $message = 'Destination "' . htmlspecialchars($name) . '" added.';
        } catch (Exception $e) {
            $error = 'Failed to add destination: ' . $e->getMessage();
        }
    }
}

// ── Load data ─────────────────────────────────────────────────────────────
try {
    $destinations = $pdo->query(
        'SELECT d.id, d.name, d.is_active, d.is_featured,
                d.short_description, d.description, d.address,
                d.latitude, d.longitude, d.price_label, d.contact_number,
                d.cover_image, d.images, d.avg_rating, d.view_count,
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

$gmKey = env('GOOGLE_MAPS_API_KEY', '');
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
    <form method="POST" enctype="multipart/form-data">
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
        <div class="rf-g"><label class="rf-lbl">Email</label><input class="rf-ctrl" type="email" name="email" placeholder="info@example.com"></div>
        <div class="rf-g"><label class="rf-lbl">Website URL</label><input class="rf-ctrl" type="url" name="website_url" placeholder="https://"></div>
        <div class="rf-g"><label class="rf-lbl">Facebook URL</label><input class="rf-ctrl" type="url" name="facebook_url" placeholder="https://facebook.com/..."></div>
        <div class="rf-g"><label class="rf-lbl">Opening Time</label><input class="rf-ctrl" type="time" name="opening_time"></div>
        <div class="rf-g"><label class="rf-lbl">Closing Time</label><input class="rf-ctrl" type="time" name="closing_time"></div>
        <div class="rf-g" style="grid-column:1/-1;">
          <label class="rf-lbl">Open Days</label>
          <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:4px;">
            <?php foreach (['mon'=>'Mon','tue'=>'Tue','wed'=>'Wed','thu'=>'Thu','fri'=>'Fri','sat'=>'Sat','sun'=>'Sun'] as $val => $lbl): ?>
            <label style="display:flex;align-items:center;gap:4px;font-size:.85rem;"><input type="checkbox" name="open_days[]" value="<?php echo $val; ?>"> <?php echo $lbl; ?></label>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="rf-g" style="display:flex;align-items:center;gap:16px;margin-top:4px;">
          <label style="display:flex;align-items:center;gap:6px;"><input type="checkbox" name="is_active" checked> Active</label>
          <label style="display:flex;align-items:center;gap:6px;"><input type="checkbox" name="is_featured"> Featured</label>
        </div>
        <div class="rf-g" style="grid-column:1/-1;">
          <label class="rf-lbl">Images (JPG/PNG/WebP, max 5 MB each)</label>
          <input class="rf-ctrl" type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple style="padding:6px;">
          <span style="font-size:.75rem;color:var(--i4);margin-top:4px;display:block;">First image becomes the cover photo shown on the destination page.</span>
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
            <td style="white-space:nowrap;">
              <a class="s-btn" href="/doon-app/admin/destination-edit.php?id=<?php echo $d['id']; ?>" style="padding:2px 8px;font-size:.78rem;">Edit</a>
              <button class="s-btn view-dest-btn" type="button" style="padding:2px 8px;font-size:.78rem;margin-left:4px;"
                data-id="<?php echo $d['id']; ?>"
                data-name="<?php echo escape($d['name']); ?>"
                data-province="<?php echo escape($d['province_name']); ?>"
                data-category="<?php echo escape($d['category_name']); ?>"
                data-short="<?php echo escape($d['short_description'] ?? ''); ?>"
                data-desc="<?php echo escape($d['description'] ?? ''); ?>"
                data-address="<?php echo escape($d['address'] ?? ''); ?>"
                data-lat="<?php echo (float) ($d['latitude'] ?? 0); ?>"
                data-lng="<?php echo (float) ($d['longitude'] ?? 0); ?>"
                data-price="<?php echo escape($d['price_label'] ?? ''); ?>"
                data-contact="<?php echo escape($d['contact_number'] ?? ''); ?>"
                data-rating="<?php echo number_format((float) ($d['avg_rating'] ?? 0), 1); ?>"
                data-views="<?php echo number_format((int) ($d['view_count'] ?? 0)); ?>"
                data-cover="<?php echo escape($d['cover_image'] ?? ''); ?>"
                data-gmkey="<?php echo escape($gmKey); ?>">View</button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
</main>
</div>

<!-- Destination detail modal -->
<div id="dest-modal-backdrop" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:900;align-items:center;justify-content:center;">
  <div style="background:var(--wh,#fff);border-radius:var(--r2,10px);box-shadow:0 8px 40px rgba(0,0,0,.2);width:min(680px,94vw);max-height:90vh;overflow-y:auto;padding:28px;">
    <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:16px;">
      <div>
        <div id="dm-name" style="font-size:1.2rem;font-weight:700;color:var(--i);"></div>
        <div id="dm-sub" style="font-size:.85rem;color:var(--i4);margin-top:2px;"></div>
      </div>
      <button id="dest-modal-close" class="s-btn dark" type="button" style="flex-shrink:0;margin-left:16px;">Close</button>
    </div>
    <div id="dm-map" style="margin-bottom:14px;"></div>
    <div id="dm-body" style="display:grid;gap:8px;font-size:.88rem;"></div>
  </div>
</div>

<script src="/doon-app/assets/js/main.js"></script>
<script>
(function () {
  var backdrop = document.getElementById('dest-modal-backdrop');
  var closeBtn = document.getElementById('dest-modal-close');

  function openModal(btn) {
    var d = btn.dataset;
    document.getElementById('dm-name').textContent = d.name;
    document.getElementById('dm-sub').textContent  = [d.province, d.category].filter(Boolean).join(' — ');

    // Cover image
    var mapEl = document.getElementById('dm-map');
    if (d.cover) {
      mapEl.innerHTML = '<img src="' + d.cover + '" alt="' + d.name + '" style="width:100%;height:200px;object-fit:cover;border-radius:var(--r2);display:block;margin-bottom:10px;">';
    }

    // Map (append after cover)
    var lat = parseFloat(d.lat), lng = parseFloat(d.lng);
    if (d.gmkey && lat && lng) {
      mapEl.innerHTML += '<iframe width="100%" height="180" style="border:1px solid var(--bd);border-radius:var(--r2);display:block;" loading="lazy"'
        + ' src="https://www.google.com/maps/embed/v1/view?key=' + encodeURIComponent(d.gmkey)
        + '&center=' + lat + ',' + lng + '&zoom=15"></iframe>';
    } else if (d.gmkey && d.name) {
      mapEl.innerHTML += '<iframe width="100%" height="180" style="border:1px solid var(--bd);border-radius:var(--r2);display:block;" loading="lazy"'
        + ' src="https://www.google.com/maps/embed/v1/place?key=' + encodeURIComponent(d.gmkey)
        + '&q=' + encodeURIComponent(d.name + ', CALABARZON, Philippines') + '"></iframe>';
    } else {
      mapEl.innerHTML = '';
    }

    // Detail rows
    var rows = [];
    if (d.address)  rows.push(['Address',     d.address]);
    if (d.contact)  rows.push(['Contact',     d.contact]);
    if (d.price)    rows.push(['Price range', d.price.replace('_', ' ')]);
    if (d.rating && d.rating !== '0.0') rows.push(['Avg rating', '★ ' + d.rating]);
    if (d.views)    rows.push(['Views',       d.views]);
    if (lat && lng) rows.push(['Coordinates', lat + ', ' + lng]);

    var bodyEl = document.getElementById('dm-body');
    bodyEl.innerHTML = rows.map(function (r) {
      return '<div style="display:flex;gap:12px;">'
        + '<span style="font-weight:600;min-width:110px;color:var(--i3);">' + r[0] + '</span>'
        + '<span>' + r[1] + '</span></div>';
    }).join('');

    if (d.short) {
      bodyEl.innerHTML += '<p style="margin-top:10px;color:var(--i2);">' + d.short + '</p>';
    }
    if (d.desc && d.desc !== d.short) {
      bodyEl.innerHTML += '<p style="margin-top:6px;font-size:.82rem;color:var(--i3);">' + d.desc + '</p>';
    }

    backdrop.style.display = 'flex';
  }

  function closeModal() {
    backdrop.style.display = 'none';
    document.getElementById('dm-map').innerHTML = '';
  }

  document.querySelectorAll('.view-dest-btn').forEach(function (btn) {
    btn.addEventListener('click', function () { openModal(btn); });
  });

  closeBtn.addEventListener('click', closeModal);
  backdrop.addEventListener('click', function (e) {
    if (e.target === backdrop) closeModal();
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeModal();
  });
}());
</script>
<?php include '../includes/footer.php'; ?>
