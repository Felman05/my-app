<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/env.php';
require_once '../includes/provinces.php';
requireRole('admin');
$pageTitle = 'Provider Map';
$additionalCSS = '<link rel="stylesheet" href="/doon-app/assets/css/dashboard.css"><link rel="stylesheet" href="/doon-app/assets/css/components.css">';

$gmKey = env('GOOGLE_MAPS_API_KEY', '');

// Load all active providers with their province/municipality
try {
    $providers = $pdo->query(
        'SELECT u.id, u.name, u.email, u.is_active,
                lpp.business_name, lpp.business_type, lpp.province, lpp.municipality,
                COUNT(pl.id) as listing_count
         FROM local_provider_profiles lpp
         JOIN users u ON lpp.user_id = u.id
         LEFT JOIN provider_listings pl ON pl.provider_id = lpp.id
         GROUP BY lpp.id
         ORDER BY lpp.province, u.name'
    )->fetchAll();
} catch (Exception $e) {
    $providers = [];
}

// Group by normalised province name
$byProvince = [];
foreach ($providers as $p) {
    $key = strtolower(trim($p['province'] ?? ''));
    $byProvince[$key][] = $p;
}

// Summary counts per province
$provinceSummary = [];
foreach ($PROVINCE_LOCATIONS as $key => $loc) {
    $provinceSummary[$key] = [
        'name'  => $loc['name'],
        'lat'   => $loc['lat'],
        'lon'   => $loc['lon'],
        'count' => count($byProvince[$key] ?? []),
        'providers' => $byProvince[$key] ?? [],
    ];
}
?>
<?php include '../includes/header.php'; ?>
<div class="d-wrap">
<?php include '../includes/sidebar.php'; ?>
<main class="d-main">
  <div class="d-topbar">
    <div><h1 class="d-page-title">Provider Map</h1><p class="d-page-sub">Providers by province across CALABARZON.</p></div>
  </div>

  <!-- KPI row -->
  <section class="kpi-row c5" style="margin-bottom:16px;">
    <?php foreach ($provinceSummary as $key => $ps): ?>
    <article class="kpi" style="cursor:pointer;" onclick="focusProvince('<?php echo $key; ?>')">
      <div class="kpi-lbl"><?php echo escape($ps['name']); ?></div>
      <div class="kpi-val"><?php echo $ps['count']; ?></div>
      <div class="kpi-sub">provider<?php echo $ps['count'] != 1 ? 's' : ''; ?></div>
    </article>
    <?php endforeach; ?>
  </section>

  <div class="g2" style="gap:16px;">
    <!-- Map -->
    <section class="dc" style="padding:0;overflow:hidden;min-height:480px;">
      <div id="provider-map" style="width:100%;height:480px;"></div>
    </section>

    <!-- Provider list panel -->
    <section class="dc" style="overflow-y:auto;max-height:480px;">
      <div class="dc-title" id="panel-title" style="margin-bottom:10px;">All Providers</div>
      <div id="provider-list">
        <?php foreach ($providers as $p): ?>
        <div class="dest-row" style="flex-direction:column;align-items:flex-start;gap:2px;padding:10px 0;">
          <div class="dest-name"><?php echo escape($p['business_name'] ?: $p['name']); ?></div>
          <div class="dest-meta"><?php echo escape($p['province']); ?><?php echo $p['municipality'] ? ', ' . escape($p['municipality']) : ''; ?> &mdash; <?php echo ucfirst(str_replace('_', ' ', $p['business_type'] ?? 'other')); ?></div>
          <div class="dest-meta"><?php echo (int) $p['listing_count']; ?> listing<?php echo $p['listing_count'] != 1 ? 's' : ''; ?> &bull; <?php echo $p['is_active'] ? '<span style="color:#16a34a;">Active</span>' : '<span style="color:#dc2626;">Inactive</span>'; ?></div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($providers)): ?>
        <div class="dest-row" style="opacity:.5;">No providers registered yet.</div>
        <?php endif; ?>
      </div>
    </section>
  </div>
