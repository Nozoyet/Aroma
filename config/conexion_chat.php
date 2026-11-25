<?php
declare(strict_types=1);

/**
 * Conexión de SOLO LECTURA para el chat.
 * Usa el usuario MySQL `aroma_chat` (ver sql/chat_usuario.sql), que solo tiene SELECT.
 * Si AROMA_CHAT_DB_USER no está definida (desarrollo local) cae a la conexión principal y lo avisa en el log.
 */
function conexionChat(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $usuario = getenv('AROMA_CHAT_DB_USER');
    if ($usuario === false || $usuario === '') {
        error_log('[Aroma][chat] AROMA_CHAT_DB_USER no definida: se usa la conexión principal (solo desarrollo).');
        return $pdo = conexion();
    }

    $host   = getenv('AROMA_DB_HOST') ?: '127.0.0.1';
    $puerto = getenv('AROMA_DB_PORT') ?: '3306';
    $base   = getenv('AROMA_DB_NAME') ?: 'aroma_se';
    $clave  = getenv('AROMA_CHAT_DB_PASS');
    $clave  = ($clave === false) ? '' : $clave;

    try {
        $pdo = new PDO("mysql:host={$host};port={$puerto};dbname={$base};charset=utf8mb4", $usuario, $clave, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $pdo->exec("SET time_zone = '-04:00'");
        $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec('SET SESSION TRANSACTION READ ONLY');
    } catch (PDOException $e) {
        error_log('[Aroma][chat] Error de conexión: ' . $e->getMessage());
        throw new RuntimeException('No se pudo conectar a la base de datos.', 0, $e);
    }
    return $pdo;
}
