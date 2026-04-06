<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireRole('admin');
$pageTitle = 'Reports';
$additionalCSS = '<link rel="stylesheet" href="/doon-app/assets/css/dashboard.css"><link rel="stylesheet" href="/doon-app/assets/css/components.css">';

try {
    $reports = $pdo->query(
        'SELECT r.*, p.name AS province_name FROM lgu_monthly_reports r
         LEFT JOIN provinces p ON r.province_id = p.id
         ORDER BY r.report_month DESC LIMIT 24'
    )->fetchAll();
} catch (Exception $e) {
    $reports = [];
}
?>
<?php include '../includes/header.php'; ?>
<div class="d-wrap">
<?php include '../includes/sidebar.php'; ?>
<main class="d-main">
  <div class="d-topbar"><div><h1 class="d-page-title">LGU Monthly Reports</h1><p class="d-page-sub">Auto-generated visitor and destination performance data.</p></div></div>

  <section class="dc">
    <?php if (empty($reports)): ?>
    <div class="dest-row" style="opacity:.5;">No monthly reports generated yet. Reports will appear here once the system has collected visitor data.</div>
    <?php else: ?>
    <table class="d-table">
      <thead>
        <tr>
          <th>Month</th>
          <th>Province</th>
          <th>Total Visitors</th>
          <th>Unique Visitors</th>
          <th>Itineraries</th>
          <th>Reviews</th>
          <th>Avg Rating</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($reports as $r): ?>
        <tr>
          <td><?php echo date('M Y', strtotime($r['report_month'])); ?></td>
          <td><?php echo escape($r['province_name']); ?></td>
          <td><?php echo number_format((int) $r['total_visitors']); ?></td>
          <td><?php echo number_format((int) $r['unique_visitors']); ?></td>
          <td><?php echo (int) $r['total_itineraries_created']; ?></td>
          <td><?php echo (int) $r['total_reviews']; ?></td>
          <td><?php echo $r['avg_destination_rating'] ? number_format((float) $r['avg_destination_rating'], 2) : '—'; ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </section>
</main>
</div>
<script src="/doon-app/assets/js/main.js"></script>
<?php include '../includes/footer.php'; ?>
