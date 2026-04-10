<?php
/**
 * Doon Landing Page
 */

require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$pageTitle = 'Home';
$additionalCSS = '<link rel="stylesheet" href="/doon-app/assets/css/components.css">';

$currentUser = getCurrentUser();

// Fetch landing page data
try {
    // Get stats
    $stats = [
        'destinations' => $pdo->query('SELECT COUNT(*) FROM destinations WHERE is_active = 1')->fetchColumn(),
        'provinces' => $pdo->query('SELECT COUNT(*) FROM provinces')->fetchColumn(),
        'reviews' => $pdo->query('SELECT COUNT(*) FROM reviews WHERE is_published = 1')->fetchColumn(),
        'tourists' => $pdo->query('SELECT COUNT(*) FROM users WHERE role = "tourist"')->fetchColumn()
    ];

    // Get featured destinations
    $stmt = $pdo->prepare(
        'SELECT d.*, ac.name as category_name, p.name as province_name
         FROM destinations d
         LEFT JOIN activity_categories ac ON d.category_id = ac.id
         LEFT JOIN provinces p ON d.province_id = p.id
         WHERE d.is_active = 1 AND d.is_featured = 1
         ORDER BY d.view_count DESC, d.avg_rating DESC
         LIMIT 8'
    );
    $stmt->execute();
    $featuredDestinations = $stmt->fetchAll();

    // Get provinces with destination counts
    $stmt = $pdo->query(
        'SELECT p.*, COUNT(d.id) as destination_count
         FROM provinces p
         LEFT JOIN destinations d ON p.id = d.province_id AND d.is_active = 1
         GROUP BY p.id
         ORDER BY p.name ASC'
    );
    $provinces = $stmt->fetchAll();

    // Get latest reviews
    $stmt = $pdo->prepare(
        'SELECT r.*, u.name as reviewer_name, d.name as destination_name
         FROM reviews r
         JOIN users u ON r.user_id = u.id
         JOIN destinations d ON r.destination_id = d.id
         WHERE r.is_published = 1
         ORDER BY r.created_at DESC
         LIMIT 6'
    );
    $stmt->execute();
    $latestReviews = $stmt->fetchAll();

} catch (PDOException $e) {
    $stats = ['destinations' => 0, 'provinces' => 0, 'reviews'=> 0, 'tourists' => 0];
    $featuredDestinations = [];
    $provinces = [];
    $latestReviews = [];
}
?>

<?php include 'includes/header.php'; ?>

<nav id="nav">
  <a class="nlogo" href="/doon-app/index.php">Doon<b>.</b></a>
  <div class="nctr">
    <a class="nlnk" href="#features">Features</a>
    <a class="nlnk" href="#provinces">Provinces</a>
    <a class="nlnk" href="#how-it-works">How It Works</a>
  </div>
  <div class="nrt">
    <?php if ($currentUser): ?>
      <a class="nbtn-o" href="/doon-app/<?php echo $currentUser['role']; ?>/dashboard.php">Dashboard</a>
      <a class="nbtn-s" href="/doon-app/api/auth.php?action=logout">Logout</a>
    <?php else: ?>
      <a class="nbtn-o" href="/doon-app/login.php">Sign in</a>
      <a class="nbtn-s" href="/doon-app/register.php">Get started</a>
    <?php endif; ?>
  </div>
</nav>

<section class="hero">
  <div class="hero-grain"></div>
  <div class="hero-tag"><div class="hero-tag-dot">UP</div>Smart Tourism Platform - CALABARZON</div>
  <h1 class="hero-h1">Discover<br><em>where to go</em><br>in CALABARZON</h1>
  <p class="hero-sub">AI-powered recommendations, interactive maps, and smart trip planning - all five provinces in one platform.</p>
  <div class="hero-btns">
    <a class="hbp" href="/doon-app/register.php">Start exploring -></a>
    <a class="hbs" href="/doon-app/login.php">Sign in</a>
  </div>
  <div class="hero-provs">
    <span class="ptag">Batangas</span><span class="ptag">Laguna</span><span class="ptag">Cavite</span><span class="ptag">Rizal</span><span class="ptag">Quezon</span>
  </div>
  <div class="scroll-hint"><span>Scroll</span><div class="scline"></div></div>
</section>

<section class="strip sr">
  <div class="strip-item"><div class="strip-num" data-c="<?php echo (int) $stats['destinations']; ?>">0</div><div class="strip-lbl">Active Destinations</div></div>
  <div class="strip-item"><div class="strip-num" data-c="<?php echo (int) $stats['provinces']; ?>">0</div><div class="strip-lbl">Provinces Covered</div></div>
  <div class="strip-item"><div class="strip-num" data-c="<?php echo (int) $stats['reviews']; ?>">0</div><div class="strip-lbl">Published Reviews</div></div>
  <div class="strip-item"><div class="strip-num" data-c="<?php echo (int) $stats['tourists']; ?>">0</div><div class="strip-lbl">Tourist Accounts</div></div>
