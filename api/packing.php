<?php
/**
 * Packing suggestions API
 *
 * GET params:
 *   category_id  — activity_categories.id (optional)
 *   weather      — raw OpenWeather condition string OR mapped key (optional)
 *                  Maps to packing_templates.weather_condition enum: sunny|rainy|cold|windy|any
 *
 * Resolution order:
 *   1. category + matched weather condition
 *   2. category + any
 *   3. NULL category + matched weather condition
 *   4. NULL category + any (hard fallback)
 */

require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
requireRole('tourist');

$categoryId = (int) ($_GET['category_id'] ?? 0);
$rawWeather = strtolower(trim($_GET['weather'] ?? ''));

// Map OpenWeather description strings → packing_templates weather_condition
function mapWeatherCondition(string $raw): string {
    if (!$raw || $raw === 'any') return 'any';
    if (str_contains($raw, 'rain') || str_contains($raw, 'drizzle') ||
        str_contains($raw, 'thunder') || str_contains($raw, 'storm')) return 'rainy';
    if (str_contains($raw, 'clear') || str_contains($raw, 'sunny') ||
        str_contains($raw, 'sun')) return 'sunny';
    if (str_contains($raw, 'snow') || str_contains($raw, 'cold') ||
        str_contains($raw, 'frost') || str_contains($raw, 'ice')) return 'cold';
    if (str_contains($raw, 'wind') || str_contains($raw, 'squall') ||
        str_contains($raw, 'tornado') || str_contains($raw, 'gale')) return 'windy';
    return 'any';
}

$weatherCondition = mapWeatherCondition($rawWeather);

$template = null;

try {
    if ($categoryId && $weatherCondition !== 'any') {
        // 1. category + specific weather
        $stmt = $pdo->prepare(
            "SELECT label, items FROM packing_templates
             WHERE category_id = ? AND weather_condition = ? LIMIT 1"
        );
        $stmt->execute([$categoryId, $weatherCondition]);
        $template = $stmt->fetch();
    }

    if (!$template && $categoryId) {
        // 2. category + any
        $stmt = $pdo->prepare(
            "SELECT label, items FROM packing_templates
             WHERE category_id = ? AND weather_condition = 'any' LIMIT 1"
        );
        $stmt->execute([$categoryId]);
        $template = $stmt->fetch();
    }

    if (!$template && $weatherCondition !== 'any') {
        // 3. no category + specific weather
        $stmt = $pdo->prepare(
            "SELECT label, items FROM packing_templates
             WHERE category_id IS NULL AND weather_condition = ? LIMIT 1"
        );
        $stmt->execute([$weatherCondition]);
        $template = $stmt->fetch();
    }

    if (!$template) {
        // 4. no category + any
        $stmt = $pdo->query(
            "SELECT label, items FROM packing_templates
             WHERE category_id IS NULL AND weather_condition = 'any' LIMIT 1"
        );
        $template = $stmt->fetch();
    }
} catch (Exception $e) {
    $template = null;
}

if (!$template) {
    // Hard fallback
    $items = $weatherCondition === 'rainy'
        ? [
            ['item' => 'Rain jacket', 'essential' => true],
            ['item' => 'Waterproof bag', 'essential' => true],
            ['item' => 'Extra clothes', 'essential' => true],
            ['item' => 'Rubber sandals', 'essential' => false],
            ['item' => 'Powerbank', 'essential' => false],
          ]
        : [
            ['item' => 'Water', 'essential' => true],
            ['item' => 'ID', 'essential' => true],
            ['item' => 'Sunscreen', 'essential' => false],
            ['item' => 'Umbrella', 'essential' => false],
            ['item' => 'Snacks', 'essential' => false],
            ['item' => 'Powerbank', 'essential' => false],
          ];

    echo json_encode(['success' => true, 'label' => 'General essentials', 'weather' => $weatherCondition, 'data' => $items]);
    exit;
}

$raw = json_decode($template['items'], true);
if (!is_array($raw)) {
    $raw = [];
}

// Support both flat [{item,essential}] and structured {essential:[],recommended:[],wardrobe_tip}
$items = [];
$wardrobeTip = null;

if (isset($raw['essential']) || isset($raw['recommended'])) {
    foreach ($raw['essential'] ?? [] as $i) {
        $items[] = ['item' => $i['item'] ?? '', 'essential' => true, 'reason' => $i['reason'] ?? null];
    }
    foreach ($raw['recommended'] ?? [] as $i) {
        $items[] = ['item' => $i['item'] ?? '', 'essential' => false];
    }
    $wardrobeTip = $raw['wardrobe_tip'] ?? null;
} else {
    foreach ($raw as $i) {
        $items[] = ['item' => $i['item'] ?? '', 'essential' => !empty($i['essential'])];
    }
}

echo json_encode([
    'success'      => true,
    'label'        => $template['label'],
    'weather'      => $weatherCondition,
    'wardrobe_tip' => $wardrobeTip,
    'data'         => $items,
]);
