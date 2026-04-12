<?php
/**
 * Single Destination Detail Page
 */

require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireRole('tourist');

$currentUser = getCurrentUser();
$destId = (int) ($_GET['id'] ?? 0);

if (!$destId) {
    header('Location: /doon-app/tourist/discover.php');
    exit;
}

$pageTitle = 'Destination';
$additionalCSS = '<link rel="stylesheet" href="/doon-app/assets/css/dashboard.css"><link rel="stylesheet" href="/doon-app/assets/css/components.css">';

try {
    $stmt = $pdo->prepare(
        'SELECT d.*, ac.name as category_name, ac.icon, p.name as province_name
         FROM destinations d
         LEFT JOIN activity_categories ac ON d.category_id = ac.id
         LEFT JOIN provinces p ON d.province_id = p.id
         WHERE d.id = ? AND d.is_active = 1'
    );
    $stmt->execute([$destId]);
    $destination = $stmt->fetch();

    if (!$destination) {
        header('Location: /doon-app/tourist/discover.php');
        exit;
    }

    logAnalyticsEvent($pdo, $currentUser['id'], 'destination_view', ['destination_id' => $destId]);

    $stmt = $pdo->prepare(
        'SELECT r.*, u.name FROM reviews r
         JOIN users u ON r.user_id = u.id
         WHERE r.destination_id = ? AND r.is_published = 1
         ORDER BY r.created_at DESC'
    );
    $stmt->execute([$destId]);
    $reviews = $stmt->fetchAll();

    $stmt = $pdo->prepare('SELECT id FROM favorites WHERE user_id = ? AND destination_id = ?');
    $stmt->execute([$currentUser['id'], $destId]);
    $isFavorited = $stmt->fetch() !== false;

    $stmt = $pdo->prepare('SELECT id FROM reviews WHERE user_id = ? AND destination_id = ?');
    $stmt->execute([$currentUser['id'], $destId]);
    $hasReviewed = $stmt->fetch() !== false;

    // Load user's itineraries for the add-to-itinerary form
    $stmt = $pdo->prepare('SELECT id, title, start_date FROM itineraries WHERE user_id = ? ORDER BY created_at DESC LIMIT 20');
    $stmt->execute([$currentUser['id']]);
    $userItineraries = $stmt->fetchAll();

} catch (PDOException $e) {
    header('Location: /doon-app/tourist/discover.php');
    exit;
}

$itinerarySuccess = false;
$itineraryError   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_to_itinerary') {
    $itId   = (int) ($_POST['itinerary_id'] ?? 0);
    $dayNum = max(1, (int) ($_POST['day_number'] ?? 1));
    $notes  = trim($_POST['notes'] ?? '');

    if (!$itId) {
        $itineraryError = 'Please select an itinerary.';
    } else {
        try {
            // Verify the itinerary belongs to this user
            $stmt = $pdo->prepare('SELECT id, total_days FROM itineraries WHERE id = ? AND user_id = ?');
            $stmt->execute([$itId, $currentUser['id']]);
            $itRow = $stmt->fetch();

            if (!$itRow) {
                $itineraryError = 'Invalid itinerary.';
            } else {
                $dayNum = min($dayNum, max(1, (int) $itRow['total_days']));
                $stmt = $pdo->prepare(
                    'SELECT COALESCE(MAX(order_index),0)+1 FROM itinerary_items WHERE itinerary_id = ? AND day_number = ?'
                );
                $stmt->execute([$itId, $dayNum]);
                $nextOrder = (int) $stmt->fetchColumn();

                $stmt = $pdo->prepare(
                    'INSERT INTO itinerary_items (itinerary_id, destination_id, day_number, order_index, notes, created_at)
                     VALUES (?, ?, ?, ?, ?, NOW())'
                );
                $stmt->execute([$itId, $destId, $dayNum, $nextOrder, $notes ?: null]);
                $itinerarySuccess = true;
            }
        } catch (PDOException $e) {
            $itineraryError = 'Failed to add to itinerary.';
        }
    }
}

