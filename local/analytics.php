<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireRole('local');
$currentUser = getCurrentUser();
$pageTitle = 'My Analytics';
$additionalCSS = '<link rel="stylesheet" href="/doon-app/assets/css/dashboard.css"><link rel="stylesheet" href="/doon-app/assets/css/components.css">';

try {
    // Get provider profile
    $stmt = $pdo->prepare('SELECT * FROM local_provider_profiles WHERE user_id = ? LIMIT 1');
    $stmt->execute([$currentUser['id']]);
    $profile = $stmt->fetch();
} catch (Exception $e) {
    $profile = null;
}

if (!$profile) {
    header('Location: /doon-app/local/profile.php?setup=1');
    exit;
}

$providerId = $profile['id'];

try {
    // Listing counts by status
    $stmt = $pdo->prepare(
        'SELECT status, COUNT(*) as cnt FROM provider_listings WHERE provider_id = ? GROUP BY status'
    );
    $stmt->execute([$providerId]);
    $statusCounts = [];
    foreach ($stmt->fetchAll() as $row) { $statusCounts[$row['status']] = (int) $row['cnt']; }

    // Listings with linked destinations — get views and reviews
    $stmt = $pdo->prepare(
        'SELECT pl.id, pl.listing_title, pl.listing_type, pl.status, pl.destination_id,
                d.name AS dest_name, d.avg_rating, d.view_count
         FROM provider_listings pl
         LEFT JOIN destinations d ON pl.destination_id = d.id
         WHERE pl.provider_id = ?
         ORDER BY pl.created_at DESC'
    );
    $stmt->execute([$providerId]);
    $listings = $stmt->fetchAll();

    // Destination IDs linked to this provider's listings
    $linkedDestIds = array_filter(array_column($listings, 'destination_id'));

    // Reviews for linked destinations
    $reviewRows = [];
    if (!empty($linkedDestIds)) {
        $ph = implode(',', array_fill(0, count($linkedDestIds), '?'));
        $stmt = $pdo->prepare(
            "SELECT r.rating, r.title, r.body, r.created_at, u.name AS reviewer, d.name AS dest_name
             FROM reviews r
             JOIN users u ON r.user_id = u.id
             JOIN destinations d ON r.destination_id = d.id
             WHERE r.destination_id IN ($ph) AND r.is_published = 1
             ORDER BY r.created_at DESC LIMIT 10"
        );
        $stmt->execute(array_values($linkedDestIds));
        $reviewRows = $stmt->fetchAll();

        // View counts per destination this month
        $stmt = $pdo->prepare(
            "SELECT CAST(JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.destination_id')) AS UNSIGNED) AS dest_id,
                    COUNT(*) AS views
             FROM analytics_events
             WHERE event_type = 'destination_view'
               AND CAST(JSON_UNQUOTE(JSON_EXTRACT(metadata,'$.destination_id')) AS UNSIGNED) IN ($ph)
               AND created_at >= DATE_FORMAT(NOW(),'%Y-%m-01')
             GROUP BY dest_id"
        );
        $stmt->execute(array_values($linkedDestIds));
        $monthlyViews = [];
        foreach ($stmt->fetchAll() as $row) { $monthlyViews[(int) $row['dest_id']] = (int) $row['views']; }
    } else {
        $monthlyViews = [];
    }

    $totalListings = array_sum($statusCounts);
    $activeCount   = $statusCounts['active']   ?? 0;
    $pendingCount  = $statusCounts['pending']  ?? 0;
    $rejectedCount = $statusCounts['rejected'] ?? 0;

} catch (Exception $e) {
    $listings = [];
    $reviewRows = [];
    $monthlyViews = [];
    $statusCounts = [];
    $totalListings = $activeCount = $pendingCount = $rejectedCount = 0;
}

