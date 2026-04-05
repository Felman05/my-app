<?php
/**
 * CALABARZON province coordinates and text aliases.
 * Shared by api/weather.php and api/chatbot.php.
 */

// Keyed by slug — used for weather API calls
$PROVINCE_LOCATIONS = [
    'batangas' => ['name' => 'Batangas', 'lat' => 13.7565, 'lon' => 121.0583],
    'laguna'   => ['name' => 'Laguna',   'lat' => 14.1667, 'lon' => 121.3333],
    'cavite'   => ['name' => 'Cavite',   'lat' => 14.2826, 'lon' => 120.8687],
    'rizal'    => ['name' => 'Rizal',    'lat' => 14.6036, 'lon' => 121.3084],
    'quezon'   => ['name' => 'Quezon',   'lat' => 13.9411, 'lon' => 121.6223],
];

// Text aliases used for free-text matching in the chatbot
$PROVINCE_ALIASES = [
    'batangas' => ['batangas'],
    'laguna'   => ['laguna', 'calamba', 'santa rosa', 'los banos'],
    'cavite'   => ['cavite', 'tagaytay', 'imus', 'bacoor'],
    'rizal'    => ['rizal', 'antipolo', 'cainta', 'taytay'],
    'quezon'   => ['quezon', 'lucena'],
];
