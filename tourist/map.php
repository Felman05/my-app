<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireRole('tourist');
$currentUser = getCurrentUser();
$pageTitle = 'Map';
$loadGoogleMapsApi = true;
$additionalCSS = '<link rel="stylesheet" href="/doon-app/assets/css/dashboard.css"><link rel="stylesheet" href="/doon-app/assets/css/components.css">';

$destinations = [];
try {
    $stmt = $pdo->query(
        'SELECT d.id, d.name, d.latitude, d.longitude, d.short_description,
                p.name AS province_name, ac.name AS category_name
         FROM destinations d
         LEFT JOIN provinces p ON d.province_id = p.id
         LEFT JOIN activity_categories ac ON d.category_id = ac.id
         WHERE d.is_active = 1
           AND d.latitude IS NOT NULL
           AND d.longitude IS NOT NULL
         LIMIT 300'
    );
    $destinations = $stmt->fetchAll();
} catch (Exception $e) {
    $destinations = [];
}
?>
<?php include '../includes/header.php'; ?>
<div class="d-wrap">
<?php include '../includes/sidebar.php'; ?>
<main class="d-main">
  <div class="d-topbar">
    <div>
      <h1 class="d-page-title">Map Explorer</h1>
      <p class="d-page-sub">Browse destinations across CALABARZON. Plan routes between stops.</p>
    </div>
  </div>

  <!-- Location consent modal -->
  <div id="consent-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:var(--bg,#fff);border-radius:var(--r2,8px);padding:24px;max-width:420px;width:90%;box-shadow:0 8px 32px rgba(0,0,0,.2);">
      <div style="font-weight:700;font-size:1.05rem;margin-bottom:8px;">Share Your Location?</div>
      <p style="font-size:.88rem;opacity:.8;margin-bottom:16px;">Doon would like to show your current position on the map to help you navigate to nearby destinations. Your location is only used within this page and is not stored or shared, in accordance with the Data Privacy Act of 2012 (R.A. 10173).</p>
      <div style="display:flex;gap:8px;">
        <button class="rf-go" id="consent-yes" style="flex:1;">Allow</button>
        <button class="s-btn dark" id="consent-no" style="flex:1;">No Thanks</button>
      </div>
    </div>
  </div>

  <section class="dc mb20">
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px;align-items:center;">
      <button class="s-btn" id="btn-locate">Show My Location</button>
      <select class="rf-ctrl" id="travel-mode" style="width:auto;padding:6px 10px;font-size:.85rem;" title="Travel mode for route planning">
        <option value="DRIVING">🚗 Driving</option>
        <option value="WALKING">🚶 Walking</option>
        <option value="BICYCLING">🚲 Cycling</option>
      </select>
      <button class="s-btn" id="btn-route" disabled title="Select at least 2 destinations from the list below to plan a route">Plan Route</button>
      <button class="s-btn" id="btn-clear-route" style="display:none;">Clear Route</button>
      <span id="route-info" style="font-size:.82rem;opacity:.6;"></span>
    </div>
    <div style="font-size:.78rem;opacity:.55;margin-bottom:6px;">💡 Choose Walking or Cycling for an eco-friendly route.</div>
    <div id="touristMap" style="height:480px;border:1px solid var(--bd);border-radius:var(--r2);"></div>
  </section>

  <section class="dc">
    <div class="dc-title mb16">Select destinations for route planning</div>
    <div class="dest-list" id="dest-checklist">
      <?php foreach ($destinations as $d): ?>
      <label class="dest-row" style="cursor:pointer;">
        <input type="checkbox" class="dest-route-cb" value="<?php echo (int) $d['id']; ?>"
               data-lat="<?php echo (float) $d['latitude']; ?>"
               data-lng="<?php echo (float) $d['longitude']; ?>"
               data-name="<?php echo escape($d['name']); ?>"
               style="margin-right:8px;">
        <div>
          <div class="dest-name"><?php echo escape($d['name']); ?></div>
          <div class="dest-meta"><?php echo escape($d['province_name'] ?? ''); ?> &mdash; <?php echo escape($d['category_name'] ?? ''); ?></div>
        </div>
        <a href="/doon-app/tourist/destination.php?id=<?php echo (int) $d['id']; ?>" class="s-btn" style="margin-left:auto;" onclick="event.stopPropagation();">View</a>
      </label>
      <?php endforeach; ?>
      <?php if (empty($destinations)): ?>
      <div class="dest-row" style="opacity:.5;">No destinations with map coordinates found.</div>
      <?php endif; ?>
    </div>
  </section>
</main>
</div>