</section>

<section id="features" class="section">
  <div class="section-head sr">
    <div class="section-kicker">Featured</div>
    <h2 class="section-title">Top Destination Picks</h2>
    <p class="section-sub">The most viewed and highest-rated spots right now.</p>
  </div>
  <div class="feat-grid">
    <?php foreach ($featuredDestinations as $destination): ?>
      <a class="feat-cell" href="/doon-app/tourist/destination.php?id=<?php echo $destination['id']; ?>">
        <h3><?php echo escape($destination['name']); ?></h3>
        <p><?php echo escape($destination['province_name']); ?> - <?php echo escape($destination['category_name']); ?></p>
        <p>Rating <?php echo number_format((float) ($destination['avg_rating'] ?? 0), 1); ?> - <?php echo (int) ($destination['total_reviews'] ?? 0); ?> reviews</p>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<section id="provinces" class="prov-sec">
  <div class="prov-inner">
    <div class="section-head sr">
      <div class="section-kicker" style="color: rgba(255,255,255,.45);">Provinces</div>
      <h2 class="section-title" style="color: #fff;">Explore CALABARZON</h2>
      <p class="section-sub" style="color: rgba(255,255,255,.55);">Jump into each province and browse local attractions.</p>
    </div>
    <div class="prov-grid">
      <?php foreach ($provinces as $province): ?>
        <a class="prov-card" href="/doon-app/tourist/discover.php?province_id=<?php echo $province['id']; ?>">
          <span class="prov-emo">PR</span>
          <div class="prov-name"><?php echo escape($province['name']); ?></div>
          <div class="prov-n"><?php echo (int) $province['destination_count']; ?> destinations</div>
          <div class="prov-bar"></div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section id="how-it-works" class="section">
  <div class="section-head sr">
    <div class="section-kicker">How It Works</div>
    <h2 class="section-title">Plan Smarter, Travel Better</h2>
  </div>
  <div class="steps-row">
    <article class="step-cell"><h3>1. Discover</h3><p>Browse destinations and region-specific experiences.</p></article>
    <article class="step-cell"><h3>2. Recommend</h3><p>Use preference-aware recommendations for better picks.</p></article>
    <article class="step-cell"><h3>3. Itinerary</h3><p>Build and manage complete trip plans in minutes.</p></article>
  </div>
</section>

<?php if (!$currentUser): ?>
<section class="section">
  <div class="section-head sr">
    <div class="section-kicker">Get Started</div>
    <h2 class="section-title">Join Doon</h2>
    <p class="section-sub">Create a free account to start planning your CALABARZON adventure.</p>
  </div>
  <div class="roles-grid" style="grid-template-columns: repeat(2,1fr); max-width: 600px;">
    <a class="role-card" href="/doon-app/register.php"><h3>Tourist</h3><p>Discover attractions, save favorites, and plan your perfect trip.</p></a>
    <a class="role-card" href="/doon-app/login.php"><h3>Sign In</h3><p>Already have an account? Log in to access your dashboard.</p></a>
  </div>
</section>
<?php endif; ?>

<section class="section">
  <div class="section-head sr">
    <div class="section-kicker">Social Proof</div>
    <h2 class="section-title">Latest Reviews</h2>
  </div>
  <div class="feat-grid">
    <?php foreach ($latestReviews as $review): ?>
      <article class="feat-cell">
        <h3><?php echo escape($review['title']); ?></h3>
        <p>By <?php echo escape($review['reviewer_name']); ?>  -  <?php echo formatDate($review['created_at']); ?></p>
        <p><?php echo escape(substr($review['body'], 0, 120)) . (strlen($review['body']) > 120 ? '...' : ''); ?></p>
        <p>Destination: <?php echo escape($review['destination_name']); ?></p>
      </article>
    <?php endforeach; ?>
  </div>
</section>

<footer class="footer">
  <div class="footer-top">
    <div><h3 class="section-title" style="font-size: 28px;">Doon</h3><p>Smart Tourism Platform for CALABARZON.</p></div>
    <div><h4>Explore</h4><p><a href="/doon-app/tourist/discover.php">Destinations</a></p><p><a href="/doon-app/tourist/map.php">Map</a></p></div>
    <div><h4>Account</h4><p><a href="/doon-app/login.php">Login</a></p><p><a href="/doon-app/register.php">Register</a></p></div>
  </div>
  <p>� <?php echo date('Y'); ?> Doon. All rights reserved.</p>
</footer>

<script src="/doon-app/assets/js/main.js"></script>

<?php include 'includes/footer.php'; ?>

