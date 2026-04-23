<?php
/**
 * Gemini chatbot endpoint.
 * Contract: POST JSON { session_token, message }
 * Response: { success, response, destinations, session_token }
 */

require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/env.php';
require_once '../includes/provinces.php';

header('Content-Type: application/json');
requireRole('tourist');

$currentUser = getCurrentUser();
$userId = (int) $currentUser['id'];
$geminiApiKey = env('GEMINI_API_KEY', '');
$openWeatherApiKey = env('OPENWEATHER_API_KEY', '');
$geminiModel = env('GEMINI_MODEL', 'gemini-2.5-flash');
$geminiFallbackModels = env('GEMINI_FALLBACK_MODELS', 'gemini-2.5-flash-lite,gemini-1.5-flash');

function readJsonInput() {
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function sendJson($payload, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function generateSessionToken() {
    try {
        return bin2hex(random_bytes(16));
    } catch (Exception $e) {
        return sha1(uniqid('doon_', true) . mt_rand());
    }
}

function normalizeText($text) {
    return strtolower(trim((string) $text));
}

function parseCsvModels($csv) {
    $models = array_map('trim', explode(',', (string) $csv));
    $models = array_values(array_filter($models, function ($m) {
        return $m !== '';
    }));
    return array_values(array_unique($models));
}

function detectIntents($message) {
    $m = normalizeText($message);
    return [
        'weather'  => (bool) preg_match('/\b(weather|temperature|temp|rain|humidity|wind|tomorrow|today)\b/i', $m),
        'forecast' => (bool) preg_match('/\b(forecast|this\s+week|weekly|coming\s+days?|multi.?day|next\s+(?:\d+|few|several|two|three|four|five|six|seven|eight|nine|ten)\s+days?|(?:\d+|two|three|four|five|six|seven|eight|nine|ten)\s+days?\s+(?:forecast|weather)|7.?day|5.?day|week)\b/i', $m),
        'favorites' => (bool) preg_match('/\b(saved|favorite|favorites|my places|bookmarked)\b/i', $m),
        'recommend' => (bool) preg_match('/\b(recommend|suggest|suggestion|where to go|place|destination|visit|attraction|beach|resort|trip)\b/i', $m),
        'itinerary' => (bool) preg_match('/\b(itinerary|my trip|trip plan|plan my trip|schedule)\b/i', $m)
    ];
}

function findProvinceInMessage($message) {
    global $PROVINCE_ALIASES;
    $m = normalizeText($message);

    foreach ($PROVINCE_ALIASES as $province => $aliases) {
        foreach ($aliases as $alias) {
            if (strpos($m, $alias) !== false) {
                return $province;
            }
        }
    }

    return '';
}

function getOrCreateSession($pdo, $userId, $sessionToken) {
    $stmt = $pdo->prepare('SELECT * FROM chatbot_sessions WHERE session_token = ? LIMIT 1');
    $stmt->execute([$sessionToken]);
    $session = $stmt->fetch();

    if ($session) {
        if ((int) ($session['user_id'] ?? 0) !== $userId) {
            return null;
        }
        return $session;
    }

    $insert = $pdo->prepare(
        'INSERT INTO chatbot_sessions (user_id, session_token, context, created_at, updated_at)
         VALUES (?, ?, ?, NOW(), NOW())'
    );
    $insert->execute([$userId, $sessionToken, json_encode([])]);

    $id = (int) $pdo->lastInsertId();
    $fetch = $pdo->prepare('SELECT * FROM chatbot_sessions WHERE id = ? LIMIT 1');
    $fetch->execute([$id]);
    return $fetch->fetch();
}

function fetchConversation($pdo, $sessionId) {
    $stmt = $pdo->prepare(
        'SELECT role, content, metadata, created_at
         FROM chatbot_messages
         WHERE session_id = ?
         ORDER BY created_at ASC, id ASC'
    );
    $stmt->execute([(int) $sessionId]);
    return $stmt->fetchAll();
}

function insertChatMessage($pdo, $sessionId, $role, $content, $metadata = null) {
    $stmt = $pdo->prepare(
        'INSERT INTO chatbot_messages (session_id, role, content, metadata, created_at)
         VALUES (?, ?, ?, ?, NOW())'
    );
    $stmt->execute([(int) $sessionId, $role, $content, $metadata]);
}

function updateSessionContext($pdo, $sessionId, $contextArray) {
    $stmt = $pdo->prepare('UPDATE chatbot_sessions SET context = ?, updated_at = NOW() WHERE id = ?');
    $stmt->execute([json_encode($contextArray), (int) $sessionId]);
}

function fetchJsonFromUrl($url) {
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 15,
            'ignore_errors' => true
        ]
    ]);

    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        return null;
    }

    $decoded = json_decode($response, true);
    return is_array($decoded) ? $decoded : null;
}

