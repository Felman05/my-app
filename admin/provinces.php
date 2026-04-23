<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('admin');

$pageTitle = 'Province Images';
$additionalCSS = '<link rel="stylesheet" href="/doon-app/assets/css/dashboard.css"><link rel="stylesheet" href="/doon-app/assets/css/components.css">';

// Auto-migrate
try { $pdo->exec('ALTER TABLE provinces ADD COLUMN IF NOT EXISTS landing_image VARCHAR(255) NULL'); } catch (Exception $e) {}

$uploadBase = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . '/doon-app/uploads/provinces/';
$allowedMime = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

// ── Upload / replace image ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_id'])) {
    $provinceId = (int) ($_POST['upload_id'] ?? 0);
    $province   = $provinceId ? getProvince($pdo, $provinceId) : null;

    if (!$province) {
        header('Location: /doon-app/admin/provinces.php?err=Province+not+found');
        exit;
    }

    if (empty($_FILES['province_image']) || $_FILES['province_image']['error'] !== UPLOAD_ERR_OK) {
        header('Location: /doon-app/admin/provinces.php?err=No+file+uploaded');
        exit;
    }

    $file = $_FILES['province_image'];

    if ($file['size'] > 5 * 1024 * 1024) {
        header('Location: /doon-app/admin/provinces.php?err=File+exceeds+5MB+limit');
        exit;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!isset($allowedMime[$mime])) {
        header('Location: /doon-app/admin/provinces.php?err=Invalid+file+type+(JPG%2FPNG%2FWebP+only)');
        exit;
    }

    if (!is_dir($uploadBase)) mkdir($uploadBase, 0755, true);

    $ext      = $allowedMime[$mime];
    $filename = 'province_' . generateUUID() . '.' . $ext;
    $destPath = $uploadBase . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        header('Location: /doon-app/admin/provinces.php?err=Upload+failed');
        exit;
    }

    // Delete old file safely
    if (!empty($province['landing_image'])) {
        $oldAbs = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . '/doon-app/' . ltrim($province['landing_image'], '/');
        $real   = realpath($oldAbs);
        $base   = realpath($uploadBase);
        if ($real && $base && strncmp($real, $base, strlen($base)) === 0) {
            @unlink($real);
        }
    }

    $webPath = 'uploads/provinces/' . $filename;
    $pdo->prepare('UPDATE provinces SET landing_image = ? WHERE id = ?')->execute([$webPath, $provinceId]);
    logAdminActivity($pdo, (int) $_SESSION['user_id'], 'upload_province_image', 'province', $provinceId, "Uploaded landing image for province #{$provinceId}");

    header('Location: /doon-app/admin/provinces.php?msg=Image+updated+successfully');
    exit;
}

// ── Remove image ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_id'])) {
    $provinceId = (int) ($_POST['remove_id'] ?? 0);
    $province   = $provinceId ? getProvince($pdo, $provinceId) : null;

    if ($province && !empty($province['landing_image'])) {
        $oldAbs = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . '/doon-app/' . ltrim($province['landing_image'], '/');
        $real   = realpath($oldAbs);
        $base   = realpath($uploadBase);
        if ($real && $base && strncmp($real, $base, strlen($base)) === 0) {
            @unlink($real);
        }
        $pdo->prepare('UPDATE provinces SET landing_image = NULL WHERE id = ?')->execute([$provinceId]);
        logAdminActivity($pdo, (int) $_SESSION['user_id'], 'remove_province_image', 'province', $provinceId, "Removed landing image for province #{$provinceId}");
    }

    header('Location: /doon-app/admin/provinces.php?msg=Image+removed');
    exit;
}

// ── Fetch all provinces ───────────────────────────────────────────────────
try {
    $provinces = $pdo->query(
        'SELECT p.*, COUNT(d.id) as destination_count
         FROM provinces p
         LEFT JOIN destinations d ON p.id = d.province_id AND d.is_active = 1
         GROUP BY p.id ORDER BY p.name ASC'
    )->fetchAll();
} catch (PDOException $e) {
    $provinces = [];
}

$msg = $_GET['msg'] ?? '';
$err = $_GET['err'] ?? '';
?>
<?php include '../includes/header.php'; ?>
<div class="d-wrap">
<?php include '../includes/sidebar.php'; ?>
<main class="d-main">
  <div class="d-topbar">
    <div>
      <h1 class="d-page-title">Province Images</h1>
      <p class="d-page-sub">Manage the landing page carousel photo for each province.</p>
    </div>
  </div>

  <?php if ($msg): ?><div class="alert ok" style="margin-bottom:16px;"><?php echo escape($msg); ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert err" style="margin-bottom:16px;"><?php echo escape($err); ?></div><?php endif; ?>

  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:16px;">
    <?php foreach ($provinces as $p): ?>
    <div class="dc" style="padding:20px;">
      <div class="dc-title" style="margin-bottom:12px;"><?php echo escape($p['name']); ?></div>

      <?php if (!empty($p['landing_image'])): ?>
      <img src="/doon-app/<?php echo escape($p['landing_image']); ?>"
           alt="<?php echo escape($p['name']); ?>"
           style="width:100%;height:160px;object-fit:cover;border-radius:8px;display:block;margin-bottom:12px;">
      <?php else: ?>
      <div style="width:100%;height:160px;border-radius:8px;background:#e8e7e1;display:flex;align-items:center;justify-content:center;font-size:48px;margin-bottom:12px;">
        <?php $emojis = ['Batangas'=>'🌋','Laguna'=>'🏞️','Cavite'=>'🏰','Rizal'=>'🌿','Quezon'=>'🌊'];
              echo $emojis[$p['name']] ?? '📍'; ?>
      </div>
      <?php endif; ?>

      <p style="font-size:13px;color:var(--i3);margin-bottom:14px;">
        <?php echo (int) $p['destination_count']; ?> active destination<?php echo $p['destination_count'] != 1 ? 's' : ''; ?>
        &nbsp;&bull;&nbsp;
        <?php echo !empty($p['landing_image']) ? 'Image uploaded' : 'No image — fallback gradient shown'; ?>
      </p>

      <form method="POST" enctype="multipart/form-data" style="margin-bottom:8px;">
        <input type="hidden" name="upload_id" value="<?php echo $p['id']; ?>">
        <div style="display:flex;gap:8px;align-items:center;">
          <input type="file" name="province_image" accept="image/jpeg,image/png,image/webp" required
                 style="flex:1;font-size:13px;" id="file_<?php echo $p['id']; ?>">
          <button type="submit" class="s-btn dark" style="white-space:nowrap;">
            <?php echo !empty($p['landing_image']) ? 'Replace' : 'Upload'; ?>
          </button>
        </div>
        <p style="font-size:11px;color:var(--i4);margin-top:4px;">JPG, PNG or WebP &bull; Max 5 MB</p>
      </form>

      <?php if (!empty($p['landing_image'])): ?>
      <form method="POST" onsubmit="return confirm('Remove image for <?php echo escape($p['name']); ?>?');">
        <input type="hidden" name="remove_id" value="<?php echo $p['id']; ?>">
        <button type="submit" class="s-btn" style="font-size:12px;color:var(--i3);">Remove image</button>
      </form>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</main>
</div>
<?php include '../includes/footer.php'; ?>
