<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireRole('admin');
$pageTitle = 'Manage Reviews';
$additionalCSS = '<link rel="stylesheet" href="/doon-app/assets/css/dashboard.css"><link rel="stylesheet" href="/doon-app/assets/css/components.css">';

$message = '';

// Toggle published / unpublished
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_id'])) {
    verifyCsrf();
    $rid = (int) $_POST['toggle_id'];
    try {
        $pdo->prepare('UPDATE reviews SET is_published = 1 - is_published, updated_at = NOW() WHERE id = ?')->execute([$rid]);
        $message = 'Review updated.';
    } catch (Exception $e) {
        $message = 'Update failed.';
    }
    header('Location: /doon-app/admin/reviews.php');
    exit;
}

// Delete review
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    verifyCsrf();
    $rid = (int) $_POST['delete_id'];
    try {
        $pdo->prepare('DELETE FROM reviews WHERE id = ?')->execute([$rid]);
        $message = 'Review deleted.';
    } catch (Exception $e) {
        $message = 'Delete failed.';
    }
    header('Location: /doon-app/admin/reviews.php');
    exit;
}

// Filter
$filter = $_GET['filter'] ?? 'all'; // all | published | unpublished
$where  = $filter === 'published'   ? 'WHERE r.is_published = 1'
        : ($filter === 'unpublished' ? 'WHERE r.is_published = 0'
        : '');

try {
    $stTotal = $pdo->query('SELECT COUNT(*) FROM reviews');
    $total   = (int) $stTotal->fetchColumn();
    $stTotal->closeCursor();

    $stPub   = $pdo->query('SELECT COUNT(*) FROM reviews WHERE is_published = 1');
    $published = (int) $stPub->fetchColumn();
    $stPub->closeCursor();

    $pending = $total - $published;

    $sql = "SELECT r.id, r.rating, r.title, r.body, r.is_published, r.created_at, r.updated_at,
                   r.helpful_count, r.visit_date,
                   COALESCE(u.name, 'Deleted User') AS user_name,
                   COALESCE(d.name, 'Unknown Destination') AS destination_name,
                   d.id AS dest_id
            FROM reviews r
            LEFT JOIN users u ON r.user_id = u.id
            LEFT JOIN destinations d ON r.destination_id = d.id";
    if ($where) $sql .= ' ' . $where;
    $sql .= ' ORDER BY r.created_at DESC LIMIT 200';

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $reviews = $stmt->fetchAll();
} catch (Exception $e) {
    error_log('[reviews.php] ' . $e->getMessage());
    $reviews = [];
    $total = $published = $pending = 0;
}
?>
<?php include '../includes/header.php'; ?>
<div class="d-wrap">
<?php include '../includes/sidebar.php'; ?>
<main class="d-main">
  <div class="d-topbar">
    <div><h1 class="d-page-title">Manage Reviews</h1><p class="d-page-sub">Publish, unpublish, or remove user reviews.</p></div>
  </div>

  <?php if ($message): ?><div class="alert ok" style="margin-bottom:12px;"><?php echo escape($message); ?></div><?php endif; ?>

  <section class="kpi-row c4" style="margin-bottom:16px;">
    <article class="kpi"><div class="kpi-lbl">Total Reviews</div><div class="kpi-val"><?php echo $total; ?></div></article>
    <article class="kpi"><div class="kpi-lbl">Published</div><div class="kpi-val"><?php echo $published; ?></div></article>
    <article class="kpi"><div class="kpi-lbl">Unpublished</div><div class="kpi-val"><?php echo $pending; ?></div></article>
  </section>

  <section class="dc">
    <div style="display:flex;gap:8px;margin-bottom:12px;">
      <a class="s-btn <?php echo $filter === 'all'         ? 'dark' : ''; ?>" href="?filter=all">All</a>
      <a class="s-btn <?php echo $filter === 'published'   ? 'dark' : ''; ?>" href="?filter=published">Published</a>
      <a class="s-btn <?php echo $filter === 'unpublished' ? 'dark' : ''; ?>" href="?filter=unpublished">Unpublished</a>
    </div>

    <?php if (empty($reviews)): ?>
    <div class="dest-row" style="opacity:.5;">No reviews found.</div>
    <?php else: ?>
    <div style="overflow-x:auto;">
    <table class="d-table">
      <thead>
        <tr>
          <th>Reviewer</th>
          <th>Destination</th>
          <th>Rating</th>
          <th>Title</th>
          <th>Date</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($reviews as $i => $r):
          $stars = str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5 - (int)$r['rating']);
        ?>
        <tr style="cursor:pointer;" onclick="openReview(<?php echo $i; ?>)">
          <td><?php echo escape($r['user_name']); ?></td>
          <td><?php echo escape($r['destination_name']); ?></td>
          <td style="letter-spacing:1px;color:#f59e0b;"><?php echo $stars; ?></td>
          <td><?php echo escape($r['title'] ?? '—'); ?></td>
          <td style="font-size:.8rem;white-space:nowrap;"><?php echo $r['created_at'] ? date('M j, Y', strtotime($r['created_at'])) : '—'; ?></td>
          <td>
            <span class="badge <?php echo $r['is_published'] ? 'badge-success' : 'badge-danger'; ?>">
              <?php echo $r['is_published'] ? 'Published' : 'Hidden'; ?>
            </span>
          </td>
          <td style="white-space:nowrap;" onclick="event.stopPropagation()">
            <form method="POST" style="display:inline;">
              <input type="hidden" name="csrf_token" value="<?php echo escape(csrfToken()); ?>">
              <input type="hidden" name="toggle_id" value="<?php echo $r['id']; ?>">
              <button class="s-btn" type="submit" style="font-size:.75rem;padding:2px 8px;">
                <?php echo $r['is_published'] ? 'Unpublish' : 'Publish'; ?>
              </button>
            </form>
            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this review permanently?');">
              <input type="hidden" name="csrf_token" value="<?php echo escape(csrfToken()); ?>">
              <input type="hidden" name="delete_id" value="<?php echo $r['id']; ?>">
              <button class="s-btn dark" type="submit" style="font-size:.75rem;padding:2px 8px;">Delete</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php endif; ?>
  </section>
