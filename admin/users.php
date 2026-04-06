<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('admin');
$pageTitle = 'Manage Users';
$additionalCSS = '<link rel="stylesheet" href="/doon-app/assets/css/dashboard.css"><link rel="stylesheet" href="/doon-app/assets/css/components.css">';

$message = '';
$error   = '';

// ── Create user ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? 'tourist';

    if (!$name)                                             $error = 'Name is required.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))     $error = 'Invalid email.';
    elseif (strlen($password) < 8)                         $error = 'Password must be at least 8 characters.';
    elseif (!in_array($role, ['tourist','local','admin']))  $error = 'Invalid role.';
    else {
        try {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'Email is already registered.';
            } else {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare(
                    'INSERT INTO users (name, email, password, role, is_active, data_privacy_consent, created_at)
                     VALUES (?, ?, ?, ?, 1, 1, NOW())'
                );
                $stmt->execute([$name, $email, hashPassword($password), $role]);
                $userId = $pdo->lastInsertId();

                if ($role === 'tourist') {
                    $pdo->prepare(
                        'INSERT INTO tourist_profiles (user_id, generational_profile, preferred_budget, travel_style, location_tracking_consent, created_at)
                         VALUES (?, "millennial", "mid_range", "adventure", 0, NOW())'
                    )->execute([$userId]);
                }

                if ($role === 'local') {
                    $businessName = trim($_POST['business_name'] ?? '') ?: $name;
                    $businessType = $_POST['business_type'] ?? 'other';
                    $province     = trim($_POST['province'] ?? '');
                    $municipality = trim($_POST['municipality'] ?? '');
                    $pdo->prepare(
                        'INSERT INTO local_provider_profiles
                            (user_id, business_name, business_type, province, municipality, address, description, contact_number, is_verified, created_at)
                         VALUES (?, ?, ?, ?, ?, "", "", "", 0, NOW())'
                    )->execute([$userId, $businessName, $businessType, $province, $municipality]);
                }

                $pdo->commit();
                $message = 'User "' . htmlspecialchars($name) . '" created as ' . ucfirst($role) . '.';
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = 'Failed to create user.';
        }
    }
}

// ── Toggle active ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_user'])) {
    $uid = (int) $_POST['toggle_user'];
    try {
        $pdo->prepare('UPDATE users SET is_active = 1 - is_active WHERE id = ?')->execute([$uid]);
    } catch (Exception $e) {}
    header('Location: /doon-app/admin/users.php');
    exit;
}

try {
    $users = $pdo->query(
        'SELECT id, name, email, role, is_active, created_at FROM users ORDER BY created_at DESC'
    )->fetchAll();
    $provinces = $pdo->query('SELECT id, name FROM provinces ORDER BY name')->fetchAll();
} catch (Exception $e) {
    $users = [];
    $provinces = [];
}
?>
<?php include '../includes/header.php'; ?>
<div class="d-wrap">
<?php include '../includes/sidebar.php'; ?>
<main class="d-main">
  <div class="d-topbar">
    <div><h1 class="d-page-title">Manage Users</h1><p class="d-page-sub">All registered user accounts.</p></div>
    <button class="s-btn dark" onclick="document.getElementById('create-form').style.display=document.getElementById('create-form').style.display==='none'?'block':'none'">+ Create User</button>
  </div>

  <?php if ($message): ?><div class="alert ok" style="margin-bottom:12px;"><?php echo escape($message); ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert err" style="margin-bottom:12px;"><?php echo escape($error); ?></div><?php endif; ?>

  <!-- Create user form -->
  <section class="dc" id="create-form" style="display:none;margin-bottom:16px;">
    <div class="dc-title" style="margin-bottom:12px;">Create New Account</div>
    <form method="POST" id="userCreateForm">
      <input type="hidden" name="create_user" value="1">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div class="rf-g">
          <label class="rf-lbl">Role *</label>
          <select class="rf-ctrl" name="role" id="roleSelect" onchange="toggleProviderFields(this.value)">
            <option value="tourist">Tourist</option>
            <option value="local">Local Provider</option>
            <option value="admin">Admin</option>
          </select>
        </div>
        <div class="rf-g">
          <label class="rf-lbl">Full Name *</label>
          <input class="rf-ctrl" name="name" required>
        </div>
        <div class="rf-g">
          <label class="rf-lbl">Email *</label>
          <input class="rf-ctrl" type="email" name="email" required>
        </div>
        <div class="rf-g">
          <label class="rf-lbl">Password * (min 8 chars)</label>
          <input class="rf-ctrl" type="password" name="password" required minlength="8">
        </div>

        <!-- Provider-specific fields -->
        <div id="provider-fields" style="display:none;grid-column:1/-1;">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div class="rf-g">
              <label class="rf-lbl">Business Name</label>
              <input class="rf-ctrl" name="business_name" placeholder="Leave blank to use full name">
            </div>
            <div class="rf-g">
              <label class="rf-lbl">Business Type</label>
              <select class="rf-ctrl" name="business_type">
                <option value="other">Other</option>
                <option value="accommodation">Accommodation</option>
                <option value="tour_operator">Tour Operator</option>
                <option value="restaurant">Restaurant</option>
                <option value="transport">Transport</option>
                <option value="event_organizer">Event Organizer</option>
              </select>
            </div>
            <div class="rf-g">
              <label class="rf-lbl">Province</label>
              <select class="rf-ctrl" name="province">
                <option value="">Select</option>
                <?php foreach ($provinces as $p): ?>
                <option value="<?php echo escape($p['name']); ?>"><?php echo escape($p['name']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="rf-g">
              <label class="rf-lbl">Municipality</label>
              <input class="rf-ctrl" name="municipality" placeholder="e.g., Tagaytay City">
            </div>
          </div>
        </div>
      </div>
      <button class="rf-go" type="submit" style="margin-top:12px;">Create Account</button>
    </form>
  </section>

  <section class="dc">
    <div style="overflow-x:auto;">
      <table class="d-table">
        <thead>
          <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Role</th>
            <th>Status</th>
            <th>Joined</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $user): ?>
          <tr>
            <td><?php echo escape($user['name']); ?></td>
            <td><?php echo escape($user['email']); ?></td>
            <td><span class="badge badge-primary"><?php echo ucfirst($user['role']); ?></span></td>
            <td><span class="badge <?php echo $user['is_active'] ? 'badge-success' : 'badge-danger'; ?>"><?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?></span></td>
            <td><?php echo formatDate($user['created_at']); ?></td>
            <td>
              <form method="POST" style="display:inline;" onsubmit="return confirm('Toggle account status?');">
                <input type="hidden" name="toggle_user" value="<?php echo $user['id']; ?>">
                <button class="s-btn" type="submit" style="font-size:.75rem;padding:2px 8px;">
                  <?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?>
                </button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
</main>
</div>
<script>
function toggleProviderFields(role) {
  document.getElementById('provider-fields').style.display = role === 'local' ? 'block' : 'none';
}
</script>
<script src="/doon-app/assets/js/main.js"></script>
<?php include '../includes/footer.php'; ?>
