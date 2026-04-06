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
  <div class="d-topbar"><div><h1 class="d-page-title">Weather</h1><p class="d-page-sub">Live weather and travel advisories for CALABARZON provinces.</p></div></div>

  <div id="advisory-banner" style="display:none;margin-bottom:12px;"></div>

  <section class="dc">
    <div id="weatherStatus" class="dc-sub" style="margin-bottom:10px;">Loading weather data...</div>
    <div id="weatherGrid" class="wx-grid">
      <?php foreach ($provinces as $p): ?>
      <div class="wx-cell" data-province="<?php echo strtolower($p); ?>">
        <span class="wx-ico">-</span>
        <div class="wx-temp">--°C</div>
        <div class="wx-loc"><?php echo escape($p); ?></div>
        <div class="wx-cond">Loading...</div>
        <div class="wx-advisory" style="font-size:.72rem;margin-top:4px;font-weight:600;display:none;"></div>
      </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="dc" style="margin-top:16px;">
    <div class="dc-title" style="margin-bottom:8px;">Advisory Guide</div>
    <table class="d-table">
      <thead><tr><th>Condition</th><th>Advisory</th><th>Travel Tip</th></tr></thead>
      <tbody>
        <tr><td>Thunderstorm</td><td><span class="badge badge-danger">Avoid Travel</span></td><td>Postpone outdoor activities. Avoid elevated and coastal areas.</td></tr>
        <tr><td>Heavy Rain</td><td><span class="badge badge-danger">High Risk</span></td><td>Bring rain gear. Watch for flooding on low-lying roads.</td></tr>
        <tr><td>Light Rain / Drizzle</td><td><span class="badge badge-primary">Take Caution</span></td><td>Bring umbrella and waterproof bag. Trails may be slippery.</td></tr>
        <tr><td>Mist / Fog</td><td><span class="badge badge-primary">Take Caution</span></td><td>Reduced visibility on mountain roads. Drive carefully.</td></tr>
        <tr><td>Cloudy</td><td><span class="badge badge-success">Good</span></td><td>Comfortable for outdoor activities. Light jacket recommended.</td></tr>
        <tr><td>Clear / Sunny</td><td><span class="badge badge-success">Excellent</span></td><td>Great day for beaches, hikes, and outdoor tours. Bring sunscreen.</td></tr>
      </tbody>
    </table>
  </section>
</main>
</div>

<script>
(function () {
  const statusEl  = document.getElementById('weatherStatus');
  const bannerEl  = document.getElementById('advisory-banner');

  function iconFromCondition(c) {
    c = String(c || '').toLowerCase();
    if (c.includes('thunder')) return '⚡';
    if (c.includes('rain') || c.includes('drizzle')) return '🌧';
    if (c.includes('cloud')) return '☁';
    if (c.includes('mist') || c.includes('fog') || c.includes('haze')) return '🌫';
    if (c.includes('clear') || c.includes('sunny')) return '☀';
    return '🌤';
  }

  function getAdvisory(weather) {
    const c    = String(weather.condition || '').toLowerCase();
    const wind = weather.wind || 0;
    const temp = weather.temp !== null ? weather.temp : 30;

    if (c.includes('thunder') || c.includes('storm')) {
      return { level: 'danger', label: 'Avoid Travel', msg: 'Thunderstorm detected. Postpone outdoor activities and avoid elevated or coastal areas.' };
    }
    if (c.includes('heavy rain') || (c.includes('rain') && wind > 10)) {
      return { level: 'danger', label: 'High Risk', msg: 'Heavy rainfall or strong winds. Watch for flooding on low-lying roads. Bring rain gear.' };
    }
    if (c.includes('rain') || c.includes('drizzle')) {
      return { level: 'warn', label: 'Take Caution', msg: 'Light rain expected. Bring umbrella and waterproof bag. Trails may be slippery.' };
    }
    if (c.includes('mist') || c.includes('fog') || c.includes('haze')) {
      return { level: 'warn', label: 'Take Caution', msg: 'Reduced visibility. Drive carefully on mountain roads.' };
    }
    if (temp >= 36) {
      return { level: 'warn', label: 'Heat Advisory', msg: 'Very high temperature. Stay hydrated, wear sunscreen, and avoid prolonged sun exposure.' };
    }
    if (c.includes('cloud')) {
      return { level: 'ok', label: 'Good', msg: 'Comfortable weather for most activities. Light jacket recommended.' };
    }
    return { level: 'ok', label: 'Excellent', msg: 'Great conditions for outdoor activities. Enjoy your trip!' };
  }

  function levelClass(level) {
    return { danger: 'err', warn: 'warn', ok: 'ok' }[level] || 'info';
  }

  fetch('/doon-app/api/weather.php?action=list', { credentials: 'same-origin' })
    .then(r => r.json())
    .then(res => {
      if (!res.success) throw new Error(res.error || 'Failed to load weather data.');

      var dangerProvinces = [];

      (res.data || []).forEach(item => {
        const weather = item.weather;
        const cell = document.querySelector('.wx-cell[data-province="' + item.key + '"]');
        if (!cell) return;

        if (!weather) {
          cell.querySelector('.wx-cond').textContent = 'Unavailable';
          return;
        }

        const advisory = getAdvisory(weather);
        cell.querySelector('.wx-ico').textContent  = iconFromCondition(weather.condition);
        cell.querySelector('.wx-temp').textContent = (weather.temp !== null ? weather.temp + '°C' : '--');
        cell.querySelector('.wx-cond').textContent = weather.condition;

        const advEl = cell.querySelector('.wx-advisory');
        advEl.textContent = advisory.label;
        advEl.style.display = 'block';
        advEl.style.color = advisory.level === 'danger' ? 'var(--danger, #e53)' :
                            advisory.level === 'warn'   ? 'var(--warn, #f90)'   : 'var(--ok, #3a3)';

        if (advisory.level === 'danger') dangerProvinces.push({ name: weather.province, msg: advisory.msg });
      });

      statusEl.textContent = 'Live weather updated just now.';

      if (dangerProvinces.length) {
        bannerEl.style.display = 'block';
        bannerEl.innerHTML = dangerProvinces.map(p =>
          '<div class="alert err" style="margin-bottom:6px;"><strong>' + p.name + ':</strong> ' + p.msg + '</div>'
        ).join('');
      }
    })
    .catch(err => {
      statusEl.textContent = 'Weather service unavailable: ' + err.message;
      document.querySelectorAll('.wx-cond').forEach(el => { el.textContent = 'Unavailable'; });
    });
})();
</script>
<script src="/doon-app/assets/js/main.js"></script>
<?php include '../includes/footer.php'; ?>
