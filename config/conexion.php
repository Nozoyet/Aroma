<?php
declare(strict_types=1);


function conexion(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host    = getenv('AROMA_DB_HOST') ?: '127.0.0.1';
    $puerto  = getenv('AROMA_DB_PORT') ?: '3307';
    $base    = getenv('AROMA_DB_NAME') ?: 'aroma_se';
    $usuario = getenv('AROMA_DB_USER') ?: 'root';
    $clave   = getenv('AROMA_DB_PASS');
    $clave   = ($clave === false) ? '' : $clave;

    $dsn = "mysql:host={$host};port={$puerto};dbname={$base};charset=utf8mb4";

    try {
        $pdo = new PDO($dsn, $usuario, $clave, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,   
        ]);
        
        $pdo->exec("SET time_zone = '-04:00'");
        $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    } catch (PDOException $e) {
    
        error_log('[Aroma] Error de conexión: ' . $e->getMessage());
        throw new RuntimeException('No se pudo conectar a la base de datos.', 0, $e);
    }

    return $pdo;
}