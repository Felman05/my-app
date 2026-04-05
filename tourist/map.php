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
        'SELECT d.id, d.name, d.latitude, d.longitude, p.name AS province_name
         FROM destinations d
         LEFT JOIN provinces p ON d.province_id = p.id
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
      <p class="d-page-sub">Browse destinations geographically across CALABARZON.</p>
    </div>
  </div>

  <section class="dc mb20">
    <div id="touristMap" style="height:420px;border:1px solid var(--bd);border-radius:var(--r2);"></div>
  </section>

  <section class="dc">
    <div class="dc-title mb16">Mapped destinations</div>
    <div class="dest-list">
      <?php foreach ($destinations as $d): ?>
      <a class="dest-row" href="/doon-app/tourist/destination.php?id=<?php echo (int) $d['id']; ?>">
        <div class="dest-ico">M</div>
        <div>
          <div class="dest-name"><?php echo escape($d['name']); ?></div>
          <div class="dest-meta"><?php echo escape($d['province_name'] ?? 'Unknown'); ?> � <?php echo number_format((float) $d['latitude'], 4); ?>, <?php echo number_format((float) $d['longitude'], 4); ?></div>
        </div>
      </a>
      <?php endforeach; ?>
      <?php if (empty($destinations)): ?>
      <div class="dest-row">No destinations with latitude/longitude were found.</div>
      <?php endif; ?>
    </div>
  </section>
</main>
</div>

<script>
(function () {
  const mapData = <?php echo json_encode(array_map(function ($row) {
      return [
          'id' => (int) $row['id'],
          'name' => $row['name'],
          'lat' => (float) $row['latitude'],
          'lng' => (float) $row['longitude'],
          'province' => $row['province_name'] ?? ''
      ];
  }, $destinations)); ?>;

  function initMap() {
    const mapEl = document.getElementById('touristMap');
    if (!mapEl || typeof google === 'undefined' || !google.maps) {
      return;
    }

    const center = { lat: 14.10, lng: 121.25 };
    const map = new google.maps.Map(mapEl, {
      center: center,
      zoom: 8,
      mapTypeControl: false,
      streetViewControl: false
    });

    const infoWindow = new google.maps.InfoWindow();

    mapData.forEach(function (dest) {
      const marker = new google.maps.Marker({
        position: { lat: dest.lat, lng: dest.lng },
        map: map,
        title: dest.name
      });

      const html =
        '<div style="min-width:190px">' +
        '<strong>' + dest.name + '</strong><br>' +
        '<span style="font-size:12px;color:#666">' + (dest.province || '') + '</span><br>' +
        '<a href="/doon-app/tourist/destination.php?id=' + dest.id + '">View destination</a>' +
        '</div>';

      marker.addListener('click', function () {
        infoWindow.setContent(html);
        infoWindow.open(map, marker);
      });
    });
  }

  function waitForGoogle() {
    if (typeof google !== 'undefined' && google.maps) {
      initMap();
      return;
    }
    setTimeout(waitForGoogle, 150);
  }

  document.addEventListener('DOMContentLoaded', waitForGoogle);
})();
</script>
<script src="/doon-app/assets/js/main.js"></script>
<?php include '../includes/footer.php'; ?>
