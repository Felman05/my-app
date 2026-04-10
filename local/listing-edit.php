<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('local');
$currentUser = getCurrentUser();
$pageTitle = 'Edit Listing';
$additionalCSS = '<link rel="stylesheet" href="/doon-app/assets/css/dashboard.css"><link rel="stylesheet" href="/doon-app/assets/css/components.css">';

$listingId = (int) ($_GET['id'] ?? 0);
if (!$listingId) {
    header('Location: /doon-app/local/listings.php');
    exit;
}

try {
    $stmt = $pdo->prepare(
        'SELECT pl.* FROM provider_listings pl
         JOIN local_provider_profiles lpp ON pl.provider_id = lpp.id
         WHERE pl.id = ? AND lpp.user_id = ? LIMIT 1'
    );
    $stmt->execute([$listingId, $currentUser['id']]);
    $listing = $stmt->fetch();
} catch (Exception $e) {
    $listing = null;
}

if (!$listing) {
    header('Location: /doon-app/local/listings.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['listing_title'] ?? '');
    $listingType = $_POST['listing_type'] ?? 'other';
    $description = trim($_POST['description'] ?? '');
    $priceLabel  = $_POST['price_label'] ?? null;
    $price       = ($_POST['price'] ?? '') !== '' ? (float) $_POST['price'] : null;
    $contact     = trim($_POST['contact_number'] ?? '');
    $capacity    = ($_POST['capacity'] ?? '') !== '' ? (int) $_POST['capacity'] : null;
    $openTime    = trim($_POST['open_time'] ?? '');
    $closeTime   = trim($_POST['close_time'] ?? '');
    $openDays    = $_POST['open_days'] ?? [];
    $availability = (!empty($openDays) || $openTime || $closeTime)
        ? json_encode(['open_days' => array_values($openDays), 'open_time' => $openTime ?: null, 'close_time' => $closeTime ?: null])
        : null;

    $validTypes = ['accommodation','tour_package','restaurant','transport','event','other'];

    if (!$title) {
        $error = 'Title is required.';
    } elseif (!in_array($listingType, $validTypes)) {
        $error = 'Invalid listing type.';
    } else {
        try {
            $existingImages = ($listing['images'] ?? null) ? json_decode($listing['images'], true) : [];
            $removeImages   = $_POST['remove_images'] ?? [];
            $keptImages     = array_values(array_diff($existingImages, $removeImages));
            $newImages      = uploadImages('listings', 5);
            $finalImages    = array_slice(array_merge($keptImages, $newImages), 0, 5);
            $imagesJson     = !empty($finalImages) ? json_encode($finalImages) : null;

            $pdo->prepare(
                'UPDATE provider_listings
                 SET listing_title=?, listing_type=?, description=?, images=?, price=?,
                     price_label=?, contact_number=?, capacity=?, availability=?, updated_at=NOW()
                 WHERE id=?'
            )->execute([$title, $listingType, $description, $imagesJson, $price, $priceLabel ?: null, $contact, $capacity, $availability, $listingId]);
            header('Location: /doon-app/local/listings.php?updated=1');
            exit;
        } catch (Exception $e) {
            $error = 'Failed to update listing.';
        }
    }
}

$avail        = ($listing['availability'] ?? null) ? json_decode($listing['availability'], true) : [];
$existingDays = $avail['open_days'] ?? [];
$existingImgs = ($listing['images'] ?? null) ? json_decode($listing['images'], true) : [];
?>
<?php include '../includes/header.php'; ?>
<div class="d-wrap">
<?php include '../includes/sidebar.php'; ?>
<main class="d-main">
  <div class="d-topbar">
    <div><h1 class="d-page-title">Edit Listing</h1><p class="d-page-sub">Update your listing details.</p></div>
    <a class="s-btn dark" href="/doon-app/local/listings.php">Back</a>
  </div>

  <?php if ($error): ?>
  <div class="alert err" style="margin-bottom:12px;"><?php echo escape($error); ?></div>
  <?php endif; ?>

  <?php if ($listing['status'] === 'rejected'): ?>
  <div class="alert err" style="margin-bottom:12px;">
    This listing was rejected.<?php if ($listing['rejection_reason']): ?> Reason: <em><?php echo escape($listing['rejection_reason']); ?></em><?php endif; ?>
    Edit and resubmit below.
  </div>
  <?php endif; ?>

  <section class="dc" style="max-width:640px;">
    <form method="POST" enctype="multipart/form-data">
      <div class="rf-g mb16">
        <label class="rf-lbl">Listing Title</label>
        <input class="rf-ctrl" type="text" name="listing_title" required value="<?php echo escape($listing['listing_title']); ?>">
      </div>
      <div class="rf-g mb16">
        <label class="rf-lbl">Type</label>
        <select class="rf-ctrl" name="listing_type">
          <?php foreach (['accommodation'=>'Accommodation','tour_package'=>'Tour Package','restaurant'=>'Restaurant / Food','transport'=>'Transport','event'=>'Event','other'=>'Other'] as $val => $lbl): ?>
          <option value="<?php echo $val; ?>" <?php echo $listing['listing_type'] === $val ? 'selected' : ''; ?>><?php echo $lbl; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="rf-g mb16">
        <label class="rf-lbl">Description</label>
        <textarea class="rf-ctrl" name="description"><?php echo escape($listing['description'] ?? ''); ?></textarea>
      </div>
      <div class="rf-g mb16">
        <label class="rf-lbl">Price (₱)</label>
        <input class="rf-ctrl" type="number" name="price" min="0" step="0.01" value="<?php echo $listing['price'] ?? ''; ?>">
      </div>
      <div class="rf-g mb16">
        <label class="rf-lbl">Price Category</label>
        <select class="rf-ctrl" name="price_label">
          <option value="">Select</option>
          <?php foreach (['free'=>'Free','budget'=>'Budget','mid_range'=>'Mid Range','luxury'=>'Luxury'] as $val => $lbl): ?>
          <option value="<?php echo $val; ?>" <?php echo ($listing['price_label'] ?? '') === $val ? 'selected' : ''; ?>><?php echo $lbl; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="rf-g mb16">
        <label class="rf-lbl">Contact Number</label>
        <input class="rf-ctrl" type="text" name="contact_number" value="<?php echo escape($listing['contact_number'] ?? ''); ?>">
      </div>
      <div class="rf-g mb16">
        <label class="rf-lbl">Capacity (max guests / pax, optional)</label>
        <input class="rf-ctrl" type="number" name="capacity" min="1" value="<?php echo $listing['capacity'] ?? ''; ?>">
      </div>
      <div class="rf-g mb16">
        <label class="rf-lbl">Operating Hours</label>
        <div style="display:flex;gap:8px;">
          <input class="rf-ctrl" type="time" name="open_time" value="<?php echo escape($avail['open_time'] ?? ''); ?>">
          <input class="rf-ctrl" type="time" name="close_time" value="<?php echo escape($avail['close_time'] ?? ''); ?>">
        </div>
      </div>
      <div class="rf-g mb16">
        <label class="rf-lbl">Open Days</label>
        <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:4px;">
          <?php foreach (['mon'=>'Mon','tue'=>'Tue','wed'=>'Wed','thu'=>'Thu','fri'=>'Fri','sat'=>'Sat','sun'=>'Sun'] as $val => $lbl): ?>
          <label style="display:flex;align-items:center;gap:4px;font-size:.85rem;">
            <input type="checkbox" name="open_days[]" value="<?php echo $val; ?>" <?php echo in_array($val, $existingDays) ? 'checked' : ''; ?>> <?php echo $lbl; ?>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <?php if (!empty($existingImgs)): ?>
      <div class="rf-g mb16">
        <label class="rf-lbl">Current Photos</label>
        <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:6px;">
          <?php foreach ($existingImgs as $imgPath): ?>
          <div style="position:relative;width:100px;height:80px;border-radius:var(--r);overflow:hidden;border:1px solid var(--bd);">
            <img src="<?php echo escape($imgPath); ?>" alt="Listing photo" style="width:100%;height:100%;object-fit:cover;">
            <label style="position:absolute;top:4px;right:4px;background:rgba(0,0,0,.6);border-radius:4px;padding:2px 5px;cursor:pointer;display:flex;align-items:center;gap:3px;font-size:10px;color:#fff;">
              <input type="checkbox" name="remove_images[]" value="<?php echo escape($imgPath); ?>" style="width:12px;height:12px;"> Remove
            </label>
          </div>
          <?php endforeach; ?>
        </div>
        <span style="font-size:.75rem;color:var(--i4);margin-top:4px;display:block;">Check the box on any photo to remove it on save.</span>
      </div>
      <?php endif; ?>

      <div class="rf-g mb16">
        <label class="rf-lbl">Add Photos (JPG/PNG/WebP, max 5 MB each)</label>
        <input class="rf-ctrl" type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple style="padding:6px;">
      </div>
      <button class="rf-go" type="submit">Save Changes</button>
    </form>
  </section>
</main>
</div>
<script src="/doon-app/assets/js/main.js"></script>
<?php include '../includes/footer.php'; ?>
