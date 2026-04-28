<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireRole('admin');
$pageTitle = 'Analytics';
$additionalCSS = '<link rel="stylesheet" href="/doon-app/assets/css/dashboard.css"><link rel="stylesheet" href="/doon-app/assets/css/components.css">';

$thisMonthStart = date('Y-m-01 00:00:00');
$lastMonthStart = date('Y-m-01 00:00:00', strtotime('-1 month'));
$lastMonthEnd   = date('Y-m-t 23:59:59', strtotime('-1 month'));

function momBadge($curr, $prev) {
    if ($prev == 0) return ['kpi-up', $curr > 0 ? "+{$curr} this month" : 'No prior data'];
    $pct = round((($curr - $prev) / $prev) * 100, 1);
    $str = ($pct >= 0 ? '+' : '') . $pct . '% vs last month';
    return [$pct >= 0 ? 'kpi-up' : 'kpi-warn', $str];
}

try {
    // ── Overview KPIs ────────────────────────────────────────────
    $totalTourists   = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE role = "tourist"')->fetchColumn();

    // batch helpers using single queries where possible
    $stmt = $pdo->prepare('SELECT
        SUM(created_at >= ?) as this_m,
        SUM(created_at BETWEEN ? AND ?) as last_m
        FROM users WHERE role = "tourist"');
    $stmt->execute([$thisMonthStart, $lastMonthStart, $lastMonthEnd]);
    $userMom = $stmt->fetch();

    $stmt = $pdo->prepare('SELECT
        SUM(created_at >= ?) as this_m,
        SUM(created_at BETWEEN ? AND ?) as last_m
        FROM recommendation_requests');
    $stmt->execute([$thisMonthStart, $lastMonthStart, $lastMonthEnd]);
    $recMom = $stmt->fetch();

    $stmt = $pdo->prepare('SELECT
        SUM(created_at >= ?) as this_m,
        SUM(created_at BETWEEN ? AND ?) as last_m
        FROM reviews');
    $stmt->execute([$thisMonthStart, $lastMonthStart, $lastMonthEnd]);
    $revMom = $stmt->fetch();

    $stmt = $pdo->prepare('SELECT
        SUM(created_at >= ?) as this_m,
        SUM(created_at BETWEEN ? AND ?) as last_m
        FROM itineraries');
    $stmt->execute([$thisMonthStart, $lastMonthStart, $lastMonthEnd]);
    $itinMom = $stmt->fetch();

    $stmt = $pdo->prepare('SELECT
        SUM(created_at >= ?) as this_m,
        SUM(created_at BETWEEN ? AND ?) as last_m
        FROM favorites');
    $stmt->execute([$thisMonthStart, $lastMonthStart, $lastMonthEnd]);
    $favMom = $stmt->fetch();

    $totalDests    = (int) $pdo->query('SELECT COUNT(*) FROM destinations WHERE is_active = 1')->fetchColumn();
    $totalRecs     = (int) $pdo->query('SELECT COUNT(*) FROM recommendation_requests')->fetchColumn();
    $totalReviews  = (int) $pdo->query('SELECT COUNT(*) FROM reviews')->fetchColumn();
    $publishedReviews = (int) $pdo->query('SELECT COUNT(*) FROM reviews WHERE is_published = 1')->fetchColumn();
    $totalItins    = (int) $pdo->query('SELECT COUNT(*) FROM itineraries')->fetchColumn();
    $totalFavs     = (int) $pdo->query('SELECT COUNT(*) FROM favorites')->fetchColumn();
    $totalEvents   = (int) $pdo->query('SELECT COUNT(*) FROM analytics_events')->fetchColumn();
    $avgRating     = (float) $pdo->query('SELECT ROUND(AVG(avg_rating),2) FROM destinations WHERE avg_rating > 0')->fetchColumn();
    $avgRecMs      = (int)   $pdo->query('SELECT ROUND(AVG(response_time_ms)) FROM recommendation_requests WHERE response_time_ms > 0')->fetchColumn();

    // ── 30-Day Activity Trend ───────────────────────────────────
    $trendRows = $pdo->query(
        "SELECT DATE(created_at) as d,
                SUM(event_type='destination_view') as views,
                SUM(event_type='itinerary_created') as itins,
                SUM(event_type='chatbot_query') as chats,
                SUM(event_type='review_submitted') as revs
         FROM analytics_events
         WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
         GROUP BY DATE(created_at)"
    )->fetchAll(PDO::FETCH_ASSOC);
    $trendByDay = array_column($trendRows, null, 'd');

    $recTrend = $pdo->query(
        "SELECT DATE(created_at) as d, COUNT(*) as cnt
         FROM recommendation_requests
         WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
         GROUP BY DATE(created_at)"
    )->fetchAll(PDO::FETCH_ASSOC);
    $recByDay = array_column($recTrend, 'cnt', 'd');

    $tDays = []; for ($i = 29; $i >= 0; $i--) $tDays[] = date('Y-m-d', strtotime("-{$i} days"));
    $tLabels = $tViews = $tItins = $tChats = $tRevs = $tRecs = [];
    foreach ($tDays as $d) {
        $tLabels[] = date('M j', strtotime($d));
        $tViews[]  = (int) ($trendByDay[$d]['views'] ?? 0);
        $tItins[]  = (int) ($trendByDay[$d]['itins'] ?? 0);
        $tChats[]  = (int) ($trendByDay[$d]['chats'] ?? 0);
        $tRevs[]   = (int) ($trendByDay[$d]['revs']  ?? 0);
        $tRecs[]   = (int) ($recByDay[$d] ?? 0);
    }

    // ── User Demographics ───────────────────────────────────────
    $genDist   = $pdo->query("SELECT generational_profile as k, COUNT(*) as v FROM tourist_profiles WHERE generational_profile IS NOT NULL GROUP BY k")->fetchAll();
    $styleDist = $pdo->query("SELECT travel_style as k, COUNT(*) as v FROM tourist_profiles WHERE travel_style IS NOT NULL GROUP BY k")->fetchAll();
    $budgDist  = $pdo->query("SELECT preferred_budget as k, COUNT(*) as v FROM tourist_profiles WHERE preferred_budget IS NOT NULL GROUP BY k ORDER BY FIELD(k,'free','budget','mid_range','luxury')")->fetchAll();

    // ── Destination Intelligence ────────────────────────────────
    $topDests = $pdo->query(
        "SELECT d.name, d.view_count, d.avg_rating, d.total_reviews,
                COUNT(DISTINCT f.id) as fav_count,
                p.name AS province_name, ac.name AS cat_name
         FROM destinations d
         LEFT JOIN provinces p ON d.province_id = p.id
         LEFT JOIN activity_categories ac ON d.category_id = ac.id
         LEFT JOIN favorites f ON f.destination_id = d.id
         WHERE d.is_active = 1
         GROUP BY d.id ORDER BY d.view_count DESC LIMIT 10"
    )->fetchAll();
    $maxViews = max(1, (int) ($topDests[0]['view_count'] ?? 1));

    $catDist = $pdo->query(
        "SELECT ac.name as k, COUNT(d.id) as v
         FROM activity_categories ac
         LEFT JOIN destinations d ON d.category_id = ac.id AND d.is_active = 1
         GROUP BY ac.id HAVING v > 0 ORDER BY v DESC"
    )->fetchAll();

    // ── Province Overview ───────────────────────────────────────
    $provStats = $pdo->query(
        "SELECT p.name,
                COUNT(DISTINCT d.id) as dest_count,
                COALESCE(SUM(d.view_count),0) as total_views,
                COUNT(DISTINCT r.id) as review_count,
                ROUND(AVG(r.rating),1) as avg_rating,
                COUNT(DISTINCT i.id) as itin_count
         FROM provinces p
         LEFT JOIN destinations d ON d.province_id = p.id AND d.is_active = 1
         LEFT JOIN reviews r ON r.destination_id = d.id
         LEFT JOIN itineraries i ON i.province_id = p.id
         GROUP BY p.id ORDER BY total_views DESC"
    )->fetchAll();

    // ── Recommendation Analytics ────────────────────────────────
    $recBudget   = $pdo->query("SELECT budget_label as k, COUNT(*) as v FROM recommendation_requests WHERE budget_label IS NOT NULL GROUP BY k ORDER BY FIELD(k,'free','budget','mid_range','luxury')")->fetchAll();
    $recGen      = $pdo->query("SELECT generational_profile as k, COUNT(*) as v FROM recommendation_requests WHERE generational_profile IS NOT NULL GROUP BY k ORDER BY v DESC")->fetchAll();
    $recProv     = $pdo->query("SELECT p.name as k, COUNT(*) as v FROM recommendation_requests rr JOIN provinces p ON p.id=rr.province_id WHERE rr.province_id IS NOT NULL GROUP BY p.id ORDER BY v DESC")->fetchAll();
    $recStats    = $pdo->query("SELECT ROUND(AVG(number_of_people),1) as avg_ppl, ROUND(AVG(trip_duration_days),1) as avg_days, ROUND(AVG(results_count),1) as avg_results, COUNT(*) as total FROM recommendation_requests")->fetch();

    // ── Engagement ──────────────────────────────────────────────
    $eventDist = $pdo->query("SELECT event_type as k, COUNT(*) as v FROM analytics_events GROUP BY event_type ORDER BY v DESC")->fetchAll();

    // ── Review Analytics ────────────────────────────────────────
    $ratingDist  = $pdo->query("SELECT rating as k, COUNT(*) as v FROM reviews GROUP BY rating ORDER BY rating")->fetchAll();
    $topReviewed = $pdo->query(
        "SELECT d.name, COUNT(r.id) as cnt, ROUND(AVG(r.rating),1) as avg_r, p.name as prov
         FROM reviews r JOIN destinations d ON r.destination_id=d.id JOIN provinces p ON d.province_id=p.id
         GROUP BY r.destination_id ORDER BY cnt DESC LIMIT 8"
    )->fetchAll();

    // ── Itinerary Analytics ─────────────────────────────────────
    $itinStatus  = $pdo->query("SELECT status as k, COUNT(*) as v FROM itineraries GROUP BY status ORDER BY v DESC")->fetchAll();
    $itinProvince= $pdo->query("SELECT p.name as k, COUNT(*) as v FROM itineraries i JOIN provinces p ON i.province_id=p.id WHERE i.province_id IS NOT NULL GROUP BY p.id ORDER BY v DESC")->fetchAll();
    $itinStyle   = $pdo->query("SELECT travel_theme as k, COUNT(*) as v FROM itineraries WHERE travel_theme IS NOT NULL GROUP BY travel_theme ORDER BY v DESC LIMIT 8")->fetchAll();
    $itinStats   = $pdo->query("SELECT ROUND(AVG(total_days),1) as avg_days, ROUND(AVG(number_of_people),1) as avg_ppl, ROUND(AVG(budget_amount),0) as avg_budget FROM itineraries")->fetch();

} catch (Exception $e) {
    error_log('[analytics.php] ' . $e->getMessage());
    $totalTourists=$totalDests=$totalRecs=$totalReviews=$publishedReviews=$totalItins=$totalFavs=$totalEvents=$avgRecMs=0;
    $avgRating=0;
    $userMom=$recMom=$revMom=$itinMom=$favMom=['this_m'=>0,'last_m'=>0];
    $tLabels=$tViews=$tItins=$tChats=$tRevs=$tRecs=[];
    $genDist=$styleDist=$budgDist=$topDests=$catDist=$provStats=[];
    $recBudget=$recGen=$recProv=[];
    $recStats=['avg_ppl'=>0,'avg_days'=>0,'avg_results'=>0,'total'=>0];
    $eventDist=$ratingDist=$topReviewed=[];
    $itinStatus=$itinProvince=$itinStyle=[];
    $itinStats=['avg_days'=>0,'avg_ppl'=>0,'avg_budget'=>0];
    $maxViews=1;
}

// helpers
$genLabels  = ['gen_z'=>'Gen Z','millennial'=>'Millennial','gen_x'=>'Gen X','boomer'=>'Boomer'];
$budgLabels = ['free'=>'Free','budget'=>'Budget','mid_range'=>'Mid-Range','luxury'=>'Luxury'];
$styleLabels= ['solo'=>'Solo','couple'=>'Couple','family'=>'Family','group'=>'Group'];
$statusLabels=['draft'=>'Draft','planned'=>'Planned','ongoing'=>'Ongoing','completed'=>'Completed'];

function kv(array $rows): array {
    $out = [];
    foreach ($rows as $r) $out[$r['k']] = (int) $r['v'];
    return $out;
}
?>
<?php include '../includes/header.php'; ?>
<div class="d-wrap">
<?php include '../includes/sidebar.php'; ?>
<main class="d-main">

  <div class="d-topbar">
    <div>
      <h1 class="d-page-title">Analytics</h1>
      <p class="d-page-sub">Platform intelligence — CALABARZON tourism data as of <?php echo date('F j, Y'); ?></p>
    </div>
  </div>

  <!-- ── KPI Row 1: Volume ─────────────────────────────────────── -->
  <section class="kpi-row c4 mb20">
    <?php [$cls,$txt] = momBadge((int)$userMom['this_m'], (int)$userMom['last_m']); ?>
    <article class="kpi">
      <div class="kpi-lbl">Tourist Users</div>
      <div class="kpi-val"><?php echo number_format($totalTourists); ?></div>
      <div class="kpi-sub <?php echo $cls; ?>"><?php echo $txt; ?></div>
    </article>

    <article class="kpi">
      <div class="kpi-lbl">Active Destinations</div>
      <div class="kpi-val"><?php echo number_format($totalDests); ?></div>
      <div class="kpi-sub">Across 5 provinces</div>
    </article>

    <?php [$cls,$txt] = momBadge((int)$recMom['this_m'], (int)$recMom['last_m']); ?>
    <article class="kpi">
      <div class="kpi-lbl">Recommendations</div>
      <div class="kpi-val"><?php echo number_format($totalRecs); ?></div>
      <div class="kpi-sub <?php echo $cls; ?>"><?php echo $txt; ?></div>
    </article>

    <article class="kpi">
      <div class="kpi-lbl">Avg Response Time</div>
      <div class="kpi-val"><?php echo $avgRecMs > 0 ? number_format($avgRecMs) . '<span style="font-size:14px;font-weight:400;color:var(--i4)">ms</span>' : '—'; ?></div>
      <div class="kpi-sub">Recommendation engine</div>
    </article>
  </section>

  <!-- ── KPI Row 2: Engagement ──────────────────────────────────── -->
  <section class="kpi-row c4 mb20">
    <?php [$cls,$txt] = momBadge((int)$revMom['this_m'], (int)$revMom['last_m']); ?>
    <article class="kpi">
      <div class="kpi-lbl">Total Reviews</div>
      <div class="kpi-val"><?php echo number_format($totalReviews); ?></div>
      <div class="kpi-sub <?php echo $cls; ?>"><?php echo $txt; ?> &mdash; <?php echo number_format($publishedReviews); ?> published</div>
    </article>

    <?php [$cls,$txt] = momBadge((int)$itinMom['this_m'], (int)$itinMom['last_m']); ?>
    <article class="kpi">
      <div class="kpi-lbl">Itineraries Created</div>
      <div class="kpi-val"><?php echo number_format($totalItins); ?></div>
      <div class="kpi-sub <?php echo $cls; ?>"><?php echo $txt; ?></div>
    </article>

    <?php [$cls,$txt] = momBadge((int)$favMom['this_m'], (int)$favMom['last_m']); ?>
    <article class="kpi">
      <div class="kpi-lbl">Favorites Saved</div>
      <div class="kpi-val"><?php echo number_format($totalFavs); ?></div>
      <div class="kpi-sub <?php echo $cls; ?>"><?php echo $txt; ?></div>
    </article>

    <article class="kpi">
      <div class="kpi-lbl">Avg Destination Rating</div>
      <div class="kpi-val"><?php echo $avgRating > 0 ? number_format($avgRating, 1) . '<span style="font-size:14px;font-weight:400;color:var(--i4)">/5</span>' : '—'; ?></div>
      <div class="kpi-sub"><?php echo number_format($totalEvents); ?> events logged</div>
    </article>
  </section>

  <!-- ── Trend + Demographics ───────────────────────────────────── -->
  <div class="g31 mb20">
    <section class="dc">
      <div class="dc-head">
        <div>
          <div class="dc-title">30-Day Platform Activity</div>
          <div class="dc-sub">Daily event counts across key actions</div>
        </div>
        <div style="display:flex;gap:12px;flex-wrap:wrap;">
          <span style="font-size:11px;color:var(--i3);display:flex;align-items:center;gap:5px;"><span style="width:10px;height:3px;background:#1a1a18;display:inline-block;border-radius:2px;"></span>Views</span>
          <span style="font-size:11px;color:var(--i3);display:flex;align-items:center;gap:5px;"><span style="width:10px;height:3px;background:#2563eb;display:inline-block;border-radius:2px;"></span>Recs</span>
          <span style="font-size:11px;color:var(--i3);display:flex;align-items:center;gap:5px;"><span style="width:10px;height:3px;background:#16a34a;display:inline-block;border-radius:2px;"></span>Itins</span>
          <span style="font-size:11px;color:var(--i3);display:flex;align-items:center;gap:5px;"><span style="width:10px;height:3px;background:#d97706;display:inline-block;border-radius:2px;"></span>Chats</span>
        </div>
      </div>
      <div style="height:200px;position:relative;">
        <canvas id="chartTrend"></canvas>
      </div>
    </section>

    <section class="dc">
      <div class="dc-title mb16">User Demographics</div>
      <div class="sub-lbl">Generational Profile</div>
      <div style="height:120px;position:relative;margin-bottom:14px;">
        <canvas id="chartGen"></canvas>
      </div>
      <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:14px;">
        <?php
        $genColors = ['gen_z'=>'#4f46e5','millennial'=>'#2563eb','gen_x'=>'#d97706','boomer'=>'#dc2626'];
        $genTotal  = array_sum(array_column($genDist, 'v'));
        foreach ($genDist as $g):
            $pct = $genTotal > 0 ? round($g['v']/$genTotal*100) : 0;
        ?>
        <span style="font-size:11px;padding:3px 9px;border-radius:100px;background:<?php echo $genColors[$g['k']] ?? '#9b9b92'; ?>18;color:<?php echo $genColors[$g['k']] ?? '#9b9b92'; ?>;border:1px solid <?php echo $genColors[$g['k']] ?? '#9b9b92'; ?>44;">
          <?php echo ($genLabels[$g['k']] ?? $g['k']) . ' ' . $pct . '%'; ?>
        </span>
        <?php endforeach; ?>
        <?php if (empty($genDist)): ?><span style="opacity:.5;font-size:12px;">No data yet</span><?php endif; ?>
      </div>
      <div class="sub-lbl">Travel Style</div>
      <div style="height:110px;position:relative;">
        <canvas id="chartStyle"></canvas>
      </div>
    </section>
  </div>

  <!-- ── Destination Intelligence ──────────────────────────────── -->
  <section class="dc mb20">
    <div class="dc-head">
      <div>
        <div class="dc-title">Top 10 Destinations</div>
        <div class="dc-sub">Ranked by total views — all-time</div>
      </div>
    </div>
    <div style="overflow-x:auto;">
      <table style="width:100%;border-collapse:collapse;font-size:12px;">
        <thead>
          <tr style="border-bottom:1px solid var(--bd);">
            <th style="padding:7px 10px;text-align:left;font-size:10px;font-weight:700;letter-spacing:.4px;text-transform:uppercase;color:var(--i4);white-space:nowrap;">#</th>
            <th style="padding:7px 10px;text-align:left;font-size:10px;font-weight:700;letter-spacing:.4px;text-transform:uppercase;color:var(--i4);">Destination</th>
            <th style="padding:7px 10px;text-align:left;font-size:10px;font-weight:700;letter-spacing:.4px;text-transform:uppercase;color:var(--i4);">Province</th>
            <th style="padding:7px 10px;text-align:left;font-size:10px;font-weight:700;letter-spacing:.4px;text-transform:uppercase;color:var(--i4);">Category</th>
            <th style="padding:7px 10px;text-align:left;font-size:10px;font-weight:700;letter-spacing:.4px;text-transform:uppercase;color:var(--i4);min-width:140px;">Views</th>
            <th style="padding:7px 10px;text-align:center;font-size:10px;font-weight:700;letter-spacing:.4px;text-transform:uppercase;color:var(--i4);">Rating</th>
            <th style="padding:7px 10px;text-align:center;font-size:10px;font-weight:700;letter-spacing:.4px;text-transform:uppercase;color:var(--i4);">Reviews</th>
            <th style="padding:7px 10px;text-align:center;font-size:10px;font-weight:700;letter-spacing:.4px;text-transform:uppercase;color:var(--i4);">Favs</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($topDests as $i => $d): $pct = round(($d['view_count'] / $maxViews) * 100); ?>
          <tr style="border-bottom:1px solid var(--bd);" onmouseover="this.style.background='var(--bg)'" onmouseout="this.style.background=''">
            <td style="padding:8px 10px;color:var(--i4);font-weight:700;"><?php echo $i + 1; ?></td>
            <td style="padding:8px 10px;font-weight:600;color:var(--i);max-width:180px;"><?php echo escape($d['name']); ?></td>
            <td style="padding:8px 10px;color:var(--i3);"><?php echo escape($d['province_name'] ?? '—'); ?></td>
            <td style="padding:8px 10px;color:var(--i3);white-space:nowrap;"><?php echo escape($d['cat_name'] ?? '—'); ?></td>
            <td style="padding:8px 10px;">
              <div style="display:flex;align-items:center;gap:8px;">
                <div style="flex:1;background:var(--bg2);border-radius:3px;height:6px;overflow:hidden;">
                  <div style="width:<?php echo $pct; ?>%;height:100%;background:var(--i);border-radius:3px;"></div>
                </div>
                <span style="font-weight:600;color:var(--i);min-width:38px;"><?php echo number_format((int)$d['view_count']); ?></span>
              </div>
            </td>
            <td style="padding:8px 10px;text-align:center;font-weight:600;color:var(--i);"><?php echo $d['avg_rating'] > 0 ? number_format((float)$d['avg_rating'],1) : '—'; ?></td>
            <td style="padding:8px 10px;text-align:center;color:var(--i3);"><?php echo (int)$d['total_reviews']; ?></td>
            <td style="padding:8px 10px;text-align:center;color:var(--i3);"><?php echo (int)$d['fav_count']; ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($topDests)): ?>
          <tr><td colspan="8" style="padding:20px;text-align:center;color:var(--i4);">No destinations yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <!-- ── Province Overview ──────────────────────────────────────── -->
  <section class="dc mb20">
    <div class="dc-head">
      <div>
        <div class="dc-title">Province Performance</div>
        <div class="dc-sub">Destinations, total views, reviews, itineraries per province</div>
      </div>
    </div>
    <?php $maxProvViews = max(1, max(array_column($provStats ?: [[''=> 0,'total_views'=>1]], 'total_views'))); ?>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:4px;">
    <?php foreach ($provStats as $pr): $pvPct = round(($pr['total_views']/$maxProvViews)*100); ?>
      <div style="background:var(--bg);border:1px solid var(--bd);border-radius:var(--r2);padding:14px 16px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
          <span style="font-size:13px;font-weight:700;color:var(--i);"><?php echo escape($pr['name']); ?></span>
          <span style="font-size:11px;color:var(--i4);"><?php echo $pr['dest_count']; ?> destinations</span>
        </div>
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
          <div style="flex:1;background:var(--bd);border-radius:3px;height:5px;overflow:hidden;">
            <div style="width:<?php echo $pvPct; ?>%;height:100%;background:var(--i);border-radius:3px;transition:width 1s ease;"></div>
          </div>
          <span style="font-size:11px;font-weight:600;color:var(--i);min-width:42px;"><?php echo number_format((int)$pr['total_views']); ?> views</span>
        </div>
        <div style="display:flex;gap:16px;">
          <span style="font-size:11px;color:var(--i4);">&#9733; <?php echo $pr['avg_rating'] ?? '—'; ?> avg</span>
          <span style="font-size:11px;color:var(--i4);"><?php echo (int)$pr['review_count']; ?> reviews</span>
          <span style="font-size:11px;color:var(--i4);"><?php echo (int)$pr['itin_count']; ?> itineraries</span>
        </div>
      </div>
    <?php endforeach; ?>
    </div>
  </section>

  <!-- ── Recommendation Analytics ──────────────────────────────── -->
  <div class="g3 mb20">
    <section class="dc">
      <div class="dc-title mb16">Rec. by Budget</div>
      <?php $maxRB = max(1, max(array_column($recBudget ?: [['v'=>1]], 'v'))); ?>
      <div class="bar-list">
        <?php foreach ($recBudget as $r): $pct = round($r['v']/$maxRB*100); ?>
        <div class="bar-row">
          <div class="bar-lbl" style="text-align:left;min-width:80px;"><?php echo $budgLabels[$r['k']] ?? $r['k']; ?></div>
          <div class="bar-bg"><div class="bar-f ac" style="width:<?php echo $pct; ?>%"></div></div>
          <div class="bar-val"><?php echo (int)$r['v']; ?></div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($recBudget)): ?><div style="opacity:.5;font-size:12px;">No data yet.</div><?php endif; ?>
      </div>
      <?php if ($recStats['total'] > 0): ?>
      <div style="margin-top:14px;padding-top:12px;border-top:1px solid var(--bd);display:flex;flex-direction:column;gap:6px;">
        <div style="display:flex;justify-content:space-between;font-size:12px;"><span style="color:var(--i4);">Avg people/trip</span><strong><?php echo $recStats['avg_ppl']; ?></strong></div>
        <div style="display:flex;justify-content:space-between;font-size:12px;"><span style="color:var(--i4);">Avg trip duration</span><strong><?php echo $recStats['avg_days']; ?> days</strong></div>
        <div style="display:flex;justify-content:space-between;font-size:12px;"><span style="color:var(--i4);">Avg results returned</span><strong><?php echo $recStats['avg_results']; ?></strong></div>
      </div>
      <?php endif; ?>
    </section>

    <section class="dc">
      <div class="dc-title mb16">Rec. by Generation</div>
      <div style="height:170px;position:relative;margin-bottom:12px;">
        <canvas id="chartRecGen"></canvas>
      </div>
      <div style="display:flex;flex-wrap:wrap;gap:6px;">
        <?php foreach ($recGen as $g): $gcol = $genColors[$g['k']] ?? '#9b9b92'; ?>
        <span style="font-size:11px;padding:3px 9px;border-radius:100px;background:<?php echo $gcol; ?>18;color:<?php echo $gcol; ?>;border:1px solid <?php echo $gcol; ?>44;">
          <?php echo ($genLabels[$g['k']] ?? $g['k']) . ': ' . $g['v']; ?>
        </span>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="dc">
      <div class="dc-title mb16">Rec. by Province</div>
      <?php $maxRP = max(1, max(array_column($recProv ?: [['v'=>1]], 'v'))); ?>
      <div class="bar-list">
        <?php foreach ($recProv as $r): $pct = round($r['v']/$maxRP*100); ?>
        <div class="bar-row">
          <div class="bar-lbl" style="text-align:left;min-width:80px;"><?php echo escape($r['k']); ?></div>
          <div class="bar-bg"><div class="bar-f" style="width:<?php echo $pct; ?>%"></div></div>
          <div class="bar-val"><?php echo (int)$r['v']; ?></div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($recProv)): ?><div style="opacity:.5;font-size:12px;">No data yet.</div><?php endif; ?>
      </div>
      <div style="margin-top:14px;padding-top:12px;border-top:1px solid var(--bd);">
        <div style="font-size:11px;color:var(--i4);margin-bottom:6px;">Destination category mix</div>
        <?php $maxCat = max(1, max(array_column($catDist ?: [['v'=>1]], 'v'))); ?>
        <?php foreach (array_slice($catDist, 0, 5) as $c): $pct = round($c['v']/$maxCat*100); ?>
        <div style="display:flex;align-items:center;gap:7px;margin-bottom:5px;">
          <div style="font-size:11px;color:var(--i3);min-width:90px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo escape($c['k']); ?></div>
          <div style="flex:1;background:var(--bg2);border-radius:2px;height:5px;overflow:hidden;"><div style="width:<?php echo $pct; ?>%;height:100%;background:var(--i2);border-radius:2px;"></div></div>
          <div style="font-size:11px;font-weight:600;color:var(--i);min-width:20px;"><?php echo (int)$c['v']; ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </section>
  </div>

  <!-- ── Engagement + Reviews ───────────────────────────────────── -->
  <div class="g2 mb20">
    <section class="dc">
      <div class="dc-title mb16">Engagement Breakdown</div>
      <div class="dc-sub" style="margin-bottom:14px;">All-time event distribution</div>
      <div style="height:180px;position:relative;margin-bottom:14px;">
        <canvas id="chartEvents"></canvas>
      </div>
      <div class="bar-list" style="margin-top:8px;">
        <?php
        $evLabels = [
            'destination_view'      => 'Destination Views',
            'destination_click'     => 'Destination Clicks',
            'recommendation_generated' => 'Recs Generated',
            'itinerary_created'     => 'Itineraries Created',
            'review_submitted'      => 'Reviews Submitted',
            'chatbot_query'         => 'Chatbot Queries',
            'map_search'            => 'Map Searches',
            'provider_listing_view' => 'Provider Views',
            'share_itinerary'       => 'Shared Itineraries',
        ];
        $maxEv = max(1, max(array_column($eventDist ?: [['v'=>1]], 'v')));
        foreach ($eventDist as $ev): $pct = round($ev['v']/$maxEv*100); ?>
        <div class="bar-row">
          <div class="bar-lbl" style="text-align:left;min-width:130px;font-size:11px;"><?php echo $evLabels[$ev['k']] ?? $ev['k']; ?></div>
          <div class="bar-bg"><div class="bar-f" style="width:<?php echo $pct; ?>%"></div></div>
          <div class="bar-val"><?php echo number_format((int)$ev['v']); ?></div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($eventDist)): ?><div style="opacity:.5;font-size:12px;">No events yet.</div><?php endif; ?>
      </div>
    </section>

    <section class="dc">
      <div class="dc-title mb16">Review Quality</div>
      <div class="sub-lbl" style="margin-bottom:8px;">Rating Distribution</div>
      <div style="height:120px;position:relative;margin-bottom:14px;">
        <canvas id="chartRatings"></canvas>
      </div>
      <div class="sub-lbl" style="margin-bottom:8px;">Most Reviewed Destinations</div>
      <div class="dest-list">
        <?php foreach (array_slice($topReviewed, 0, 6) as $r): ?>
        <div class="dest-row">
          <div style="flex:1;">
            <div class="dest-name"><?php echo escape($r['name']); ?></div>
            <div class="dest-meta"><?php echo escape($r['prov']); ?></div>
          </div>
          <div style="text-align:right;flex-shrink:0;">
            <div style="font-size:12px;font-weight:600;color:var(--i);">&#9733; <?php echo $r['avg_r']; ?></div>
            <div style="font-size:11px;color:var(--i4);"><?php echo $r['cnt']; ?> reviews</div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($topReviewed)): ?><div class="dest-row" style="opacity:.5;">No reviews yet.</div><?php endif; ?>
      </div>
    </section>
  </div>

  <!-- ── Itinerary Analytics ────────────────────────────────────── -->
  <div class="g2 mb20">
    <section class="dc">
      <div class="dc-title mb16">Itinerary Analytics</div>
      <?php if ($itinStats['avg_days'] > 0): ?>
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-bottom:16px;">
        <div style="background:var(--bg);border:1px solid var(--bd);border-radius:var(--r);padding:12px;text-align:center;">
          <div style="font-family:'Fraunces',serif;font-size:22px;font-weight:700;color:var(--i);"><?php echo $itinStats['avg_days']; ?></div>
          <div style="font-size:10px;color:var(--i4);margin-top:2px;">Avg Days</div>
        </div>
        <div style="background:var(--bg);border:1px solid var(--bd);border-radius:var(--r);padding:12px;text-align:center;">
          <div style="font-family:'Fraunces',serif;font-size:22px;font-weight:700;color:var(--i);"><?php echo $itinStats['avg_ppl']; ?></div>
          <div style="font-size:10px;color:var(--i4);margin-top:2px;">Avg People</div>
        </div>
        <div style="background:var(--bg);border:1px solid var(--bd);border-radius:var(--r);padding:12px;text-align:center;">
          <div style="font-family:'Fraunces',serif;font-size:18px;font-weight:700;color:var(--i);">₱<?php echo number_format((int)$itinStats['avg_budget']); ?></div>
          <div style="font-size:10px;color:var(--i4);margin-top:2px;">Avg Budget</div>
        </div>
      </div>
      <?php endif; ?>

      <div class="sub-lbl" style="margin-bottom:8px;">Status Breakdown</div>
      <?php $maxSt = max(1, max(array_column($itinStatus ?: [['v'=>1]], 'v'))); ?>
      <div class="bar-list" style="margin-bottom:14px;">
        <?php foreach ($itinStatus as $s): $pct = round($s['v']/$maxSt*100); ?>
        <div class="bar-row">
          <div class="bar-lbl" style="text-align:left;min-width:80px;"><?php echo $statusLabels[$s['k']] ?? ucfirst($s['k']); ?></div>
          <div class="bar-bg"><div class="bar-f ac" style="width:<?php echo $pct; ?>%"></div></div>
          <div class="bar-val"><?php echo (int)$s['v']; ?></div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($itinStatus)): ?><div style="opacity:.5;font-size:12px;">No itineraries yet.</div><?php endif; ?>
      </div>

      <div class="sub-lbl" style="margin-bottom:8px;">Province Preference</div>
      <?php $maxIP = max(1, max(array_column($itinProvince ?: [['v'=>1]], 'v'))); ?>
      <div class="bar-list">
        <?php foreach ($itinProvince as $ip): $pct = round($ip['v']/$maxIP*100); ?>
        <div class="bar-row">
          <div class="bar-lbl" style="text-align:left;min-width:80px;"><?php echo escape($ip['k']); ?></div>
          <div class="bar-bg"><div class="bar-f" style="width:<?php echo $pct; ?>%"></div></div>
          <div class="bar-val"><?php echo (int)$ip['v']; ?></div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($itinProvince)): ?><div style="opacity:.5;font-size:12px;">No data yet.</div><?php endif; ?>
      </div>
    </section>

    <section class="dc">
      <div class="dc-title mb16">Destination Category Distribution</div>
      <div style="height:200px;position:relative;margin-bottom:16px;">
        <canvas id="chartCat"></canvas>
      </div>
      <div class="sub-lbl" style="margin-bottom:8px;">Popular Travel Themes</div>
      <?php $maxTh = max(1, max(array_column($itinStyle ?: [['v'=>1]], 'v'))); ?>
      <div class="bar-list">
        <?php foreach ($itinStyle as $th): $pct = round($th['v']/$maxTh*100); ?>
        <div class="bar-row">
          <div class="bar-lbl" style="text-align:left;min-width:100px;text-transform:capitalize;"><?php echo escape(str_replace(['-','_'], ' ', $th['k'])); ?></div>
          <div class="bar-bg"><div class="bar-f" style="width:<?php echo $pct; ?>%"></div></div>
          <div class="bar-val"><?php echo (int)$th['v']; ?></div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($itinStyle)): ?><div style="opacity:.5;font-size:12px;">No theme data yet.</div><?php endif; ?>
      </div>
    </section>
  </div>

