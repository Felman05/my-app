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

    // Handle adding a destination stop
    $addError = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_stop') {
        $destId    = (int) ($_POST['destination_id'] ?? 0);
        $dayNum    = max(1, (int) ($_POST['day_number'] ?? 1));
        $notes     = trim($_POST['notes'] ?? '');
        $transport = $_POST['transport_mode'] ?? null;

        if (!$destId) {
            $addError = 'Please select a destination.';
        } else {
            try {
                $stmt = $pdo->prepare(
                    'SELECT COALESCE(MAX(order_index),0)+1 FROM itinerary_items WHERE itinerary_id = ? AND day_number = ?'
                );
                $stmt->execute([$itineraryId, $dayNum]);
                $nextOrder = (int) $stmt->fetchColumn();

                $stmt = $pdo->prepare(
                    'INSERT INTO itinerary_items
                        (itinerary_id, destination_id, day_number, order_index, notes, transport_mode, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, NOW())'
                );
                $validTransport = ['walking','tricycle','jeepney','bus','car','boat','other'];
                $stmt->execute([
                    $itineraryId, $destId, $dayNum, $nextOrder,
                    $notes ?: null,
                    ($transport && in_array($transport, $validTransport)) ? $transport : null
                ]);
                header('Location: /doon-app/tourist/itinerary.php?id=' . $itineraryId);
                exit;
            } catch (Exception $e) {
                $addError = 'Failed to add stop.';
            }
        }
    }

    // Handle removing a stop
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_stop') {
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

    // Load destinations for the add-stop form
    try {
        $allDests = $pdo->query('SELECT id, name FROM destinations WHERE is_active = 1 ORDER BY name LIMIT 300')->fetchAll();
    } catch (Exception $e) { $allDests = []; }

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
            <form method="POST">
              <input type="hidden" name="action" value="remove_stop">
              <input type="hidden" name="item_id" value="<?php echo (int) $stop['id']; ?>">
              <button class="s-btn dark" type="submit" style="font-size:11px;padding:4px 10px;" onclick="return confirm('Remove this stop?');">Remove</button>
            </form>
          </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="dest-row" style="opacity:.5;">No stops for day <?php echo $day; ?> yet.</div>
        <?php endif; ?>
      </div>
      <?php endfor; ?>
    </section>

    <aside class="dc">
      <div class="dc-title mb16">Add a Stop</div>
      <?php if ($addError): ?>
      <div class="alert err" style="margin-bottom:8px;"><?php echo escape($addError); ?></div>
      <?php endif; ?>
      <form method="POST">
        <input type="hidden" name="action" value="add_stop">
        <div class="rf-g mb12">
          <label class="rf-lbl">Destination</label>
          <select class="rf-ctrl" name="destination_id" required>
            <option value="">Select...</option>
            <?php foreach ($allDests as $d): ?>
            <option value="<?php echo $d['id']; ?>"><?php echo escape($d['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="rf-g mb12">
          <label class="rf-lbl">Day</label>
          <select class="rf-ctrl" name="day_number">
            <?php for ($d = 1; $d <= $totalDays; $d++): ?>
            <option value="<?php echo $d; ?>">Day <?php echo $d; ?></option>
            <?php endfor; ?>
          </select>
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
    </aside>
  </div>
</main>
</div>
<script src="/doon-app/assets/js/main.js"></script>
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
        <div>
          <div class="itin-name"><?php echo escape($it['title']); ?></div>
          <div class="itin-meta">
            <?php echo formatDate($it['start_date']); ?> to <?php echo formatDate($it['end_date']); ?>
            &nbsp;&bull;&nbsp; <?php echo (int) $it['number_of_people']; ?> people
          </div>
        </div>
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
