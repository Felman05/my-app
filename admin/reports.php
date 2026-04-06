<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireRole('admin');
$pageTitle = 'Reports';
$additionalCSS = '<link rel="stylesheet" href="/doon-app/assets/css/dashboard.css"><link rel="stylesheet" href="/doon-app/assets/css/components.css">';

$currentUser = getCurrentUser();
$message = '';

// Generate report for a given month
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate'])) {
    $month = $_POST['report_month'] ?? date('Y-m-01');
    // Normalise to first of month
    $month = date('Y-m-01', strtotime($month));

    try {
        $provinces = $pdo->query('SELECT id, name FROM provinces')->fetchAll();

        foreach ($provinces as $prov) {
            $pid = $prov['id'];

            // Destination IDs for this province
            $destIds = $pdo->prepare('SELECT id FROM destinations WHERE province_id = ?');
            $destIds->execute([$pid]);
            $ids = array_column($destIds->fetchAll(), 'id');

            if (empty($ids)) {
                continue; // no destinations, skip
            }

            $placeholders = implode(',', array_fill(0, count($ids), '?'));

            // Total & unique visitors (destination_view events for this province's destinations in this month)
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) as total, COUNT(DISTINCT user_id) as uniq
                 FROM analytics_events
                 WHERE event_type = 'destination_view'
                   AND JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.destination_id')) IN ($placeholders)
                   AND created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 MONTH)"
            );
            $stmt->execute(array_merge($ids, [$month, $month]));
            $visitorRow = $stmt->fetch();

            // Itineraries that include a destination from this province in this month
            $stmt = $pdo->prepare(
                "SELECT COUNT(DISTINCT i.id)
                 FROM itineraries i
                 JOIN itinerary_items ii ON ii.itinerary_id = i.id
                 WHERE ii.destination_id IN ($placeholders)
                   AND i.created_at >= ? AND i.created_at < DATE_ADD(?, INTERVAL 1 MONTH)"
            );
            $stmt->execute(array_merge($ids, [$month, $month]));
            $itinCount = (int) $stmt->fetchColumn();

            // Reviews for destinations in this province in this month
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) as cnt, AVG(rating) as avg_rating
                 FROM reviews
                 WHERE destination_id IN ($placeholders)
                   AND created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 MONTH)"
            );
            $stmt->execute(array_merge($ids, [$month, $month]));
            $reviewRow = $stmt->fetch();

            // Top 3 destinations by views this month
            $stmt = $pdo->prepare(
                "SELECT d.id, d.name, COUNT(*) as views
                 FROM analytics_events ae
                 JOIN destinations d ON d.id = CAST(JSON_UNQUOTE(JSON_EXTRACT(ae.metadata,'$.destination_id')) AS UNSIGNED)
                 WHERE ae.event_type = 'destination_view'
                   AND d.province_id = ?
                   AND ae.created_at >= ? AND ae.created_at < DATE_ADD(?, INTERVAL 1 MONTH)
                 GROUP BY d.id, d.name ORDER BY views DESC LIMIT 3"
            );
            $stmt->execute([$pid, $month, $month]);
            $topDests = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Visitor demographics from recommendation_requests this month
            $stmt = $pdo->prepare(
                "SELECT generational_profile, COUNT(*) as cnt
                 FROM recommendation_requests
                 WHERE province_id = ?
                   AND created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 MONTH)
                   AND generational_profile IS NOT NULL
                 GROUP BY generational_profile"
            );
            $stmt->execute([$pid, $month, $month]);
            $demo = [];
            foreach ($stmt->fetchAll() as $row) {
                $demo[$row['generational_profile']] = (int) $row['cnt'];
            }

            // Upsert
            $upsert = $pdo->prepare(
                'INSERT INTO lgu_monthly_reports
                    (province_id, report_month, total_visitors, unique_visitors, top_destinations,
                     visitor_demographics, total_itineraries_created, total_reviews,
                     avg_destination_rating, generated_at, generated_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE
                    total_visitors=VALUES(total_visitors), unique_visitors=VALUES(unique_visitors),
                    top_destinations=VALUES(top_destinations), visitor_demographics=VALUES(visitor_demographics),
                    total_itineraries_created=VALUES(total_itineraries_created),
                    total_reviews=VALUES(total_reviews), avg_destination_rating=VALUES(avg_destination_rating),
                    generated_at=NOW(), generated_by=VALUES(generated_by), updated_at=NOW()'
            );
            $upsert->execute([
                $pid, $month,
                (int) ($visitorRow['total'] ?? 0),
                (int) ($visitorRow['uniq'] ?? 0),
                json_encode($topDests),
                json_encode($demo),
                $itinCount,
                (int) ($reviewRow['cnt'] ?? 0),
                $reviewRow['avg_rating'] ? round((float) $reviewRow['avg_rating'], 2) : null,
                $currentUser['id'],
            ]);
        }
        $message = 'Reports generated for ' . date('F Y', strtotime($month)) . '.';
    } catch (Exception $e) {
        $message = 'Error generating reports: ' . $e->getMessage();
    }
}

