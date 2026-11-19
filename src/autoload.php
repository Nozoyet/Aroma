<?php
declare(strict_types=1);


date_default_timezone_set('America/La_Paz');

spl_autoload_register(static function (string $clase): void {
    $ruta = __DIR__ . '/' . $clase . '.php';
    if (is_file($ruta)) {
        require_once $ruta;
    }
});