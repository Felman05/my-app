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

if ($action === 'forecast') {
    $province = strtolower(trim($_GET['province'] ?? ''));
    if (!isset($locations[$province])) {
        http_response_code(400);
        echo jsonResponse(false, null, 'Unknown province.');
        exit;
    }

    $loc = $locations[$province];
    $url = sprintf(
        'https://api.openweathermap.org/data/2.5/forecast?lat=%s&lon=%s&appid=%s&units=metric&cnt=40',
        urlencode($loc['lat']),
        urlencode($loc['lon']),
        urlencode($apiKey)
    );

    $context  = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 12, 'ignore_errors' => true]]);
    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        http_response_code(502);
        echo jsonResponse(false, null, 'Failed to fetch forecast.');
        exit;
    }

    $data = json_decode($response, true);
    if (!is_array($data) || (isset($data['cod']) && (int) $data['cod'] !== 200)) {
        http_response_code(502);
        echo jsonResponse(false, null, 'Forecast API error.');
        exit;
    }

    // Group 3-hour entries into daily summaries
    $days = [];
    foreach ($data['list'] ?? [] as $entry) {
        $date = date('Y-m-d', $entry['dt']);
        if (!isset($days[$date])) {
            $days[$date] = ['temps' => [], 'conditions' => [], 'humidity' => [], 'wind' => []];
        }
        if (isset($entry['main']['temp']))       $days[$date]['temps'][]      = (float) $entry['main']['temp'];
        if (isset($entry['weather'][0]['description'])) $days[$date]['conditions'][] = $entry['weather'][0]['description'];
        if (isset($entry['main']['humidity']))   $days[$date]['humidity'][]   = (int) $entry['main']['humidity'];
        if (isset($entry['wind']['speed']))      $days[$date]['wind'][]       = (float) $entry['wind']['speed'];
    }

    $forecast = [];
    foreach ($days as $date => $day) {
        $temps = $day['temps'];
        $hums  = $day['humidity'];
        $winds = $day['wind'];

        // Most frequent condition for the day
        $condCount = array_count_values($day['conditions']);
        arsort($condCount);
        reset($condCount);
        $condition = key($condCount) ?: 'Unavailable';

        $forecast[] = [
            'date'      => $date,
            'day'       => date('D, M j', strtotime($date)),
            'min_temp'  => $temps ? (int) round(min($temps)) : null,
            'max_temp'  => $temps ? (int) round(max($temps)) : null,
            'condition' => $condition,
            'humidity'  => $hums  ? (int) round(array_sum($hums)  / count($hums))  : null,
            'wind'      => $winds ? round(array_sum($winds) / count($winds), 1)     : null,
        ];
    }

    echo jsonResponse(true, ['province' => $loc['name'], 'forecast' => $forecast]);
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