</main>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script src="/doon-app/assets/js/main.js"></script>
<script>
(function () {
  var GEN_C   = { gen_z:'#4f46e5', millennial:'#2563eb', gen_x:'#d97706', boomer:'#dc2626' };
  var STYLE_C = { solo:'#1a1a18', couple:'#4a4a44', family:'#6b6b64', group:'#9b9b92' };
  var GEN_LBL = { gen_z:'Gen Z', millennial:'Millennial', gen_x:'Gen X', boomer:'Boomer' };
  var STY_LBL = { solo:'Solo', couple:'Couple', family:'Family', group:'Group' };
  var PALETTE = ['#1a1a18','#2563eb','#16a34a','#d97706','#dc2626','#7c3aed','#0891b2','#db2777','#059669','#ea580c'];

  var defaults = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { display: false }, tooltip: { bodyFont: { size: 12 }, titleFont: { size: 12 } } }
  };

  // ── 30-Day Trend Line ──────────────────────────────────────────
  var trendEl = document.getElementById('chartTrend');
  if (trendEl) {
    new Chart(trendEl, {
      type: 'line',
      data: {
        labels: <?php echo json_encode($tLabels); ?>,
        datasets: [
          { label: 'Views',        data: <?php echo json_encode($tViews); ?>,  borderColor: '#1a1a18', backgroundColor: 'transparent', borderWidth: 2, pointRadius: 0, tension: .4 },
          { label: 'Recs',         data: <?php echo json_encode($tRecs); ?>,   borderColor: '#2563eb', backgroundColor: 'transparent', borderWidth: 2, pointRadius: 0, tension: .4 },
          { label: 'Itineraries',  data: <?php echo json_encode($tItins); ?>,  borderColor: '#16a34a', backgroundColor: 'transparent', borderWidth: 1.5, pointRadius: 0, tension: .4 },
          { label: 'Chatbot',      data: <?php echo json_encode($tChats); ?>,  borderColor: '#d97706', backgroundColor: 'transparent', borderWidth: 1.5, pointRadius: 0, tension: .4 },
        ]
      },
      options: Object.assign({}, defaults, {
        scales: {
          x: { grid: { display: false }, ticks: { maxTicksLimit: 8, font: { size: 10 }, color: '#9b9b92' } },
          y: { grid: { color: '#f1f0ec' }, ticks: { font: { size: 10 }, color: '#9b9b92' }, beginAtZero: true }
        },
        plugins: Object.assign({}, defaults.plugins, { legend: { display: false } })
      })
    });
  }

  // ── Generational Donut ─────────────────────────────────────────
  var genData  = <?php echo json_encode(array_column($genDist, 'v')); ?>;
  var genKeys  = <?php echo json_encode(array_column($genDist, 'k')); ?>;
  var genEl = document.getElementById('chartGen');
  if (genEl && genData.length) {
    new Chart(genEl, {
      type: 'doughnut',
      data: {
        labels: genKeys.map(function(k){ return GEN_LBL[k] || k; }),
        datasets: [{ data: genData, backgroundColor: genKeys.map(function(k){ return GEN_C[k] || '#9b9b92'; }), borderWidth: 0, hoverOffset: 4 }]
      },
      options: Object.assign({}, defaults, { cutout: '68%', plugins: { legend: { display: true, position: 'right', labels: { boxWidth: 9, font: { size: 10 }, color: '#6b6b64' } } } })
    });
  }

  // ── Travel Style Donut ─────────────────────────────────────────
  var styData = <?php echo json_encode(array_column($styleDist, 'v')); ?>;
  var styKeys = <?php echo json_encode(array_column($styleDist, 'k')); ?>;
  var styEl = document.getElementById('chartStyle');
  if (styEl && styData.length) {
    new Chart(styEl, {
      type: 'doughnut',
      data: {
        labels: styKeys.map(function(k){ return STY_LBL[k] || k; }),
        datasets: [{ data: styData, backgroundColor: styKeys.map(function(k){ return STYLE_C[k] || '#c1c1b8'; }), borderWidth: 0, hoverOffset: 4 }]
      },
      options: Object.assign({}, defaults, { cutout: '65%', plugins: { legend: { display: true, position: 'right', labels: { boxWidth: 9, font: { size: 10 }, color: '#6b6b64' } } } })
    });
  }

  // ── Rec by Gen Donut ──────────────────────────────────────────
  var rgData = <?php echo json_encode(array_column($recGen, 'v')); ?>;
  var rgKeys = <?php echo json_encode(array_column($recGen, 'k')); ?>;
  var rgEl = document.getElementById('chartRecGen');
  if (rgEl && rgData.length) {
    new Chart(rgEl, {
      type: 'doughnut',
      data: {
        labels: rgKeys.map(function(k){ return GEN_LBL[k] || k; }),
        datasets: [{ data: rgData, backgroundColor: rgKeys.map(function(k){ return GEN_C[k] || '#9b9b92'; }), borderWidth: 0, hoverOffset: 4 }]
      },
      options: Object.assign({}, defaults, { cutout: '68%', plugins: { legend: { display: true, position: 'right', labels: { boxWidth: 9, font: { size: 10 }, color: '#6b6b64' } } } })
    });
  }

  // ── Event Distribution Donut ───────────────────────────────────
  var evData = <?php echo json_encode(array_column($eventDist, 'v')); ?>;
  var evKeys = <?php echo json_encode(array_column($eventDist, 'k')); ?>;
  var evLbls = { destination_view:'Views', destination_click:'Clicks', recommendation_generated:'Recs', itinerary_created:'Itineraries', review_submitted:'Reviews', chatbot_query:'Chatbot', map_search:'Map', provider_listing_view:'Providers', share_itinerary:'Shares' };
  var evEl = document.getElementById('chartEvents');
  if (evEl && evData.length) {
    new Chart(evEl, {
      type: 'doughnut',
      data: {
        labels: evKeys.map(function(k){ return evLbls[k] || k; }),
        datasets: [{ data: evData, backgroundColor: PALETTE, borderWidth: 0, hoverOffset: 4 }]
      },
      options: Object.assign({}, defaults, { cutout: '60%', plugins: { legend: { display: true, position: 'right', labels: { boxWidth: 9, font: { size: 10 }, color: '#6b6b64' } } } })
    });
  }

  // ── Rating Histogram ───────────────────────────────────────────
  var ratingMap = {};
  var ratingRaw = <?php echo json_encode(array_column($ratingDist, null, 'k')); ?>;
  for (var s = 1; s <= 5; s++) ratingMap[s] = ratingRaw[s] ? parseInt(ratingRaw[s]['v']) : 0;
  var ratEl = document.getElementById('chartRatings');
  if (ratEl) {
    new Chart(ratEl, {
      type: 'bar',
      data: {
        labels: ['1★','2★','3★','4★','5★'],
        datasets: [{ data: [ratingMap[1],ratingMap[2],ratingMap[3],ratingMap[4],ratingMap[5]], backgroundColor: ['#dc2626','#f97316','#f59e0b','#84cc16','#16a34a'], borderWidth: 0, borderRadius: 4 }]
      },
      options: Object.assign({}, defaults, {
        scales: {
          x: { grid: { display: false }, ticks: { font: { size: 11 }, color: '#9b9b92' } },
          y: { grid: { color: '#f1f0ec' }, ticks: { font: { size: 10 }, color: '#9b9b92' }, beginAtZero: true }
        }
      })
    });
  }

  // ── Category Distribution Donut ────────────────────────────────
  var catData = <?php echo json_encode(array_column($catDist, 'v')); ?>;
  var catKeys = <?php echo json_encode(array_column($catDist, 'k')); ?>;
  var catEl = document.getElementById('chartCat');
  if (catEl && catData.length) {
    new Chart(catEl, {
      type: 'doughnut',
      data: {
        labels: catKeys,
        datasets: [{ data: catData, backgroundColor: PALETTE, borderWidth: 0, hoverOffset: 4 }]
      },
      options: Object.assign({}, defaults, { cutout: '55%', plugins: { legend: { display: true, position: 'right', labels: { boxWidth: 9, font: { size: 10 }, color: '#6b6b64', padding: 6 } } } })
    });
  }
}());
</script>
<?php include '../includes/footer.php'; ?>
