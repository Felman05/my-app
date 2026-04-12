<?php
/**
 * Registration Page
 */

require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$pageTitle = 'Register';
$additionalCSS = '<link rel="stylesheet" href="/doon-app/assets/css/auth.css">';

// If already logged in, redirect to dashboard
if (isAuthenticated()) {
    $user = getCurrentUser();
    header('Location: /doon-app/' . $user['role'] . '/dashboard.php');
    exit;
}

$errors = [];
$formData = [
    'name' => '',
    'email' => '',
    'role' => 'tourist'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? 'tourist';

    // Validate
    if (empty($name)) $errors[] = 'Name is required.';
    if (empty($email)) $errors[] = 'Email is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format.';
    if (empty($password)) $errors[] = 'Password is required.';
    if ($password !== $confirmPassword) $errors[] = 'Passwords do not match.';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    $role = 'tourist'; // Public registration is tourist-only; providers are created by admin

    // Check if email exists
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = 'Email is already registered.';
            }
        } catch (PDOException $e) {
            $errors[] = 'An error occurred. Please try again.';
        }
    }

    // Create user if no errors
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Insert user
            $hashedPassword = hashPassword($password);
            $stmt = $pdo->prepare(
                'INSERT INTO users (name, email, password, role, is_active, data_privacy_consent, created_at)
                 VALUES (?, ?, ?, ?, 1, 1, NOW())'
            );
            $stmt->execute([$name, $email, $hashedPassword, $role]);
            $userId = $pdo->lastInsertId();

            // If tourist, create tourist profile
            if ($role === 'tourist') {
                $stmt = $pdo->prepare(
                    'INSERT INTO tourist_profiles (user_id, generational_profile, preferred_budget, travel_style, location_tracking_consent, created_at)
                     VALUES (?, ?, ?, ?, 0, NOW())'
                );
                $stmt->execute([$userId, 'millennial', 'mid_range', 'solo']);
            }

            $pdo->commit();

            // Set session and redirect
            $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            setUserSession($user);

            header("Location: /doon-app/{$role}/dashboard.php");
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = 'Registration failed. Please try again.';
        }
    }

    $formData = [
        'name'  => $name,
        'email' => $email,
        'role'  => 'tourist',
    ];
}
?>

<?php include 'includes/header.php'; ?>

<div class="auth-wrap">
  <aside class="auth-left">
    <div class="auth-left-inner">
      <div class="auth-logo">Doon<b>.</b></div>
      <h1 class="auth-tagline">Build your <em>smart travel profile</em></h1>
      <p class="auth-sub-txt">Create your account and start discovering curated destinations across CALABARZON.</p>
      <div class="auth-provs">
        <span class="auth-prov">Batangas</span><span class="auth-prov">Laguna</span><span class="auth-prov">Cavite</span><span class="auth-prov">Rizal</span><span class="auth-prov">Quezon</span>
      </div>
    </div>
  </aside>

  <section class="auth-right">
    <div class="auth-form-box">
      <a class="auth-back" href="/doon-app/index.php"><- Back to home</a>
      <h2 class="auth-form-title">Create account</h2>
      <p class="auth-form-sub">Already have an account? <a href="/doon-app/login.php">Sign in</a></p>

      <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
          <?php foreach ($errors as $error): ?>
            <div><?php echo escape($error); ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form method="POST">
        <input type="hidden" name="role" value="tourist">
        <div class="form-group">
          <label class="form-label" for="name">Full Name</label>
          <input class="form-input" type="text" id="name" name="name" value="<?php echo escape($formData['name']); ?>" required placeholder="John Doe">
        </div>
        <div class="form-group">
          <label class="form-label" for="email">Email</label>
          <input class="form-input" type="email" id="email" name="email" value="<?php echo escape($formData['email']); ?>" required placeholder="you@example.com">
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label" for="password">Password</label>
            <input class="form-input" type="password" id="password" name="password" required placeholder="Min. 8 chars">
          </div>
          <div class="form-group">
            <label class="form-label" for="confirm_password">Confirm</label>
            <input class="form-input" type="password" id="confirm_password" name="confirm_password" required placeholder="Repeat password">
          </div>
        </div>

        <div class="consent-box">
          <input type="checkbox" checked disabled>
          <div class="consent-txt">By creating an account, you consent to platform processing aligned with our <a href="#">privacy policy</a>.</div>
        </div>

        <button class="btn-full btn-primary" type="submit">Create Account</button>
      </form>

      <p class="auth-footer-txt">Need help? Contact support through the Doon help desk.</p>
    </div>
  </section>
</div>

<script src="/doon-app/assets/js/main.js"></script>

<?php include 'includes/footer.php'; ?>
