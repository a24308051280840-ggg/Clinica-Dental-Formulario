<?php
require_once __DIR__ . '/../vendor/autoload.php';

// Headers primero — siempre, incluso si hay error
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$host   = 'mongodb+srv://Argenis_Galicia:argenis1234@cluster0.gmolxyq.mongodb.net/dentista?appName=Cluster0';
$dbName = 'dentista';

try {
    $client = new MongoDB\Client($host);
    $db     = $client->selectDatabase($dbName);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'No se pudo conectar a MongoDB: ' . $e->getMessage()]);
    exit;
}

// ... resto igual
