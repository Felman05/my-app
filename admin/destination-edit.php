<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('admin');
$pageTitle = 'Edit Destination';
$additionalCSS = '<link rel="stylesheet" href="/doon-app/assets/css/dashboard.css"><link rel="stylesheet" href="/doon-app/assets/css/components.css">';

$id = (int) ($_GET['id'] ?? 0);
if (!$id) {
    header('Location: /doon-app/admin/destinations.php');
    exit;
}

try {
    $stmt = $pdo->prepare(
        'SELECT d.*, p.name AS province_name, ac.name AS category_name
         FROM destinations d
         LEFT JOIN provinces p ON d.province_id = p.id
         LEFT JOIN activity_categories ac ON d.category_id = ac.id
         WHERE d.id = ?'
    );
    $stmt->execute([$id]);
    $dest = $stmt->fetch();
} catch (Exception $e) {
    $dest = null;
}

if (!$dest) {
    header('Location: /doon-app/admin/destinations.php');
    exit;
}

$error   = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name       = trim($_POST['name'] ?? '');
    $provinceId = (int) ($_POST['province_id'] ?? 0);
    $categoryId = (int) ($_POST['category_id'] ?? 0);
    $shortDesc  = trim($_POST['short_description'] ?? '');
    $desc       = trim($_POST['description'] ?? '');
    $address    = trim($_POST['address'] ?? '');
    $lat        = ($_POST['latitude'] ?? '') !== '' ? (float) $_POST['latitude'] : null;
    $lng        = ($_POST['longitude'] ?? '') !== '' ? (float) $_POST['longitude'] : null;
    $priceLabel = $_POST['price_label'] ?: null;
    $contact    = trim($_POST['contact_number'] ?? '');
    $isActive   = isset($_POST['is_active']) ? 1 : 0;
    $isFeatured = isset($_POST['is_featured']) ? 1 : 0;

    if (!$name) {
        $error = 'Name is required.';
    } elseif (!$provinceId || !$categoryId) {
        $error = 'Province and category are required.';
    } else {
        try {
            // Handle images
            $existingImages  = ($dest['images'] ?? null) ? json_decode($dest['images'], true) : [];
            $removeImages    = $_POST['remove_images'] ?? [];
            $keptImages      = array_values(array_diff($existingImages, $removeImages));
            $newImages       = uploadImages('destinations', 10);
            $finalImages     = array_slice(array_merge($keptImages, $newImages), 0, 10);
            $imagesJson      = !empty($finalImages) ? json_encode($finalImages) : null;
            $coverImage      = $finalImages[0] ?? ($dest['cover_image'] ?? null);
            // If the original cover_image was removed, use next available
            if (in_array($coverImage, $removeImages)) {
                $coverImage = $finalImages[0] ?? null;
            }

            $pdo->prepare(
                'UPDATE destinations
                 SET name=?, province_id=?, category_id=?, short_description=?, description=?,
                     address=?, latitude=?, longitude=?, price_label=?, contact_number=?,
                     cover_image=?, images=?, is_active=?, is_featured=?, updated_at=NOW()
                 WHERE id=?'
            )->execute([
                $name, $provinceId, $categoryId, $shortDesc, $desc,
                $address, $lat, $lng, $priceLabel, $contact,
                $coverImage, $imagesJson, $isActive, $isFeatured, $id
            ]);

            // Reload
            $stmt = $pdo->prepare('SELECT d.*, p.name AS province_name, ac.name AS category_name FROM destinations d LEFT JOIN provinces p ON d.province_id = p.id LEFT JOIN activity_categories ac ON d.category_id = ac.id WHERE d.id = ?');
            $stmt->execute([$id]);
            $dest = $stmt->fetch();
            $message = 'Destination updated.';
        } catch (Exception $e) {
            $error = 'Update failed: ' . $e->getMessage();
        }
    }
}

