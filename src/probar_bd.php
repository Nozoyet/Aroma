<?php
declare(strict_types=1);

/**
 * Prueba de integración CON MySQL: ejecuta el sistema completo sobre los datos reales de aroma_se
 * (equivale a enviar el formulario sin cambios). Guarda una consulta en el historial.
 * Ejecutar:  php pruebas/probar_bd.php
 * Si no usas root sin contraseña:  AROMA_DB_USER=... AROMA_DB_PASS=... php pruebas/probar_bd.php
 */
require_once __DIR__ . '/../src/autoload.php';
require_once __DIR__ . '/../config/conexion.php';

$repo = new Repositorio();
$est  = $repo->estadisticasDemanda(date('Y-m-d'), 90);
$n    = array_sum(array_map(static fn(array $p): int => array_sum(array_column($p, 'n')), $est));
echo "Ventas usadas para la demanda (últimos 90 días): {$n} registros de " . count($est) . " platos.\n";
if ($n === 0) {
    echo "AVISO: no hay ventas recientes; carga aroma_ventas_simuladas_mysql.sql con fechas que lleguen a ayer.\n";
}

$res = (new Sistema($repo))->ejecutar(['tipo' => 'plan_3_dias', 'observaciones' => 'prueba de integración']);

echo 'Consulta #' . $res['consulta_id'] . ' | ' . $res['fecha'] . ' | tipo de día: '
    . implode(', ', array_column($res['tipos_dia'], 'tipo_dia')) . "\n\n";

foreach ($res['resultados'] as $r) {
    $l = $r['lote'];
    printf("%-18s lote %-3d %8.0f g  vida %2d d -> %-16s FC %.3f\n", $l['insumo'], $l['lote_id'],
        $l['stock_actual'], $l['dias_vida'], $r['accion'] ?? 'sin accion', $r['fc']);
    foreach ($r['filas'] as $f) {
        echo "      {$f}\n";
    }
    foreach ($r['alertas'] as $a) {
        echo "      ALERTA: {$a}\n";
    }
}
echo "\nAhorro total estimado: {$res['ahorro_total']} Bs\n";