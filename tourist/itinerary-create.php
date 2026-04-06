<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('tourist');
$currentUser = getCurrentUser();
$pageTitle = 'Create Itinerary';
$additionalCSS = '<link rel="stylesheet" href="/doon-app/assets/css/dashboard.css"><link rel="stylesheet" href="/doon-app/assets/css/components.css">';

// Load tourist profile to auto-fill generational_profile
try {
    $stmt = $pdo->prepare('SELECT * FROM tourist_profiles WHERE user_id = ? LIMIT 1');
    $stmt->execute([$currentUser['id']]);
    $touristProfile = $stmt->fetch();
} catch (Exception $e) {
    $touristProfile = null;
}

$error   = '';
$success = false;
$itId    = null;

// Optional: pre-linked destination
$addDestId  = (int) ($_GET['add_dest'] ?? 0);
$linkedDest = null;
if ($addDestId) {
    try {
        $stmt = $pdo->prepare('SELECT id, name FROM destinations WHERE id = ? AND is_active = 1');
        $stmt->execute([$addDestId]);
        $linkedDest = $stmt->fetch();
    } catch (Exception $e) {}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title        = trim($_POST['title'] ?? '');
    $description  = trim($_POST['description'] ?? '');
    $dateStart    = $_POST['date_start'] ?? '';
    $dateEnd      = $_POST['date_end'] ?? '';
    $numPeople    = max(1, (int) ($_POST['number_of_people'] ?? 1));
    $budgetLabel  = $_POST['budget_label'] ?? null;
    $travelTheme  = trim($_POST['travel_theme'] ?? '');
    $genProfile   = $_POST['generational_profile'] ?? ($touristProfile['generational_profile'] ?? null);
    $destIdToAdd  = (int) ($_POST['add_dest_id'] ?? 0);

    $validGen    = ['gen_z', 'millennial', 'gen_x', 'boomer', '', null];
    $validBudget = ['budget', 'mid_range', 'luxury', '', null];

    if (!$title || !$dateStart || !$dateEnd) {
        $error = 'Title, start date, and end date are required.';
    } elseif (strtotime($dateEnd) < strtotime($dateStart)) {
        $error = 'End date must be after start date.';
    } else {
        try {
            $totalDays = max(1, (int) round((strtotime($dateEnd) - strtotime($dateStart)) / 86400) + 1);
            $stmt = $pdo->prepare(
                'INSERT INTO itineraries
                    (user_id, title, description, start_date, end_date, total_days, number_of_people, budget_label, travel_theme, generational_profile, status, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "draft", NOW())'
            );
            $stmt->execute([
                $currentUser['id'], $title, $description, $dateStart, $dateEnd,
                $totalDays, $numPeople,
                ($budgetLabel && in_array($budgetLabel, ['budget','mid_range','luxury'])) ? $budgetLabel : null,
                $travelTheme ?: null,
                ($genProfile && in_array($genProfile, ['gen_z','millennial','gen_x','boomer'])) ? $genProfile : null
            ]);
            $itId = $pdo->lastInsertId();

            // If a destination was pre-linked, add it as item 1 on day 1
            if ($destIdToAdd) {
                $iStmt = $pdo->prepare(
                    'INSERT INTO itinerary_items (itinerary_id, destination_id, day_number, order_index, created_at)
                     VALUES (?, ?, 1, 0, NOW())'
                );
                $iStmt->execute([$itId, $destIdToAdd]);
            }

            logAnalyticsEvent($pdo, $currentUser['id'], 'itinerary_created', ['itinerary_id' => $itId]);
            $success = true;
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
    <div><h1 class="d-page-title">Create Itinerary</h1><p class="d-page-sub">Plan a new trip.</p></div>
  </div>

  <?php if ($success): ?>
  <div class="alert ok" style="margin-bottom:12px;">Itinerary created! <a href="/doon-app/tourist/itinerary.php?id=<?php echo $itId; ?>">View it</a></div>
  <?php endif; ?>
  <?php if ($error): ?>
  <div class="alert err" style="margin-bottom:12px;"><?php echo escape($error); ?></div>
  <?php endif; ?>

  <?php if ($linkedDest): ?>
  <div class="alert info" style="margin-bottom:12px;">
    Destination <strong><?php echo escape($linkedDest['name']); ?></strong> will be added as the first stop.
  </div>
  <?php endif; ?>

  <section class="dc" style="max-width:640px;">
    <form method="POST">
      <?php if ($linkedDest): ?>
      <input type="hidden" name="add_dest_id" value="<?php echo $addDestId; ?>">
      <?php endif; ?>

      <div class="rf-g mb16">
        <label class="rf-lbl">Title</label>
        <input class="rf-ctrl" type="text" name="title" required placeholder="e.g., Summer Trip 2025">
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
        <label class="rf-lbl">Number of People</label>
        <input class="rf-ctrl" type="number" name="number_of_people" min="1" max="50" value="1">
      </div>
      <div class="rf-g mb16">
        <label class="rf-lbl">Budget Range</label>
        <select class="rf-ctrl" name="budget_label">
          <option value="">Not specified</option>
          <option value="budget">Budget</option>
          <option value="mid_range">Mid Range</option>
          <option value="luxury">Luxury</option>
        </select>
      </div>
      <div class="rf-g mb16">
        <label class="rf-lbl">Travel Theme</label>
        <input class="rf-ctrl" type="text" name="travel_theme" placeholder="e.g., beach, adventure, food trip">
      </div>
      <div class="rf-g mb16">
        <label class="rf-lbl">Generational Profile</label>
        <select class="rf-ctrl" name="generational_profile">
          <option value="">Not specified</option>
          <option value="gen_z"      <?php echo ($touristProfile['generational_profile'] ?? '') === 'gen_z'      ? 'selected' : ''; ?>>Gen Z</option>
          <option value="millennial" <?php echo ($touristProfile['generational_profile'] ?? '') === 'millennial' ? 'selected' : ''; ?>>Millennial</option>
          <option value="gen_x"      <?php echo ($touristProfile['generational_profile'] ?? '') === 'gen_x'      ? 'selected' : ''; ?>>Gen X</option>
          <option value="boomer"     <?php echo ($touristProfile['generational_profile'] ?? '') === 'boomer'     ? 'selected' : ''; ?>>Boomer</option>
        </select>
      </div>
      <div class="rf-g mb16">
        <label class="rf-lbl">Description</label>
        <textarea class="rf-ctrl" name="description" placeholder="Describe your trip..."></textarea>
      </div>
      <button class="rf-go" type="submit">Create Itinerary</button>
    </form>
  </section>
</main>
</div>
<script src="/doon-app/assets/js/main.js"></script>
<?php include '../includes/footer.php'; ?>
