<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/env.php';
require_once '../includes/provinces.php';
requireRole('admin');
$pageTitle = 'Manage Users';
$additionalCSS = '<link rel="stylesheet" href="/doon-app/assets/css/dashboard.css"><link rel="stylesheet" href="/doon-app/assets/css/components.css">';

$message      = '';
$error        = '';
$tempPassword = '';

// ── Create user ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    verifyCsrf();
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $role    = $_POST['role'] ?? 'tourist';
    $isLocal = $role === 'local';

    // Local providers get an auto-generated temp password — no field needed
    if ($isLocal) {
        $tempPassword = strtoupper(bin2hex(random_bytes(4)));
        $password     = $tempPassword;
    } else {
        $password = $_POST['password'] ?? '';
    }

    if (!$name)                                             $error = 'Name is required.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))     $error = 'Invalid email.';
    elseif (!$isLocal && strlen($password) < 8)            $error = 'Password must be at least 8 characters.';
    elseif (!in_array($role, ['tourist','local','admin']))  $error = 'Invalid role.';
    else {
        try {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error        = 'Email is already registered.';
                $tempPassword = '';
            } else {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare(
                    'INSERT INTO users (name, email, password, role, is_active, data_privacy_consent, must_change_password, created_at)
                     VALUES (?, ?, ?, ?, 1, 1, ?, NOW())'
                );
                $stmt->execute([$name, $email, hashPassword($password), $role, $isLocal ? 1 : 0]);
                $userId = $pdo->lastInsertId();

                if ($role === 'tourist') {
                    $pdo->prepare(
                        'INSERT INTO tourist_profiles (user_id, generational_profile, preferred_budget, travel_style, location_tracking_consent, created_at)
                         VALUES (?, "millennial", "mid_range", "solo", 0, NOW())'
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
                logAdminActivity($pdo, (int) $_SESSION['user_id'], 'create_user', 'user', (int) $userId, "Created {$role} account for \"{$name}\" ({$email})");
                $message = 'Account created for "' . htmlspecialchars($name) . '" as ' . ucfirst($role) . '.';
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error        = 'Failed to create user.';
            $tempPassword = '';
        }
    }
}

// ── Toggle active ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_user'])) {
    verifyCsrf();
    $uid = (int) $_POST['toggle_user'];
    try {
        $pdo->prepare('UPDATE users SET is_active = 1 - is_active WHERE id = ?')->execute([$uid]);
        logAdminActivity($pdo, (int) $_SESSION['user_id'], 'toggle_user_active', 'user', $uid, "Toggled active status for user #{$uid}");
    } catch (Exception $e) {}
    header('Location: /doon-app/admin/users.php');
    exit;
}

// ── Verify / unverify provider ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_provider'])) {
    verifyCsrf();
    $uid    = (int) ($_POST['verify_provider'] ?? 0);
    $newVal = (int) ($_POST['verify_value'] ?? 0);
    if ($uid && in_array($newVal, [0, 1])) {
        try {
            $pdo->prepare(
                'UPDATE local_provider_profiles SET is_verified = ? WHERE user_id = ?'
            )->execute([$newVal, $uid]);
            $label = $newVal ? 'Verified' : 'Unverified';
            logAdminActivity($pdo, (int) $_SESSION['user_id'], 'verify_provider', 'user', $uid, "{$label} provider profile for user #{$uid}");
        } catch (Exception $e) {}
    }
    header('Location: /doon-app/admin/users.php');
    exit;
}

$gmKey = env('GOOGLE_MAPS_API_KEY', '');

try {
    $users = $pdo->query(
        'SELECT u.id, u.name, u.email, u.role, u.is_active, u.created_at,
                lpp.is_verified
         FROM users u
         LEFT JOIN local_provider_profiles lpp ON lpp.user_id = u.id
         ORDER BY u.created_at DESC'
    )->fetchAll();
    $provinces = $pdo->query('SELECT id, name FROM provinces ORDER BY name')->fetchAll();

    // Providers grouped by province for the map
    $allProviders = $pdo->query(
        'SELECT u.name, u.is_active,
                lpp.business_name, lpp.business_type, lpp.province, lpp.municipality,
                COUNT(pl.id) as listing_count
         FROM local_provider_profiles lpp
         JOIN users u ON lpp.user_id = u.id
         LEFT JOIN provider_listings pl ON pl.provider_id = lpp.id
         GROUP BY lpp.id
         ORDER BY lpp.province, u.name'
    )->fetchAll();
} catch (Exception $e) {
    $users        = [];
    $provinces    = [];
    $allProviders = [];
}

