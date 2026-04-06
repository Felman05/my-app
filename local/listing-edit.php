<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireRole('local');
$currentUser = getCurrentUser();
$pageTitle = 'Edit Listing';
$additionalCSS = '<link rel="stylesheet" href="/doon-app/assets/css/dashboard.css"><link rel="stylesheet" href="/doon-app/assets/css/components.css">';

$listingId = (int) ($_GET['id'] ?? 0);
if (!$listingId) {
    header('Location: /doon-app/local/listings.php');
    exit;
}

// Fetch listing (must belong to this provider)
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

$error   = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['listing_title'] ?? '');
    $listingType = $_POST['listing_type'] ?? 'other';
    $description = trim($_POST['description'] ?? '');
    $priceLabel  = $_POST['price_label'] ?? null;
    $price       = $_POST['price'] !== '' ? (float) $_POST['price'] : null;
    $contact     = trim($_POST['contact_number'] ?? '');

    $validTypes = ['accommodation','tour_package','restaurant','transport','event','other'];

    if (!$title) {
        $error = 'Title is required.';
    } elseif (!in_array($listingType, $validTypes)) {
        $error = 'Invalid listing type.';
    } else {
        try {
            $stmt = $pdo->prepare(
                'UPDATE provider_listings
                 SET listing_title=?, listing_type=?, description=?, price=?, price_label=?, contact_number=?, updated_at=NOW()
                 WHERE id=?'
            );
            $stmt->execute([$title, $listingType, $description, $price, $priceLabel ?: null, $contact, $listingId]);
            $listing = array_merge($listing, [
                'listing_title' => $title, 'listing_type' => $listingType,
                'description' => $description, 'price' => $price,
                'price_label' => $priceLabel, 'contact_number' => $contact
            ]);
            $success = true;
        } catch (Exception $e) {
            $error = 'Failed to update listing.';
        }
    }
}
?>
<?php include '../includes/header.php'; ?>
<div class="d-wrap">
<?php include '../includes/sidebar.php'; ?>
<main class="d-main">
  <div class="d-topbar">
    <div><h1 class="d-page-title">Edit Listing</h1><p class="d-page-sub">Update your listing details.</p></div>
    <a class="s-btn dark" href="/doon-app/local/listings.php">Back</a>
  </div>

  <?php if ($success): ?>
  <div class="alert ok" style="margin-bottom:12px;">Listing updated.</div>
  <?php endif; ?>
  <?php if ($error): ?>
  <div class="alert err" style="margin-bottom:12px;"><?php echo escape($error); ?></div>
  <?php endif; ?>

  <?php if ($listing['status'] === 'rejected'): ?>
  <div class="alert err" style="margin-bottom:12px;">
    This listing was rejected. <?php if ($listing['rejection_reason']): ?>Reason: <em><?php echo escape($listing['rejection_reason']); ?></em><?php endif; ?>
    Edit and resubmit below.
  </div>
  <?php endif; ?>

  <section class="dc" style="max-width:640px;">
    <form method="POST">
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
      <button class="rf-go" type="submit">Save Changes</button>
    </form>
  </section>
</main>
</div>
<script src="/doon-app/assets/js/main.js"></script>
<?php include '../includes/footer.php'; ?>