</main>
</div>

<!-- Review Detail Modal -->
<div id="review-modal" style="display:none;position:fixed;inset:0;z-index:500;background:rgba(0,0,0,.45);align-items:center;justify-content:center;padding:20px;">
  <div style="background:var(--wh);border-radius:var(--r2);width:100%;max-width:560px;max-height:90vh;overflow-y:auto;box-shadow:0 8px 40px rgba(0,0,0,.18);display:flex;flex-direction:column;">
    <div style="padding:20px 24px 16px;border-bottom:1px solid var(--bd);display:flex;align-items:flex-start;justify-content:space-between;gap:12px;">
      <div>
        <div id="rm-title" style="font-size:16px;font-weight:700;color:var(--i);letter-spacing:-.2px;margin-bottom:4px;"></div>
        <div id="rm-stars" style="font-size:18px;letter-spacing:2px;color:#f59e0b;"></div>
      </div>
      <button onclick="closeReview()" style="background:none;border:none;font-size:20px;color:var(--i4);cursor:pointer;line-height:1;padding:2px 4px;flex-shrink:0;">&times;</button>
    </div>
    <div style="padding:20px 24px;display:flex;flex-direction:column;gap:14px;">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
        <div style="background:var(--bg);border:1px solid var(--bd);border-radius:var(--r);padding:10px 14px;">
          <div style="font-size:10px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;color:var(--i4);margin-bottom:3px;">Reviewer</div>
          <div id="rm-user" style="font-size:13px;font-weight:600;color:var(--i);"></div>
        </div>
        <div style="background:var(--bg);border:1px solid var(--bd);border-radius:var(--r);padding:10px 14px;">
          <div style="font-size:10px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;color:var(--i4);margin-bottom:3px;">Destination</div>
          <div id="rm-dest" style="font-size:13px;font-weight:600;color:var(--i);"></div>
        </div>
        <div style="background:var(--bg);border:1px solid var(--bd);border-radius:var(--r);padding:10px 14px;">
          <div style="font-size:10px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;color:var(--i4);margin-bottom:3px;">Submitted</div>
          <div id="rm-date" style="font-size:13px;color:var(--i);"></div>
        </div>
        <div style="background:var(--bg);border:1px solid var(--bd);border-radius:var(--r);padding:10px 14px;">
          <div style="font-size:10px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;color:var(--i4);margin-bottom:3px;">Visit Date</div>
          <div id="rm-visit" style="font-size:13px;color:var(--i);"></div>
        </div>
      </div>
      <div style="background:var(--bg);border:1px solid var(--bd);border-radius:var(--r);padding:14px 16px;">
        <div style="font-size:10px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;color:var(--i4);margin-bottom:8px;">Review</div>
        <div id="rm-body" style="font-size:13px;color:var(--i2);line-height:1.7;white-space:pre-wrap;"></div>
      </div>
      <div style="display:flex;align-items:center;justify-content:space-between;">
        <div style="display:flex;align-items:center;gap:16px;">
          <span style="font-size:12px;color:var(--i4);">&#9829; <span id="rm-helpful"></span> helpful</span>
          <span id="rm-status-badge"></span>
        </div>
        <div style="display:flex;gap:8px;">
          <form id="rm-toggle-form" method="POST" style="display:inline;">
            <input type="hidden" name="csrf_token" id="rm-csrf">
            <input type="hidden" name="toggle_id" id="rm-toggle-id">
            <button id="rm-toggle-btn" class="s-btn" type="submit" style="font-size:.8rem;"></button>
          </form>
          <form id="rm-delete-form" method="POST" style="display:inline;" onsubmit="return confirm('Delete this review permanently?');">
            <input type="hidden" name="csrf_token" id="rm-del-csrf">
            <input type="hidden" name="delete_id" id="rm-del-id">
            <button class="s-btn dark" type="submit" style="font-size:.8rem;">Delete</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="/doon-app/assets/js/main.js"></script>
