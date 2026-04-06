<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireRole('admin');
$pageTitle = 'Analytics';
$additionalCSS = '<link rel="stylesheet" href="/doon-app/assets/css/dashboard.css"><link rel="stylesheet" href="/doon-app/assets/css/components.css">';

try {
    $viewCount = $pdo->query('SELECT COUNT(*) FROM analytics_events WHERE event_type = "destination_view"')->fetchColumn();
    $recCount  = $pdo->query('SELECT COUNT(*) FROM recommendation_requests')->fetchColumn();
    $itinCount = $pdo->query('SELECT COUNT(*) FROM analytics_events WHERE event_type = "itinerary_created"')->fetchColumn();
    $reviewCount = $pdo->query('SELECT COUNT(*) FROM reviews WHERE is_published = 1')->fetchColumn();

    // Top 5 destinations by view count
    $topDests = $pdo->query(
        'SELECT d.name, d.view_count, p.name AS province_name
         FROM destinations d LEFT JOIN provinces p ON d.province_id = p.id
         WHERE d.is_active = 1 ORDER BY d.view_count DESC LIMIT 5'
    )->fetchAll();

    // Recommendation requests by generational profile
    $genBreakdown = $pdo->query(
        'SELECT generational_profile, COUNT(*) as cnt
         FROM recommendation_requests
         WHERE generational_profile IS NOT NULL
         GROUP BY generational_profile ORDER BY cnt DESC'
    )->fetchAll();

} catch (Exception $e) {
    $viewCount = $recCount = $itinCount = $reviewCount = 0;
    $topDests = $genBreakdown = [];
}
?>
<?php include '../includes/header.php'; ?>
<div class="d-wrap">
<?php include '../includes/sidebar.php'; ?>
<main class="d-main">
  <div class="d-topbar"><div><h1 class="d-page-title">Analytics</h1><p class="d-page-sub">Platform activity overview.</p></div></div>

  <section class="kpi-row c4">
    <article class="kpi"><div class="kpi-lbl">Destination Views</div><div class="kpi-val"><?php echo number_format((int) $viewCount); ?></div></article>
    <article class="kpi"><div class="kpi-lbl">Recommendations</div><div class="kpi-val"><?php echo number_format((int) $recCount); ?></div></article>
    <article class="kpi"><div class="kpi-lbl">Itineraries Created</div><div class="kpi-val"><?php echo number_format((int) $itinCount); ?></div></article>
    <article class="kpi"><div class="kpi-lbl">Published Reviews</div><div class="kpi-val"><?php echo number_format((int) $reviewCount); ?></div></article>
  </section>

  <div class="g2">
    <section class="dc">
      <div class="dc-head"><div><div class="dc-title">Top Destinations by Views</div></div></div>
      <div class="bar-list" style="margin-top:8px;">
        <?php
        $maxViews = max(1, max(array_column($topDests, 'view_count') ?: [1]));
        foreach ($topDests as $d):
            $pct = round(($d['view_count'] / $maxViews) * 100);
        ?>
        <div class="bar-row">
          <div class="bar-lbl" style="width:130px;"><?php echo escape(mb_strimwidth($d['name'], 0, 18, '..')); ?></div>
          <div class="bar-bg"><div class="bar-f ac" style="width:<?php echo $pct; ?>%"></div></div>
          <div class="bar-val"><?php echo number_format((int) $d['view_count']); ?></div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($topDests)): ?><div class="dest-row" style="opacity:.5;">No data yet.</div><?php endif; ?>
      </div>
    </section>

    <section class="dc">
      <div class="dc-head"><div><div class="dc-title">Recommendations by Generation</div></div></div>
      <div class="bar-list" style="margin-top:8px;">
        <?php
        $maxGen = max(1, max(array_column($genBreakdown, 'cnt') ?: [1]));
        $genLabels = ['gen_z'=>'Gen Z','millennial'=>'Millennial','gen_x'=>'Gen X','boomer'=>'Boomer'];
        foreach ($genBreakdown as $g):
            $pct = round(($g['cnt'] / $maxGen) * 100);
        ?>
        <div class="bar-row">
          <div class="bar-lbl" style="width:100px;"><?php echo $genLabels[$g['generational_profile']] ?? $g['generational_profile']; ?></div>
          <div class="bar-bg"><div class="bar-f" style="width:<?php echo $pct; ?>%"></div></div>
          <div class="bar-val"><?php echo (int) $g['cnt']; ?></div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($genBreakdown)): ?><div class="dest-row" style="opacity:.5;">No data yet.</div><?php endif; ?>
      </div>
    </section>
  </div>
</main>
</div>
<script src="/doon-app/assets/js/main.js"></script>
<?php include '../includes/footer.php'; ?>
