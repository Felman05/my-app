<?php
/**
 * Login Page
 */

require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$pageTitle = 'Login';
$additionalCSS = '<link rel="stylesheet" href="/doon-app/assets/css/auth.css">';

// If already logged in, redirect to dashboard
if (isAuthenticated()) {
    $user = getCurrentUser();
    header('Location: /doon-app/' . $user['role'] . '/dashboard.php');
    exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Email and password are required.';
    } else {
        try {
            $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && verifyPassword($password, $user['password'])) {
                if (!$user['is_active']) {
                    $error = 'Your account has been disabled.';
                } else {
                    setUserSession($user);
                    header('Location: /doon-app/' . $user['role'] . '/dashboard.php');
                    exit;
                }
            } else {
                $error = 'Invalid email or password.';
            }
        } catch (PDOException $e) {
            $error = 'An error occurred. Please try again.';
        }
    }
}
?>

<?php include 'includes/header.php'; ?>

<div class="auth-wrap">
  <aside class="auth-left">
    <div class="auth-left-inner">
      <div class="auth-logo">Doon<b>.</b></div>
      <h1 class="auth-tagline">Discover <em>where to go</em> across CALABARZON</h1>
      <p class="auth-sub-txt">Sign in to access recommendations, saved destinations, maps, and trip planning tools.</p>
      <div class="auth-provs">
        <span class="auth-prov">Batangas</span><span class="auth-prov">Laguna</span><span class="auth-prov">Cavite</span><span class="auth-prov">Rizal</span><span class="auth-prov">Quezon</span>
      </div>
    </div>
  </aside>

  <section class="auth-right">
    <div class="auth-form-box">
      <a class="auth-back" href="/doon-app/index.php"><- Back to home</a>
      <h2 class="auth-form-title">Welcome back</h2>
      <p class="auth-form-sub">Continue your journey. No account yet? <a href="/doon-app/register.php">Create one</a></p>

      <?php if ($error): ?>
        <div class="alert alert-error"><?php echo escape($error); ?></div>
      <?php endif; ?>

      <form method="POST">
        <div class="form-group">
          <label class="form-label" for="email">Email</label>
          <input class="form-input" type="email" id="email" name="email" value="<?php echo escape($email); ?>" required autofocus placeholder="you@example.com">
        </div>
        <div class="form-group">
          <label class="form-label" for="password">Password</label>
          <div style="position:relative;">
            <input class="form-input" type="password" id="password" name="password" required placeholder="********" style="padding-right:40px;">
            <button type="button" onclick="togglePw('password')" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#9ca3af;padding:4px;" tabindex="-1">
              <i class="fa-solid fa-eye" id="password-eye"></i>
            </button>
          </div>
        </div>
        <button class="btn-full btn-primary" type="submit">Sign In</button>
      </form>

      <div class="form-divider"><span>or</span></div>
      <a class="btn-full btn-outline" href="/doon-app/register.php">Create an account</a>
      <p class="auth-footer-txt">By continuing, you agree to Doon terms and privacy policy.</p>
    </div>
  </section>
</div>

<script src="/doon-app/assets/js/main.js"></script>
<script>
function togglePw(id) {
  var input = document.getElementById(id);
  var icon  = document.getElementById(id + '-eye');
  if (input.type === 'password') { input.type = 'text';     icon.classList.replace('fa-eye','fa-eye-slash'); }
  else                           { input.type = 'password'; icon.classList.replace('fa-eye-slash','fa-eye'); }
}
</script>

<?php include 'includes/footer.php'; ?>