function getWeatherContext($message, $openWeatherApiKey) {
    global $PROVINCE_LOCATIONS;

    if (empty($openWeatherApiKey)) {
        return ['text' => 'Weather API key is missing.', 'data' => []];
    }

    $provinceKey = findProvinceInMessage($message);

    $targets = [];
    if ($provinceKey !== '' && isset($PROVINCE_LOCATIONS[$provinceKey])) {
        $targets[$provinceKey] = $PROVINCE_LOCATIONS[$provinceKey];
    } else {
        $targets = $PROVINCE_LOCATIONS;
    }

    $lines = ['OpenWeather data:'];
    $collected = [];

    foreach ($targets as $target) {
        $query = 'lat=' . urlencode((string) $target['lat']) . '&lon=' . urlencode((string) $target['lon'])
            . '&appid=' . urlencode($openWeatherApiKey) . '&units=metric';
        $current = fetchJsonFromUrl('https://api.openweathermap.org/data/2.5/weather?' . $query);

        if (!$current || (string) ($current['cod'] ?? '200') !== '200') {
            $lines[] = '- ' . $target['name'] . ': unavailable';
            continue;
        }

        $temp = isset($current['main']['temp']) ? round((float) $current['main']['temp']) : 'n/a';
        $humidity = isset($current['main']['humidity']) ? (int) $current['main']['humidity'] : 'n/a';
        $wind = isset($current['wind']['speed']) ? (float) $current['wind']['speed'] : 'n/a';
        $condition = $current['weather'][0]['description'] ?? 'n/a';

        $lines[] = '- ' . $target['name'] . ': ' . $temp . 'C, ' . $condition . ', humidity ' . $humidity . '%, wind ' . $wind . ' m/s';
        $collected[] = [
            'province' => $target['name'],
            'temp' => $temp,
            'condition' => $condition,
            'humidity' => $humidity,
            'wind' => $wind
        ];
    }

    return ['text' => implode("\n", $lines), 'data' => $collected];
}

