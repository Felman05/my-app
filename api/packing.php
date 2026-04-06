<?php
/**
 * Packing suggestions API
 * GET ?category_id=X  → returns items from packing_templates for that category
 * Falls back to generic 'any' templates if no category match.
 */

require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
requireRole('tourist');

$categoryId = (int) ($_GET['category_id'] ?? 0);

try {
    // Try category-specific template first, then any
    if ($categoryId) {
        $stmt = $pdo->prepare(
            "SELECT label, items FROM packing_templates
             WHERE (category_id = ? OR category_id IS NULL) AND weather_condition = 'any'
             ORDER BY category_id DESC LIMIT 1"
        );
        $stmt->execute([$categoryId]);
    } else {
        $stmt = $pdo->query(
            "SELECT label, items FROM packing_templates
             WHERE category_id IS NULL AND weather_condition = 'any'
             LIMIT 1"
        );
    }
    $template = $stmt->fetch();
} catch (Exception $e) {
    $template = null;
}

if (!$template) {
    // Hard fallback so the UI always has something
    echo jsonResponse(true, [
        ['item' => 'Water', 'essential' => true],
        ['item' => 'ID', 'essential' => true],
        ['item' => 'Sunscreen', 'essential' => false],
        ['item' => 'Umbrella', 'essential' => false],
        ['item' => 'Snacks', 'essential' => false],
        ['item' => 'Powerbank', 'essential' => false],
    ]);
    // Inject label separately — wrap in full structure
    $payload = ['success' => true, 'label' => 'General essentials', 'data' => [
        ['item' => 'Water', 'essential' => true],
        ['item' => 'ID', 'essential' => true],
        ['item' => 'Sunscreen', 'essential' => false],
        ['item' => 'Umbrella', 'essential' => false],
        ['item' => 'Snacks', 'essential' => false],
        ['item' => 'Powerbank', 'essential' => false],
    ]];
    echo json_encode($payload);
    exit;
}

$items = json_decode($template['items'], true);
if (!is_array($items)) {
    $items = [];
}

echo json_encode([
    'success' => true,
    'label'   => $template['label'],
    'data'    => array_map(function ($i) {
        return [
            'item'      => $i['item'] ?? '',
            'essential' => !empty($i['essential'])
        ];
    }, $items)
]);