$reviewError = '';
$reviewSuccess = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'post_review') {
    if ($hasReviewed) {
        $reviewError = 'You have already reviewed this destination.';
    } else {
        $rating = (int) ($_POST['rating'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $body = trim($_POST['body'] ?? '');

        if ($rating < 1 || $rating > 5) $reviewError = 'Rating must be between 1 and 5.';
        elseif (empty($title)) $reviewError = 'Title is required.';
        elseif (empty($body)) $reviewError = 'Review text is required.';

        if (!$reviewError) {
            try {
                $stmt = $pdo->prepare(
                    'INSERT INTO reviews (user_id, destination_id, rating, title, body, is_published, created_at)
                     VALUES (?, ?, ?, ?, ?, 1, NOW())'
                );
                $stmt->execute([$currentUser['id'], $destId, $rating, $title, $body]);

                // Recalculate destination avg_rating and total_reviews
                $pdo->prepare(
                    'UPDATE destinations SET
                         avg_rating = (SELECT ROUND(AVG(r.rating),2) FROM reviews r WHERE r.destination_id = ? AND r.is_published = 1),
                         total_reviews = (SELECT COUNT(*) FROM reviews r WHERE r.destination_id = ? AND r.is_published = 1),
                         updated_at = NOW()
                     WHERE id = ?'
                )->execute([$destId, $destId, $destId]);

                $reviewSuccess = true;
                $hasReviewed = true;

                $stmt = $pdo->prepare(
                    'SELECT r.*, u.name FROM reviews r
                     JOIN users u ON r.user_id = u.id
                     WHERE r.destination_id = ? AND r.is_published = 1
                     ORDER BY r.created_at DESC'
                );
                $stmt->execute([$destId]);
                $reviews = $stmt->fetchAll();
            } catch (PDOException $e) {
                $reviewError = 'Failed to post review.';
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_favorite') {
    try {
        if ($isFavorited) {
            $stmt = $pdo->prepare('DELETE FROM favorites WHERE user_id = ? AND destination_id = ?');
            $stmt->execute([$currentUser['id'], $destId]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO favorites (user_id, destination_id, created_at) VALUES (?, ?, NOW())');
            $stmt->execute([$currentUser['id'], $destId]);
        }
    } catch (PDOException $e) {}
    // PRG: redirect to prevent double-submit on refresh
    header("Location: /doon-app/tourist/destination.php?id={$destId}");
    exit;
}
?>

<?php include '../includes/header.php'; ?>
<div class="d-wrap">
<?php include '../includes/sidebar.php'; ?>
<main class="d-main">
  <div class="d-topbar">
    <div>
      <h1 class="d-page-title"><?php echo escape($destination['name']); ?></h1>
      <p class="d-page-sub"><?php echo escape($destination['province_name']); ?>  -  <?php echo escape($destination['category_name']); ?></p>
    </div>
    <form method="POST"><input type="hidden" name="action" value="toggle_favorite"><button class="s-btn dark" type="submit"><?php echo $isFavorited ? 'Saved' : 'Save'; ?></button></form>
  </div>

  <div class="g31">
    <section class="dc">
      <?php
        require_once '../includes/env.php';
        $gmKey = env('GOOGLE_MAPS_API_KEY', '');
        $hasCoords = !empty($destination['latitude']) && !empty($destination['longitude']);
        $destImages = ($destination['images'] ?? null) ? json_decode($destination['images'], true) : [];
        $coverImage = $destination['cover_image'] ?? ($destImages[0] ?? null);
      ?>
      <?php if ($coverImage): ?>
      <img src="<?php echo escape($coverImage); ?>" alt="<?php echo escape($destination['name']); ?>"
           style="width:100%;height:220px;object-fit:cover;border-radius:var(--r2);display:block;margin-bottom:10px;">
      <?php if (count($destImages) > 1): ?>
      <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:10px;">
        <?php foreach (array_slice($destImages, 1) as $img): ?>
        <img src="<?php echo escape($img); ?>" alt="<?php echo escape($destination['name']); ?>"
             style="width:72px;height:54px;object-fit:cover;border-radius:var(--r);border:1px solid var(--bd);cursor:pointer;"
             onclick="this.closest('section').querySelector('img').src=this.src">
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <?php endif; ?>
      <?php if ($gmKey && $hasCoords): ?>
      <iframe
        width="100%" height="220"
        style="border:1px solid var(--bd);border-radius:var(--r2);display:block;margin-bottom:14px;"
        loading="lazy" referrerpolicy="no-referrer-when-downgrade"
        src="https://www.google.com/maps/embed/v1/view?key=<?php echo urlencode($gmKey); ?>&center=<?php echo (float) $destination['latitude']; ?>,<?php echo (float) $destination['longitude']; ?>&zoom=15">
      </iframe>
      <?php elseif ($gmKey): ?>
      <iframe
        width="100%" height="220"
        style="border:1px solid var(--bd);border-radius:var(--r2);display:block;margin-bottom:14px;"
        loading="lazy" referrerpolicy="no-referrer-when-downgrade"
        src="https://www.google.com/maps/embed/v1/place?key=<?php echo urlencode($gmKey); ?>&q=<?php echo urlencode($destination['name'] . ', CALABARZON, Philippines'); ?>">
      </iframe>
      <?php else: ?>
      <div class="map-box" style="height:220px;margin-bottom:14px;"><div class="map-pins"><span class="m-pin"></span><span class="m-pin"></span><span class="m-pin"></span></div><div>Map unavailable.</div></div>
      <?php endif; ?>
      <p><?php echo nl2br(escape($destination['description'] ?? $destination['short_description'])); ?></p>
      <div class="divider"></div>

      <?php if ($reviewSuccess): ?><div class="alert ok">Your review has been posted.</div><?php endif; ?>
      <?php if ($reviewError): ?><div class="alert err"><?php echo escape($reviewError); ?></div><?php endif; ?>

      <?php if (!$hasReviewed): ?>
      <form method="POST" class="mb20">
        <input type="hidden" name="action" value="post_review">
        <div class="rf-g mb16"><label class="rf-lbl">Rating</label><select class="rf-ctrl" name="rating" required><option value="">Select</option><option value="5">5</option><option value="4">4</option><option value="3">3</option><option value="2">2</option><option value="1">1</option></select></div>
        <div class="rf-g mb16"><label class="rf-lbl">Title</label><input class="rf-ctrl" name="title" required></div>
        <div class="rf-g mb16"><label class="rf-lbl">Review</label><textarea class="rf-ctrl" name="body" required></textarea></div>
        <button class="rf-go" type="submit">Post Review</button>
      </form>
      <?php endif; ?>

      <div class="dest-list">
        <?php foreach ($reviews as $review): ?>
        <div class="dest-row rev-item">
          <div class="dest-ico">U</div>
          <div>
            <div class="dest-name"><?php echo escape($review['name']); ?>  -  <?php echo escape($review['title']); ?></div>
            <div class="dest-meta"><?php echo escape($review['body']); ?></div>
          </div>
          <div class="dest-rating"><?php echo (int) $review['rating']; ?>/5</div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($reviews)): ?><div class="dest-row">No reviews yet.</div><?php endif; ?>
      </div>
    </section>

    <aside class="dc">
      <div class="dc-title">Details</div>
      <div class="bar-list" style="margin-top:12px;">
        <div class="bar-row"><div class="bar-lbl">Rating</div><div class="bar-bg"><div class="bar-f ac" style="width:<?php echo min(100, ((float) ($destination['avg_rating'] ?? 0) / 5) * 100); ?>%"></div></div><div class="bar-val"><?php echo number_format((float) ($destination['avg_rating'] ?? 0), 1); ?></div></div>
        <div class="bar-row"><div class="bar-lbl">Views</div><div class="bar-bg"><div class="bar-f" style="width:70%"></div></div><div class="bar-val"><?php echo number_format((int) ($destination['view_count'] ?? 0)); ?></div></div>
      </div>

      <?php
        $openDays  = ($destination['open_days'] ?? null) ? json_decode($destination['open_days'], true) : [];
        $openTime  = $destination['opening_time'] ?? null;
        $closeTime = $destination['closing_time'] ?? null;
        $dayLabels = ['mon'=>'Mon','tue'=>'Tue','wed'=>'Wed','thu'=>'Thu','fri'=>'Fri','sat'=>'Sat','sun'=>'Sun'];
      ?>

      <?php if ($destination['contact_number'] || $destination['email'] || $destination['website_url'] || $destination['facebook_url'] || $openTime || !empty($openDays)): ?>
      <div class="divider"></div>
      <div style="display:flex;flex-direction:column;gap:6px;font-size:.82rem;">
        <?php if ($openTime && $closeTime): ?>
        <div style="display:flex;gap:8px;align-items:flex-start;">
          <span style="min-width:70px;font-weight:600;color:var(--i3);">Hours</span>
          <span><?php echo escape(date('g:i A', strtotime($openTime))); ?> – <?php echo escape(date('g:i A', strtotime($closeTime))); ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($openDays)): ?>
        <div style="display:flex;gap:8px;align-items:flex-start;">
          <span style="min-width:70px;font-weight:600;color:var(--i3);">Open</span>
          <span><?php echo implode(', ', array_map(fn($d) => $dayLabels[$d] ?? $d, $openDays)); ?></span>
        </div>
        <?php endif; ?>
        <?php if ($destination['contact_number']): ?>
        <div style="display:flex;gap:8px;align-items:flex-start;">
          <span style="min-width:70px;font-weight:600;color:var(--i3);">Phone</span>
          <span><?php echo escape($destination['contact_number']); ?></span>
        </div>
        <?php endif; ?>
        <?php if ($destination['email']): ?>
        <div style="display:flex;gap:8px;align-items:flex-start;">
          <span style="min-width:70px;font-weight:600;color:var(--i3);">Email</span>
          <a href="mailto:<?php echo escape($destination['email']); ?>"><?php echo escape($destination['email']); ?></a>
        </div>
        <?php endif; ?>
        <?php if ($destination['website_url'] || $destination['facebook_url']): ?>
        <div style="display:flex;gap:6px;margin-top:2px;">
          <?php if ($destination['website_url']): ?><a class="s-btn" href="<?php echo escape($destination['website_url']); ?>" target="_blank" rel="noopener">Website</a><?php endif; ?>
          <?php if ($destination['facebook_url']): ?><a class="s-btn" href="<?php echo escape($destination['facebook_url']); ?>" target="_blank" rel="noopener">Facebook</a><?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <div class="divider"></div>

      <?php if ($itinerarySuccess): ?>
      <div class="alert ok" style="margin-bottom:8px;">Added to itinerary!</div>
      <?php endif; ?>
      <?php if ($itineraryError): ?>
      <div class="alert err" style="margin-bottom:8px;"><?php echo escape($itineraryError); ?></div>
      <?php endif; ?>

      <?php if (!empty($userItineraries)): ?>
      <div class="dc-sub" style="margin-bottom:6px;">Add to existing itinerary</div>
      <form method="POST" style="margin-bottom:10px;">
        <input type="hidden" name="action" value="add_to_itinerary">
        <select class="rf-ctrl" name="itinerary_id" style="margin-bottom:6px;">
          <option value="">Select itinerary...</option>
          <?php foreach ($userItineraries as $it): ?>
          <option value="<?php echo (int) $it['id']; ?>"><?php echo escape($it['title']); ?></option>
          <?php endforeach; ?>
        </select>
        <input class="rf-ctrl" type="number" name="day_number" min="1" value="1" placeholder="Day #" style="margin-bottom:6px;">
        <button class="s-btn green" type="submit" style="width:100%;">Add Stop</button>
      </form>
      <?php endif; ?>

      <a class="s-btn dark" href="/doon-app/tourist/itinerary-create.php?add_dest=<?php echo $destId; ?>" style="width:100%;text-align:center;display:block;">+ New itinerary with this stop</a>
    </aside>
  </div>
</main>
</div>
<script src="/doon-app/assets/js/main.js"></script>
<?php include '../includes/footer.php'; ?>