<script>
(function () {
  const mapData = <?php echo json_encode(array_map(function ($row) {
      return [
          'id'       => (int) $row['id'],
          'name'     => $row['name'],
          'lat'      => (float) $row['latitude'],
          'lng'      => (float) $row['longitude'],
          'province' => $row['province_name'] ?? '',
          'category' => $row['category_name'] ?? '',
          'desc'     => $row['short_description'] ?? '',
      ];
  }, $destinations)); ?>;

  let map, infoWindow, directionsService, directionsRenderer, userMarker;
  const consentModal = document.getElementById('consent-modal');
  const btnLocate    = document.getElementById('btn-locate');
  const btnRoute     = document.getElementById('btn-route');
  const btnClear     = document.getElementById('btn-clear-route');
  const routeInfo    = document.getElementById('route-info');

  // ── Location consent ─────────────────────────────────────────────────────
  function requestLocation() {
    if (!navigator.geolocation) {
      alert('Geolocation is not supported by your browser.');
      return;
    }
    navigator.geolocation.getCurrentPosition(
      function (pos) {
        const latlng = { lat: pos.coords.latitude, lng: pos.coords.longitude };
        if (userMarker) userMarker.setMap(null);
        userMarker = new google.maps.Marker({
          position: latlng, map: map,
          title: 'You are here',
          icon: { path: google.maps.SymbolPath.CIRCLE, scale: 8,
                  fillColor: '#4285F4', fillOpacity: 1,
                  strokeColor: '#fff', strokeWeight: 2 }
        });
        map.panTo(latlng);
      },
      function () { alert('Unable to retrieve your location.'); }
    );
  }

  btnLocate.addEventListener('click', function () {
    consentModal.style.display = 'flex';
  });
  document.getElementById('consent-yes').addEventListener('click', function () {
    consentModal.style.display = 'none';
    requestLocation();
  });
  document.getElementById('consent-no').addEventListener('click', function () {
    consentModal.style.display = 'none';
  });

  // ── Route planning ────────────────────────────────────────────────────────
  document.querySelectorAll('.dest-route-cb').forEach(function (cb) {
    cb.addEventListener('change', updateRouteBtn);
  });

  function getChecked() {
    return Array.from(document.querySelectorAll('.dest-route-cb:checked'));
  }

  function updateRouteBtn() {
    const checked = getChecked();
    btnRoute.disabled = checked.length < 2;
    routeInfo.textContent = checked.length >= 2
      ? checked.length + ' stop' + (checked.length > 1 ? 's' : '') + ' selected'
      : '';
  }

  btnRoute.addEventListener('click', function () {
    const checked = getChecked();
    if (checked.length < 2) return;

    const waypoints = checked.map(cb => ({
      lat: parseFloat(cb.dataset.lat),
      lng: parseFloat(cb.dataset.lng),
      name: cb.dataset.name
    }));

    const origin      = { lat: waypoints[0].lat, lng: waypoints[0].lng };
    const destination = { lat: waypoints[waypoints.length - 1].lat, lng: waypoints[waypoints.length - 1].lng };
    const middle      = waypoints.slice(1, -1).map(w => ({
      location: new google.maps.LatLng(w.lat, w.lng),
      stopover: true
    }));

    const modeSelect = document.getElementById('travel-mode');
    const travelMode = google.maps.TravelMode[modeSelect ? modeSelect.value : 'DRIVING'];
    const modeLabel  = modeSelect ? modeSelect.options[modeSelect.selectedIndex].text : 'Driving';

    directionsService.route({
      origin, destination,
      waypoints: middle,
      travelMode,
      optimizeWaypoints: true
    }, function (result, status) {
      if (status === 'OK') {
        directionsRenderer.setDirections(result);
        btnClear.style.display = 'inline-block';
        const legs = result.routes[0].legs;
        const totalDist = legs.reduce((s, l) => s + l.distance.value, 0);
        const totalTime = legs.reduce((s, l) => s + l.duration.value, 0);
        routeInfo.textContent = modeLabel + ' route: ' + (totalDist / 1000).toFixed(1) + ' km — ~' + Math.round(totalTime / 60) + ' min';
      } else if (status === 'ZERO_RESULTS') {
        routeInfo.textContent = 'No ' + modeLabel.toLowerCase() + ' route found between those stops. Try Driving.';
      } else {
        alert('Could not calculate route: ' + status);
      }
    });
  });

  btnClear.addEventListener('click', function () {
    directionsRenderer.setDirections({ routes: [] });
    btnClear.style.display = 'none';
    routeInfo.textContent = '';
  });

  // ── Map init ──────────────────────────────────────────────────────────────
  function initMap() {
    const mapEl = document.getElementById('touristMap');
    if (!mapEl || typeof google === 'undefined' || !google.maps) return;

    map = new google.maps.Map(mapEl, {
      center: { lat: 14.10, lng: 121.25 },
      zoom: 8,
      mapTypeControl: true,
      streetViewControl: false,
      fullscreenControl: true
    });

    infoWindow         = new google.maps.InfoWindow();
    directionsService  = new google.maps.DirectionsService();
    directionsRenderer = new google.maps.DirectionsRenderer({
      map,
      suppressMarkers: false,
      polylineOptions: { strokeColor: '#2563eb', strokeWeight: 4 }
    });

    mapData.forEach(function (dest) {
      const marker = new google.maps.Marker({
        position: { lat: dest.lat, lng: dest.lng },
        map,
        title: dest.name
      });

      const html =
        '<div style="min-width:210px;font-size:13px;">' +
        '<strong style="font-size:14px;">' + dest.name + '</strong><br>' +
        '<span style="color:#666;">' + dest.province + (dest.category ? ' &mdash; ' + dest.category : '') + '</span>' +
        (dest.desc ? '<p style="margin:6px 0 6px;font-size:12px;">' + dest.desc.substring(0, 100) + (dest.desc.length > 100 ? '…' : '') + '</p>' : '') +
        '<a href="/doon-app/tourist/destination.php?id=' + dest.id + '" style="color:#2563eb;">View destination &rarr;</a>' +
        '</div>';

      marker.addListener('click', function () {
        infoWindow.setContent(html);
        infoWindow.open(map, marker);
      });
    });
  }

  function waitForGoogle() {
    if (typeof google !== 'undefined' && google.maps) { initMap(); return; }
    setTimeout(waitForGoogle, 150);
  }
  document.addEventListener('DOMContentLoaded', waitForGoogle);
})();
</script>
<script src="/doon-app/assets/js/main.js"></script>
<?php include '../includes/footer.php'; ?>