</main>
</div>

<script>
var provinceSummary = <?php echo json_encode($provinceSummary); ?>;
var allProviders    = <?php echo json_encode($providers); ?>;
var mapMarkers      = {};
var activeMarker    = null;
var map, infoWindow;

function initMap() {
  map = new google.maps.Map(document.getElementById('provider-map'), {
    center: { lat: 14.0, lng: 121.2 },
    zoom: 9,
    mapTypeControl: false,
    streetViewControl: false,
    styles: [{ featureType: 'poi', elementType: 'labels', stylers: [{ visibility: 'off' }] }]
  });

  infoWindow = new google.maps.InfoWindow();

  Object.keys(provinceSummary).forEach(function (key) {
    var ps  = provinceSummary[key];
    var pos = { lat: parseFloat(ps.lat), lng: parseFloat(ps.lon) };

    var marker = new google.maps.Marker({
      position : pos,
      map      : map,
      title    : ps.name,
      label    : {
        text      : String(ps.count),
        color     : '#fff',
        fontWeight: 'bold',
        fontSize  : '13px'
      },
      icon: {
        path        : google.maps.SymbolPath.CIRCLE,
        scale       : 22,
        fillColor   : ps.count > 0 ? '#111827' : '#9ca3af',
        fillOpacity : 1,
        strokeColor : '#fff',
        strokeWeight: 2
      }
    });

    mapMarkers[key] = marker;

    marker.addListener('click', function () {
      showProvince(key);
      infoWindow.setContent(
        '<div style="font-weight:700;font-size:.95rem;margin-bottom:4px;">' + ps.name + '</div>' +
        '<div style="font-size:.82rem;color:#6b7280;">' + ps.count + ' provider' + (ps.count !== 1 ? 's' : '') + '</div>'
      );
      infoWindow.open(map, marker);
    });
  });
}

function focusProvince(key) {
  var ps  = provinceSummary[key];
  if (!ps) return;
  map.panTo({ lat: parseFloat(ps.lat), lng: parseFloat(ps.lon) });
  map.setZoom(11);
  google.maps.event.trigger(mapMarkers[key], 'click');
}

function showProvince(key) {
  var ps        = provinceSummary[key];
  var list      = ps.providers;
  var titleEl   = document.getElementById('panel-title');
  var listEl    = document.getElementById('provider-list');

  titleEl.textContent = ps.name + ' (' + ps.count + ')';

  if (!list || list.length === 0) {
    listEl.innerHTML = '<div class="dest-row" style="opacity:.5;">No providers in ' + ps.name + '.</div>';
    return;
  }

  listEl.innerHTML = list.map(function (p) {
    return '<div class="dest-row" style="flex-direction:column;align-items:flex-start;gap:2px;padding:10px 0;">'
      + '<div class="dest-name">' + (p.business_name || p.name) + '</div>'
      + '<div class="dest-meta">' + (p.municipality ? p.municipality + ' &mdash; ' : '') + (p.business_type || 'Other').replace('_', ' ') + '</div>'
      + '<div class="dest-meta">' + p.listing_count + ' listing' + (p.listing_count != 1 ? 's' : '')
      + ' &bull; <span style="color:' + (p.is_active ? '#16a34a' : '#dc2626') + ';">' + (p.is_active ? 'Active' : 'Inactive') + '</span></div>'
      + '</div>';
  }).join('');
}
</script>
<?php if ($gmKey): ?>
<script src="https://maps.googleapis.com/maps/api/js?key=<?php echo urlencode($gmKey); ?>&callback=initMap" async defer></script>
<?php else: ?>
<div style="padding:20px;text-align:center;color:#6b7280;">Google Maps API key not configured. Add GOOGLE_MAPS_API_KEY to your .env file.</div>
<script>function initMap(){}</script>
<?php endif; ?>
<script src="/doon-app/assets/js/main.js"></script>
<?php include '../includes/footer.php'; ?>