// Build province summary for map markers
$byProvince = [];
foreach ($allProviders as $p) {
    $key = strtolower(trim($p['province'] ?? ''));
    $byProvince[$key][] = $p;
}
$provinceSummary = [];
foreach ($PROVINCE_LOCATIONS as $key => $loc) {
    $provinceSummary[$key] = [
        'name'      => $loc['name'],
        'lat'       => $loc['lat'],
        'lon'       => $loc['lon'],
        'count'     => count($byProvince[$key] ?? []),
        'providers' => $byProvince[$key] ?? [],
    ];
}
?>
<?php include '../includes/header.php'; ?>
<div class="d-wrap">
<?php include '../includes/sidebar.php'; ?>
<main class="d-main">
  <div class="d-topbar">
    <div><h1 class="d-page-title">Manage Users</h1><p class="d-page-sub">All registered user accounts.</p></div>
    <button class="s-btn dark" onclick="document.getElementById('create-form').style.display=document.getElementById('create-form').style.display==='none'?'block':'none'">+ Create Provider</button>
  </div>

  <?php if ($message): ?>
  <div class="alert ok" style="margin-bottom:12px;">
    <?php echo escape($message); ?>
    <?php if ($tempPassword): ?>
    <div style="margin-top:10px;padding:12px;background:#fff;border:1px solid #d1fae5;border-radius:6px;">
      <div style="font-size:.78rem;color:#065f46;margin-bottom:6px;">Temporary password — share this with the provider:</div>
      <div style="font-size:1.3rem;font-weight:700;letter-spacing:4px;font-family:monospace;color:#111;"><?php echo escape($tempPassword); ?></div>
      <div style="font-size:.75rem;color:#6b7280;margin-top:6px;">The provider will be asked to set a new password on first login.</div>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>
  <?php if ($error): ?><div class="alert err" style="margin-bottom:12px;"><?php echo escape($error); ?></div><?php endif; ?>

  <!-- Create provider form -->
  <section class="dc" id="create-form" style="display:none;margin-bottom:16px;">
    <div class="dc-title" style="margin-bottom:4px;">Create Provider Account</div>
    <div style="font-size:.82rem;color:var(--i3);margin-bottom:14px;">A temporary password will be generated and shown to you after creation. The provider will be asked to change it on first login.</div>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?php echo escape(csrfToken()); ?>">
      <input type="hidden" name="create_user" value="1">
      <input type="hidden" name="role" value="local">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
        <div class="rf-g">
          <label class="rf-lbl">Full Name *</label>
          <input class="rf-ctrl" name="name" required placeholder="Provider's full name">
        </div>
        <div class="rf-g">
          <label class="rf-lbl">Email *</label>
          <input class="rf-ctrl" type="email" name="email" required placeholder="provider@email.com">
        </div>
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
      <button class="rf-go" type="submit" style="margin-top:12px;">Create Provider Account</button>
    </form>
  </section>

  <!-- Provider Map -->
  <section class="dc" style="margin-bottom:16px;padding:0;overflow:hidden;">
    <div style="padding:16px 20px 12px;border-bottom:1px solid var(--bd);">
      <div class="dc-title">Providers by Province</div>
      <div style="font-size:.8rem;color:var(--i3);margin-top:2px;">Click a province marker to see its providers.</div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 320px;">
      <div id="provider-map" style="height:400px;"></div>
      <div style="border-left:1px solid var(--bd);display:flex;flex-direction:column;">
        <div style="padding:12px 16px;border-bottom:1px solid var(--bd);font-weight:600;font-size:.85rem;" id="map-panel-title">Select a province</div>
        <div id="map-panel-list" style="overflow-y:auto;flex:1;padding:8px 0;">
          <div style="padding:12px 16px;font-size:.82rem;color:var(--i3);">Click a marker on the map.</div>
        </div>
      </div>
    </div>
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
            <th>Verified</th>
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
            <td>
              <?php if ($user['role'] === 'local'): ?>
                <?php if ($user['is_verified']): ?>
                  <span class="badge badge-success">Verified</span>
                <?php else: ?>
                  <span class="badge" style="background:#fef3c7;color:#92400e;">Pending</span>
                <?php endif; ?>
              <?php else: ?>
                <span style="color:var(--i4);font-size:.8rem;">—</span>
              <?php endif; ?>
            </td>
            <td><?php echo formatDate($user['created_at']); ?></td>
            <td style="white-space:nowrap;">
              <form method="POST" style="display:inline;" onsubmit="return confirm('Toggle account status?');">
                <input type="hidden" name="csrf_token" value="<?php echo escape(csrfToken()); ?>">
                <input type="hidden" name="toggle_user" value="<?php echo $user['id']; ?>">
                <button class="s-btn" type="submit" style="font-size:.75rem;padding:2px 8px;">
                  <?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?>
                </button>
              </form>
              <?php if ($user['role'] === 'local'): ?>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?php echo escape(csrfToken()); ?>">
                <input type="hidden" name="verify_provider" value="<?php echo $user['id']; ?>">
                <input type="hidden" name="verify_value" value="<?php echo $user['is_verified'] ? 0 : 1; ?>">
                <button class="s-btn <?php echo $user['is_verified'] ? '' : 'green'; ?>" type="submit" style="font-size:.75rem;padding:2px 8px;margin-left:4px;">
                  <?php echo $user['is_verified'] ? 'Unverify' : 'Verify'; ?>
                </button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
