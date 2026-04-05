<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireRole('tourist');
$currentUser = getCurrentUser();
$pageTitle = 'Itineraries';
$additionalCSS = '<link rel="stylesheet" href="/doon-app/assets/css/dashboard.css"><link rel="stylesheet" href="/doon-app/assets/css/components.css">';

$itineraryId = (int) ($_GET['id'] ?? 0);

// ── Detail view ──────────────────────────────────────────────────────────────
if ($itineraryId) {
    try {
        $stmt = $pdo->prepare('SELECT * FROM itineraries WHERE id = ? AND user_id = ?');
        $stmt->execute([$itineraryId, $currentUser['id']]);
        $itinerary = $stmt->fetch();
    } catch (Exception $e) {
        $itinerary = null;
    }

    if (!$itinerary) {
        header('Location: /doon-app/tourist/itinerary.php');
        exit;
    }

    include '../includes/header.php';
?>
<div class="d-wrap">
<?php include '../includes/sidebar.php'; ?>
<main class="d-main">
  <div class="d-topbar">
    <div>
      <h1 class="d-page-title"><?php echo escape($itinerary['title']); ?></h1>
      <p class="d-page-sub"><?php echo formatDate($itinerary['start_date']); ?> &mdash; <?php echo formatDate($itinerary['end_date']); ?></p>
    </div>
    <a class="s-btn dark" href="/doon-app/tourist/itinerary.php">Back to list</a>
  </div>

  <section class="dc">
    <?php if (!empty($itinerary['description'])): ?>
    <p><?php echo nl2br(escape($itinerary['description'])); ?></p>
    <div class="divider"></div>
    <?php endif; ?>
    <div class="dest-row"><div>No stops added yet.</div></div>
  </section>
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
} catch (Exception $e) {
    $itineraries = [];
}

include '../includes/header.php';
?>
<div class="d-wrap">
<?php include '../includes/sidebar.php'; ?>
<main class="d-main">
  <div class="d-topbar">
    <div>
      <h1 class="d-page-title">My Itineraries</h1>
      <p class="d-page-sub">Plan and track your trips.</p>
    </div>
    <a href="/doon-app/tourist/itinerary-create.php" class="s-btn green">Create New</a>
  </div>
  <section class="dc">
    <div class="itin">
      <?php foreach ($itineraries as $it): ?>
      <a class="itin-row" href="/doon-app/tourist/itinerary.php?id=<?php echo $it['id']; ?>">
        <div class="itin-day">TRIP</div>
        <div>
          <div class="itin-name"><?php echo escape($it['title']); ?></div>
          <div class="itin-meta"><?php echo formatDate($it['start_date']); ?> to <?php echo formatDate($it['end_date']); ?></div>
        </div>
      </a>
      <?php endforeach; ?>
      <?php if (empty($itineraries)): ?>
      <div class="itin-row">No itineraries yet.</div>
      <?php endif; ?>
    </div>
  </section>
</main>
</div>
<script src="/doon-app/assets/js/main.js"></script>
<?php include '../includes/footer.php'; ?>
