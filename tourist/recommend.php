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

// Behaviour signals: categories and provinces the user has viewed or saved
try {
    // Category IDs from destinations the user has viewed
    $stmt = $pdo->prepare(
        "SELECT d.category_id, COUNT(*) as cnt
         FROM analytics_events ae
         JOIN destinations d ON d.id = CAST(JSON_UNQUOTE(JSON_EXTRACT(ae.metadata,'$.destination_id')) AS UNSIGNED)
         WHERE ae.user_id = ? AND ae.event_type = 'destination_view' AND d.category_id IS NOT NULL
         GROUP BY d.category_id ORDER BY cnt DESC LIMIT 3"
    );
    $stmt->execute([$currentUser['id']]);
    $viewedCategories = array_column($stmt->fetchAll(), 'category_id');

    // Province IDs from destinations the user has saved
    $stmt = $pdo->prepare(
        'SELECT d.province_id, COUNT(*) as cnt
         FROM favorites f
         JOIN destinations d ON d.id = f.destination_id
         WHERE f.user_id = ?
         GROUP BY d.province_id ORDER BY cnt DESC LIMIT 1'
    );
    $stmt->execute([$currentUser['id']]);
    $savedProvinceRow = $stmt->fetch();
    $preferredProvinceId = $savedProvinceRow ? (int) $savedProvinceRow['province_id'] : null;

    // Destination IDs already viewed (to avoid repeating them)
    $stmt = $pdo->prepare(
        "SELECT DISTINCT CAST(JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.destination_id')) AS UNSIGNED) as dest_id
         FROM analytics_events
         WHERE user_id = ? AND event_type = 'destination_view'"
    );
    $stmt->execute([$currentUser['id']]);
    $viewedDestIds = array_filter(array_column($stmt->fetchAll(), 'dest_id'));
} catch (Exception $e) {
    $viewedCategories = [];
    $preferredProvinceId = null;
    $viewedDestIds = [];
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

    // Exclude already-viewed destinations (show new places)
    if (!empty($viewedDestIds)) {
        $vp = implode(',', array_map('intval', $viewedDestIds));
        $where[] = "d.id NOT IN ($vp)";
    }

    // Generational appeal ordering via JSON_EXTRACT
    $orderBy = 'd.avg_rating DESC, d.view_count DESC';
    $genSelect = '';
    $validGen  = ['gen_z', 'millennial', 'gen_x', 'boomer'];
    if ($genProfile && in_array($genProfile, $validGen)) {
        $genSelect = ", CAST(JSON_UNQUOTE(JSON_EXTRACT(d.generational_appeal, '$.{$genProfile}')) AS DECIMAL(5,2)) AS gen_score";
        $orderBy   = 'gen_score DESC, d.avg_rating DESC';
    }

    // Behaviour boost: preferred category from view history (if user didn't pick a category)
    $boostSelect = '';
    if (!$categoryId && !empty($viewedCategories)) {
        $catList     = implode(',', array_map('intval', $viewedCategories));
        $boostSelect = ", IF(d.category_id IN ($catList), 1, 0) AS behaviour_boost";
        $orderBy     = ($genProfile && in_array($genProfile, $validGen) ? 'gen_score DESC, ' : '') . 'behaviour_boost DESC, d.avg_rating DESC';
    }

    $sql = "SELECT d.*, p.name AS province_name $genSelect $boostSelect
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

        // Save each result to recommendation_results
        $requestId = (int) $pdo->lastInsertId();
        if ($requestId && !empty($recommendations)) {
            $total = count($recommendations);
            $resStmt = $pdo->prepare(
                'INSERT INTO recommendation_results (request_id, destination_id, score, rank_position, created_at)
                 VALUES (?, ?, ?, ?, NOW())'
            );
            foreach ($recommendations as $rank => $rec) {
                $score = round(1 - ($rank / $total), 4);
                $resStmt->execute([$requestId, $rec['id'], $score, $rank + 1]);
            }
        }
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
        <span class="pack-chip" style="opacity:.5;">Loading...</span>
      </div>
      <div id="packingTip" style="display:none;font-size:.78rem;opacity:.7;margin-top:6px;font-style:italic;"></div>
    </section>
  </div>

  <section class="dc" style="margin-top:16px;">
    <div class="dc-head"><div><div class="dc-title">Results</div><div class="dc-sub"><?php echo count($recommendations); ?> destination(s) found<?php if (!empty($viewedCategories) && $_SERVER['REQUEST_METHOD'] === 'POST'): ?> &mdash; personalised from your activity<?php endif; ?></div></div></div>
    <div class="dest-list">
      <?php foreach ($recommendations as $rec): ?>
      <a href="/doon-app/tourist/destination.php?id=<?php echo $rec['id']; ?>" class="dest-row">
        <?php if ($rec['cover_image']): ?>
        <img src="<?php echo escape($rec['cover_image']); ?>" alt="<?php echo escape($rec['name']); ?>"
             style="width:52px;height:44px;object-fit:cover;border-radius:var(--r);flex-shrink:0;border:1px solid var(--bd);">
        <?php else: ?>
        <div class="dest-ico">D</div>
        <?php endif; ?>
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
  if (!chips) return;

  // Fetch live weather to pass condition into packing suggestions
  fetch('/doon-app/api/weather.php?action=list', { credentials: 'same-origin' })
    .then(function (r) { return r.json(); })
    .then(function (wr) {
      var condition = 'any';
      if (wr.success && Array.isArray(wr.data)) {
        wr.data.some(function (item) {
          if (item.weather && item.weather.condition) { condition = item.weather.condition; return true; }
        });
      }
      var url = '/doon-app/api/packing.php?weather=' + encodeURIComponent(condition);
      if (categoryId) url += '&category_id=' + encodeURIComponent(categoryId);
      return fetch(url, { credentials: 'same-origin' });
    })
    .then(function (r) { return r.json(); })
    .then(function (res) {
      if (!res.success || !res.data || !res.data.length) return;
      label.textContent = res.label || 'Based on your trip';
      chips.innerHTML = res.data.map(function (item) {
        return '<span class="pack-chip' + (item.essential ? ' ess' : '') + '" title="' + (item.reason || '') + '">' + item.item + '</span>';
      }).join('');
      var tipEl = document.getElementById('packingTip');
      if (tipEl && res.wardrobe_tip) { tipEl.textContent = res.wardrobe_tip; tipEl.style.display = 'block'; }
    })
    .catch(function () {});
}());
</script>
<?php include '../includes/footer.php'; ?>
