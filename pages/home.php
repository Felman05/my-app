<?php
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>

<section class="card">
    <?php if ($isConnected): ?>
        <p class="status success">Connected to database</p>
    <?php else: ?>
        <p class="status error">Database connection failed. Check your MySQL settings.</p>
    <?php endif; ?>

    <?php if ($authSuccess): ?>
        <p class="status success"><?php echo e($authSuccess); ?></p>
    <?php endif; ?>

    <?php if (!empty($authErrors)): ?>
        <?php foreach ($authErrors as $authError): ?>
            <p class="status error"><?php echo e((string) $authError); ?></p>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

<section class="hero">
    <h2>Explore CALABARZON With Live Data</h2>
    <div class="stats-grid">
        <article class="stat-card">
            <p class="label">Destinations</p>
            <p class="value"><?php echo number_format((int) $stats['destinations']); ?></p>
        </article>
        <article class="stat-card">
            <p class="label">Provinces</p>
            <p class="value"><?php echo number_format((int) $stats['provinces']); ?></p>
        </article>
        <article class="stat-card">
            <p class="label">Published Reviews</p>
            <p class="value"><?php echo number_format((int) $stats['reviews']); ?></p>
        </article>
        <article class="stat-card">
            <p class="label">Active Tourists</p>
            <p class="value"><?php echo number_format((int) $stats['tourists']); ?></p>
        </article>
    </div>
</section>

<?php if ($currentUser): ?>
    <section class="card">
        <h3>Welcome, <?php echo e((string) $currentUser['name']); ?></h3>
        <p class="muted">Signed in as tourist: <?php echo e((string) $currentUser['email']); ?></p>
        <form method="post" class="inline-form">
            <input type="hidden" name="action" value="logout">
            <button type="submit" class="btn secondary">Sign Out</button>
        </form>
    </section>
<?php else: ?>
    <section class="auth-shell">
        <div class="auth-toggle">
            <button type="button" class="auth-tab active" data-target="signin-frame">Sign In</button>
            <button type="button" class="auth-tab" data-target="signup-frame">Sign Up</button>
        </div>

        <div id="signin-frame" class="auth-frame active">
            <h3>Tourist Sign In</h3>
            <form method="post" class="form-grid" autocomplete="on">
                <input type="hidden" name="action" value="signin">

                <label>
                    Email
                    <input type="email" name="email" required>
                </label>

                <label>
                    Password
                    <input type="password" name="password" required>
                </label>

                <button type="submit" class="btn">Sign In</button>
            </form>
        </div>

        <div id="signup-frame" class="auth-frame">
            <h3>Tourist Sign Up</h3>
            <form method="post" class="form-grid" autocomplete="on">
                <input type="hidden" name="action" value="signup">

                <label>
                    Full Name
                    <input type="text" name="name" required>
                </label>

                <label>
                    Email
                    <input type="email" name="email" required>
                </label>

                <label>
                    Password
                    <input type="password" name="password" minlength="8" required>
                </label>

                <label>
                    Confirm Password
                    <input type="password" name="password_confirm" minlength="8" required>
                </label>

                <label>
                    Account Type
                    <input type="text" value="tourist" readonly>
                </label>

                <button type="submit" class="btn">Create Tourist Account</button>
            </form>
        </div>
    </section>
<?php endif; ?>

<section class="card">
    <h3>Top Destinations</h3>
    <?php if (!empty($featuredDestinations)): ?>
        <div class="destination-grid">
            <?php foreach ($featuredDestinations as $destination): ?>
                <article class="destination-card">
                    <h4><?php echo e((string) $destination['name']); ?></h4>
                    <p class="muted"><?php echo e((string) $destination['province_name']); ?> • <?php echo e((string) $destination['category_name']); ?></p>
                    <p><?php echo e((string) ($destination['short_description'] ?? '')); ?></p>
                    <p class="meta">
                        <?php echo e((string) strtoupper((string) ($destination['price_label'] ?? 'n/a'))); ?>
                        • Rating <?php echo number_format((float) $destination['avg_rating'], 1); ?>
                        • <?php echo number_format((int) $destination['total_reviews']); ?> reviews
                    </p>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="muted">No destination records found.</p>
    <?php endif; ?>
</section>

<section class="card">
    <h3>Province Coverage</h3>
    <?php if (!empty($provinceBreakdown)): ?>
        <div class="province-grid">
            <?php foreach ($provinceBreakdown as $province): ?>
                <article class="province-card">
                    <p class="province-name"><?php echo e((string) $province['name']); ?></p>
                    <p class="province-count"><?php echo number_format((int) $province['destination_count']); ?> destinations</p>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="muted">No province records found.</p>
    <?php endif; ?>
</section>

<section class="card">
    <h3>Latest Tourist Reviews</h3>
    <?php if (!empty($latestReviews)): ?>
        <div class="review-grid">
            <?php foreach ($latestReviews as $review): ?>
                <article class="review-card">
                    <p class="meta"><?php echo e((string) $review['destination_name']); ?> • <?php echo e((string) $review['reviewer_name']); ?></p>
                    <p class="rating">Rating: <?php echo number_format((float) $review['rating'], 1); ?>/5</p>
                    <h4><?php echo e((string) ($review['title'] ?? 'Untitled review')); ?></h4>
                    <p><?php echo e((string) ($review['body'] ?? '')); ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="muted">No published reviews found.</p>
    <?php endif; ?>
</section>