try {
    $reports = $pdo->query(
        'SELECT r.*, p.name AS province_name FROM lgu_monthly_reports r
         LEFT JOIN provinces p ON r.province_id = p.id
         ORDER BY r.report_month DESC, p.name ASC LIMIT 60'
    )->fetchAll();
} catch (Exception $e) {
    $reports = [];
}
?>
<?php include '../includes/header.php'; ?>
<div class="d-wrap">
<?php include '../includes/sidebar.php'; ?>
<main class="d-main">
  <div class="d-topbar">
    <div><h1 class="d-page-title">LGU Monthly Reports</h1><p class="d-page-sub">Per-province visitor and destination performance data.</p></div>
  </div>

  <section class="dc" style="margin-bottom:16px;">
    <div class="dc-title" style="margin-bottom:10px;">Generate Report</div>
    <?php if ($message): ?>
    <div class="alert <?php echo str_contains($message, 'Error') ? 'err' : 'ok'; ?>" style="margin-bottom:10px;"><?php echo escape($message); ?></div>
    <?php endif; ?>
    <form method="POST" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;">
      <div class="rf-g" style="flex:1;min-width:180px;">
        <label class="rf-lbl">Month</label>
        <input class="rf-ctrl" type="month" name="report_month" value="<?php echo date('Y-m'); ?>">
      </div>
      <button class="rf-go" name="generate" value="1" type="submit" style="margin-bottom:0;">Generate for All Provinces</button>
    </form>
    <p style="font-size:.8rem;opacity:.6;margin-top:8px;">Aggregates destination views, itineraries, and reviews from the selected month across all provinces. Re-running overwrites existing data for that month.</p>
  </section>

  <section class="dc">
    <?php if (empty($reports)): ?>
    <div class="dest-row" style="opacity:.5;">No reports yet. Generate one above.</div>
    <?php else: ?>
    <div style="overflow-x:auto;">
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
          <th>Demographics</th>
          <th>Top Destinations</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $genLabels = ['gen_z' => 'Gen Z', 'millennial' => 'Millennial', 'gen_x' => 'Gen X', 'boomer' => 'Boomer'];
        $lastMonth = null;
        foreach ($reports as $r):
            $thisMonth = date('M Y', strtotime($r['report_month']));
            $demo = $r['visitor_demographics'] ? json_decode($r['visitor_demographics'], true) : [];
            $topDests = $r['top_destinations'] ? json_decode($r['top_destinations'], true) : [];
        ?>
        <tr>
          <td><?php echo $thisMonth !== $lastMonth ? $thisMonth : ''; $lastMonth = $thisMonth; ?></td>
          <td><?php echo escape($r['province_name']); ?></td>
          <td><?php echo number_format((int) $r['total_visitors']); ?></td>
          <td><?php echo number_format((int) $r['unique_visitors']); ?></td>
          <td><?php echo (int) $r['total_itineraries_created']; ?></td>
          <td><?php echo (int) $r['total_reviews']; ?></td>
          <td><?php echo $r['avg_destination_rating'] ? number_format((float) $r['avg_destination_rating'], 2) : '—'; ?></td>
          <td style="font-size:.8rem;">
            <?php if (!empty($demo)): ?>
              <?php foreach ($demo as $gen => $cnt): ?>
              <div><?php echo ($genLabels[$gen] ?? $gen) . ': ' . (int) $cnt; ?></div>
              <?php endforeach; ?>
            <?php else: ?>—<?php endif; ?>
          </td>
          <td style="font-size:.8rem;">
            <?php if (!empty($topDests)): ?>
              <?php foreach ($topDests as $td): ?>
              <div><?php echo escape($td['name'] ?? ''); ?> (<?php echo (int) ($td['views'] ?? 0); ?>)</div>
              <?php endforeach; ?>
            <?php else: ?>—<?php endif; ?>
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
