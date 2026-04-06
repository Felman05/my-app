<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireRole('admin');
$pageTitle = 'Manage Reviews';
$additionalCSS = '<link rel="stylesheet" href="/doon-app/assets/css/dashboard.css"><link rel="stylesheet" href="/doon-app/assets/css/components.css">';

$message = '';

// Toggle published / unpublished
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_id'])) {
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
    $reviews = $pdo->query(
        "SELECT r.id, r.rating, r.title, r.body, r.is_published, r.created_at, r.helpful_count,
                u.name AS user_name, d.name AS destination_name, d.id AS dest_id
         FROM reviews r
         JOIN users u ON r.user_id = u.id
         JOIN destinations d ON r.destination_id = d.id
         $where
         ORDER BY r.created_at DESC
         LIMIT 100"
    )->fetchAll();
} catch (Exception $e) {
    $reviews = [];
}

$total     = count($reviews);
$published = count(array_filter($reviews, fn($r) => $r['is_published']));
$pending   = $total - $published;
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
          <th>Preview</th>
          <th>Helpful</th>
          <th>Date</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($reviews as $r): ?>
        <tr>
          <td><?php echo escape($r['user_name']); ?></td>
          <td><a href="/doon-app/tourist/destination.php?id=<?php echo $r['dest_id']; ?>" target="_blank"><?php echo escape($r['destination_name']); ?></a></td>
          <td><?php echo str_repeat('★', (int) $r['rating']) . str_repeat('☆', 5 - (int) $r['rating']); ?></td>
          <td><?php echo escape($r['title'] ?? '—'); ?></td>
          <td style="font-size:.8rem;max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo escape($r['body'] ?? ''); ?></td>
          <td><?php echo (int) $r['helpful_count']; ?></td>
          <td style="font-size:.8rem;"><?php echo date('M j, Y', strtotime($r['created_at'])); ?></td>
          <td>
            <span class="badge <?php echo $r['is_published'] ? 'badge-success' : 'badge-danger'; ?>">
              <?php echo $r['is_published'] ? 'Published' : 'Hidden'; ?>
            </span>
          </td>
          <td style="white-space:nowrap;">
            <form method="POST" style="display:inline;">
              <input type="hidden" name="toggle_id" value="<?php echo $r['id']; ?>">
              <button class="s-btn" type="submit" style="font-size:.75rem;padding:2px 8px;">
                <?php echo $r['is_published'] ? 'Unpublish' : 'Publish'; ?>
              </button>
            </form>
            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this review permanently?');">
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
<script src="/doon-app/assets/js/main.js"></script>
<?php include '../includes/footer.php'; ?>