</main>
</div>
<script src="/doon-app/assets/js/main.js"></script>
<script>
var provinceSummary = <?php echo json_encode($provinceSummary); ?>;

function initMap() {
  var map = new google.maps.Map(document.getElementById('provider-map'), {
    center: { lat: 14.0, lng: 121.2 },
    zoom: 9,
    mapTypeControl: false,
    streetViewControl: false,
    fullscreenControl: false,
    styles: [{ featureType: 'poi', elementType: 'labels', stylers: [{ visibility: 'off' }] }]
  });

  var infoWindow = new google.maps.InfoWindow();

  Object.keys(provinceSummary).forEach(function (key) {
    var ps  = provinceSummary[key];
    var pos = { lat: parseFloat(ps.lat), lng: parseFloat(ps.lon) };

    var marker = new google.maps.Marker({
      position : pos,
      map      : map,
      title    : ps.name,
      label    : { text: String(ps.count), color: '#fff', fontWeight: 'bold', fontSize: '13px' },
      icon     : {
        path        : google.maps.SymbolPath.CIRCLE,
        scale       : 22,
        fillColor   : ps.count > 0 ? '#111827' : '#9ca3af',
        fillOpacity : 1,
        strokeColor : '#fff',
        strokeWeight: 2
      }
    });

    marker.addListener('click', function () {
      infoWindow.setContent('<strong>' + ps.name + '</strong><br><span style="font-size:.8rem;color:#6b7280;">' + ps.count + ' provider' + (ps.count !== 1 ? 's' : '') + '</span>');
      infoWindow.open(map, marker);
      showPanel(key);
    });
  });
}

function showPanel(key) {
  var ps      = provinceSummary[key];
  var titleEl = document.getElementById('map-panel-title');
  var listEl  = document.getElementById('map-panel-list');

  titleEl.textContent = ps.name + ' — ' + ps.count + ' provider' + (ps.count !== 1 ? 's' : '');

  if (!ps.providers || ps.providers.length === 0) {
    listEl.innerHTML = '<div style="padding:12px 16px;font-size:.82rem;color:var(--i3);">No providers in ' + ps.name + '.</div>';
    return;
  }

  listEl.innerHTML = ps.providers.map(function (p) {
    var name  = p.business_name || p.name;
    var loc   = p.municipality ? p.municipality : ps.name;
    var type  = (p.business_type || 'other').replace(/_/g, ' ');
    var color = p.is_active ? '#16a34a' : '#dc2626';
    var status = p.is_active ? 'Active' : 'Inactive';
    return '<div style="padding:10px 16px;border-bottom:1px solid var(--bd);">'
      + '<div style="font-weight:600;font-size:.85rem;">' + name + '</div>'
      + '<div style="font-size:.78rem;color:var(--i3);margin-top:2px;">' + loc + ' &bull; ' + type + '</div>'
      + '<div style="font-size:.75rem;margin-top:2px;color:' + color + ';">' + status
      + (p.listing_count > 0 ? ' &bull; ' + p.listing_count + ' listing' + (p.listing_count != 1 ? 's' : '') : '') + '</div>'
      + '</div>';
  }).join('');
}
</script>
<?php if ($gmKey): ?>
<script src="https://maps.googleapis.com/maps/api/js?key=<?php echo urlencode($gmKey); ?>&callback=initMap" async defer></script>
<?php else: ?>
<div style="display:none;"></div>
<script>
function initMap() {
  var el = document.getElementById('provider-map');
  el.style.display = 'flex';
  el.style.alignItems = 'center';
  el.style.justifyContent = 'center';
  el.style.background = '#f3f4f6';
  el.innerHTML = '<span style="color:#6b7280;font-size:.85rem;">Google Maps API key not configured.</span>';
}
initMap();
</script>
<?php endif; ?>
<?php include '../includes/footer.php'; ?>
