<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('tourist');
$currentUser = getCurrentUser();
$pageTitle = 'Itineraries';
$additionalCSS = '<link rel="stylesheet" href="/doon-app/assets/css/dashboard.css"><link rel="stylesheet" href="/doon-app/assets/css/components.css">';

$itineraryId = (int) ($_GET['id'] ?? 0);

// ── Detail view ───────────────────────────────────────────────────────────────
if ($itineraryId) {
    try {
        $stmt = $pdo->prepare('SELECT * FROM itineraries WHERE id = ? AND user_id = ?');
        $stmt->execute([$itineraryId, $currentUser['id']]);
        $itinerary = $stmt->fetch();
    } catch (Exception $e) { $itinerary = null; }

    if (!$itinerary) {
        header('Location: /doon-app/tourist/itinerary.php');
        exit;
    }

    $isExpired = !empty($itinerary['end_date']) && $itinerary['end_date'] < date('Y-m-d');

    // Handle adding a destination stop (supports day ranges)
    $addError = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_stop') {
        verifyCsrf();
        if ($isExpired) {
            $addError = 'This itinerary has ended and can no longer be modified.';
        } else {
            $destId    = (int) ($_POST['destination_id'] ?? 0);
            $dayFrom   = max(1, (int) ($_POST['day_from'] ?? 1));
            $dayTo     = max($dayFrom, (int) ($_POST['day_to'] ?? $dayFrom));
            $notes     = trim($_POST['notes'] ?? '');
            $transport = $_POST['transport_mode'] ?? null;
            $validTransport = ['walking','tricycle','jeepney','bus','car','boat','other'];
            $safeTransport  = ($transport && in_array($transport, $validTransport)) ? $transport : null;

            if (!$destId) {
                $addError = 'Please select a destination.';
            } elseif ($dayFrom > $dayTo) {
                $addError = '"From" day cannot be after "To" day.';
            } else {
                try {
                    $insertStmt = $pdo->prepare(
                        'INSERT INTO itinerary_items
                            (itinerary_id, destination_id, day_number, order_index, notes, transport_mode, created_at)
                         VALUES (?, ?, ?, ?, ?, ?, NOW())'
                    );
                    $orderStmt = $pdo->prepare(
                        'SELECT COALESCE(MAX(order_index),0)+1 FROM itinerary_items WHERE itinerary_id = ? AND day_number = ?'
                    );

                    for ($day = $dayFrom; $day <= $dayTo; $day++) {
                        $orderStmt->execute([$itineraryId, $day]);
                        $nextOrder = (int) $orderStmt->fetchColumn();
                        $insertStmt->execute([
                            $itineraryId, $destId, $day, $nextOrder,
                            $notes ?: null,
                            $safeTransport
                        ]);
                    }

                    header('Location: /doon-app/tourist/itinerary.php?id=' . $itineraryId);
                    exit;
                } catch (Exception $e) {
                    $addError = 'Failed to add stop.';
                }
            }
        }
    }

    // Handle removing a stop
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_stop') {
        verifyCsrf();
        if (!$isExpired) {
            $itemId = (int) ($_POST['item_id'] ?? 0);
            if ($itemId) {
                try {
                    $stmt = $pdo->prepare(
                        'DELETE ii FROM itinerary_items ii
                         JOIN itineraries i ON ii.itinerary_id = i.id
                         WHERE ii.id = ? AND i.user_id = ?'
                    );
                    $stmt->execute([$itemId, $currentUser['id']]);
                } catch (Exception $e) {}
            }
        }
        header('Location: /doon-app/tourist/itinerary.php?id=' . $itineraryId);
        exit;
    }

    // Load stops
    try {
        $stmt = $pdo->prepare(
            'SELECT ii.*, d.name AS dest_name, d.short_description, d.price_label,
                    p.name AS province_name
             FROM itinerary_items ii
             LEFT JOIN destinations d ON ii.destination_id = d.id
             LEFT JOIN provinces p ON d.province_id = p.id
             WHERE ii.itinerary_id = ?
             ORDER BY ii.day_number ASC, ii.order_index ASC'
        );
        $stmt->execute([$itineraryId]);
        $stops = $stmt->fetchAll();
    } catch (Exception $e) { $stops = []; }

    // Group stops by day
    $byDay = [];
    foreach ($stops as $stop) {
        $byDay[$stop['day_number']][] = $stop;
    }

    // Load destinations for the add-stop form (include province slug for weather)
    try {
        $allDests = $pdo->query(
            'SELECT d.id, d.name, LOWER(p.name) as province_slug
             FROM destinations d
             LEFT JOIN provinces p ON d.province_id = p.id
             WHERE d.is_active = 1 ORDER BY d.name LIMIT 300'
        )->fetchAll();
    } catch (Exception $e) { $allDests = []; }

    // Map destination_id → province_slug for JS weather lookup
    $destProvinceMap = [];
    foreach ($allDests as $d) {
        if (!empty($d['province_slug'])) {
            $destProvinceMap[(int) $d['id']] = $d['province_slug'];
        }
    }

    $totalDays = max(1, (int) ($itinerary['total_days'] ?? 1));

    include '../includes/header.php';
