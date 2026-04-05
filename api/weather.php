<?php
/**
 * Weather API endpoint for CALABARZON provinces.
 */

require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/env.php';
require_once '../includes/provinces.php';

header('Content-Type: application/json');
requireRole('tourist');

$apiKey = env('OPENWEATHER_API_KEY', '');
if (empty($apiKey)) {
    http_response_code(500);
    echo jsonResponse(false, null, 'OpenWeather API key is not configured.');
    exit;
}

$locations = $PROVINCE_LOCATIONS;

function fetchOpenWeather($location, $apiKey) {
    $url = sprintf(
        'https://api.openweathermap.org/data/2.5/weather?lat=%s&lon=%s&appid=%s&units=metric',
        urlencode($location['lat']),
        urlencode($location['lon']),
        urlencode($apiKey)
    );

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 12,
            'ignore_errors' => true
        ]
    ]);

    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        return null;
    }

    $data = json_decode($response, true);
    if (!is_array($data) || (isset($data['cod']) && (int) $data['cod'] !== 200)) {
        return null;
    }

    return [
        'province' => $location['name'],
        'temp' => isset($data['main']['temp']) ? round((float) $data['main']['temp']) : null,
        'feels_like' => isset($data['main']['feels_like']) ? round((float) $data['main']['feels_like']) : null,
        'humidity' => isset($data['main']['humidity']) ? (int) $data['main']['humidity'] : null,
        'wind' => isset($data['wind']['speed']) ? (float) $data['wind']['speed'] : null,
        'condition' => $data['weather'][0]['description'] ?? 'Unavailable',
        'icon' => $data['weather'][0]['icon'] ?? null,
        'updated_at' => date('c')
    ];
}

$action = $_GET['action'] ?? 'list';
if ($action === 'single') {
    $province = strtolower(trim($_GET['province'] ?? ''));
    if (!isset($locations[$province])) {
        http_response_code(400);
        echo jsonResponse(false, null, 'Unknown province.');
        exit;
    }

    $weather = fetchOpenWeather($locations[$province], $apiKey);
    if ($weather === null) {
        http_response_code(502);
        echo jsonResponse(false, null, 'Failed to fetch weather.');
        exit;
    }

    echo jsonResponse(true, $weather);
    exit;
}

$result = [];
foreach ($locations as $key => $location) {
    $weather = fetchOpenWeather($location, $apiKey);
    $result[] = [
        'key' => $key,
        'province' => $location['name'],
        'weather' => $weather
    ];
}

echo jsonResponse(true, $result);
