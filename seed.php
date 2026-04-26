<?php
/**
 * Demo Data Seeder
 * Creates 50 user accounts + 100+ records per transaction type
 * Run: http://localhost/doon-app/seed.php
 * Confirm: http://localhost/doon-app/seed.php?run=yes
 */

require_once 'includes/db.php';
require_once 'includes/functions.php';

set_time_limit(120);

if (($_GET['run'] ?? '') !== 'yes') { ?>
<!DOCTYPE html><html><head><title>Doon Seeder</title>
<style>body{font-family:sans-serif;max-width:560px;margin:60px auto;padding:0 20px;}
a.btn{display:inline-block;padding:10px 24px;background:#111;color:#fff;border-radius:6px;text-decoration:none;margin-top:16px;}
</style></head><body>
<h2>Doon Demo Seeder</h2>
<p>This will create:</p>
<ul>
  <li>50 user accounts (13 providers, 37 tourists)</li>
  <li>100+ records per transaction type</li>
</ul>
<p style="color:#b91c1c;"><strong>Note:</strong> Requires at least 1 active destination in the database.</p>
<a class="btn" href="?run=yes">Run Seeder</a>
</body></html>
<?php exit; }

// ── Guard: already seeded? ────────────────────────────────────────────────
$existing = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE email LIKE 'seed.%@doon.test'")->fetchColumn();
if ($existing > 0) {
    die("<pre>Already seeded ({$existing} seed users found). Delete them first if you want to re-seed.</pre>");
}

// ── Prerequisites ──────────────────────────────────────────────────────────
$destinations = $pdo->query('SELECT id, province_id FROM destinations WHERE is_active = 1')->fetchAll();
if (empty($destinations)) die('<pre>No active destinations found. Add destinations first.</pre>');
$destIds = array_column($destinations, 'id');

$provinces = $pdo->query('SELECT id, name FROM provinces')->fetchAll();
if (empty($provinces)) die('<pre>No provinces found.</pre>');
$provIds   = array_column($provinces, 'id');
$provNames = array_column($provinces, 'name', 'id');

$categories = $pdo->query('SELECT id FROM activity_categories')->fetchAll();
$catIds = !empty($categories) ? array_column($categories, 'id') : [1];

// Pre-hash one password for speed (used for all seed accounts)
echo "<pre>Hashing password...";
$hashedPw = password_hash('Doon@2025', PASSWORD_BCRYPT, ['cost' => 8]);
echo " done.\n";

// ── Helper ─────────────────────────────────────────────────────────────────
function pick(array $arr) { return $arr[array_rand($arr)]; }
function pickMany(array $arr, int $n): array {
    shuffle($arr);
    return array_slice($arr, 0, min($n, count($arr)));
}
function randDate(string $from = '-1 year', string $to = 'now'): string {
    return date('Y-m-d H:i:s', mt_rand(strtotime($from), strtotime($to)));
}
function randDateOnly(string $from = '-1 year', string $to = 'now'): string {
    return date('Y-m-d', mt_rand(strtotime($from), strtotime($to)));
}

// ── Data pools ─────────────────────────────────────────────────────────────
$calabarzonProvinces = ['Batangas', 'Laguna', 'Cavite', 'Rizal', 'Quezon'];
$municipalities = [
    'Batangas' => ['Batangas City', 'Lipa City', 'Nasugbu', 'Mabini', 'Taal', 'San Juan'],
    'Laguna'   => ['Calamba City', 'Santa Rosa', 'Los Baños', 'Pagsanjan', 'Pakil', 'Lumban'],
    'Cavite'   => ['Tagaytay City', 'Imus City', 'Bacoor City', 'Kawit', 'Maragondon', 'Silang'],
    'Rizal'    => ['Antipolo City', 'Cainta', 'Taytay', 'Tanay', 'Angono', 'Morong'],
    'Quezon'   => ['Lucena City', 'Tayabas', 'Atimonan', 'Mauban', 'Pagbilao', 'Sariaya'],
];

$touristNames = [
    'Maria Santos','Juan dela Cruz','Ana Reyes','Carlo Mendoza','Jessa Aquino',
    'Mark Villanueva','Christine Bautista','Ryan Delos Santos','Angelica Ramos','Daniel Castro',
    'Patricia Gonzales','Michael Flores','Jasmine Rivera','Kevin Hernandez','Maribel Concepcion',
    'Alvin Castillo','Lovely Navarro','Jose Dimaano','Shiela Gutierrez','Benedict Torres',
    'Camille Pascual','Rommel Aguilar','Fatima Macapagal','Gerson Panganiban','Hazel Ocampo',
    'Ivan Padilla','Jenny Espinosa','Kirk Delgado','Lara Enriquez','Marco Fernandez',
    'Nina Galvez','Oscar Hidalgo','Pearl Ignacio','Quirino Jaime','Rosa Lim',
    'Salvador Macaraeg','Theresa Dela Vega',
];

$providerData = [
    ['Tomas Reyes',       'Mang Tomas Beachfront Resort',  'accommodation',  'Batangas', 'Nasugbu'],
    ['Elena Natividad',   "Nena's Guesthouse",             'accommodation',  'Laguna',   'Los Baños'],
    ['Roberto Laguna',    'Laguna Eco Lodge',               'accommodation',  'Laguna',   'Calamba City'],
    ['Caridad Plata',     'Batangas Dive & Snorkel Center', 'tour_operator',  'Batangas', 'Mabini'],
    ['Fernando Mateo',    'Cavite Heritage Homestay',       'accommodation',  'Cavite',   'Kawit'],
    ['Gloria Serrano',    'Rizal Mountain Adventure Camp',  'tour_operator',  'Rizal',    'Tanay'],
    ['Hernando Quezon',   'Quezon Beachfront Cottages',     'accommodation',  'Quezon',   'Pagbilao'],
    ['Isidro Tagaytay',   'Tagaytay Ridge View Hotel',      'accommodation',  'Cavite',   'Tagaytay City'],
    ['Josefa Calamba',    'Calamba Food Hub & Catering',    'restaurant',     'Laguna',   'Calamba City'],
    ['Kristoffer Lucena', 'Lucena Bay Island Tours',        'tour_operator',  'Quezon',   'Lucena City'],
    ['Loreto Antipolo',   'Antipolo Garden Hotel',          'accommodation',  'Rizal',    'Antipolo City'],
    ['Mercedes Bacoor',   'Bacoor Heritage House',          'accommodation',  'Cavite',   'Bacoor City'],
    ['Nestor Laiya',      'Laiya Surf & Dive School',       'tour_operator',  'Batangas', 'San Juan'],
];

$genProfiles  = ['gen_z','millennial','gen_x','boomer'];
$budgetLabels = ['budget','mid_range','luxury'];
$travelStyles = ['solo','couple','family','group'];
$travelThemes = ['Beach & Island Hopping','Adventure & Trekking','Food Trip','Cultural Heritage','Nature & Eco-Tour','Romantic Getaway','Family Bonding','Budget Backpacking'];

$reviewTitles = [
    5 => ['Absolutely worth it!','Best experience in CALABARZON','Highly recommend!','A hidden gem','Will definitely come back','Perfect day trip','Exceeded expectations','Stunning place','10 out of 10','Breathtaking views'],
    4 => ['Great experience overall','Really enjoyable visit','Solid choice for a day trip','Very good, minor issues','Loved it, will return','Nice place, a bit crowded','Good value for money','Pleasant experience'],
    3 => ['Decent but could be better','It was okay','Average experience','Had some issues but manageable','Nothing extraordinary','Mixed feelings','Could use some improvements'],
    2 => ['Disappointing visit','Not what I expected','Needs a lot of improvement','Below average','Would not rush back'],
    1 => ['Very disappointing','Waste of time and money','Terrible experience','Would not recommend','Worst trip ever'],
];
$reviewBodies = [
    5 => [
        'One of the best destinations I have visited in CALABARZON. The scenery is absolutely stunning and the locals are very welcoming. Facilities are well-maintained and the food nearby is delicious. Perfect for a weekend getaway.',
        'Came here on a solo trip and had the time of my life. The place is immaculately clean and the staff are very helpful. The views are breathtaking especially during sunrise. Parking is available and entrance fee is very reasonable.',
        'Brought my family here for the holidays and everyone had a fantastic time. Kids loved the activities and the adults enjoyed the scenery. Will definitely come back for another visit.',
        'Such a peaceful escape from the city. The air is fresh and the surroundings are green and beautiful. Perfect for relaxation and photography. Highly recommend for anyone who needs a break.',
        'This place never disappoints. Been here three times already and each visit gets better. The amenities have improved a lot. Great job to the local tourism office for maintaining this spot.',
    ],
    4 => [
        'Great place to visit with the family. A bit crowded on weekends but the experience is still very enjoyable. The food stalls outside offer a good variety of local delicacies. Would visit again on a weekday.',
        'Overall a good experience. The entrance fee is reasonable and the facilities are decent. Just wish there were more trash bins in the area. The view from the top is definitely worth the hike.',
        'Nice destination but parking can be tricky during peak season. The spot itself is beautiful and well worth a visit. Local guides are knowledgeable and friendly. Four stars because of the limited amenities.',
        'Really enjoyed our visit here. The place is scenic and relaxing. Staff are courteous and helpful. Only minor issue was the queue at the entrance. Would recommend on weekdays for a more pleasant experience.',
    ],
    3 => [
        'The destination has potential but needs some improvements. The facilities are aging and maintenance could be better. The natural scenery saves it. Worth visiting once but maybe not again until they fix the infrastructure.',
        'Mixed experience. The place is beautiful but the management leaves something to be desired. Long queues and not enough staff. The destination itself is great but the overall experience drags it down to three stars.',
        'Visited on a holiday weekend and it was extremely crowded. Hard to enjoy the scenery when there are so many people. Would probably be better on a regular weekday. Average experience overall.',
    ],
    2 => [
        'Expected much more based on the photos online. The actual place is not as well-maintained as advertised. Facilities are outdated and the entrance fee seems too high for what is offered. Needs major improvements.',
        'The experience did not match the hype. The destination is nice in terms of nature but the amenities and services are below par. Overpriced given the lack of proper facilities.',
    ],
    1 => [
        'Very disappointing visit. The place was littered with trash and the facilities were in terrible condition. Staff were unhelpful and rude. Would not recommend spending your money here.',
        'Waste of time and money. The destination is nothing like the photos online. Overpriced entrance fee for a poorly maintained area. Will not be returning.',
    ],
];

$listingTitles = [
    'accommodation' => ['Beachfront Cottage','Mountain View Room','Private Villa','Family Suite','Backpacker Hostel','Glamping Tent','Garden Bungalow','Lake View Cabin','Heritage Bed & Breakfast','Budget Dormitory'],
    'tour_package'  => ['Island Hopping Tour','Waterfall Trekking Package','Heritage City Tour','Sunrise Hike Package','Kayaking Adventure','Cave Exploration Tour','Zipline & Rappel Package','Volcano Day Tour','River Cruise Tour','Farm & Nature Walk'],
    'restaurant'    => ['Traditional Filipino Breakfast','Beachside Seafood Grille','Mountain Coffee & Snacks','Kapampangan Buffet','Lutong Bahay Turo-Turo','Lakeside Grill & Bar','Farm-to-Table Restaurant','Streetfood Corner','Pancit & Halo-Halo Station','Kamayan Experience'],
    'transport'     => ['Airport Shuttle Service','Day Tour Van Rental','Banca Boat Hire','Tricycle Day Tour','UV Express to Highlands','Private Car Charter','Jeepney Heritage Tour','Motorcycle Rental','Bicycle Hire','Habal-habal Mountain Ride'],
    'event'         => ['Team Building Package','Debut & Wedding Venue','Company Outing Package','Beach Party Setup','School Field Trip Package','Photography Workshop','Cooking Class','Cultural Dance Workshop','Kayak Race Event','Night Market Bazaar'],
    'other'         => ['Travel Insurance Assistance','Souvenir & Pasalubong Shop','Local Guide Service','Currency Exchange','Luggage Storage','Laundry Service','Equipment Rental','First Aid Station','Massage & Wellness','Photography & Video Coverage'],
];

$itineraryTitles = [
    'Summer Beach Escape','Family Holiday in CALABARZON','Laguna Day Trip','Batangas Island Hopping',
    'Cavite Heritage Weekend','Rizal Nature Getaway','Quezon Province Road Trip','Solo Backpacking Tour',
    'Romantic Getaway to Tagaytay','Adventure Trek in Rizal','Food Trip Around Laguna',
    'Cultural Tour of Cavite','Volcano and Lake Day Trip','Waterfalls Hunting in Quezon',
    'Photography Trip to CALABARZON','Long Weekend Road Trip','School Break Itinerary',
    'New Year Holiday Escape','Holy Week Beach Trip','Summer Family Vacation',
];

// ── 1. CREATE LOCAL PROVIDER ACCOUNTS ─────────────────────────────────────
$providerUserIds    = [];
$providerProfileIds = [];
foreach ($providerData as $i => $pd) {
    [$pName, $bizName, $bizType, $province, $municipality] = $pd;
    $email = 'seed.provider' . ($i + 1) . '@doon.test';
    $pdo->prepare(
        'INSERT INTO users (name, email, password, role, is_active, data_privacy_consent, must_change_password, created_at)
         VALUES (?, ?, ?, "local", 1, 1, 0, ?)'
    )->execute([$pName, $email, $hashedPw, randDate('-8 months')]);
    $uid = (int) $pdo->lastInsertId();
    $providerUserIds[] = $uid;

    $pdo->prepare(
        'INSERT INTO local_provider_profiles
            (user_id, business_name, business_type, province, municipality, address, description, contact_number, is_verified, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $uid, $bizName, $bizType, $province, $municipality,
        $municipality . ', ' . $province,
        'Your trusted local partner for tourism services in ' . $province . '. We offer quality experiences for every type of traveler.',
        '09' . mt_rand(100000000, 999999999),
        pick([0, 0, 1, 1, 1]), // 60% verified
        randDate('-7 months'),
    ]);
    $providerProfileIds[] = (int) $pdo->lastInsertId();
}
echo count($providerUserIds) . " provider accounts created.\n";

// ── 2. CREATE TOURIST ACCOUNTS ─────────────────────────────────────────────
$touristIds = [];
foreach ($touristNames as $i => $tName) {
    $email = 'seed.tourist' . ($i + 1) . '@doon.test';
    $genProfile = pick($genProfiles);
    $budget     = pick($budgetLabels);
    $style      = pick($travelStyles);

    $pdo->prepare(
        'INSERT INTO users (name, email, password, role, is_active, data_privacy_consent, must_change_password, created_at)
         VALUES (?, ?, ?, "tourist", 1, 1, 0, ?)'
    )->execute([$tName, $email, $hashedPw, randDate('-10 months')]);
    $uid = (int) $pdo->lastInsertId();
    $touristIds[] = $uid;

    $pdo->prepare(
        'INSERT INTO tourist_profiles
            (user_id, generational_profile, preferred_budget, travel_style, location_tracking_consent, created_at)
         VALUES (?, ?, ?, ?, ?, ?)'
    )->execute([$uid, $genProfile, $budget, $style, pick([0, 1]), randDate('-10 months')]);
}
echo count($touristIds) . " tourist accounts created.\n";
echo "Total users: " . (count($providerUserIds) + count($touristIds)) . "\n\n";

// ── 3. PROVIDER LISTINGS (100+) ────────────────────────────────────────────
$listingInsert = $pdo->prepare(
    'INSERT INTO provider_listings
        (provider_id, listing_title, listing_type, description, price, price_label, capacity, contact_number, status, created_at, updated_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
$listingIds = [];
$listingTypes = array_keys($listingTitles);
foreach ($providerProfileIds as $profId) {
    $count = mt_rand(7, 10);
    for ($i = 0; $i < $count; $i++) {
        $type   = pick($listingTypes);
        $title  = pick($listingTitles[$type]);
        $price  = pick([null, mt_rand(200, 500) * 10, mt_rand(500, 2000) * 10, mt_rand(2000, 8000) * 10]);
        $priceL = $price === null ? 'free' : pick(['budget','mid_range','luxury']);
        $status = pick(['active','active','active','pending','inactive']);
        $created = randDate('-6 months');
        $listingInsert->execute([
            $profId, $title, $type,
            'Experience the best of CALABARZON with our ' . str_replace('_', ' ', $type) . ' service. ' .
            'We provide comfortable and memorable experiences tailored to all types of travelers.',
            $price, $priceL,
            pick([null, 5, 10, 15, 20, 30, 50]),
            '09' . mt_rand(100000000, 999999999),
            $status, $created, $created,
        ]);
        $listingIds[] = (int) $pdo->lastInsertId();
    }
}
echo count($listingIds) . " provider listings created.\n";

// ── 4. REVIEWS (100+) ──────────────────────────────────────────────────────
$reviewInsert = $pdo->prepare(
    'INSERT INTO reviews (user_id, destination_id, rating, title, body, visit_date, helpful_count, is_published, created_at, updated_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?)'
);
$reviewIds = [];
$usedReviewPairs = [];
foreach ($touristIds as $uid) {
    $myDests = pickMany($destIds, mt_rand(2, 5));
    foreach ($myDests as $did) {
        $key = $uid . '_' . $did;
        if (isset($usedReviewPairs[$key])) continue;
        $usedReviewPairs[$key] = true;
        $rating  = pick([1,2,3,3,4,4,4,5,5,5]);
        $title   = pick($reviewTitles[$rating]);
        $body    = pick($reviewBodies[min($rating, max(array_keys($reviewBodies)))]);
        $created = randDate('-9 months');
        $reviewInsert->execute([
            $uid, $did, $rating, $title, $body,
            randDateOnly('-1 year', '-1 day'),
            mt_rand(0, 20),
            $created, $created,
        ]);
        $reviewIds[] = (int) $pdo->lastInsertId();
        if (count($reviewIds) >= 150) break 2;
    }
}
echo count($reviewIds) . " reviews created.\n";

// ── 5. FAVORITES (100+) ────────────────────────────────────────────────────
$favInsert = $pdo->prepare('INSERT IGNORE INTO favorites (user_id, destination_id, created_at) VALUES (?, ?, ?)');
$favCount  = 0;
foreach ($touristIds as $uid) {
    foreach (pickMany($destIds, mt_rand(2, 5)) as $did) {
        $favInsert->execute([$uid, $did, randDate('-8 months')]);
        $favCount++;
        if ($favCount >= 150) break 2;
    }
}
echo "{$favCount} favorites created.\n";

// ── 6. ITINERARIES + ITEMS (100+ each) ────────────────────────────────────
$itinInsert = $pdo->prepare(
    'INSERT INTO itineraries
        (user_id, title, description, start_date, end_date, total_days, budget_label, number_of_people, travel_theme, generational_profile, status, created_at, updated_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
$itemInsert = $pdo->prepare(
    'INSERT INTO itinerary_items (itinerary_id, destination_id, day_number, order_index, notes, created_at)
     VALUES (?, ?, ?, ?, ?, ?)'
);
$itinIds   = [];
$itemCount = 0;
$statuses  = ['draft','planned','planned','completed','completed'];
foreach ($touristIds as $uid) {
    $numItins = mt_rand(2, 5);
    for ($i = 0; $i < $numItins; $i++) {
        $days       = mt_rand(1, 4);
        $startDate  = randDateOnly('-9 months');
        $endDate    = date('Y-m-d', strtotime($startDate) + ($days - 1) * 86400);
        $created    = randDate('-9 months');
        $itinInsert->execute([
            $uid,
            pick($itineraryTitles),
            'A well-planned trip covering the best spots in CALABARZON. Activities include sightseeing, local food, and nature adventures.',
            $startDate, $endDate, $days,
            pick($budgetLabels),
            mt_rand(1, 8),
            pick($travelThemes),
            pick($genProfiles),
            pick($statuses),
            $created, $created,
        ]);
        $itinId   = (int) $pdo->lastInsertId();
        $itinIds[] = $itinId;

        $stopDests = pickMany($destIds, mt_rand(2, min(5, $days * 3)));
        foreach ($stopDests as $order => $did) {
            $dayNum = ($order % $days) + 1;
            $itemInsert->execute([
                $itinId, $did, $dayNum, $order,
                pick([null, null, 'Must visit early morning.', 'Bring snacks.', 'Check opening hours before going.', 'Book in advance.']),
                $created,
            ]);
            $itemCount++;
        }
        if (count($itinIds) >= 130) break 2;
    }
}
echo count($itinIds) . " itineraries created.\n";
echo "{$itemCount} itinerary items created.\n";

// ── 7. ANALYTICS EVENTS (100+ each type) ──────────────────────────────────
$eventInsert = $pdo->prepare(
    'INSERT INTO analytics_events (user_id, event_type, metadata, created_at) VALUES (?, ?, ?, ?)'
);
$viewCount  = 0;
$itinEvCount = 0;
foreach ($touristIds as $uid) {
    foreach (pickMany($destIds, mt_rand(3, 8)) as $did) {
        $eventInsert->execute([$uid, 'destination_view', json_encode(['destination_id' => $did]), randDate('-9 months')]);
        $viewCount++;
    }
    foreach (pickMany($itinIds, mt_rand(1, 3)) as $iid) {
        $eventInsert->execute([$uid, 'itinerary_created', json_encode(['itinerary_id' => $iid]), randDate('-9 months')]);
        $itinEvCount++;
    }
}
echo "{$viewCount} destination_view events created.\n";
echo "{$itinEvCount} itinerary_created events created.\n";

// ── 8. RECOMMENDATION REQUESTS + RESULTS (100+) ───────────────────────────
$recReqInsert = $pdo->prepare(
    'INSERT INTO recommendation_requests
        (user_id, budget_label, number_of_people, trip_duration_days, province_id, generational_profile, results_count, response_time_ms, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
$recResInsert = $pdo->prepare(
    'INSERT INTO recommendation_results (request_id, destination_id, score, rank_position, created_at)
     VALUES (?, ?, ?, ?, ?)'
);
$reqCount = 0;
$resCount = 0;
foreach ($touristIds as $uid) {
    $numReqs = mt_rand(2, 5);
    for ($i = 0; $i < $numReqs; $i++) {
        $recReqInsert->execute([
            $uid,
            pick($budgetLabels),
            mt_rand(1, 6),
            mt_rand(1, 4),
            pick(array_merge($provIds, [null])),
            pick(array_merge($genProfiles, [null])),
            $numRes = mt_rand(3, 8),
            mt_rand(80, 600),
            randDate('-8 months'),
        ]);
        $reqId = (int) $pdo->lastInsertId();
        $reqCount++;
        $resDests = pickMany($destIds, $numRes);
        $total    = count($resDests);
        foreach ($resDests as $rank => $did) {
            $recResInsert->execute([$reqId, $did, round(1 - ($rank / $total), 4), $rank + 1, randDate('-8 months')]);
            $resCount++;
        }
        if ($reqCount >= 120) break 2;
    }
}
echo "{$reqCount} recommendation requests created.\n";
echo "{$resCount} recommendation results created.\n";

// ── 9. REVIEW HELPFUL VOTES (100+) ───────────────────────────────────────
$voteInsert = $pdo->prepare('INSERT IGNORE INTO review_helpful_votes (review_id, user_id, created_at) VALUES (?, ?, ?)');
$voteCount  = 0;
$reviewSample = pickMany($reviewIds, min(60, count($reviewIds)));
foreach ($touristIds as $uid) {
    foreach (pickMany($reviewSample, mt_rand(2, 5)) as $rid) {
        $voteInsert->execute([$rid, $uid, randDate('-7 months')]);
        $voteCount++;
        if ($voteCount >= 150) break 2;
    }
}
echo "{$voteCount} review helpful votes created.\n";

// ── 10. CHATBOT SESSIONS + MESSAGES (100+) ────────────────────────────────
$sessInsert = $pdo->prepare(
    'INSERT INTO chatbot_sessions (user_id, session_token, context, created_at, updated_at) VALUES (?, ?, ?, ?, ?)'
);
$msgInsert = $pdo->prepare(
    'INSERT INTO chatbot_messages (session_id, role, content, created_at) VALUES (?, ?, ?, ?)'
);
$chatQuestions = [
    'What are the best beaches in Batangas?',
    'Can you recommend places to visit in Laguna?',
    'What is there to do in Tagaytay?',
    'How do I get to Pagsanjan Falls?',
    'Best food trips in Quezon province?',
    'What are the top hiking spots in Rizal?',
    'How much is the entrance fee at Enchanted Kingdom?',
    'Is Masungi Georeserve safe for beginners?',
    'What is the best time to visit Batangas?',
    'Are there budget-friendly resorts in Cavite?',
];
$chatReplies = [
    'Batangas has some of the best beaches in Luzon! Top picks include Masasa Beach, Laiya Beach, and Calatagan. All offer crystal clear waters and vibrant marine life.',
    'Laguna is packed with great spots! Do not miss Pagsanjan Falls, Hidden Valley Springs, and Enchanted Kingdom in Santa Rosa.',
    'Tagaytay is famous for its cool climate and the stunning view of Taal Volcano. Must-visits include People\'s Park, Sky Ranch, and the many paluto restaurants.',
    'You can take a bus from Manila to Santa Cruz, Laguna then hire a tricycle to Pagsanjan. The boat ride through the gorge is part of the amazing experience!',
    'Quezon province is a food lover\'s paradise! Try Lucban for longganisa and pansit habhab, Tayabas for moron, and the fresh seafood along the coast.',
    'Rizal offers great hiking options. Masungi Georeserve is a must, Wawa Dam is scenic, and the trails in Tanay lead to beautiful waterfalls.',
    'Entrance to Enchanted Kingdom starts around PHP 700-800 per adult. Prices vary by season so check their website for the latest rates.',
    'Masungi Georeserve is suitable for most fitness levels, but book way in advance as slots fill up fast. The trail is well-maintained and guides are provided.',
    'The best time to visit Batangas is during the dry season, from November to May. Holy Week and summer weekends can be very crowded.',
    'Yes! Cavite has several budget-friendly options especially around Tagaytay and Bacoor. Look for transient homes and small inns for affordable stays.',
];
$sessCount = 0;
$msgCount  = 0;
foreach ($touristIds as $uid) {
    $numSess = mt_rand(1, 3);
    for ($s = 0; $s < $numSess; $s++) {
        $token   = bin2hex(random_bytes(16));
        $created = randDate('-7 months');
        $sessInsert->execute([$uid, $token, json_encode([]), $created, $created]);
        $sessId = (int) $pdo->lastInsertId();
        $sessCount++;

        $numExchanges = mt_rand(2, 5);
        for ($e = 0; $e < $numExchanges; $e++) {
            $idx = array_rand($chatQuestions);
            $msgInsert->execute([$sessId, 'user', $chatQuestions[$idx], $created]);
            $msgInsert->execute([$sessId, 'assistant', $chatReplies[$idx], $created]);
            $msgCount += 2;
        }
        if ($sessCount >= 60) break 2;
    }
}
echo "{$sessCount} chatbot sessions created.\n";
echo "{$msgCount} chatbot messages created.\n";

// ── 11. ADMIN ACTIVITY LOGS (100+) ────────────────────────────────────────
$logInsert = $pdo->prepare(
    'INSERT INTO admin_activity_logs (admin_id, action, model_type, model_id, description, created_at)
     VALUES (?, ?, ?, ?, ?, ?)'
);
$adminActions = [
    ['create_destination', 'destination', 'Created new destination record'],
    ['toggle_is_active',   'destination', 'Toggled active status on destination'],
    ['toggle_is_featured', 'destination', 'Toggled featured status on destination'],
    ['approve_listing',    'provider_listing', 'Approved provider listing'],
    ['reject_listing',     'provider_listing', 'Rejected provider listing'],
    ['create_user',        'user',         'Created new provider account'],
    ['toggle_user_active', 'user',         'Toggled user account status'],
    ['verify_provider',    'user',         'Verified local provider profile'],
    ['upload_province_image', 'province',  'Uploaded landing image for province'],
    ['toggle_review',      'review',       'Toggled review published status'],
    ['delete_review',      'review',       'Deleted inappropriate review'],
];
$logCount = 0;
$adminActorIds = $pdo->query('SELECT id FROM users WHERE role = "admin"')->fetchAll(PDO::FETCH_COLUMN);
while ($logCount < 120) {
    $action = pick($adminActions);
    $modelId = pick(array_merge($destIds, $listingIds, $touristIds));
    $logInsert->execute([
        pick($adminActorIds),
        $action[0], $action[1], $modelId,
        $action[2] . ' #' . $modelId,
        randDate('-6 months'),
    ]);
    $logCount++;
}
echo "{$logCount} admin activity logs created.\n";

// ── Done ───────────────────────────────────────────────────────────────────
echo "\n✓ Seeding complete!\n";
echo "Login with any seed account using password: Doon@2025\n";
echo "Email format: seed.tourist1@doon.test, seed.provider1@doon.test\n";
