<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('tourist');
$currentUser = getCurrentUser();
$pageTitle = 'My Profile';
$additionalCSS = '<link rel="stylesheet" href="/doon-app/assets/css/dashboard.css"><link rel="stylesheet" href="/doon-app/assets/css/components.css">';

try {
    $stmt = $pdo->prepare('SELECT * FROM tourist_profiles WHERE user_id = ? LIMIT 1');
    $stmt->execute([$currentUser['id']]);
    $profile = $stmt->fetch();
} catch (Exception $e) {
    $profile = null;
}

$success = false;
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $genProfile    = $_POST['generational_profile'] ?? '';
    $prefBudget    = $_POST['preferred_budget'] ?? '';
    $travelStyle   = $_POST['travel_style'] ?? '';

    $validGen    = ['gen_z', 'millennial', 'gen_x', 'boomer', ''];
    $validBudget = ['budget', 'mid_range', 'luxury', ''];
    $validStyle  = ['solo', 'couple', 'family', 'group', ''];

    if (!in_array($genProfile, $validGen) || !in_array($prefBudget, $validBudget) || !in_array($travelStyle, $validStyle)) {
        $error = 'Invalid selection.';
    } else {
        try {
            if ($profile) {
                $stmt = $pdo->prepare(
                    'UPDATE tourist_profiles
                     SET generational_profile = ?, preferred_budget = ?, travel_style = ?, updated_at = NOW()
                     WHERE user_id = ?'
                );
                $stmt->execute([$genProfile ?: null, $prefBudget ?: null, $travelStyle ?: null, $currentUser['id']]);
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO tourist_profiles (user_id, generational_profile, preferred_budget, travel_style, location_tracking_consent, created_at)
                     VALUES (?, ?, ?, ?, 0, NOW())'
                );
                $stmt->execute([$currentUser['id'], $genProfile ?: null, $prefBudget ?: null, $travelStyle ?: null]);
            }
            $profile = ['generational_profile' => $genProfile, 'preferred_budget' => $prefBudget, 'travel_style' => $travelStyle];
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
    <div><h1 class="d-page-title">My Profile</h1><p class="d-page-sub">Update your travel preferences for better recommendations.</p></div>
    <a href="/doon-app/api/auth.php?action=logout" class="s-btn dark">Logout</a>
  </div>

  <?php if ($success): ?>
  <div class="alert ok" style="margin-bottom:12px;">Profile saved.</div>
  <?php endif; ?>
  <?php if ($error): ?>
  <div class="alert err" style="margin-bottom:12px;"><?php echo escape($error); ?></div>
  <?php endif; ?>

  <div class="g2">
    <section class="dc">
      <div class="dc-title mb16">Account</div>
      <div class="dest-row"><div class="dest-lbl">Name</div><div><?php echo escape($currentUser['name']); ?></div></div>
      <div class="dest-row"><div class="dest-lbl">Email</div><div><?php echo escape($currentUser['email']); ?></div></div>
      <div class="dest-row"><div class="dest-lbl">Role</div><div><?php echo ucfirst($currentUser['role']); ?></div></div>
    </section>

    <section class="dc">
      <div class="dc-title mb16">Travel Preferences</div>
      <form method="POST">
        <div class="rf-g mb16">
          <label class="rf-lbl">Generational Profile</label>
          <select class="rf-ctrl" name="generational_profile">
            <option value="">Not set</option>
            <option value="gen_z"      <?php echo ($profile['generational_profile'] ?? '') === 'gen_z'      ? 'selected' : ''; ?>>Gen Z (1997–2012)</option>
            <option value="millennial" <?php echo ($profile['generational_profile'] ?? '') === 'millennial' ? 'selected' : ''; ?>>Millennial (1981–1996)</option>
            <option value="gen_x"      <?php echo ($profile['generational_profile'] ?? '') === 'gen_x'      ? 'selected' : ''; ?>>Gen X (1965–1980)</option>
            <option value="boomer"     <?php echo ($profile['generational_profile'] ?? '') === 'boomer'     ? 'selected' : ''; ?>>Boomer (1946–1964)</option>
          </select>
        </div>
        <div class="rf-g mb16">
          <label class="rf-lbl">Preferred Budget</label>
          <select class="rf-ctrl" name="preferred_budget">
            <option value="">Not set</option>
            <option value="budget"    <?php echo ($profile['preferred_budget'] ?? '') === 'budget'    ? 'selected' : ''; ?>>Budget</option>
            <option value="mid_range" <?php echo ($profile['preferred_budget'] ?? '') === 'mid_range' ? 'selected' : ''; ?>>Mid Range</option>
            <option value="luxury"    <?php echo ($profile['preferred_budget'] ?? '') === 'luxury'    ? 'selected' : ''; ?>>Luxury</option>
          </select>
        </div>
        <div class="rf-g mb16">
          <label class="rf-lbl">Travel Style</label>
          <select class="rf-ctrl" name="travel_style">
            <option value="">Not set</option>
            <option value="solo"   <?php echo ($profile['travel_style'] ?? '') === 'solo'   ? 'selected' : ''; ?>>Solo</option>
            <option value="couple" <?php echo ($profile['travel_style'] ?? '') === 'couple' ? 'selected' : ''; ?>>Couple</option>
            <option value="family" <?php echo ($profile['travel_style'] ?? '') === 'family' ? 'selected' : ''; ?>>Family</option>
            <option value="group"  <?php echo ($profile['travel_style'] ?? '') === 'group'  ? 'selected' : ''; ?>>Group</option>
          </select>
        </div>
        <button class="rf-go" type="submit">Save Preferences</button>
      </form>
    </section>
  </div>
</main>
</div>
<script src="/doon-app/assets/js/main.js"></script>
<?php include '../includes/footer.php'; ?>