function getForecastContext($message, $openWeatherApiKey) {
    global $PROVINCE_LOCATIONS;

    if (empty($openWeatherApiKey)) {
        return ['text' => 'Weather forecast API key is missing.', 'data' => []];
    }

    $provinceKey = findProvinceInMessage($message);

    if ($provinceKey === '' || !isset($PROVINCE_LOCATIONS[$provinceKey])) {
        return [
            'text' => '7-day forecasts are available for: Batangas, Laguna, Cavite, Rizal, Quezon. Ask the user to specify a province.',
            'data' => []
        ];
    }

    $target = $PROVINCE_LOCATIONS[$provinceKey];
    $query = 'lat=' . urlencode((string) $target['lat'])
        . '&lon=' . urlencode((string) $target['lon'])
        . '&appid=' . urlencode($openWeatherApiKey)
        . '&units=metric&cnt=56';

    $data = fetchJsonFromUrl('https://api.openweathermap.org/data/2.5/forecast?' . $query);

    if (!$data || empty($data['list'])) {
        return ['text' => '7-day forecast for ' . $target['name'] . ': unavailable.', 'data' => []];
    }

    $daily = [];
    foreach ($data['list'] as $entry) {
        $day = date('Y-m-d', (int) ($entry['dt'] ?? 0));
        if (!isset($daily[$day])) {
            $daily[$day] = [
                'label'      => date('l, M j', (int) ($entry['dt'] ?? 0)),
                'temps'      => [],
                'humidity'   => [],
                'conditions' => []
            ];
        }
        $daily[$day]['temps'][]    = (float) ($entry['main']['temp'] ?? 0);
        $daily[$day]['humidity'][] = (int) ($entry['main']['humidity'] ?? 0);
        $desc = $entry['weather'][0]['description'] ?? '';
        if ($desc !== '') {
            $daily[$day]['conditions'][] = $desc;
        }
    }

    $lines = [$target['name'] . ' — 7-day weather forecast:'];
    $collected = [];

    foreach (array_slice($daily, 0, 7, true) as $dateKey => $day) {
        $high = !empty($day['temps']) ? round(max($day['temps'])) : 'n/a';
        $low  = !empty($day['temps']) ? round(min($day['temps'])) : 'n/a';
        $avgHum = !empty($day['humidity'])
            ? round(array_sum($day['humidity']) / count($day['humidity']))
            : 'n/a';
        $condCount = array_count_values($day['conditions']);
        arsort($condCount);
        $mainCond = !empty($condCount) ? (string) array_key_first($condCount) : 'n/a';
        $lines[] = '  ' . $day['label'] . ': High ' . $high . 'C, Low ' . $low . 'C, ' . $mainCond . ', humidity ' . $avgHum . '%';
        $collected[] = [
            'province'  => $target['name'],
            'date'      => $dateKey,
            'day_label' => $day['label'],
            'high'      => $high,
            'low'       => $low,
            'condition' => $mainCond,
            'humidity'  => $avgHum
        ];
    }

    return ['text' => implode("\n", $lines), 'data' => $collected];
}

