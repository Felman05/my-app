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
    verifyCsrf();
    $prefBudget  = $_POST['preferred_budget'] ?? '';
    $travelStyle = $_POST['travel_style'] ?? '';
    $dobMonth    = (int) ($_POST['dob_month'] ?? 0);
    $dobDay      = (int) ($_POST['dob_day']   ?? 0);
    $dobYear     = (int) ($_POST['dob_year']  ?? 0);

    $validBudget = ['budget', 'mid_range', 'luxury', ''];
    $validStyle  = ['solo', 'couple', 'family', 'group', ''];

    $newDob = null;
    if ($dobYear && $dobMonth && $dobDay) {
        if (!checkdate($dobMonth, $dobDay, $dobYear) || $dobYear < 1920) {
            $error = 'Invalid date of birth.';
        } else {
            $newDob = sprintf('%04d-%02d-%02d', $dobYear, $dobMonth, $dobDay);
            if (calcAge($newDob) < 13) $error = 'You must be at least 13 years old.';
        }
    }

    if (!$error && (!in_array($prefBudget, $validBudget) || !in_array($travelStyle, $validStyle))) {
        $error = 'Invalid selection.';
    }

    if (!$error) {
        try {
            // Derive generational_profile from new DOB if provided, else keep existing
            $genProfile = $newDob ? dobToGenerationalProfile($newDob) : ($profile['generational_profile'] ?? null);

            // Update date_of_birth on users table
            if ($newDob) {
                $pdo->prepare('UPDATE users SET date_of_birth = ? WHERE id = ?')
                    ->execute([$newDob, $currentUser['id']]);
                $_SESSION['date_of_birth'] = $newDob;
            }

            if ($profile) {
                $stmt = $pdo->prepare(
                    'UPDATE tourist_profiles
                     SET generational_profile = ?, preferred_budget = ?, travel_style = ?, updated_at = NOW()
                     WHERE user_id = ?'
                );
                $stmt->execute([$genProfile, $prefBudget ?: null, $travelStyle ?: null, $currentUser['id']]);
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO tourist_profiles (user_id, generational_profile, preferred_budget, travel_style, location_tracking_consent, created_at)
                     VALUES (?, ?, ?, ?, 0, NOW())'
                );
                $stmt->execute([$currentUser['id'], $genProfile, $prefBudget ?: null, $travelStyle ?: null]);
            }
            $profile = ['generational_profile' => $genProfile, 'preferred_budget' => $prefBudget, 'travel_style' => $travelStyle];
            $currentUser['date_of_birth'] = $newDob ?? $currentUser['date_of_birth'];
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
      <?php
        $dob = $currentUser['date_of_birth'];
        $computedAge = $dob ? calcAge($dob) : null;
        $detectedGen = $dob ? dobToGenerationalProfile($dob) : null;
        $genLabels = ['gen_z' => 'Gen Z', 'millennial' => 'Millennial', 'gen_x' => 'Gen X', 'boomer' => 'Boomer'];
      ?>
      <?php if ($dob): ?>
      <div class="dest-row"><div class="dest-lbl">Date of Birth</div><div><?php echo date('F j, Y', strtotime($dob)); ?></div></div>
      <div class="dest-row"><div class="dest-lbl">Age</div><div><?php echo $computedAge; ?> years old &mdash; <strong><?php echo $genLabels[$detectedGen] ?? '—'; ?></strong></div></div>
      <?php endif; ?>
    </section>

    <section class="dc">
      <div class="dc-title mb16">Travel Preferences</div>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo escape(csrfToken()); ?>">
        <div class="rf-g mb16">
          <label class="rf-lbl">Date of Birth <span style="font-weight:400;color:var(--i4);">(sets your generational profile)</span></label>
          <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <?php
              $dobParts = $dob ? explode('-', $dob) : [null, null, null];
              $selYear  = $dobParts[0] ?? '';
              $selMonth = $dobParts[1] ? (int)$dobParts[1] : 0;
              $selDay   = $dobParts[2] ? (int)$dobParts[2] : 0;
              $months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
            ?>
            <select class="rf-ctrl" name="dob_month" style="flex:2;min-width:110px;">
              <option value="">Month</option>
              <?php foreach ($months as $i => $m): $val = $i + 1; ?>
              <option value="<?php echo $val; ?>" <?php echo $selMonth === $val ? 'selected' : ''; ?>><?php echo $m; ?></option>
              <?php endforeach; ?>
            </select>
            <select class="rf-ctrl" name="dob_day" style="flex:1;min-width:70px;">
              <option value="">Day</option>
              <?php for ($d = 1; $d <= 31; $d++): ?>
              <option value="<?php echo $d; ?>" <?php echo $selDay === $d ? 'selected' : ''; ?>><?php echo $d; ?></option>
              <?php endfor; ?>
            </select>
            <input class="rf-ctrl" type="number" name="dob_year" placeholder="Year" min="1920" max="<?php echo date('Y') - 13; ?>" value="<?php echo escape($selYear); ?>" style="flex:1;min-width:80px;">
          </div>
          <?php if ($detectedGen): ?>
          <div style="margin-top:6px;font-size:.78rem;color:var(--i4);">Auto-detected: <strong><?php echo $genLabels[$detectedGen]; ?></strong></div>
          <?php endif; ?>
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
