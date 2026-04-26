<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('local');
$currentUser = getCurrentUser();

$pwError   = '';
$pwSuccess = false;

// ── Handle forced password change ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    verifyCsrf();
    $newPass     = $_POST['new_password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';

    if (strlen($newPass) < 8) {
        $pwError = 'Password must be at least 8 characters.';
    } elseif ($newPass !== $confirmPass) {
        $pwError = 'Passwords do not match.';
    } else {
        try {
            $pdo->prepare('UPDATE users SET password = ?, must_change_password = 0 WHERE id = ?')
                ->execute([hashPassword($newPass), $currentUser['id']]);
            $_SESSION['must_change_password'] = 0;
            $pwSuccess = true;
        } catch (Exception $e) {
            $pwError = 'Failed to update password. Please try again.';
        }
    }
}
$pageTitle = 'Provider Dashboard';
$additionalCSS = '<link rel="stylesheet" href="/doon-app/assets/css/dashboard.css"><link rel="stylesheet" href="/doon-app/assets/css/components.css">';
try {
    $stmt = $pdo->prepare('SELECT * FROM local_provider_profiles WHERE user_id = ?');
    $stmt->execute([$currentUser['id']]);
    $profile = $stmt->fetch();
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM provider_listings pl
         JOIN local_provider_profiles lpp ON pl.provider_id = lpp.id
         WHERE lpp.user_id = ?'
    );
    $stmt->execute([$currentUser['id']]);
    $listingCount = $stmt->fetchColumn();
} catch (Exception $e) {
    $profile = null;
    $listingCount = 0;
}
?>
<?php include '../includes/header.php'; ?>
<div class="d-wrap">
<?php include '../includes/sidebar.php'; ?>
<main class="d-main">
  <div class="d-topbar"><div><h1 class="d-page-title">Provider Dashboard</h1><p class="d-page-sub">Manage listings and monitor performance.</p></div><a class="s-btn green" href="/doon-app/local/listing-create.php">Add Listing</a></div>
  <section class="kpi-row c3">
    <article class="kpi"><div class="kpi-lbl">My Listings</div><div class="kpi-val"><?php echo (int) $listingCount; ?></div><div class="kpi-sub">Active portfolio</div></article>
    <article class="kpi"><div class="kpi-lbl">Profile Status</div><div class="kpi-val"><?php echo $profile && $profile['is_verified'] ? 'OK' : 'PENDING'; ?></div><div class="kpi-sub"><?php echo $profile && $profile['is_verified'] ? 'Verified account' : 'Verification in progress'; ?></div></article>
    <article class="kpi"><div class="kpi-lbl">Quick Link</div><div class="kpi-val">OPEN</div><div class="kpi-sub"><a href="/doon-app/local/listings.php">Manage listings</a></div></article>
  </section>
  <section class="dc">
    <div class="dc-head"><div><div class="dc-title">Recent Listings</div></div><a class="s-btn" href="/doon-app/local/analytics.php">Full Analytics</a></div>
    <div class="dest-list" style="margin-top:8px;">
      <?php if ($profile): ?>
      <?php
        try {
            $stmt2 = $pdo->prepare(
                'SELECT pl.listing_title, pl.status, pl.listing_type FROM provider_listings pl
                 JOIN local_provider_profiles lpp ON pl.provider_id = lpp.id
                 WHERE lpp.user_id = ? ORDER BY pl.created_at DESC LIMIT 5'
            );
            $stmt2->execute([$currentUser['id']]);
            $recentListings = $stmt2->fetchAll();
        } catch (Exception $e) { $recentListings = []; }
        foreach ($recentListings as $rl):
      ?>
      <div class="dest-row">
        <div class="dest-ico">L</div>
        <div>
          <div class="dest-name"><?php echo escape($rl['listing_title']); ?></div>
          <div class="dest-meta"><?php echo ucfirst(str_replace('_', ' ', $rl['listing_type'])); ?></div>
        </div>
        <span class="badge <?php echo $rl['status'] === 'active' ? 'badge-success' : ($rl['status'] === 'rejected' ? 'badge-danger' : 'badge-primary'); ?>">
          <?php echo ucfirst($rl['status']); ?>
        </span>
      </div>
      <?php endforeach; ?>
      <?php if (empty($recentListings ?? [])): ?>
      <div class="dest-row" style="opacity:.5;">No listings yet. <a href="/doon-app/local/listing-create.php">Create one.</a></div>
      <?php endif; ?>
      <?php endif; ?>
    </div>
  </section>
</main>
</div>
<script src="/doon-app/assets/js/main.js"></script>
<script>
function togglePw(id) {
  var input = document.getElementById(id);
  var icon  = document.getElementById(id + '-eye');
  if (input.type === 'password') { input.type = 'text';     icon.classList.replace('fa-eye','fa-eye-slash'); }
  else                           { input.type = 'password'; icon.classList.replace('fa-eye-slash','fa-eye'); }
}
</script>

<?php if ($_SESSION['must_change_password'] ?? 0): ?>
<!-- Forced password change modal -->
<div id="pw-modal-backdrop" style="position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:1000;display:flex;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:12px;box-shadow:0 8px 40px rgba(0,0,0,.2);width:min(420px,94vw);padding:32px;">
    <div style="font-size:1.1rem;font-weight:700;margin-bottom:4px;">Set your password</div>
    <div style="font-size:.85rem;color:#6b7280;margin-bottom:20px;">Your account was created by an admin. Please set a new password before continuing.</div>

    <?php if ($pwError): ?>
    <div class="alert err" style="margin-bottom:14px;"><?php echo escape($pwError); ?></div>
    <?php endif; ?>

    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?php echo escape(csrfToken()); ?>">
      <input type="hidden" name="change_password" value="1">
      <div class="rf-g mb16">
        <label class="rf-lbl">New Password</label>
        <div style="position:relative;">
          <input class="rf-ctrl" type="password" id="modal_new_pw" name="new_password" required minlength="8" placeholder="Min. 8 characters" autofocus style="padding-right:40px;">
          <button type="button" onclick="togglePw('modal_new_pw')" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#9ca3af;padding:4px;" tabindex="-1">
            <i class="fa-solid fa-eye" id="modal_new_pw-eye"></i>
          </button>
        </div>
      </div>
      <div class="rf-g mb16">
        <label class="rf-lbl">Confirm Password</label>
        <div style="position:relative;">
          <input class="rf-ctrl" type="password" id="modal_confirm_pw" name="confirm_password" required minlength="8" placeholder="Repeat password" style="padding-right:40px;">
          <button type="button" onclick="togglePw('modal_confirm_pw')" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#9ca3af;padding:4px;" tabindex="-1">
            <i class="fa-solid fa-eye" id="modal_confirm_pw-eye"></i>
          </button>
        </div>
      </div>
      <button class="rf-go" type="submit" style="width:100%;">Save Password</button>
    </form>
  </div>
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