function getFavoritesData($pdo, $userId) {
    $stmt = $pdo->prepare(
        'SELECT d.id, d.name, d.slug, d.short_description, d.price_label, d.avg_rating, p.name AS province_name
         FROM favorites f
         JOIN destinations d ON d.id = f.destination_id
         LEFT JOIN provinces p ON p.id = d.province_id
         WHERE f.user_id = ?
         ORDER BY f.created_at DESC
         LIMIT 10'
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function getRecommendationCandidates($pdo, $message) {
    $province = findProvinceInMessage($message);

    $sql = 'SELECT d.id, d.name, d.slug, d.short_description, d.description, d.price_label, d.avg_rating,
                   d.latitude, d.longitude, d.tags, p.name AS province_name
            FROM destinations d
            LEFT JOIN provinces p ON p.id = d.province_id
            WHERE d.is_active = 1';
    $params = [];

    if ($province !== '') {
        $sql .= ' AND LOWER(p.name) = ?';
        $params[] = strtolower($province);
    }

    $sql .= ' ORDER BY d.avg_rating DESC, d.view_count DESC LIMIT 8';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getItineraryData($pdo, $userId) {
    $stmt = $pdo->prepare(
        'SELECT id, title, description, start_date, end_date
         FROM itineraries
         WHERE user_id = ?
         ORDER BY created_at DESC
         LIMIT 8'
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function formatDestinationsForResponse($rows) {
    $out = [];
    foreach ($rows as $row) {
        $out[] = [
            'id' => (int) ($row['id'] ?? 0),
            'name' => $row['name'] ?? '',
            'slug' => $row['slug'] ?? '',
            'short_description' => $row['short_description'] ?? '',
            'description' => $row['description'] ?? '',
            'price_label' => $row['price_label'] ?? '',
            'avg_rating' => isset($row['avg_rating']) ? round((float) $row['avg_rating'], 1) : 0,
            'province' => $row['province_name'] ?? '',
            'latitude' => $row['latitude'] ?? null,
            'longitude' => $row['longitude'] ?? null
        ];
    }
    return $out;
}

function buildGeminiPrompt($message, $history, $contextBlocks) {
    $system = [];
    $system[] = 'You are Doon Assistant — a smart, friendly, and knowledgeable assistant built into the Doon travel app.';
    $system[] = 'You can answer ANY question the user asks, just like a capable general-purpose AI assistant (history, science, math, language, general advice, etc.).';
    $system[] = 'You also have special expertise in CALABARZON tourism (Batangas, Laguna, Cavite, Rizal, Quezon): destinations, local activities, travel tips, weather, and itinerary planning.';
    $system[] = 'When real-time data is provided in the context (weather, forecast, favorites, itineraries, destinations), always use it. Never tell the user that live data is unavailable when it has been provided.';
    $system[] = 'If 7-day forecast data is provided, present each day clearly: **Day Name**: High X°C, Low X°C, condition, humidity X%.';
    $system[] = 'Use **bold** for emphasis (day labels, destination names, key figures). Formatting is rendered by the client.';
    $system[] = 'When recommending destinations from the database, include name, province, price label, and rating. Append each with [destination_id:<id>] when the ID is known.';
    $system[] = 'Be concise, helpful, and conversational. Never refuse to answer a question just because it is not tourism-related.';

    $prompt = implode("\n", $system) . "\n\n";

    if (!empty($history)) {
        $prompt .= "Recent conversation:\n";
        $start = max(0, count($history) - 12);
        for ($i = $start; $i < count($history); $i++) {
            $role = $history[$i]['role'] ?? 'user';
            $content = trim((string) ($history[$i]['content'] ?? ''));
            if ($content !== '') {
                $prompt .= strtoupper($role) . ': ' . $content . "\n";
            }
        }
        $prompt .= "\n";
    }

    if (!empty($contextBlocks)) {
        $prompt .= "Context data:\n";
        foreach ($contextBlocks as $block) {
            $prompt .= $block . "\n\n";
        }
    }

    $prompt .= "User: " . $message . "\nAssistant:";
    return $prompt;
}

function callGeminiFlash($apiKey, $prompt, $primaryModel, $fallbackModels = []) {
    if (empty($apiKey)) {
        return ['ok' => false, 'text' => '', 'error' => 'GEMINI_API_KEY is not configured.'];
    }

    $models = array_values(array_unique(array_merge([(string) $primaryModel], $fallbackModels)));
    $models = array_values(array_filter($models, function ($m) {
        return trim((string) $m) !== '';
    }));

    if (empty($models)) {
        return ['ok' => false, 'text' => '', 'error' => 'No Gemini model configured.'];
    }

    $payload = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.7,
            'maxOutputTokens' => 1200
        ]
    ];

    $lastError = 'Unable to reach Gemini API.';

    foreach ($models as $model) {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . urlencode($model) . ':generateContent?key=' . urlencode($apiKey);
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => json_encode($payload),
                'timeout' => 30,
                'ignore_errors' => true
            ]
        ]);

        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            $lastError = 'Unable to reach Gemini API for model ' . $model . '.';
            continue;
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            $lastError = 'Invalid Gemini API response for model ' . $model . '.';
            continue;
        }

        if (isset($decoded['error']['message'])) {
            $lastError = (string) $decoded['error']['message'];
            continue;
        }

        $text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $text = trim((string) $text);
        if ($text === '') {
            $lastError = 'Gemini returned an empty response for model ' . $model . '.';
            continue;
        }

        return ['ok' => true, 'text' => $text, 'error' => '', 'model' => $model];
    }

    return ['ok' => false, 'text' => '', 'error' => $lastError];
}

function isGeminiQuotaError($errorText) {
    $e = strtolower((string) $errorText);
    return (strpos($e, 'quota') !== false)
        || (strpos($e, 'rate limit') !== false)
        || (strpos($e, 'resource_exhausted') !== false)
        || (strpos($e, '429') !== false);
}

