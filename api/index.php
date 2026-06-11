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

// --- Helpers ---

// Función utilitaria que serializa un array como JSON y termina el script
function responder(array $data, int $code = 200): void {
    // Establece el código de estado HTTP de la respuesta (ej. 200, 201, 400, 500)
    http_response_code($code);

    // Convierte el array a JSON; JSON_UNESCAPED_UNICODE evita escapar tildes/ñ,
    // JSON_PRETTY_PRINT formatea el JSON con sangría para facilitar su lectura
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    // Termina la ejecución del script tras enviar la respuesta
    exit;
}

// Función que lee y decodifica el cuerpo de la petición HTTP como JSON
function bodyJson(): array {
    // Lee el cuerpo crudo de la petición (usado en POST con Content-Type: application/json)
    $raw = file_get_contents('php://input');

    // Decodifica el JSON a un array asociativo de PHP
    $decoded = json_decode($raw, true);

    // Retorna el array si la decodificación fue exitosa, o un array vacío si falló
    return is_array($decoded) ? $decoded : [];
}

// --- Enrutamiento ---

// Obtiene el método HTTP de la petición actual (GET, POST, OPTIONS, etc.)
$method = $_SERVER['REQUEST_METHOD'];

// Lee el parámetro "col" de la URL para saber a qué colección apuntar;
// si no se envía, usa cadena vacía como valor por defecto
$coleccion = $_GET['col'] ?? '';

// Lee el parámetro "accion" de la URL para saber qué operación ejecutar;
// "listar" es la acción por defecto si no se especifica ninguna
$accion = $_GET['accion'] ?? 'listar';

// Lista blanca de colecciones permitidas para prevenir accesos no autorizados
// a otras colecciones que pudieran existir en la base de datos
$colecciones_validas = [
    'pacientes',        // Datos personales de los pacientes
    'empleados',        // Información del personal de la clínica
    'pagos',            // Registros de pagos realizados
    'citas',            // Citas agendadas por los pacientes
    'tratamiento',      // Tratamientos dentales aplicados
    'historial_clinico', // Historial médico de cada paciente
];

// Verifica que la colección recibida esté en la lista blanca
// true como tercer argumento activa la comparación estricta (tipo + valor)
if (!in_array($coleccion, $colecciones_validas, true)) {
    // Si la colección no es válida, responde con error 400 (Bad Request)
    // e incluye las opciones válidas para orientar al desarrollador
    responder([
        'error'    => "Colección '$coleccion' no válida.",
        'opciones' => $colecciones_validas
    ], 400);
}

// Obtiene un objeto Collection de MongoDB apuntando a la colección solicitada
$collection = $db->selectCollection($coleccion);

// ============================================================
//  GET → Listar documentos
// ============================================================

// Maneja las peticiones GET con acción "listar"
if ($method === 'GET' && $accion === 'listar') {

    // Ejecuta una consulta find sin filtros para obtener todos los documentos;
    // typeMap fuerza que los resultados sean arrays PHP en lugar de objetos BSON
    $cursor = $collection->find([], [
        'typeMap' => [
            'root'     => 'array', // El documento raíz se convierte a array PHP
            'document' => 'array', // Los sub-documentos embebidos también serán arrays
            'array'    => 'array', // Los arrays BSON se convierten a arrays PHP
        ]
    ]);

    // Array donde se acumularán los documentos procesados
    $docs = [];

    // Itera sobre cada documento devuelto por el cursor de MongoDB
    foreach ($cursor as $doc) {
        // Verifica si el documento tiene el campo "_id" (ObjectId de MongoDB)
        if (isset($doc['_id'])) {
            // Convierte el ObjectId de MongoDB a string para poder serializarlo en JSON
            $doc['_id'] = (string) $doc['_id'];
        }

        // Agrega el documento procesado al array de resultados
        $docs[] = $doc;
    }

    // Responde con un JSON que incluye el nombre de la colección,
    // el total de documentos encontrados y el array de documentos
    responder([
        'coleccion'  => $coleccion,  // Nombre de la colección consultada
        'total'      => count($docs), // Cantidad total de documentos recuperados
        'documentos' => $docs,        // Array con todos los documentos
    ]);
}

// ============================================================
//  POST → Insertar documento
// ============================================================

// Maneja las peticiones POST con acción "insertar"
if ($method === 'POST' && $accion === 'insertar') {

    // Lee y decodifica el cuerpo JSON de la petición
    $data = bodyJson();

    // Verifica que el cuerpo no esté vacío; si lo está, responde con error 400
    if (empty($data)) {
        responder(['error' => 'El cuerpo está vacío o no es JSON válido.'], 400);
    }

    // Si el cliente envió un campo "_id" vacío, lo elimina para que MongoDB
    // genere automáticamente un ObjectId único en lugar de insertar uno nulo
    if (isset($data['_id']) && $data['_id'] === '') {
        unset($data['_id']);
    }

    // Bloque try/catch para capturar errores durante la inserción
    try {
        // Inserta el documento en la colección seleccionada de MongoDB
        $resultado = $collection->insertOne($data);

        // Responde con código 201 (Created) e incluye el ID generado por MongoDB
        responder([
            'mensaje'    => 'Documento insertado correctamente.',
            'insertedId' => (string) $resultado->getInsertedId(), // ObjectId como string
            'coleccion'  => $coleccion, // Nombre de la colección donde se insertó
        ], 201);

    } catch (Exception $e) {
        // Si MongoDB lanza un error al insertar, responde con código 500
        // e incluye el mensaje de la excepción para facilitar la depuración
        responder(['error' => 'Error al insertar: ' . $e->getMessage()], 500);
    }
}

// Si el método o la acción no coinciden con ningún bloque anterior,
// responde con código 405 (Method Not Allowed) indicando que no está soportado
responder(['error' => 'Método o acción no soportados.'], 405);
