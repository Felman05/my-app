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
} catch (Exception $e) {
    $userCount = $destCount = $provCount = 0;
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
      <div class="dc-head"><div><div class="dc-title">Traffic Snapshot</div><div class="dc-sub">Mock analytics bars</div></div></div>
      <div class="bar-list">
        <div class="bar-row"><div class="bar-lbl">Web</div><div class="bar-bg"><div class="bar-f ac" style="width:84%"></div></div><div class="bar-val">84</div></div>
        <div class="bar-row"><div class="bar-lbl">App</div><div class="bar-bg"><div class="bar-f" style="width:47%"></div></div><div class="bar-val">47</div></div>
        <div class="bar-row"><div class="bar-lbl">API</div><div class="bar-bg"><div class="bar-f" style="width:63%"></div></div><div class="bar-val">63</div></div>
      </div>
      <div class="divider"></div>
      <div class="alert warn">Review seasonal traffic spikes before campaign launch.</div>
      <div class="alert info">Daily report generation is healthy.</div>
    </section>

    <section class="dc">
      <div class="dc-head"><div><div class="dc-title">Approval Queue</div><div class="dc-sub">Pending provider approvals</div></div></div>
      <div class="appr-item"><div><div class="appr-name">North Ridge Tours</div><div class="appr-meta">Tagaytay  -  submitted today</div></div><div class="appr-btns"><button class="btn-ok">Approve</button><button class="btn-no">Reject</button></div></div>
      <div class="appr-item"><div><div class="appr-name">Calamba Nature Walks</div><div class="appr-meta">Laguna  -  submitted yesterday</div></div><div class="appr-btns"><button class="btn-ok">Approve</button><button class="btn-no">Reject</button></div></div>
      <div class="divider"></div>
      <table class="d-table">
        <thead><tr><th>LGU</th><th>Destinations</th><th>Status</th></tr></thead>
        <tbody>
          <tr><td>Batangas</td><td>124</td><td><span class="pill p-g">Healthy</span></td></tr>
          <tr><td>Laguna</td><td>98</td><td><span class="pill p-y">Monitor</span></td></tr>
          <tr><td>Cavite</td><td>84</td><td><span class="pill p-g">Healthy</span></td></tr>
        </tbody>
      </table>
    </section>
  </div>
</main>
</div>
<script src="/doon-app/assets/js/main.js"></script>
<?php include '../includes/footer.php'; ?>

