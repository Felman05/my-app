<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireRole('tourist');
$currentUser = getCurrentUser();
$pageTitle = 'Create Itinerary';
$additionalCSS = '<link rel="stylesheet" href="/doon-app/assets/css/dashboard.css"><link rel="stylesheet" href="/doon-app/assets/css/components.css">';

$error = '';
$success = false;
$itId = null;

// Optional: pre-linked destination from destination.php
$addDestId = (int) ($_GET['add_dest'] ?? 0);
$linkedDest = null;
if ($addDestId) {
    try {
        $stmt = $pdo->prepare('SELECT id, name FROM destinations WHERE id = ? AND is_active = 1');
        $stmt->execute([$addDestId]);
        $linkedDest = $stmt->fetch();
    } catch (Exception $e) {}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title      = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $date_start = $_POST['date_start'] ?? '';
    $date_end   = $_POST['date_end'] ?? '';

    if (!$title || !$date_start || !$date_end) {
        $error = 'Title, start date, and end date are required.';
    } elseif (strtotime($date_end) < strtotime($date_start)) {
        $error = 'End date must be after start date.';
    } else {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO itineraries (user_id, title, description, start_date, end_date, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW())'
            );
            $stmt->execute([$currentUser['id'], $title, $description, $date_start, $date_end]);
            $success = true;
            $itId = $pdo->lastInsertId();
        } catch (Exception $e) {
            $error = 'Failed to create itinerary.';
        }
    }
}
?>
<?php include '../includes/header.php'; ?>
<div class="d-wrap">
<?php include '../includes/sidebar.php'; ?>
<main class="d-main">
  <div class="d-topbar">
    <div>
      <h1 class="d-page-title">Create Itinerary</h1>
      <p class="d-page-sub">Plan a new trip.</p>
    </div>
  </div>

  <?php if ($success): ?>
  <div class="alert ok">Itinerary created! <a href="/doon-app/tourist/itinerary.php?id=<?php echo $itId; ?>">View it</a></div>
  <?php endif; ?>
  <?php if ($error): ?>
  <div class="alert err"><?php echo escape($error); ?></div>
  <?php endif; ?>

  <?php if ($linkedDest): ?>
  <div class="alert info" style="margin-bottom:12px;">
    Adding destination: <strong><?php echo escape($linkedDest['name']); ?></strong> — you can add it to your itinerary after creation.
  </div>
  <?php endif; ?>

  <section class="dc" style="max-width:600px;">
    <form method="POST">
      <div class="rf-g mb16">
        <label class="rf-lbl">Title</label>
        <input class="rf-ctrl" type="text" name="title" required placeholder="e.g., Summer Vacation 2025">
      </div>
      <div class="rf-g mb16">
        <label class="rf-lbl">Start Date</label>
        <input class="rf-ctrl" type="date" name="date_start" required>
      </div>
      <div class="rf-g mb16">
        <label class="rf-lbl">End Date</label>
        <input class="rf-ctrl" type="date" name="date_end" required>
      </div>
      <div class="rf-g mb16">
        <label class="rf-lbl">Description</label>
        <textarea class="rf-ctrl" name="description" placeholder="Describe your trip..."></textarea>
      </div>
      <button class="rf-go" type="submit">Create</button>
    </form>
  </section>
</main>
</div>
<script src="/doon-app/assets/js/main.js"></script>
<?php include '../includes/footer.php'; ?>
