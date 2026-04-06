<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireRole('admin');
$pageTitle = 'Admin Dashboard';
$additionalCSS = '<link rel="stylesheet" href="/doon-app/assets/css/dashboard.css"><link rel="stylesheet" href="/doon-app/assets/css/components.css">';
try {
    $userCount = $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $destCount = $pdo->query('SELECT COUNT(*) FROM destinations')->fetchColumn();
    $provCount = $pdo->query('SELECT COUNT(*) FROM provider_listings WHERE status = "pending"')->fetchColumn();

    // Traffic snapshot: real event counts
    $viewCount  = $pdo->query('SELECT COUNT(*) FROM analytics_events WHERE event_type = "destination_view"')->fetchColumn();
    $itinCount  = $pdo->query('SELECT COUNT(*) FROM analytics_events WHERE event_type = "itinerary_created"')->fetchColumn();
    $recCount   = $pdo->query('SELECT COUNT(*) FROM recommendation_requests')->fetchColumn();

    // Pending listings for approval queue
    $pendingListings = $pdo->query(
        'SELECT pl.id, pl.listing_title, pl.listing_type, pl.created_at,
                lpp.business_name, lpp.municipality, lpp.province
         FROM provider_listings pl
         JOIN local_provider_profiles lpp ON pl.provider_id = lpp.id
         ORDER BY pl.created_at ASC
         LIMIT 5'
    )->fetchAll();

    // Destination counts per province
    $provinceCounts = $pdo->query(
        'SELECT p.name, COUNT(d.id) as cnt
         FROM provinces p
         LEFT JOIN destinations d ON d.province_id = p.id AND d.is_active = 1
         GROUP BY p.id, p.name ORDER BY cnt DESC'
    )->fetchAll();

} catch (Exception $e) {
    $userCount = $destCount = $provCount = 0;
    $viewCount = $itinCount = $recCount = 0;
    $pendingListings = [];
    $provinceCounts = [];
}
?>
<?php include '../includes/header.php'; ?>
<div class="d-wrap">
<?php include '../includes/sidebar.php'; ?>
<main class="d-main">
  <div class="d-topbar"><div><h1 class="d-page-title">Admin Dashboard</h1><p class="d-page-sub">System overview and queue management.</p></div></div>

  <section class="kpi-row c4">
    <article class="kpi"><div class="kpi-lbl">Total Users</div><div class="kpi-val"><?php echo (int) $userCount; ?></div><div class="kpi-sub"><a href="/doon-app/admin/users.php">Manage users</a></div></article>
    <article class="kpi"><div class="kpi-lbl">Destinations</div><div class="kpi-val"><?php echo (int) $destCount; ?></div><div class="kpi-sub"><a href="/doon-app/admin/destinations.php">Manage destinations</a></div></article>
    <article class="kpi"><div class="kpi-lbl">Pending Providers</div><div class="kpi-val"><?php echo (int) $provCount; ?></div><div class="kpi-sub"><a href="/doon-app/admin/providers.php">Review providers</a></div></article>
    <article class="kpi"><div class="kpi-lbl">Reports</div><div class="kpi-val">LIVE</div><div class="kpi-sub"><a href="/doon-app/admin/reports.php">Open reports</a></div></article>
  </section>

  <div class="g2">
    <section class="dc">
      <div class="dc-head"><div><div class="dc-title">Platform Activity</div><div class="dc-sub">Cumulative event counts</div></div></div>
      <?php
        $maxStat = max(1, $viewCount, $itinCount, $recCount);
        $stats = [
            ['Destination Views', $viewCount,  'ac'],
            ['Itineraries Created', $itinCount, ''],
            ['Recommendations',   $recCount,   ''],
        ];
      ?>
      <div class="bar-list" style="margin-top:8px;">
        <?php foreach ($stats as [$lbl, $val, $cls]): $pct = round(($val / $maxStat) * 100); ?>
        <div class="bar-row">
          <div class="bar-lbl" style="width:150px;"><?php echo $lbl; ?></div>
          <div class="bar-bg"><div class="bar-f <?php echo $cls; ?>" style="width:<?php echo $pct; ?>%"></div></div>
          <div class="bar-val"><?php echo number_format((int) $val); ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="divider"></div>
      <div class="dc-title" style="margin-bottom:6px;font-size:.8rem;">Active Destinations by Province</div>
      <?php
        $maxProv = max(1, ...array_column($provinceCounts, 'cnt') ?: [1]);
      ?>
      <div class="bar-list">
        <?php foreach ($provinceCounts as $row): $pct = round(($row['cnt'] / $maxProv) * 100); ?>
        <div class="bar-row">
          <div class="bar-lbl" style="width:90px;"><?php echo escape($row['name']); ?></div>
          <div class="bar-bg"><div class="bar-f" style="width:<?php echo $pct; ?>%"></div></div>
          <div class="bar-val"><?php echo (int) $row['cnt']; ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="dc">
      <div class="dc-head"><div><div class="dc-title">Approval Queue</div><div class="dc-sub">Pending provider listings</div></div><a class="s-btn" href="/doon-app/admin/providers.php">View all</a></div>
      <?php if (empty($pendingListings)): ?>
      <div class="dest-row" style="opacity:.5;">No pending listings.</div>
      <?php else: ?>
      <?php foreach ($pendingListings as $pl): ?>
      <div class="appr-item">
        <div>
          <div class="appr-name"><?php echo escape($pl['listing_title']); ?></div>
          <div class="appr-meta"><?php echo escape($pl['business_name']); ?> &mdash; <?php echo escape($pl['municipality'] . ', ' . $pl['province']); ?></div>
        </div>
        <div class="appr-btns">
          <a class="btn-ok" href="/doon-app/admin/providers.php">Review</a>
        </div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </section>
  </div>
</main>
</div>
<script src="/doon-app/assets/js/main.js"></script>
<?php include '../includes/footer.php'; ?>