<script>
var REVIEWS = <?php
  $jsReviews = [];
  $csrf = csrfToken();
  foreach ($reviews as $r) {
      $jsReviews[] = [
          'id'         => $r['id'],
          'user'       => $r['user_name'],
          'dest'       => $r['destination_name'],
          'stars'      => str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5 - (int)$r['rating']),
          'title'      => $r['title'] ?? '',
          'body'       => $r['body'] ?? '',
          'helpful'    => (int)$r['helpful_count'],
          'date'       => $r['created_at'] ? date('F j, Y', strtotime($r['created_at'])) : '—',
          'visit_date' => $r['visit_date'] ? date('F j, Y', strtotime($r['visit_date'])) : null,
          'published'  => (bool)$r['is_published'],
          'csrf'       => $csrf,
      ];
  }
  echo json_encode($jsReviews);
?>;

var modal = document.getElementById('review-modal');

function openReview(i) {
  var r = REVIEWS[i];
  document.getElementById('rm-title').textContent   = r.title || '(No title)';
  document.getElementById('rm-stars').textContent   = r.stars;
  document.getElementById('rm-user').textContent    = r.user;
  document.getElementById('rm-dest').textContent    = r.dest;
  document.getElementById('rm-date').textContent    = r.date;
  document.getElementById('rm-visit').textContent   = r.visit_date || 'Not specified';
  document.getElementById('rm-body').textContent    = r.body || '(No content)';
  document.getElementById('rm-helpful').textContent = r.helpful;

  var badge = document.getElementById('rm-status-badge');
  badge.className = 'badge ' + (r.published ? 'badge-success' : 'badge-danger');
  badge.textContent = r.published ? 'Published' : 'Hidden';

  document.getElementById('rm-csrf').value      = r.csrf;
  document.getElementById('rm-toggle-id').value = r.id;
  document.getElementById('rm-toggle-btn').textContent = r.published ? 'Unpublish' : 'Publish';
  document.getElementById('rm-del-csrf').value  = r.csrf;
  document.getElementById('rm-del-id').value    = r.id;

  modal.style.display = 'flex';
  document.body.style.overflow = 'hidden';
}

function closeReview() {
  modal.style.display = 'none';
  document.body.style.overflow = '';
}

modal.addEventListener('click', function(e) {
  if (e.target === modal) closeReview();
});

document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') closeReview();
});
</script>
<?php include '../includes/footer.php'; ?>
