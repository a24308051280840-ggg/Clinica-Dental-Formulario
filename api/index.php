<?php
// ============================================================
//  Clínica Dental Galicia Argenis — Backend MongoDB
//  Requiere: composer require mongodb/mongodb
//  Autoload incluido automáticamente desde vendor/autoload.php
// ============================================================

require_once __DIR__ . '/../vendor/autoload.php';

// --- Configuración de conexión ---
$host     = 'mongodb://localhost:27017';
$dbName   = 'dentista';

try {
    $client = new MongoDB\Client($host);
    $db     = $client->selectDatabase($dbName);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'No se pudo conectar a MongoDB: ' . $e->getMessage()]);
    exit;
}

// --- Helpers ---
header('Content-Type: application/json; charset=utf-8');

function responder(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function bodyJson(): array {
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

// --- Enrutamiento simple ---
$method     = $_SERVER['REQUEST_METHOD'];
$coleccion  = $_GET['col'] ?? '';          // ?col=pacientes
$accion     = $_GET['accion'] ?? 'listar'; // ?accion=listar|insertar

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
    responder(['error' => "Colección '$coleccion' no válida. Opciones: " . implode(', ', $colecciones_validas)], 400);
}

$collection = $db->selectCollection($coleccion);

// ============================================================
//  GET  →  Listar todos los documentos
// ============================================================
if ($method === 'GET' && $accion === 'listar') {
    $cursor = $collection->find([], ['typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']]);
    $docs   = [];

    foreach ($cursor as $doc) {
        // Convertir ObjectId a string para JSON
        if (isset($doc['_id'])) {
            $doc['_id'] = (string) $doc['_id'];
        }
        $docs[] = $doc;
    }

    responder(['coleccion' => $coleccion, 'total' => count($docs), 'documentos' => $docs]);
}

// ============================================================
//  POST  →  Insertar un nuevo documento
// ============================================================
if ($method === 'POST' && $accion === 'insertar') {
    $data = bodyJson();

    if (empty($data)) {
        responder(['error' => 'El cuerpo de la petición está vacío o no es JSON válido.'], 400);
    }

    // Eliminar _id si viene vacío para que Mongo lo genere
    if (isset($data['_id']) && $data['_id'] === '') {
        unset($data['_id']);
    }

    try {
        $resultado = $collection->insertOne($data);
        responder([
            'mensaje'      => 'Documento insertado correctamente.',
            'insertedId'   => (string) $resultado->getInsertedId(),
            'coleccion'    => $coleccion,
        ], 201);
    } catch (Exception $e) {
        responder(['error' => 'Error al insertar: ' . $e->getMessage()], 500);
    }
}

// Si ninguna condición aplica
responder(['error' => 'Método o acción no soportados.'], 405);