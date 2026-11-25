<?php
declare(strict_types=1);

/**
 * Endpoint del chat: POST JSON {"mensaje": "..."} + cabecera X-CSRF-Token.
 * Responde {"texto": "...", "emocion": "ok", "intencion": "stock"}.
 * Es independiente de index.php: si algo aquí falla, el sistema principal no se entera.
 */
session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function chat_salir(array $datos, int $codigo = 200): never
{
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    chat_salir(['texto' => 'Método no permitido.', 'emocion' => 'cry'], 405);
}

$token = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (empty($_SESSION['csrf']) || !hash_equals((string)$_SESSION['csrf'], $token)) {
    chat_salir(['texto' => 'La sesión caducó. Recarga la página e inténtalo de nuevo.', 'emocion' => 'cry'], 403);
}

// Límite simple por sesión: 20 mensajes por minuto.
$ahora = time();
$marcas = array_values(array_filter((array)($_SESSION['chat_marcas'] ?? []), static fn($t): bool => is_int($t) && $t > $ahora - 60));
if (count($marcas) >= 20) {
    $_SESSION['chat_marcas'] = $marcas;
    chat_salir(['texto' => 'Vas muy rápido 😅. Espera unos segundos y vuelve a preguntar.', 'emocion' => 'shocked'], 429);
}
$marcas[] = $ahora;
$_SESSION['chat_marcas'] = $marcas;
session_write_close();

$entrada = json_decode((string)file_get_contents('php://input'), true);
$mensaje = is_array($entrada) ? trim((string)($entrada['mensaje'] ?? '')) : '';
if ($mensaje === '') {
    chat_salir(['texto' => 'Escribe una pregunta y te ayudo.', 'emocion' => 'think', 'intencion' => 'vacio']);
}
$mensaje = mb_substr($mensaje, 0, 300);

try {
    $raiz = dirname(__DIR__);
    require_once $raiz . '/src/autoload.php';
    require_once $raiz . '/config/conexion.php';
    require_once $raiz . '/config/conexion_chat.php';
    foreach (['lecturachat', 'repositoriochat', 'intencionchat', 'reaccionmascota', 'chataroma'] as $f) {
        require_once $raiz . "/src/{$f}.php";
    }
    $chat = new ChatAroma(new RepositorioChat());
    chat_salir($chat->responder($mensaje));
} catch (Throwable $e) {
    error_log('[Aroma][chat] ' . $e->getMessage());
    chat_salir(['texto' => 'No pude consultar los datos en este momento. Inténtalo de nuevo en un rato.', 'emocion' => 'cry', 'intencion' => 'error'], 500);
}
