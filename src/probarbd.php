<?php
declare(strict_types=1);

/**
 * Prueba de integración CON base de datos: lee los lotes reales de MySQL y muestra qué
 * recomienda el motor para cada uno.
 * Ejecutar:  php pruebas/probar_bd.php
 * (configurar antes AROMA_DB_USER y AROMA_DB_PASS si no usas root sin contraseña)
 *
 * NOTA: la demanda real llega con el módulo Demanda (siguiente paso). Aquí se usa un
 * marcador de posición (demanda_total = 0), por lo que R17 sugerirá promocionar siempre
 * que haya platos candidatos. Sirve para probar la conexión, el repositorio y las reglas.
 */
require_once __DIR__ . '/../src/autoload.php';
require_once __DIR__ . '/../config/conexion.php';

$repo       = new Repositorio();
$motor      = new MotorReglas($repo->reglas(), $repo->parametros());
$cobertura  = $repo->coberturaPorInsumo();
$porciones  = $repo->porcionesPosibles();
$tipos      = array_column($repo->tiposDia(date('Y-m-d'), 3), 'tipo_dia');

// Mapa insumo_id => platos que lo usan (aristas del grafo, versión mínima)
$platosPorInsumo = [];
foreach ($repo->recetas() as $f) {
    $platosPorInsumo[$f['insumo_id']][$f['plato_id']] = [
        'nombre'             => $f['plato'],
        'demanda_total'      => 0.0,   // marcador: lo reemplaza Demanda
        'fc_demanda'         => 0.7,   // marcador
        'porciones_posibles' => $porciones[$f['plato_id']] ?? 0,
    ];
}

echo 'Fecha: ' . date('Y-m-d') . ' | tipo de dia (hoy, +1, +2): ' . implode(', ', $tipos) . "\n\n";

foreach ($repo->lotes() as $lote) {
    $ctx = [
        'cobertura_dias'    => $cobertura[$lote['insumo_id']]['cobertura_dias'] ?? INF,
        'tipo_dia_proximos' => $tipos,
        'platos'            => $platosPorInsumo[$lote['insumo_id']] ?? [],
    ];
    $r = $motor->inferir($lote, $ctx);

    printf("%-18s %7.0f g  vida %2d d  %-10s riesgo %-5s -> %s (FC %.3f)\n",
        $lote['insumo'], $lote['stock_actual'], $lote['dias_vida'], $lote['estado_lote'],
        $lote['categoria_riesgo'], $r['accion_principal'] ?? 'sin accion', $r['fc_principal']);
    echo '      reglas: ' . implode(', ', array_unique(array_column($r['traza'], 'regla'))) . "\n";
    foreach ($r['alertas'] as $a) {
        echo "      ALERTA: {$a}\n";
    }
}