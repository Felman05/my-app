<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireRole('local');
$currentUser = getCurrentUser();
$pageTitle = 'Create Listing';
$additionalCSS = '<link rel="stylesheet" href="/doon-app/assets/css/dashboard.css"><link rel="stylesheet" href="/doon-app/assets/css/components.css">';

// Get this provider's profile id
try {
    $stmt = $pdo->prepare('SELECT id FROM local_provider_profiles WHERE user_id = ? LIMIT 1');
    $stmt->execute([$currentUser['id']]);
    $providerProfile = $stmt->fetch();
} catch (Exception $e) {
    $providerProfile = null;
}

if (!$providerProfile) {
    header('Location: /doon-app/local/profile.php?setup=1');
    exit;
}

$providerId = $providerProfile['id'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title        = trim($_POST['listing_title'] ?? '');
    $listingType  = $_POST['listing_type'] ?? 'other';
    $description  = trim($_POST['description'] ?? '');
    $priceLabel   = $_POST['price_label'] ?? null;
    $price        = $_POST['price'] !== '' ? (float) $_POST['price'] : null;
    $contact      = trim($_POST['contact_number'] ?? '');

    $validTypes = ['accommodation','tour_package','restaurant','transport','event','other'];
    if (!$title) {
        $error = 'Title is required.';
    } elseif (!in_array($listingType, $validTypes)) {
        $error = 'Invalid listing type.';
    } else {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO provider_listings
                    (provider_id, listing_title, listing_type, description, price, price_label, contact_number, status, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, "pending", NOW())'
            );
            $stmt->execute([$providerId, $title, $listingType, $description, $price, $priceLabel ?: null, $contact]);
            header('Location: /doon-app/local/listings.php');
            exit;
        } catch (Exception $e) {
            $error = 'Failed to create listing.';
        }
    }
}
?>
<?php include '../includes/header.php'; ?>
<div class="d-wrap">
<?php include '../includes/sidebar.php'; ?>
<main class="d-main">
  <div class="d-topbar">
    <div><h1 class="d-page-title">Create Listing</h1><p class="d-page-sub">Submit a new listing for admin review.</p></div>
  </div>

  <?php if ($error): ?>
  <div class="alert err" style="margin-bottom:12px;"><?php echo escape($error); ?></div>
  <?php endif; ?>

  <section class="dc" style="max-width:640px;">
    <form method="POST">
      <div class="rf-g mb16">
        <label class="rf-lbl">Listing Title</label>
        <input class="rf-ctrl" type="text" name="listing_title" required placeholder="e.g., Tagaytay Nature Tour">
      </div>
      <div class="rf-g mb16">
        <label class="rf-lbl">Type</label>
        <select class="rf-ctrl" name="listing_type" required>
          <option value="accommodation">Accommodation</option>
          <option value="tour_package">Tour Package</option>
          <option value="restaurant">Restaurant / Food</option>
          <option value="transport">Transport</option>
          <option value="event">Event</option>
          <option value="other" selected>Other</option>
        </select>
      </div>
      <div class="rf-g mb16">
        <label class="rf-lbl">Description</label>
        <textarea class="rf-ctrl" name="description" placeholder="Describe your listing..."></textarea>
      </div>
      <div class="rf-g mb16">
        <label class="rf-lbl">Price (₱, leave blank if free)</label>
        <input class="rf-ctrl" type="number" name="price" min="0" step="0.01" placeholder="0.00">
      </div>
      <div class="rf-g mb16">
        <label class="rf-lbl">Price Category</label>
        <select class="rf-ctrl" name="price_label">
          <option value="">Select</option>
          <option value="free">Free</option>
          <option value="budget">Budget</option>
          <option value="mid_range">Mid Range</option>
          <option value="luxury">Luxury</option>
        </select>
      </div>
      <div class="rf-g mb16">
        <label class="rf-lbl">Contact Number</label>
        <input class="rf-ctrl" type="text" name="contact_number" placeholder="+63 9XX XXX XXXX">
      </div>
      <button class="rf-go" type="submit">Submit for Review</button>
    </form>
  </section>
</main>
</div>
<script src="/doon-app/assets/js/main.js"></script>
<?php include '../includes/footer.php'; ?>
