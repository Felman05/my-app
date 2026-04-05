<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
requireRole('local');
$currentUser = getCurrentUser();
$pageTitle = 'Create Listing';
$additionalCSS = '<link rel="stylesheet" href="/doon-app/assets/css/dashboard.css"><link rel="stylesheet" href="/doon-app/assets/css/components.css">';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    if (!$title) { $error = 'Title is required.'; } else {
        try {
            $stmt = $pdo->prepare('INSERT INTO provider_listings (user_id, title, description, status, created_at) VALUES (?, ?, ?, "pending", NOW())');
            $stmt->execute([$currentUser['id'], $title, $description]);
            header('Location: /doon-app/local/listings.php');
            exit;
        } catch (Exception $e) { $error = 'Failed to create listing.'; }
    }
}
?>
<?php include '../includes/header.php'; ?>
<div class="dashboard-layout">
<?php include '../includes/sidebar.php'; ?>
<main class="main-content">
<h1>Create Listing</h1>
<?php if ($error): ?><div class="alert alert-error">⚠️ <?php echo escape($error); ?></div><?php endif; ?>
<div class="card" style="max-width: 600px;">
<form method="POST">
<div class="form-group">
<label>Title</label>
<input type="text" name="title" required placeholder="Your Business/Activity Name">
</div>
<div class="form-group">
<label>Description</label>
<textarea name="description" placeholder="Describe your business..."></textarea>
</div>
<button type="submit" class="btn btn-accent">Create Listing</button>
</form>
</div>
</main>
</div>
<link rel="stylesheet" href="/doon-app/assets/css/main.css">
<script src="/doon-app/assets/js/main.js"></script>
<?php include '../includes/footer.php'; ?>
