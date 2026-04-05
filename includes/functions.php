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
            'INSERT INTO analytics_events (user_id, event_type, event_data, created_at) 
             VALUES (?, ?, ?, NOW())'
        );
        $stmt->execute([$userId, $eventType, json_encode($data)]);
    } catch (PDOException $e) {
        // Silently fail if analytics logging fails
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
?>