function buildFallbackReply($intents, $weatherData, $favorites, $itineraryRows, $destinations) {
    $lines = [];
    $lines[] = 'Gemini is temporarily unavailable due to API quota limits, but here is the latest platform data I can share:';

    if (!empty($intents['weather'])) {
        if (!empty($weatherData)) {
            $lines[] = '';
            $lines[] = 'Weather snapshot:';
            foreach (array_slice($weatherData, 0, 5) as $w) {
                $lines[] = '- ' . ($w['province'] ?? 'Unknown')
                    . ': ' . ($w['temp'] ?? 'n/a') . 'C, '
                    . ($w['condition'] ?? 'n/a')
                    . ', humidity ' . ($w['humidity'] ?? 'n/a') . '%';
            }
        } else {
            $lines[] = '';
            $lines[] = 'Weather data is not available right now.';
        }
    }

    if (!empty($intents['favorites'])) {
        $lines[] = '';
        if (!empty($favorites)) {
            $lines[] = 'Your saved places:';
            foreach (array_slice($favorites, 0, 5) as $fav) {
                $lines[] = '- ' . ($fav['name'] ?? 'Destination') . ' (' . ($fav['province_name'] ?? 'Unknown province') . ')';
            }
        } else {
            $lines[] = 'You do not have saved places yet.';
        }
    }

    if (!empty($intents['itinerary'])) {
        $lines[] = '';
        if (!empty($itineraryRows)) {
            $lines[] = 'Your itineraries:';
            foreach (array_slice($itineraryRows, 0, 5) as $it) {
                $lines[] = '- ' . ($it['title'] ?? 'Trip') . ' (' . ($it['start_date'] ?? '?') . ' to ' . ($it['end_date'] ?? '?') . ')';
            }
        } else {
            $lines[] = 'You do not have itineraries yet.';
        }
    }

    if (!empty($intents['recommend']) && !empty($destinations)) {
        $lines[] = '';
        $lines[] = 'Recommended destinations based on current data:';
        foreach (array_slice($destinations, 0, 4) as $d) {
            $lines[] = '- ' . ($d['name'] ?? 'Destination')
                . ' in ' . ($d['province'] ?? 'CALABARZON')
                . ' | Price: ' . ($d['price_label'] ?? 'n/a')
                . ' | Rating: ' . ($d['avg_rating'] ?? 'n/a');
        }
    }

    if (count($lines) === 1) {
        $lines[] = '';
        $lines[] = 'Please try again in about a minute, or ask for recommendations/weather to get DB-based answers while quota is limited.';
    }

    return implode("\n", $lines);
}

function extractDestinationIdsFromReply($replyText) {
    $ids = [];

    if (preg_match_all('/\[destination_id\s*:\s*(\d+)\]/i', $replyText, $matches)) {
        foreach ($matches[1] as $id) {
            $ids[] = (int) $id;
        }
    }

    if (preg_match_all('/\bID\s*[:#-]?\s*(\d+)\b/i', $replyText, $matches2)) {
        foreach ($matches2[1] as $id) {
            $ids[] = (int) $id;
        }
    }

    return array_values(array_unique(array_filter($ids)));
}

