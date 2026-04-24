<?php
/**
 * Shared Helper Functions
 */

/**
 * Format date
 * @param string $date
 * @param string $format
 * @return string
 */
function formatDate($date, $format = 'M d, Y') {
    return date($format, strtotime($date));
}

/**
 * Get price range label from destinations table value
 * @param string $priceRange (free, budget, mid_range, luxury)
 * @return string
 */
function getPriceRangeLabel($priceRange) {
    $labels = [
        'free'      => 'Free',
        'budget'    => 'Budget',
        'mid_range' => 'Mid Range',
        'luxury'    => 'Luxury'
    ];
    return $labels[$priceRange] ?? $priceRange;
}

/**
 * Get generational profile display name
 * @param string $profile (gen_z, millennial, gen_x, boomer)
 * @return string
 */
function getGenerationalLabel($profile) {
    $labels = [
        'gen_z'     => 'Gen Z',
        'millennial' => 'Millennial',
        'gen_x'     => 'Gen X',
        'boomer'    => 'Boomer'
    ];
    return $labels[$profile] ?? $profile;
}

/**
 * Get activity category by ID
 * @param PDO $pdo
 * @param int $categoryId
 * @return array|null
 */
function getActivityCategory($pdo, $categoryId) {
    $stmt = $pdo->prepare('SELECT * FROM activity_categories WHERE id = ?');
    $stmt->execute([$categoryId]);
    return $stmt->fetch();
}

/**
 * Get province by ID
 * @param PDO $pdo
 * @param int $provinceId
 * @return array|null
 */
function getProvince($pdo, $provinceId) {
    $stmt = $pdo->prepare('SELECT * FROM provinces WHERE id = ?');
    $stmt->execute([$provinceId]);
    return $stmt->fetch();
}

/**
 * Calculate average rating for a destination
 * @param PDO $pdo
 * @param int $destinationId
 * @return float
 */
function getAverageRating($pdo, $destinationId) {
    $stmt = $pdo->prepare('SELECT AVG(rating) as avg_rating FROM reviews WHERE destination_id = ? AND is_published = 1');
    $stmt->execute([$destinationId]);
    $result = $stmt->fetch();
    return round($result['avg_rating'] ?? 0, 1);
}

/**
 * Get review count for destination
 * @param PDO $pdo
 * @param int $destinationId
 * @return int
 */
function getReviewCount($pdo, $destinationId) {
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM reviews WHERE destination_id = ? AND is_published = 1');
    $stmt->execute([$destinationId]);
    $result = $stmt->fetch();
    return $result['count'] ?? 0;
}

/**
 * Generate UUID v4
 * @return string
 */
function generateUUID() {
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

/**
 * Log analytics event
 * @param PDO $pdo
 * @param int $userId
 * @param string $eventType (view_destination, create_itinerary, etc.)
 * @param array $data
 */
function logAnalyticsEvent($pdo, $userId, $eventType, $data = []) {
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO analytics_events (user_id, event_type, metadata, created_at)
             VALUES (?, ?, ?, NOW())'
        );
        $stmt->execute([$userId, $eventType, json_encode($data)]);
    } catch (PDOException $e) {
        // Silently fail so analytics never breaks the main request
    }
}

/**
 * Build JSON response
 * @param bool $success
 * @param mixed $data
 * @param string $error
 * @return string
 */
function jsonResponse($success, $data = null, $error = '') {
    return json_encode([
        'success' => $success,
        'data'    => $data,
        'error'   => $error
    ]);
}

/**
 * Log an admin activity to admin_activity_logs.
 * Silently fails so it never interrupts the main request.
 */
function logAdminActivity(PDO $pdo, int $adminId, string $action, ?string $modelType = null, ?int $modelId = null, ?string $description = null): void {
    try {
        $pdo->prepare(
            'INSERT INTO admin_activity_logs (admin_id, action, model_type, model_id, description, ip_address, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())'
        )->execute([$adminId, $action, $modelType, $modelId, $description, $_SERVER['REMOTE_ADDR'] ?? null]);
    } catch (PDOException $e) {}
}

/**
 * Generate (or retrieve) the CSRF token for the current session.
 */
function csrfToken(): string {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token on POST requests. Terminates with 403 on failure.
 */
function verifyCsrf(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $submitted = $_POST['csrf_token'] ?? '';
    $expected  = $_SESSION['csrf_token'] ?? '';
    if ($expected === '' || !hash_equals($expected, $submitted)) {
        http_response_code(403);
        die('Request validation failed. Please go back and try again.');
    }
}

/**
 * Upload images from a multi-file input named "images[]".
 * Stores files under /uploads/$subfolder/, returns web-accessible paths.
 * @param string $subfolder  e.g. 'listings' or 'destinations'
 * @param int    $maxFiles   Maximum number of files to accept
 * @return string[]          Array of web-accessible paths
 */
function uploadImages(string $subfolder, int $maxFiles = 10): array {
    $allowedSubfolders = ['listings', 'destinations', 'provinces'];
    if (!in_array($subfolder, $allowedSubfolders, true)) return [];
    $paths = [];
    if (empty($_FILES['images']['name'][0])) return $paths;
    $uploadDir = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') . '/doon-app/uploads/' . $subfolder . '/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    foreach ($_FILES['images']['tmp_name'] as $i => $tmp) {
        if (count($paths) >= $maxFiles) break;
        if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) continue;
        if ($_FILES['images']['size'][$i] > 5 * 1024 * 1024) continue;
        $mime = mime_content_type($tmp);
        if (!isset($allowed[$mime])) continue;
        $filename = "{$subfolder}_" . bin2hex(random_bytes(8)) . ".{$allowed[$mime]}";
        if (move_uploaded_file($tmp, "{$uploadDir}{$filename}")) {
            $paths[] = "/doon-app/uploads/{$subfolder}/{$filename}";
        }
    }
    return $paths;
}
