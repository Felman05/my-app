<?php
/**
 * Tourist Dashboard
 */

require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$pageTitle = 'Dashboard';
$additionalCSS = '<link rel="stylesheet" href="/doon-app/assets/css/dashboard.css"><link rel="stylesheet" href="/doon-app/assets/css/components.css">';

// Require tourist role
requireRole('tourist');

$currentUser = getCurrentUser();

// Get tourist profile
try {
    $stmt = $pdo->prepare('SELECT * FROM tourist_profiles WHERE user_id = ?');
    $stmt->execute([$currentUser['id']]);
    $profile = $stmt->fetch();

    // Get saved favorites count
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM favorites WHERE user_id = ?');
    $stmt->execute([$currentUser['id']]);
    $favCount = $stmt->fetchColumn();

    // Get itineraries count
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM itineraries WHERE user_id = ?');
    $stmt->execute([$currentUser['id']]);
    $itineraryCount = $stmt->fetchColumn();

    // Get recent reviews
    $stmt = $pdo->prepare(
        'SELECT r.*, d.name as destination_name, d.province_id
         FROM reviews r
         JOIN destinations d ON r.destination_id = d.id
         WHERE r.user_id = ?
         ORDER BY r.created_at DESC
         LIMIT 5'
    );
    $stmt->execute([$currentUser['id']]);
    $recentReviews = $stmt->fetchAll();

    // For quick recommendation form
    $provinces   = $pdo->query('SELECT id, name FROM provinces ORDER BY name')->fetchAll();
    $categories  = $pdo->query('SELECT id, name FROM activity_categories ORDER BY name')->fetchAll();

} catch (PDOException $e) {
    $profile = null;
    $favCount = 0;
    $itineraryCount = 0;
    $recentReviews = [];
    $provinces = [];
    $categories = [];
}
?>

<?php include '../includes/header.php'; ?>
<div class="d-wrap">
  <?php include '../includes/sidebar.php'; ?>

  <main class="d-main">
    <div class="d-topbar">
      <div>
        <h1 class="d-page-title">Tourist Dashboard</h1>
        <p class="d-page-sub">Welcome back, <?php echo escape($currentUser['name']); ?>. Plan your next trip.</p>
      </div>
      <div class="d-actions">
        <button class="d-ico-btn">N<span class="notif-dot"></span></button>
        <a class="d-ava-btn" href="/doon-app/tourist/profile.php"><?php echo strtoupper(substr($currentUser['name'], 0, 1)); ?></a>
      </div>
    </div>

    <section class="kpi-row c4">
      <article class="kpi"><div class="kpi-lbl">Saved Destinations</div><div class="kpi-val"><?php echo (int) $favCount; ?></div><div class="kpi-sub">Personal picks</div></article>
      <article class="kpi"><div class="kpi-lbl">Itineraries</div><div class="kpi-val"><?php echo (int) $itineraryCount; ?></div><div class="kpi-sub">Active plans</div></article>
      <article class="kpi"><div class="kpi-lbl">Reviews Posted</div><div class="kpi-val"><?php echo count($recentReviews); ?></div><div class="kpi-sub">Community input</div></article>
      <article class="kpi"><div class="kpi-lbl">Profile Completion</div><div class="kpi-val"><?php echo $profile ? '75%' : '25%'; ?></div><div class="kpi-sub">Complete profile for better recommendations</div></article>
    </section>

    <div class="g31 mb20">
      <section class="dc">
        <div class="dc-head"><div><div class="dc-title">Quick Recommendation</div><div class="dc-sub">Get a filtered shortlist</div></div></div>
        <form class="rec-form" action="/doon-app/tourist/recommend.php" method="POST">
          <div class="rf-g"><label class="rf-lbl">Budget</label><select class="rf-ctrl" name="budget"><option value="">Any</option><option value="free">Free</option><option value="budget">Budget</option><option value="mid_range">Mid range</option><option value="luxury">Luxury</option></select></div>
          <div class="rf-g"><label class="rf-lbl">Province</label><select class="rf-ctrl" name="province_id">
            <option value="">Any</option>
            <?php foreach ($provinces as $p): ?>
            <option value="<?php echo $p['id']; ?>"><?php echo escape($p['name']); ?></option>
            <?php endforeach; ?>
          </select></div>
          <div class="rf-g"><label class="rf-lbl">Category</label><select class="rf-ctrl" name="category_id">
            <option value="">Any</option>
            <?php foreach ($categories as $c): ?>
            <option value="<?php echo $c['id']; ?>"><?php echo escape($c['name']); ?></option>
            <?php endforeach; ?>
          </select></div>
          <div class="rf-g"><label class="rf-lbl">Generation</label><select class="rf-ctrl" name="generational_profile">
            <option value="">Any</option>
            <option value="gen_z" <?php echo ($profile['generational_profile'] ?? '') === 'gen_z' ? 'selected' : ''; ?>>Gen Z</option>
            <option value="millennial" <?php echo ($profile['generational_profile'] ?? '') === 'millennial' ? 'selected' : ''; ?>>Millennial</option>
            <option value="gen_x" <?php echo ($profile['generational_profile'] ?? '') === 'gen_x' ? 'selected' : ''; ?>>Gen X</option>
            <option value="boomer" <?php echo ($profile['generational_profile'] ?? '') === 'boomer' ? 'selected' : ''; ?>>Boomer</option>
          </select></div>
          <button class="rf-go" type="submit">Generate</button>
        </form>
      </section>

      <section class="dc">
        <div class="dc-head"><div><div class="dc-title">Weather Snapshot</div><div class="dc-sub">Live CALABARZON data</div></div></div>
        <div class="wx-grid" id="wx-grid">
          <div class="wx-cell" style="grid-column:1/-1;text-align:center;opacity:.5;">Loading...</div>
        </div>
      </section>
    </div>

    <div class="g2">
      <section class="dc">
        <div class="dc-head"><div><div class="dc-title">Recent Reviews</div><div class="dc-sub">Your latest activity</div></div></div>
        <div class="dest-list">
          <?php foreach ($recentReviews as $review): ?>
          <a class="dest-row" href="/doon-app/tourist/destination.php?id=<?php echo $review['destination_id']; ?>">
            <div class="dest-ico">R</div>
            <div>
              <div class="dest-name"><?php echo escape($review['destination_name']); ?></div>
              <div class="dest-meta"><?php echo escape($review['title']); ?>  -  <?php echo formatDate($review['created_at']); ?></div>
            </div>
            <div class="dest-rating"><?php echo (int) $review['rating']; ?>/5</div>
          </a>
          <?php endforeach; ?>
          <?php if (empty($recentReviews)): ?>
          <div class="dest-row"><div>No reviews yet.</div></div>
          <?php endif; ?>
        </div>
      </section>

      <section class="dc col-stack">
        <div>
          <div class="dc-title mb16">Map Preview</div>
          <div class="map-box">
            <div class="map-pins"><span class="m-pin"></span><span class="m-pin"></span><span class="m-pin"></span></div>
            <div>Interactive map preview</div>
          </div>
        </div>
        <div>
          <div class="sub-lbl">Packing Essentials</div>
          <div class="pack-row" id="pack-chips"><span class="pack-chip" style="opacity:.5;">Loading...</span></div>
        </div>
      </section>
    </div>
  </main>
