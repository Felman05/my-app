<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireRole('local');
$currentUser = getCurrentUser();
$pageTitle = 'Business Profile';
$additionalCSS = '<link rel="stylesheet" href="/doon-app/assets/css/dashboard.css"><link rel="stylesheet" href="/doon-app/assets/css/components.css">';

$success = false;
$error   = '';

try {
    $stmt = $pdo->prepare('SELECT * FROM local_provider_profiles WHERE user_id = ? LIMIT 1');
    $stmt->execute([$currentUser['id']]);
    $profile = $stmt->fetch();
} catch (Exception $e) {
    $profile = null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $businessName = trim($_POST['business_name'] ?? '');
    $businessType = $_POST['business_type'] ?? 'other';
    $province     = trim($_POST['province'] ?? '');
    $municipality = trim($_POST['municipality'] ?? '');
    $address      = trim($_POST['address'] ?? '');
    $contact      = trim($_POST['contact_number'] ?? '');
    $website      = trim($_POST['website_url'] ?? '');
    $facebook     = trim($_POST['facebook_url'] ?? '');
    $description  = trim($_POST['description'] ?? '');

    $validTypes = ['accommodation','restaurant','tour_operator','transport','event_organizer','attraction','other'];

    if (!$businessName) {
        $error = 'Business name is required.';
    } elseif (!in_array($businessType, $validTypes)) {
        $error = 'Invalid business type.';
    } else {
        try {
            if ($profile) {
                $stmt = $pdo->prepare(
                    'UPDATE local_provider_profiles
                     SET business_name=?, business_type=?, province=?, municipality=?, address=?,
                         contact_number=?, website_url=?, facebook_url=?, description=?, updated_at=NOW()
                     WHERE user_id=?'
                );
                $stmt->execute([$businessName, $businessType, $province, $municipality, $address, $contact, $website, $facebook, $description, $currentUser['id']]);
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO local_provider_profiles
                        (user_id, business_name, business_type, province, municipality, address, contact_number, website_url, facebook_url, description, is_verified, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())'
                );
                $stmt->execute([$currentUser['id'], $businessName, $businessType, $province, $municipality, $address, $contact, $website, $facebook, $description]);
            }
            // Reload profile
            $stmt = $pdo->prepare('SELECT * FROM local_provider_profiles WHERE user_id = ? LIMIT 1');
            $stmt->execute([$currentUser['id']]);
            $profile = $stmt->fetch();
            $success = true;
        } catch (Exception $e) {
            $error = 'Failed to save profile.';
        }
    }
}
?>
<?php include '../includes/header.php'; ?>
<div class="d-wrap">
<?php include '../includes/sidebar.php'; ?>
<main class="d-main">
  <div class="d-topbar">
    <div><h1 class="d-page-title">Business Profile</h1><p class="d-page-sub">Keep your business info up to date.</p></div>
    <a href="/doon-app/api/auth.php?action=logout" class="s-btn dark">Logout</a>
  </div>

  <?php if ($success): ?>
  <div class="alert ok" style="margin-bottom:12px;">Profile saved.</div>
  <?php endif; ?>
  <?php if ($error): ?>
  <div class="alert err" style="margin-bottom:12px;"><?php echo escape($error); ?></div>
  <?php endif; ?>

  <section class="dc" style="max-width:640px;">
    <form method="POST">
      <div class="rf-g mb16">
        <label class="rf-lbl">Business Name</label>
        <input class="rf-ctrl" type="text" name="business_name" required value="<?php echo escape($profile['business_name'] ?? $currentUser['name']); ?>">
      </div>
      <div class="rf-g mb16">
        <label class="rf-lbl">Business Type</label>
        <select class="rf-ctrl" name="business_type">
          <?php foreach (['accommodation'=>'Accommodation','restaurant'=>'Restaurant','tour_operator'=>'Tour Operator','transport'=>'Transport','event_organizer'=>'Event Organizer','attraction'=>'Attraction','other'=>'Other'] as $val => $lbl): ?>
          <option value="<?php echo $val; ?>" <?php echo ($profile['business_type'] ?? 'other') === $val ? 'selected' : ''; ?>><?php echo $lbl; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="rf-g mb16">
        <label class="rf-lbl">Province</label>
        <select class="rf-ctrl" name="province">
          <option value="">Select province</option>
          <?php foreach (['Batangas','Laguna','Cavite','Rizal','Quezon'] as $prov): ?>
          <option value="<?php echo $prov; ?>" <?php echo ($profile['province'] ?? '') === $prov ? 'selected' : ''; ?>><?php echo $prov; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="rf-g mb16">
        <label class="rf-lbl">Municipality / City</label>
        <input class="rf-ctrl" type="text" name="municipality" value="<?php echo escape($profile['municipality'] ?? ''); ?>" placeholder="e.g., Tagaytay City">
      </div>
      <div class="rf-g mb16">
        <label class="rf-lbl">Full Address</label>
        <textarea class="rf-ctrl" name="address" placeholder="Street address..."><?php echo escape($profile['address'] ?? ''); ?></textarea>
      </div>
      <div class="rf-g mb16">
        <label class="rf-lbl">Contact Number</label>
        <input class="rf-ctrl" type="text" name="contact_number" value="<?php echo escape($profile['contact_number'] ?? ''); ?>" placeholder="+63 9XX XXX XXXX">
      </div>
      <div class="rf-g mb16">
        <label class="rf-lbl">Website URL</label>
        <input class="rf-ctrl" type="url" name="website_url" value="<?php echo escape($profile['website_url'] ?? ''); ?>" placeholder="https://...">
      </div>
      <div class="rf-g mb16">
        <label class="rf-lbl">Facebook URL</label>
        <input class="rf-ctrl" type="url" name="facebook_url" value="<?php echo escape($profile['facebook_url'] ?? ''); ?>" placeholder="https://facebook.com/...">
      </div>
      <div class="rf-g mb16">
        <label class="rf-lbl">Business Description</label>
        <textarea class="rf-ctrl" name="description" placeholder="Tell tourists about your business..."><?php echo escape($profile['description'] ?? ''); ?></textarea>
      </div>
      <button class="rf-go" type="submit">Save Profile</button>
    </form>
  </section>
</main>
</div>
<script src="/doon-app/assets/js/main.js"></script>
<?php include '../includes/footer.php'; ?>