function loadDestinationsByIds($pdo, $ids) {
    if (empty($ids)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = 'SELECT d.id, d.name, d.slug, d.short_description, d.description, d.price_label, d.avg_rating,
                   d.latitude, d.longitude, p.name AS province_name
            FROM destinations d
            LEFT JOIN provinces p ON p.id = d.province_id
            WHERE d.id IN (' . $placeholders . ')';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($ids);
    return $stmt->fetchAll();
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    sendJson(['success' => false, 'error' => 'Method not allowed. Use POST.'], 405);
}

$body = readJsonInput();
$sessionToken = trim((string) ($body['session_token'] ?? ''));
$message = trim((string) ($body['message'] ?? ''));

if ($message === '') {
    sendJson(['success' => false, 'error' => 'message is required.'], 400);
}

if ($sessionToken === '') {
    $sessionToken = generateSessionToken();
}

try {
    $session = getOrCreateSession($pdo, $userId, $sessionToken);
    if (!$session) {
        sendJson(['success' => false, 'error' => 'Invalid session token for this user.'], 403);
    }

    $sessionId = (int) $session['id'];
    $history = fetchConversation($pdo, $sessionId);
    $intents = detectIntents($message);

    $contextBlocks = [];
    $recommendedRows = [];
    $favorites = [];
    $itineraryRows = [];
    $weatherData = [];
    $metadata = [
        'intents' => array_keys(array_filter($intents)),
        'destination_ids' => []
    ];

    if ($intents['weather']) {
        $weatherContext = getWeatherContext($message, $openWeatherApiKey);
        $contextBlocks[] = $weatherContext['text'];
        $weatherData = $weatherContext['data'];
        $metadata['weather'] = $weatherContext['data'];
    }

    if ($intents['forecast']) {
        $forecastContext = getForecastContext($message, $openWeatherApiKey);
        $contextBlocks[] = $forecastContext['text'];
        $metadata['forecast'] = $forecastContext['data'];
    }

    if ($intents['favorites']) {
        $favorites = getFavoritesData($pdo, $userId);
        $contextBlocks[] = 'User favorites (saved places): ' . json_encode($favorites);
        $metadata['favorites_count'] = count($favorites);
    }

    if ($intents['recommend']) {
        $recommendedRows = getRecommendationCandidates($pdo, $message);
        $contextBlocks[] = 'Recommendation candidates from DB: ' . json_encode($recommendedRows);
    }

    if ($intents['itinerary']) {
        $itineraryRows = getItineraryData($pdo, $userId);
        $contextBlocks[] = 'User itineraries: ' . json_encode($itineraryRows);
        $metadata['itineraries_count'] = count($itineraryRows);
    }

    $prompt = buildGeminiPrompt($message, $history, $contextBlocks);

    insertChatMessage($pdo, $sessionId, 'user', $message, null);

    $gemini = callGeminiFlash($geminiApiKey, $prompt, $geminiModel, parseCsvModels($geminiFallbackModels));
    $reply = '';
    $destinationIds = [];

    if ($gemini['ok']) {
        $reply = $gemini['text'];
        $destinationIds = extractDestinationIdsFromReply($reply);
    }

    if (empty($destinationIds) && !empty($recommendedRows)) {
        $destinationIds = array_map(function ($row) {
            return (int) ($row['id'] ?? 0);
        }, array_slice($recommendedRows, 0, 4));
        $destinationIds = array_values(array_unique(array_filter($destinationIds)));
    }

    $destinationRows = loadDestinationsByIds($pdo, $destinationIds);
    $destinations = formatDestinationsForResponse($destinationRows);

    if (empty($destinations) && !empty($recommendedRows) && $intents['recommend']) {
        $destinations = formatDestinationsForResponse(array_slice($recommendedRows, 0, 4));
        $destinationIds = array_map(function ($row) {
            return (int) ($row['id'] ?? 0);
        }, $destinations);
    }

    if (!$gemini['ok']) {
        $reply = buildFallbackReply($intents, $weatherData, $favorites, $itineraryRows, $destinations);
        $metadata['ai_fallback'] = true;
        $metadata['ai_error'] = $gemini['error'];
        $metadata['ai_error_type'] = isGeminiQuotaError($gemini['error']) ? 'quota' : 'api';
    }

    $metadata['destination_ids'] = $destinationIds;
    $metadata['destinations'] = $destinations;

    insertChatMessage($pdo, $sessionId, 'assistant', $reply, json_encode($metadata));

    $updatedHistory = fetchConversation($pdo, $sessionId);
    $contextPayload = array_map(function ($row) {
        return [
            'role' => $row['role'],
            'content' => $row['content'],
            'created_at' => $row['created_at']
        ];
    }, array_slice($updatedHistory, -30));
    updateSessionContext($pdo, $sessionId, $contextPayload);

    sendJson([
        'success' => true,
        'response' => $reply,
        'destinations' => $destinations,
        'session_token' => $sessionToken,
        'ai_available' => $gemini['ok'],
        'error' => $gemini['ok'] ? '' : $gemini['error']
    ]);
} catch (Exception $e) {
    sendJson([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage(),
        'response' => '',
        'destinations' => []
    ], 500);
}
