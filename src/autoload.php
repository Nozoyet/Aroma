<?php
declare(strict_types=1);

/**
 * Cargador automático: cada clase vive en src/NombreClase.php (sin namespaces).
 * Uso:  require_once __DIR__ . '/../src/autoload.php';
 */
date_default_timezone_set('America/La_Paz');

spl_autoload_register(static function (string $clase): void {
    $ruta = __DIR__ . '/' . $clase . '.php';
    if (is_file($ruta)) {
        require_once $ruta;
    }
});
