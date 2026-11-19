<?php
declare(strict_types=1);

/**
 * Datos de ejemplo (los mismos del script SQL) para probar sin base de datos.
 * Devuelve las recetas en el formato de Repositorio::recetas().
 */
function recetas_ejemplo(): array
{
    $insumos = [   // id => [nombre, costo Bs/g]
        1 => ['Lengua de res', 0.06], 2 => ['Pollo', 0.045], 3 => ['Trucha', 0.08], 4 => ['Calamar', 0.09],
        5 => ['Lechuga', 0.01], 6 => ['Tomate', 0.008], 7 => ['Mantequilla', 0.06], 8 => ['Aji chino', 0.04],
        9 => ['Salsa anticuchera', 0.04], 10 => ['Papa', 0.006], 11 => ['Quinua', 0.03], 12 => ['Mani', 0.025],
        13 => ['Tahini', 0.07], 14 => ['Pasta de curry', 0.12], 15 => ['Salsa thai', 0.05], 16 => ['Especias chinas', 0.15],
    ];
    $platos = [    // id => [nombre, precio, receta insumo_id => g]
        1 => ['Lengua de Res Picante', 140, [1 => 250, 16 => 5, 7 => 30, 13 => 20, 8 => 10]],
        2 => ['Lengua con Pure de Papa', 125, [1 => 250, 16 => 5, 7 => 30, 13 => 20, 8 => 10, 10 => 200, 12 => 20]],
        3 => ['Pollo Picante', 120, [2 => 250, 7 => 40, 8 => 15]],
        4 => ['Trucha al Curry', 145, [3 => 220, 14 => 25, 5 => 60, 6 => 50, 11 => 80]],
        5 => ['Calamar en Salsa Anticuchera', 150, [4 => 220, 9 => 50, 10 => 200, 8 => 10]],
        6 => ['Pollo Frito con Salsa Thai', 135, [2 => 250, 15 => 40, 5 => 60, 6 => 40, 10 => 150]],
    ];
    $filas = [];
    foreach ($platos as $pid => [$nombre, $precio, $receta]) {
        foreach ($receta as $iid => $g) {
            $filas[] = [
                'plato_id' => $pid, 'plato' => $nombre, 'precio_venta' => (float)$precio,
                'insumo_id' => $iid, 'insumo' => $insumos[$iid][0],
                'cantidad_por_porcion' => (float)$g, 'costo_unitario' => (float)$insumos[$iid][1],
            ];
        }
    }
    return $filas;
}

/** FC de R1..R22 como están en la tabla `regla`. */
function reglas_ejemplo(): array
{
    $fc = [
        'R1' => 1.00, 'R2' => 1.00, 'R3' => 1.00, 'R4' => 1.00, 'R5' => 1.00, 'R6' => 0.90, 'R7' => 0.95,
        'R8' => 0.90, 'R9' => 0.90, 'R10' => 0.80, 'R11' => 0.70, 'R12' => 0.95, 'R13' => 0.80, 'R14' => 0.80,
        'R15' => 0.90, 'R16' => 0.80, 'R17' => 0.80, 'R18' => 0.90, 'R19' => 1.00, 'R20' => 0.85,
        'R21' => 0.70, 'R22' => 0.90,
    ];
    $salida = [];
    foreach ($fc as $id => $v) {
        $salida[$id] = ['grupo' => '', 'condicion' => "condicion de {$id}", 'conclusion' => "conclusion de {$id}",
                        'fc_regla' => $v, 'activa' => true];
    }
    return $salida;
}