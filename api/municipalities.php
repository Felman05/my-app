<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

if (!isAuthenticated()) {
    http_response_code(401);
    exit;
}

header('Content-Type: application/json');

$provinceId = (int) ($_GET['province_id'] ?? 0);
if (!$provinceId) {
    echo json_encode([]);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT id, name FROM municipalities WHERE province_id = ? ORDER BY name');
    $stmt->execute([$provinceId]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (PDOException $e) {
    echo json_encode([]);
}