$provinces  = $pdo->query('SELECT id, name FROM provinces ORDER BY name')->fetchAll();
$categories = $pdo->query('SELECT id, name FROM activity_categories ORDER BY name')->fetchAll();
$existingImages = ($dest['images'] ?? null) ? json_decode($dest['images'], true) : [];
?>
<?php include '../includes/header.php'; ?>
<div class="d-wrap">
<?php include '../includes/sidebar.php'; ?>
<main class="d-main">
  <div class="d-topbar">
    <div><h1 class="d-page-title">Edit Destination</h1><p class="d-page-sub"><?php echo escape($dest['name']); ?></p></div>
    <a class="s-btn dark" href="/doon-app/admin/destinations.php">Back</a>
  </div>

  <?php if ($message): ?><div class="alert ok" style="margin-bottom:12px;"><?php echo escape($message); ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert err" style="margin-bottom:12px;"><?php echo escape($error); ?></div><?php endif; ?>

  <section class="dc" style="max-width:720px;">
    <form method="POST" enctype="multipart/form-data">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div class="rf-g"><label class="rf-lbl">Name *</label><input class="rf-ctrl" name="name" required value="<?php echo escape($dest['name']); ?>"></div>
        <div class="rf-g"><label class="rf-lbl">Province *</label>
          <select class="rf-ctrl" name="province_id" required>
            <?php foreach ($provinces as $p): ?>
            <option value="<?php echo $p['id']; ?>" <?php echo $dest['province_id'] == $p['id'] ? 'selected' : ''; ?>><?php echo escape($p['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="rf-g"><label class="rf-lbl">Category *</label>
          <select class="rf-ctrl" name="category_id" required>
            <?php foreach ($categories as $c): ?>
            <option value="<?php echo $c['id']; ?>" <?php echo $dest['category_id'] == $c['id'] ? 'selected' : ''; ?>><?php echo escape($c['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="rf-g"><label class="rf-lbl">Price Label</label>
          <select class="rf-ctrl" name="price_label">
            <option value="">—</option>
            <?php foreach (['free'=>'Free','budget'=>'Budget','mid_range'=>'Mid Range','luxury'=>'Luxury'] as $val => $lbl): ?>
            <option value="<?php echo $val; ?>" <?php echo ($dest['price_label'] ?? '') === $val ? 'selected' : ''; ?>><?php echo $lbl; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="rf-g" style="grid-column:1/-1;"><label class="rf-lbl">Short Description</label><input class="rf-ctrl" name="short_description" maxlength="500" value="<?php echo escape($dest['short_description'] ?? ''); ?>"></div>
        <div class="rf-g" style="grid-column:1/-1;"><label class="rf-lbl">Full Description</label><textarea class="rf-ctrl" name="description"><?php echo escape($dest['description'] ?? ''); ?></textarea></div>
        <div class="rf-g" style="grid-column:1/-1;"><label class="rf-lbl">Address</label><input class="rf-ctrl" name="address" value="<?php echo escape($dest['address'] ?? ''); ?>"></div>
        <div class="rf-g"><label class="rf-lbl">Latitude</label><input class="rf-ctrl" type="number" name="latitude" step="any" value="<?php echo $dest['latitude'] ?? ''; ?>"></div>
        <div class="rf-g"><label class="rf-lbl">Longitude</label><input class="rf-ctrl" type="number" name="longitude" step="any" value="<?php echo $dest['longitude'] ?? ''; ?>"></div>
        <div class="rf-g"><label class="rf-lbl">Contact Number</label><input class="rf-ctrl" name="contact_number" value="<?php echo escape($dest['contact_number'] ?? ''); ?>"></div>
        <div class="rf-g" style="display:flex;align-items:center;gap:16px;margin-top:20px;">
          <label style="display:flex;align-items:center;gap:6px;"><input type="checkbox" name="is_active" <?php echo $dest['is_active'] ? 'checked' : ''; ?>> Active</label>
          <label style="display:flex;align-items:center;gap:6px;"><input type="checkbox" name="is_featured" <?php echo $dest['is_featured'] ? 'checked' : ''; ?>> Featured</label>
        </div>
      </div>

      <?php if (!empty($existingImages)): ?>
      <div class="rf-g mb16" style="margin-top:16px;">
        <label class="rf-lbl">Current Images</label>
        <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:6px;">
          <?php foreach ($existingImages as $imgPath): ?>
          <div style="position:relative;width:110px;height:80px;border-radius:var(--r);overflow:hidden;border:1px solid var(--bd);">
            <img src="<?php echo escape($imgPath); ?>" alt="Destination image" style="width:100%;height:100%;object-fit:cover;">
            <label style="position:absolute;top:4px;right:4px;background:rgba(0,0,0,.6);border-radius:4px;padding:2px 5px;cursor:pointer;display:flex;align-items:center;gap:3px;font-size:10px;color:#fff;">
              <input type="checkbox" name="remove_images[]" value="<?php echo escape($imgPath); ?>" style="width:12px;height:12px;"> Remove
            </label>
            <?php if ($imgPath === ($dest['cover_image'] ?? null)): ?>
            <span style="position:absolute;bottom:0;left:0;right:0;background:rgba(0,0,0,.55);font-size:9px;color:#fff;text-align:center;padding:2px;">Cover</span>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
        <span style="font-size:.75rem;color:var(--i4);margin-top:4px;display:block;">First remaining image becomes the cover photo.</span>
      </div>
      <?php endif; ?>

      <div class="rf-g mb16" style="margin-top:<?php echo empty($existingImages) ? '16' : '0'; ?>px;">
        <label class="rf-lbl">Add Images (JPG/PNG/WebP, max 5 MB each)</label>
        <input class="rf-ctrl" type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple style="padding:6px;">
        <span style="font-size:.75rem;color:var(--i4);margin-top:4px;display:block;">First image will be used as the cover photo if no existing images remain.</span>
      </div>

      <button class="rf-go" type="submit" style="margin-top:4px;">Save Changes</button>
    </form>
  </section>
</main>
</div>
<script src="/doon-app/assets/js/main.js"></script>
<?php include '../includes/footer.php'; ?>
