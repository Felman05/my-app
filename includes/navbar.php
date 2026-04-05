<?php
/**
 * Landing page navbar
 */
$currentUser = getCurrentUser();
?>

<header class="navbar-landing">
    <div class="container navbar-content">
        <div class="navbar-logo">
            <h1><a href="/doon-app/index.php">Doon</a></h1>
        </div>
        <nav class="navbar-menu">
            <a href="/doon-app/index.php#features">Features</a>
            <a href="/doon-app/index.php#provinces">Provinces</a>
            <a href="/doon-app/index.php#how-it-works">How it Works</a>
        </nav>
        <div class="navbar-actions">
            <?php if ($currentUser): ?>
                <span class="navbar-user"><?php echo escape($currentUser['name']); ?></span>
                <a href="/doon-app/<?php echo $currentUser['role']; ?>/dashboard.php" class="btn btn-accent btn-small">Dashboard</a>
                <a href="/doon-app/api/auth.php?action=logout" class="btn btn-secondary btn-small">Logout</a>
            <?php else: ?>
                <a href="/doon-app/login.php" class="btn btn-secondary btn-small">Sign In</a>
                <a href="/doon-app/register.php" class="btn btn-accent btn-small">Sign Up</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<style>
.navbar-landing {
    background-color: white;
    border-bottom: 1px solid var(--bd);
    position: sticky;
    top: 0;
    z-index: var(--z-sticky);
}

.navbar-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: var(--sp3) var(--sp4);
    gap: var(--sp4);
}

.navbar-logo h1 {
    margin: 0;
    font-size: 1.5rem;
}

.navbar-logo a {
    color: var(--ac);
    text-decoration: none;
}

.navbar-menu {
    display: flex;
    gap: var(--sp4);
    flex: 1;
    justify-content: center;
}

.navbar-menu a {
    color: var(--i);
    font-weight: 500;
    transition: color 0.2s ease;
}

.navbar-menu a:hover {
    color: var(--ac);
}

.navbar-actions {
    display: flex;
    align-items: center;
    gap: var(--sp2);
}

.navbar-user {
    color: var(--i2);
    font-size: 0.9rem;
}

@media (max-width: 768px) {
    .navbar-content {
        flex-wrap: wrap;
        padding: var(--sp2) var(--sp3);
    }

    .navbar-menu {
        order: 3;
        width: 100%;
        margin-top: var(--sp2);
        flex-direction: column;
        gap: var(--sp2);
    }

    .navbar-actions {
        flex-direction: column;
        width: 100%;
        order: 4;
        margin-top: var(--sp2);
    }

    .navbar-actions .btn {
        width: 100%;
    }
}
</style>
