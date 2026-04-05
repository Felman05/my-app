<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireRole('tourist');
$currentUser = getCurrentUser();
$pageTitle = 'Weather';
$additionalCSS = '<link rel="stylesheet" href="/doon-app/assets/css/dashboard.css"><link rel="stylesheet" href="/doon-app/assets/css/components.css">';
$provinces = ['Batangas', 'Laguna', 'Cavite', 'Rizal', 'Quezon'];
?>
<?php include '../includes/header.php'; ?>
<div class="d-wrap">
<?php include '../includes/sidebar.php'; ?>
<main class="d-main">
  <div class="d-topbar"><div><h1 class="d-page-title">Weather</h1><p class="d-page-sub">Live weather from OpenWeather for CALABARZON provinces.</p></div></div>
  <section class="dc">
    <div id="weatherStatus" class="dc-sub" style="margin-bottom:10px;">Loading weather data...</div>
    <div id="weatherGrid" class="wx-grid">
      <?php foreach ($provinces as $p): ?>
      <div class="wx-cell" data-province="<?php echo strtolower($p); ?>">
        <span class="wx-ico">-</span>
        <div class="wx-temp">--C</div>
        <div class="wx-loc"><?php echo escape($p); ?></div>
        <div class="wx-cond">Loading...</div>
      </div>
      <?php endforeach; ?>
    </div>
  </section>
</main>
</div>

<script>
(function () {
  const statusEl = document.getElementById('weatherStatus');
  const cells = Array.from(document.querySelectorAll('.wx-cell'));

  function iconFromCondition(condition) {
    const c = String(condition || '').toLowerCase();
    if (c.includes('rain') || c.includes('drizzle') || c.includes('thunder')) return 'R';
    if (c.includes('cloud')) return 'C';
    if (c.includes('mist') || c.includes('fog') || c.includes('haze')) return 'M';
    if (c.includes('clear')) return 'S';
    return 'W';
  }

  function render(data) {
    data.forEach(item => {
      const weather = item.weather;
      const cell = document.querySelector('.wx-cell[data-province="' + item.key + '"]');
      if (!cell || !weather) return;

      const iconEl = cell.querySelector('.wx-ico');
      const tempEl = cell.querySelector('.wx-temp');
      const condEl = cell.querySelector('.wx-cond');

      iconEl.textContent = iconFromCondition(weather.condition);
      tempEl.textContent = String(weather.temp) + 'C';
      condEl.textContent = weather.condition;
    });
  }

  fetch('/doon-app/api/weather.php?action=list', { credentials: 'same-origin' })
    .then(r => r.json())
    .then(res => {
      if (!res.success) {
        throw new Error(res.error || 'Failed to load weather data.');
      }
      render(res.data || []);
      statusEl.textContent = 'Live weather updated.';
    })
    .catch(err => {
      statusEl.textContent = 'Weather service unavailable: ' + err.message;
      cells.forEach(cell => {
        cell.querySelector('.wx-cond').textContent = 'Unavailable';
      });
    });
})();
</script>
<script src="/doon-app/assets/js/main.js"></script>
<?php include '../includes/footer.php'; ?>
