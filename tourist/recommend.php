<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('tourist');
$currentUser = getCurrentUser();
$pageTitle = 'Recommendations';
$additionalCSS = '<link rel="stylesheet" href="/doon-app/assets/css/dashboard.css"><link rel="stylesheet" href="/doon-app/assets/css/components.css">';

// Load tourist profile to pre-fill form
try {
    $stmt = $pdo->prepare('SELECT * FROM tourist_profiles WHERE user_id = ? LIMIT 1');
    $stmt->execute([$currentUser['id']]);
    $touristProfile = $stmt->fetch();
} catch (Exception $e) {
    $touristProfile = null;
}

$provinces   = [];
$categories  = [];
$recommendations = [];
$startTime   = microtime(true);

try {
    $provinces  = $pdo->query('SELECT * FROM provinces ORDER BY name')->fetchAll();
    $categories = $pdo->query('SELECT * FROM activity_categories ORDER BY name')->fetchAll();
} catch (Exception $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $budget       = $_POST['budget'] ?? ($touristProfile['preferred_budget'] ?? '');
    $provinceId   = $_POST['province_id'] ?? null;
    $categoryId   = $_POST['category_id'] ?? null;
    $genProfile   = $_POST['generational_profile'] ?? ($touristProfile['generational_profile'] ?? '');
    $numPeople    = (int) ($_POST['number_of_people'] ?? 1);
    $tripDays     = (int) ($_POST['trip_duration_days'] ?? 1);

    $where  = ['d.is_active = 1'];
    $params = [];

    if ($budget && in_array($budget, ['free', 'budget', 'mid_range', 'luxury'])) {
        $where[] = 'd.price_label = ?';
        $params[] = $budget;
    }
    if ($provinceId) {
        $where[] = 'd.province_id = ?';
        $params[] = (int) $provinceId;
    }
    if ($categoryId) {
        $where[] = 'd.category_id = ?';
        $params[] = (int) $categoryId;
    }

    // Generational appeal ordering via JSON_EXTRACT
    $orderBy = 'd.avg_rating DESC, d.view_count DESC';
    $genSelect = '';
    $validGen  = ['gen_z', 'millennial', 'gen_x', 'boomer'];
    if ($genProfile && in_array($genProfile, $validGen)) {
        $genSelect = ", CAST(JSON_UNQUOTE(JSON_EXTRACT(d.generational_appeal, '$." . $genProfile . "')) AS DECIMAL(5,2)) AS gen_score";
        $orderBy   = 'gen_score DESC, d.avg_rating DESC';
    }

    $sql = "SELECT d.*, p.name AS province_name $genSelect
            FROM destinations d
            LEFT JOIN provinces p ON d.province_id = p.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY $orderBy
            LIMIT 10";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $recommendations = $stmt->fetchAll();

        // Log to recommendation_requests
        $elapsed = (int) round((microtime(true) - $startTime) * 1000);
        $logStmt = $pdo->prepare(
            'INSERT INTO recommendation_requests
                (user_id, budget_label, number_of_people, trip_duration_days, province_id, generational_profile, results_count, response_time_ms, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $logStmt->execute([
            $currentUser['id'],
            $budget ?: null,
            $numPeople ?: 1,
            $tripDays ?: 1,
            $provinceId ?: null,
            ($genProfile && in_array($genProfile, $validGen)) ? $genProfile : null,
            count($recommendations),
            $elapsed
        ]);
    } catch (Exception $e) {}
}
?>
<?php include '../includes/header.php'; ?>
<div class="d-wrap">
<?php include '../includes/sidebar.php'; ?>
<main class="d-main">
  <div class="d-topbar"><div><h1 class="d-page-title">Recommendations</h1><p class="d-page-sub">Tell us about your trip and get a personalised list.</p></div></div>

  <div class="g31">
    <section class="dc">
      <div class="dc-title mb16">Trip Preferences</div>
      <form method="POST" class="rec-form" style="grid-template-columns:1fr 1fr;">
        <div class="rf-g">
          <label class="rf-lbl">Budget</label>
          <select class="rf-ctrl" name="budget">
            <option value="">Any</option>
            <option value="free"      <?php echo ($_POST['budget'] ?? $touristProfile['preferred_budget'] ?? '') === 'free'      ? 'selected' : ''; ?>>Free</option>
            <option value="budget"    <?php echo ($_POST['budget'] ?? $touristProfile['preferred_budget'] ?? '') === 'budget'    ? 'selected' : ''; ?>>Budget</option>
            <option value="mid_range" <?php echo ($_POST['budget'] ?? $touristProfile['preferred_budget'] ?? '') === 'mid_range' ? 'selected' : ''; ?>>Mid Range</option>
            <option value="luxury"    <?php echo ($_POST['budget'] ?? $touristProfile['preferred_budget'] ?? '') === 'luxury'    ? 'selected' : ''; ?>>Luxury</option>
          </select>
        </div>
        <div class="rf-g">
          <label class="rf-lbl">Province</label>
          <select class="rf-ctrl" name="province_id">
            <option value="">All</option>
            <?php foreach ($provinces as $p): ?>
            <option value="<?php echo $p['id']; ?>" <?php echo ($_POST['province_id'] ?? '') == $p['id'] ? 'selected' : ''; ?>><?php echo escape($p['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="rf-g">
          <label class="rf-lbl">Activity Category</label>
          <select class="rf-ctrl" name="category_id">
            <option value="">All</option>
            <?php foreach ($categories as $c): ?>
            <option value="<?php echo $c['id']; ?>" <?php echo ($_POST['category_id'] ?? '') == $c['id'] ? 'selected' : ''; ?>><?php echo escape($c['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="rf-g">
          <label class="rf-lbl">Generational Profile</label>
          <select class="rf-ctrl" name="generational_profile">
            <option value="">Any</option>
            <option value="gen_z"      <?php echo ($_POST['generational_profile'] ?? $touristProfile['generational_profile'] ?? '') === 'gen_z'      ? 'selected' : ''; ?>>Gen Z</option>
            <option value="millennial" <?php echo ($_POST['generational_profile'] ?? $touristProfile['generational_profile'] ?? '') === 'millennial' ? 'selected' : ''; ?>>Millennial</option>
            <option value="gen_x"      <?php echo ($_POST['generational_profile'] ?? $touristProfile['generational_profile'] ?? '') === 'gen_x'      ? 'selected' : ''; ?>>Gen X</option>
            <option value="boomer"     <?php echo ($_POST['generational_profile'] ?? $touristProfile['generational_profile'] ?? '') === 'boomer'     ? 'selected' : ''; ?>>Boomer</option>
          </select>
        </div>
        <div class="rf-g">
          <label class="rf-lbl">Number of People</label>
          <input class="rf-ctrl" type="number" name="number_of_people" min="1" max="50" value="<?php echo (int) ($_POST['number_of_people'] ?? 1); ?>">
        </div>
        <div class="rf-g">
          <label class="rf-lbl">Trip Duration (days)</label>
          <input class="rf-ctrl" type="number" name="trip_duration_days" min="1" max="30" value="<?php echo (int) ($_POST['trip_duration_days'] ?? 1); ?>">
        </div>
        <div class="rf-g" style="grid-column:1/-1;">
          <button class="rf-go" type="submit">Get Picks</button>
        </div>
      </form>
    </section>

    <section class="dc">
      <div class="dc-title">Packing Suggestions</div>
      <div class="sub-lbl" style="margin-top:12px;" id="packingLabel">Based on your trip</div>
      <div class="pack-row" id="packingChips">
        <span class="pack-chip ess">Water</span>
        <span class="pack-chip ess">ID</span>
        <span class="pack-chip">Sunscreen</span>
        <span class="pack-chip">Umbrella</span>
      </div>
    </section>
  </div>

  <section class="dc" style="margin-top:16px;">
    <div class="dc-head"><div><div class="dc-title">Results</div><div class="dc-sub"><?php echo count($recommendations); ?> destination(s) found</div></div></div>
    <div class="dest-list">
      <?php foreach ($recommendations as $rec): ?>
      <a href="/doon-app/tourist/destination.php?id=<?php echo $rec['id']; ?>" class="dest-row">
        <div class="dest-ico">R</div>
        <div>
          <div class="dest-name"><?php echo escape($rec['name']); ?></div>
          <div class="dest-meta"><?php echo escape($rec['province_name']); ?> &mdash; <?php echo escape($rec['price_label'] ?? 'Price on request'); ?></div>
        </div>
        <div class="dest-rating">&#9733; <?php echo number_format((float) ($rec['avg_rating'] ?? 0), 1); ?></div>
      </a>
      <?php endforeach; ?>
      <?php if (empty($recommendations) && $_SERVER['REQUEST_METHOD'] === 'POST'): ?>
      <div class="dest-row">No destinations matched your preferences. Try adjusting the filters.</div>
      <?php elseif (empty($recommendations)): ?>
      <div class="dest-row">Fill in the form above and click Get Picks.</div>
      <?php endif; ?>
    </div>
  </section>
</main>
</div>
<script src="/doon-app/assets/js/main.js"></script>
<script>
(function () {
  var categoryId = <?php echo json_encode($_POST['category_id'] ?? ''); ?>;
  var chips = document.getElementById('packingChips');
  var label = document.getElementById('packingLabel');
  if (!chips || !categoryId) return;

  fetch('/doon-app/api/packing.php?category_id=' + encodeURIComponent(categoryId), { credentials: 'same-origin' })
    .then(function (r) { return r.json(); })
    .then(function (res) {
      if (!res.success || !res.data || !res.data.length) return;
      label.textContent = res.label || 'Based on your trip';
      chips.innerHTML = res.data.map(function (item) {
        return '<span class="pack-chip' + (item.essential ? ' ess' : '') + '">' + item.item + '</span>';
      }).join('');
    })
    .catch(function () {});
}());
</script>
<?php include '../includes/footer.php'; ?>