?>
<div class="d-wrap">
<?php include '../includes/sidebar.php'; ?>
<main class="d-main">
  <div class="d-topbar">
    <div>
      <h1 class="d-page-title"><?php echo escape($itinerary['title']); ?></h1>
      <p class="d-page-sub">
        <?php echo formatDate($itinerary['start_date']); ?> &mdash; <?php echo formatDate($itinerary['end_date']); ?>
        &nbsp;&bull;&nbsp; <?php echo (int) $itinerary['number_of_people']; ?> people
        <?php if ($itinerary['budget_label']): ?>&nbsp;&bull;&nbsp; <?php echo ucfirst(str_replace('_',' ',$itinerary['budget_label'])); ?><?php endif; ?>
      </p>
    </div>
    <a class="s-btn dark" href="/doon-app/tourist/itinerary.php">Back to list</a>
  </div>

  <?php if (!empty($itinerary['description'])): ?>
  <section class="dc mb16">
    <p><?php echo nl2br(escape($itinerary['description'])); ?></p>
  </section>
  <?php endif; ?>

  <?php if ($isExpired): ?>
  <div class="alert warn" style="margin-bottom:16px;">
    This itinerary has ended. The stops are read-only and can no longer be modified.
  </div>
  <?php endif; ?>

  <div class="g31">
    <section class="dc">
      <div class="dc-head"><div><div class="dc-title">Stops</div><div class="dc-sub"><?php echo count($stops); ?> destination(s)</div></div></div>

      <?php for ($day = 1; $day <= $totalDays; $day++): ?>
      <div style="margin-bottom:16px;">
        <div class="dc-sub" style="font-weight:600;margin-bottom:6px;">Day <?php echo $day; ?></div>
        <?php if (!empty($byDay[$day])): ?>
          <?php foreach ($byDay[$day] as $stop): ?>
          <div class="dest-row">
            <div class="dest-ico">S</div>
            <div style="flex:1;">
              <div class="dest-name"><a href="/doon-app/tourist/destination.php?id=<?php echo (int) $stop['destination_id']; ?>"><?php echo escape($stop['dest_name'] ?? 'Unknown'); ?></a></div>
              <div class="dest-meta"><?php echo escape($stop['province_name'] ?? ''); ?><?php echo $stop['transport_mode'] ? ' &bull; via ' . $stop['transport_mode'] : ''; ?></div>
              <?php if ($stop['notes']): ?><div class="dest-meta"><?php echo escape($stop['notes']); ?></div><?php endif; ?>
            </div>
            <?php if (!$isExpired): ?>
            <form method="POST">
              <input type="hidden" name="csrf_token" value="<?php echo escape(csrfToken()); ?>">
              <input type="hidden" name="action" value="remove_stop">
              <input type="hidden" name="item_id" value="<?php echo (int) $stop['id']; ?>">
              <button class="s-btn dark" type="submit" style="font-size:11px;padding:4px 10px;" onclick="return confirm('Remove this stop?');">Remove</button>
            </form>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="dest-row" style="opacity:.5;">No stops for day <?php echo $day; ?> yet.</div>
        <?php endif; ?>
      </div>
      <?php endfor; ?>
    </section>

    <aside class="dc">
      <?php if ($isExpired): ?>
      <div class="dc-title mb16">Add a Stop</div>
      <div class="alert warn">This itinerary has ended. No new stops can be added.</div>
      <?php else: ?>
      <div class="dc-title mb16">Add a Stop</div>
      <?php if ($addError): ?>
      <div class="alert err" style="margin-bottom:8px;"><?php echo escape($addError); ?></div>
      <?php endif; ?>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo escape(csrfToken()); ?>">
        <input type="hidden" name="action" value="add_stop">
        <div class="rf-g mb12">
          <label class="rf-lbl">Destination</label>
          <select class="rf-ctrl" name="destination_id" id="stopDestSelect" required>
            <option value="">Select...</option>
            <?php foreach ($allDests as $d): ?>
            <option value="<?php echo $d['id']; ?>"><?php echo escape($d['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Weather forecast panel — shown after destination is picked -->
        <div id="stopWeatherPanel" style="display:none;margin-bottom:12px;">
          <div style="font-size:11px;font-weight:600;letter-spacing:.5px;text-transform:uppercase;color:var(--i4);margin-bottom:6px;">Weather Forecast</div>
          <div id="stopWeatherLoading" style="font-size:12px;color:var(--i4);">Loading...</div>
          <div id="stopWeatherCards" style="display:flex;gap:5px;flex-wrap:wrap;"></div>
          <div id="stopWeatherErr" style="font-size:12px;color:#b91c1c;display:none;">Could not load forecast.</div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:12px;">
          <div class="rf-g">
            <label class="rf-lbl">From Day</label>
            <select class="rf-ctrl" name="day_from" id="dayFromSelect">
              <?php for ($d = 1; $d <= $totalDays; $d++): ?>
              <option value="<?php echo $d; ?>">Day <?php echo $d; ?></option>
              <?php endfor; ?>
            </select>
          </div>
          <div class="rf-g">
            <label class="rf-lbl">To Day</label>
            <select class="rf-ctrl" name="day_to" id="dayToSelect">
              <?php for ($d = 1; $d <= $totalDays; $d++): ?>
              <option value="<?php echo $d; ?>">Day <?php echo $d; ?></option>
              <?php endfor; ?>
            </select>
          </div>
        </div>
        <div class="rf-g mb12">
          <label class="rf-lbl">Transport</label>
          <select class="rf-ctrl" name="transport_mode">
            <option value="">Not specified</option>
            <option value="walking">Walking</option>
            <option value="tricycle">Tricycle</option>
            <option value="jeepney">Jeepney</option>
            <option value="bus">Bus</option>
            <option value="car">Car</option>
            <option value="boat">Boat</option>
            <option value="other">Other</option>
          </select>
        </div>
        <div class="rf-g mb12">
          <label class="rf-lbl">Notes</label>
          <textarea class="rf-ctrl" name="notes" placeholder="Optional notes..." style="height:60px;"></textarea>
        </div>
        <button class="rf-go" type="submit">Add Stop</button>
      </form>
      <?php endif; ?>
    </aside>
  </div>
</main>
</div>
<script src="/doon-app/assets/js/main.js"></script>
<script>
(function () {
  var destProvinceMap = <?php echo json_encode($destProvinceMap); ?>;

  var weatherIcons = {
    'clear': '☀️', 'sunny': '☀️', 'clouds': '☁️', 'overcast': '☁️',
    'rain': '🌧️', 'drizzle': '🌦️', 'thunderstorm': '⛈️',
    'mist': '🌫️', 'fog': '🌫️', 'haze': '🌫️', 'smoke': '🌫️',
    'snow': '❄️', 'sleet': '🌨️', 'wind': '💨', 'tornado': '🌪️'
  };

  function weatherEmoji(condition) {
    var c = (condition || '').toLowerCase();
    for (var key in weatherIcons) {
      if (c.indexOf(key) !== -1) return weatherIcons[key];
    }
    return '🌤️';
  }

  // Keep "To Day" >= "From Day"
  var dayFrom = document.getElementById('dayFromSelect');
  var dayTo   = document.getElementById('dayToSelect');
  if (dayFrom && dayTo) {
    dayFrom.addEventListener('change', function () {
      var from = parseInt(this.value, 10);
      var to   = parseInt(dayTo.value, 10);
      if (to < from) dayTo.value = from;
      // Disable options in "To" that are before "From"
      Array.from(dayTo.options).forEach(function (opt) {
        opt.disabled = parseInt(opt.value, 10) < from;
      });
    });
  }

  var lastProvince = null;
  var panel   = document.getElementById('stopWeatherPanel');
  var loading = document.getElementById('stopWeatherLoading');
  var cards   = document.getElementById('stopWeatherCards');
  var errMsg  = document.getElementById('stopWeatherErr');
  var select  = document.getElementById('stopDestSelect');

  if (!select || !panel) return;

  select.addEventListener('change', function () {
    var destId = parseInt(this.value, 10);
    if (!destId) {
      panel.style.display = 'none';
      lastProvince = null;
      return;
    }

    var province = destProvinceMap[destId];
    if (!province) {
      panel.style.display = 'none';
      return;
    }

    // Skip refetch if same province
    if (province === lastProvince) {
      panel.style.display = '';
      return;
    }

    lastProvince = province;
    panel.style.display = '';
    loading.style.display = '';
    cards.style.display = 'none';
    errMsg.style.display = 'none';
    cards.innerHTML = '';

    fetch('/doon-app/api/weather.php?action=forecast&province=' + encodeURIComponent(province))
      .then(function (r) { return r.json(); })
      .then(function (json) {
        loading.style.display = 'none';
        if (!json.success || !json.data || !json.data.forecast) {
          errMsg.style.display = '';
          return;
        }

        var forecast = json.data.forecast.slice(0, 7);
        cards.innerHTML = forecast.map(function (day) {
          return '<div style="flex:1;min-width:48px;background:var(--bg2);border:1px solid var(--bd);border-radius:8px;padding:6px 4px;text-align:center;">'
            + '<div style="font-size:18px;line-height:1;">' + weatherEmoji(day.condition) + '</div>'
            + '<div style="font-size:9px;font-weight:600;color:var(--i4);margin-top:3px;white-space:nowrap;">' + (day.day ? day.day.split(',')[0] : day.date) + '</div>'
            + '<div style="font-size:11px;font-weight:700;color:var(--i);margin-top:2px;">' + (day.max_temp !== null ? day.max_temp + '°' : '--') + '</div>'
            + '<div style="font-size:10px;color:var(--i4);">' + (day.min_temp !== null ? day.min_temp + '°' : '--') + '</div>'
            + '</div>';
        }).join('');
        cards.style.display = 'flex';
      })
      .catch(function () {
        loading.style.display = 'none';
        errMsg.style.display = '';
      });
  });
}());
</script>
<?php
    include '../includes/footer.php';
    exit;
}

// ── List view ─────────────────────────────────────────────────────────────────
try {
    $stmt = $pdo->prepare('SELECT * FROM itineraries WHERE user_id = ? ORDER BY start_date DESC');
    $stmt->execute([$currentUser['id']]);
    $itineraries = $stmt->fetchAll();
} catch (Exception $e) { $itineraries = []; }

include '../includes/header.php';
?>
<div class="d-wrap">
<?php include '../includes/sidebar.php'; ?>
<main class="d-main">
  <div class="d-topbar">
    <div><h1 class="d-page-title">My Itineraries</h1><p class="d-page-sub">Plan and track your trips.</p></div>
    <a href="/doon-app/tourist/itinerary-create.php" class="s-btn green">+ Create New</a>
  </div>
  <section class="dc">
    <div class="itin">
      <?php foreach ($itineraries as $it): ?>
      <a class="itin-row" href="/doon-app/tourist/itinerary.php?id=<?php echo $it['id']; ?>">
        <div class="itin-day">D<?php echo (int) $it['total_days']; ?></div>
        <div style="flex:1;">
          <div class="itin-name"><?php echo escape($it['title']); ?></div>
          <div class="itin-meta">
            <?php echo formatDate($it['start_date']); ?> to <?php echo formatDate($it['end_date']); ?>
            &nbsp;&bull;&nbsp; <?php echo (int) $it['number_of_people']; ?> people
          </div>
        </div>
        <?php if (!empty($it['end_date']) && $it['end_date'] < date('Y-m-d')): ?>
        <span class="pill p-n">Ended</span>
        <?php else: ?>
        <span class="pill p-g">Upcoming</span>
        <?php endif; ?>
      </a>
      <?php endforeach; ?>
      <?php if (empty($itineraries)): ?>
      <div class="itin-row">No itineraries yet. <a href="/doon-app/tourist/itinerary-create.php">Create one</a></div>
      <?php endif; ?>
    </div>
  </section>
</main>
</div>
<script src="/doon-app/assets/js/main.js"></script>
<?php include '../includes/footer.php'; ?>