$typeLabels = [
    'accommodation' => 'Accommodation', 'tour_package' => 'Tour Package',
    'restaurant' => 'Restaurant', 'transport' => 'Transport',
    'event' => 'Event', 'other' => 'Other',
];
?>
<?php include '../includes/header.php'; ?>
<div class="d-wrap">
<?php include '../includes/sidebar.php'; ?>
<main class="d-main">
  <div class="d-topbar">
    <div><h1 class="d-page-title">My Analytics</h1><p class="d-page-sub">Performance overview for <?php echo escape($profile['business_name']); ?>.</p></div>
  </div>

  <section class="kpi-row c4" style="margin-bottom:16px;">
    <article class="kpi"><div class="kpi-lbl">Total Listings</div><div class="kpi-val"><?php echo $totalListings; ?></div></article>
    <article class="kpi"><div class="kpi-lbl">Active</div><div class="kpi-val"><?php echo $activeCount; ?></div><div class="kpi-sub">Live on platform</div></article>
    <article class="kpi"><div class="kpi-lbl">Pending Review</div><div class="kpi-val"><?php echo $pendingCount; ?></div><div class="kpi-sub">Awaiting admin approval</div></article>
    <article class="kpi"><div class="kpi-lbl">Rejected</div><div class="kpi-val"><?php echo $rejectedCount; ?></div><div class="kpi-sub"><a href="/doon-app/local/listings.php">View &amp; resubmit</a></div></article>
  </section>

  <div class="g2">
    <section class="dc">
      <div class="dc-title" style="margin-bottom:12px;">Listing Performance</div>
      <?php if (empty($listings)): ?>
      <div class="dest-row" style="opacity:.5;">No listings yet. <a href="/doon-app/local/listing-create.php">Create one.</a></div>
      <?php else: ?>
      <?php
        $maxViews = max(1, ...array_map(fn($l) => (int) ($l['view_count'] ?? 0), $listings));
      ?>
      <div class="bar-list">
        <?php foreach ($listings as $l): $views = (int) ($l['view_count'] ?? 0); $pct = round(($views / $maxViews) * 100); ?>
        <div class="bar-row" style="flex-wrap:wrap;">
          <div class="bar-lbl" style="width:140px;font-size:.82rem;" title="<?php echo escape($l['listing_title']); ?>"><?php echo escape(mb_strimwidth($l['listing_title'], 0, 20, '..')); ?></div>
          <div class="bar-bg"><div class="bar-f <?php echo $l['status'] === 'active' ? 'ac' : ''; ?>" style="width:<?php echo $pct; ?>%"></div></div>
          <div class="bar-val"><?php echo $views; ?> views</div>
          <?php if ($l['status'] !== 'active'): ?>
          <span class="badge <?php echo $l['status'] === 'pending' ? 'badge-primary' : 'badge-danger'; ?>" style="margin-left:8px;font-size:.72rem;"><?php echo ucfirst($l['status']); ?></span>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <p style="font-size:.75rem;opacity:.5;margin-top:8px;">Views reflect the linked destination page. Only active listings appear in the Directory.</p>
      <?php endif; ?>
    </section>

    <section class="dc">
      <div class="dc-title" style="margin-bottom:12px;">Destination Views This Month</div>
      <?php if (empty($linkedDestIds)): ?>
      <div class="dest-row" style="opacity:.5;">No listings are linked to a destination yet.</div>
      <?php else: ?>
      <?php
        $maxMV = max(1, ...array_values($monthlyViews) ?: [1]);
        $hasMonthly = !empty($monthlyViews);
      ?>
      <?php if (!$hasMonthly): ?>
      <div class="dest-row" style="opacity:.5;">No destination views recorded this month yet.</div>
      <?php else: ?>
      <div class="bar-list">
        <?php foreach ($listings as $l):
          if (!$l['destination_id']) continue;
          $mv = $monthlyViews[$l['destination_id']] ?? 0;
          $pct = round(($mv / $maxMV) * 100);
        ?>
        <div class="bar-row">
          <div class="bar-lbl" style="width:140px;font-size:.82rem;"><?php echo escape(mb_strimwidth($l['dest_name'] ?? $l['listing_title'], 0, 20, '..')); ?></div>
          <div class="bar-bg"><div class="bar-f" style="width:<?php echo $pct; ?>%"></div></div>
          <div class="bar-val"><?php echo $mv; ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <?php endif; ?>
    </section>
  </div>

  <section class="dc" style="margin-top:16px;">
    <div class="dc-title" style="margin-bottom:12px;">Recent Reviews on Linked Destinations</div>
    <?php if (empty($reviewRows)): ?>
    <div class="dest-row" style="opacity:.5;">No reviews yet on your linked destinations.</div>
    <?php else: ?>
    <div class="dest-list">
      <?php foreach ($reviewRows as $rv): ?>
      <div class="dest-row">
        <div class="dest-ico">R</div>
        <div style="flex:1;">
          <div class="dest-name"><?php echo escape($rv['dest_name']); ?> &mdash; <?php echo str_repeat('★', (int) $rv['rating']); ?></div>
          <div class="dest-meta"><strong><?php echo escape($rv['reviewer']); ?></strong>: <?php echo escape($rv['title'] ?? ''); ?></div>
          <div style="font-size:.8rem;opacity:.7;margin-top:2px;"><?php echo escape(mb_strimwidth($rv['body'] ?? '', 0, 120, '...')); ?></div>
        </div>
        <div style="font-size:.75rem;opacity:.5;white-space:nowrap;"><?php echo date('M j', strtotime($rv['created_at'])); ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </section>
</main>
</div>
<script src="/doon-app/assets/js/main.js"></script>
<?php include '../includes/footer.php'; ?>