</div>

<script src="/doon-app/assets/js/main.js"></script>
<script>
(function () {
  var grid = document.getElementById('wx-grid');
  if (!grid) return;

  var condIcons = {
    clear: 'S', sunny: 'S', clouds: 'C', rain: 'R',
    drizzle: 'R', thunderstorm: 'T', mist: 'M', fog: 'M',
    haze: 'M', snow: 'W', wind: 'W'
  };

  function iconFor(condition) {
    var c = (condition || '').toLowerCase();
    for (var k in condIcons) {
      if (c.indexOf(k) !== -1) return condIcons[k];
    }
    return '?';
  }

  fetch('/doon-app/api/weather.php')
    .then(function (r) { return r.json(); })
    .then(function (json) {
      if (!json.success || !Array.isArray(json.data)) {
        grid.innerHTML = '<div class="wx-cell" style="grid-column:1/-1;opacity:.5;">Weather unavailable.</div>';
        return;
      }
      grid.innerHTML = json.data.map(function (item) {
        var w = item.weather;
        if (!w) {
          return '<div class="wx-cell"><span class="wx-ico">?</span>'
            + '<div class="wx-loc">' + item.province + '</div>'
            + '<div class="wx-cond">Unavailable</div></div>';
        }
        return '<div class="wx-cell">'
          + '<span class="wx-ico">' + iconFor(w.condition) + '</span>'
          + '<div class="wx-temp">' + (w.temp !== null ? w.temp + 'C' : '--') + '</div>'
          + '<div class="wx-loc">' + w.province + '</div>'
          + '<div class="wx-cond">' + w.condition + '</div>'
          + '</div>';
      }).join('');
    })
    .catch(function () {
      grid.innerHTML = '<div class="wx-cell" style="grid-column:1/-1;opacity:.5;">Weather unavailable.</div>';
    });
}());

// Dynamic packing chips
(function () {
  var chips = document.getElementById('pack-chips');
  if (!chips) return;
  fetch('/doon-app/api/packing.php')
    .then(function (r) { return r.json(); })
    .then(function (json) {
      if (!json.success || !Array.isArray(json.data) || !json.data.length) {
        chips.innerHTML = '<span class="pack-chip" style="opacity:.5;">No suggestions.</span>';
        return;
      }
      chips.innerHTML = json.data.map(function (item) {
        return '<span class="pack-chip' + (item.essential ? ' ess' : '') + '">' + item.item + '</span>';
      }).join('');
    })
    .catch(function () {
      chips.innerHTML = '<span class="pack-chip" style="opacity:.5;">Unavailable.</span>';
    });
}());
</script>
<?php include '../includes/footer.php'; ?>

