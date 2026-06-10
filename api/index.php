<?php
// ============================================================
//  Clínica Dental Galicia Argenis — Backend MongoDB
//  Requiere: composer require mongodb/mongodb
// ============================================================

require_once __DIR__ . '/../vendor/autoload.php';

// --- Configuración de conexión ---
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

// --- Headers CORS (necesario para Railway) ---
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// --- Helpers ---
function responder(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function bodyJson(): array {
    $raw     = file_get_contents('php://input');
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

// --- Enrutamiento ---
$method    = $_SERVER['REQUEST_METHOD'];
$coleccion = $_GET['col']    ?? '';
$accion    = $_GET['accion'] ?? 'listar';

// Colecciones permitidas
$colecciones_validas = [
    'pacientes',
    'empleados',
    'pagos',
    'citas',
    'tratamiento',
    'historial_clinico',
];

if (!in_array($coleccion, $colecciones_validas, true)) {
    responder([
        'error'   => "Colección '$coleccion' no válida.",
        'opciones' => $colecciones_validas
    ], 400);
}

$collection = $db->selectCollection($coleccion);

// ============================================================
//  GET → Listar documentos
// ============================================================
if ($method === 'GET' && $accion === 'listar') {
    $cursor = $collection->find([], [
        'typeMap' => [
            'root'     => 'array',
            'document' => 'array',
            'array'    => 'array',
        ]
    ]);

    $docs = [];
    foreach ($cursor as $doc) {
        if (isset($doc['_id'])) {
            $doc['_id'] = (string) $doc['_id'];
        }
        $docs[] = $doc;
    }

    responder([
        'coleccion'  => $coleccion,
        'total'      => count($docs),
        'documentos' => $docs,
    ]);
}

// ============================================================
//  POST → Insertar documento
// ============================================================
if ($method === 'POST' && $accion === 'insertar') {
    $data = bodyJson();

    if (empty($data)) {
        responder(['error' => 'El cuerpo está vacío o no es JSON válido.'], 400);
    }

    if (isset($data['_id']) && $data['_id'] === '') {
        unset($data['_id']);
    }

    try {
        $resultado = $collection->insertOne($data);
        responder([
            'mensaje'    => 'Documento insertado correctamente.',
            'insertedId' => (string) $resultado->getInsertedId(),
            'coleccion'  => $coleccion,
        ], 201);
    } catch (Exception $e) {
        responder(['error' => 'Error al insertar: ' . $e->getMessage()], 500);
    }
}

// Método no soportado
responder(['error' => 'Método o acción no soportados.'], 405);
