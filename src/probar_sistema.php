<?php
declare(strict_types=1);

/**
 * Prueba de extremo a extremo (formulario -> reglas -> DFS/A* -> explicación -> historial)
 * con un repositorio en memoria, sin MySQL.
 * Ejecutar:  php pruebas/probar_sistema.php
 */
require_once __DIR__ . '/../src/autoload.php';
require_once __DIR__ . '/datos_ejemplo.php';
require_once __DIR__ . '/RepositorioMemoria.php';

$fallos = 0;
function verificar(string $d, bool $ok): void
{
    global $fallos;
    echo ($ok ? '  [OK]    ' : '  [FALLA] ') . $d . "\n";
    $fallos += $ok ? 0 : 1;
}

$insumos = [
    1 => ['nombre' => 'Lengua de res', 'costo_unitario' => 0.06, 'categoria' => 'alto'],
    2 => ['nombre' => 'Pollo', 'costo_unitario' => 0.045, 'categoria' => 'alto'],
    3 => ['nombre' => 'Trucha', 'costo_unitario' => 0.08, 'categoria' => 'alto'],
    4 => ['nombre' => 'Calamar', 'costo_unitario' => 0.09, 'categoria' => 'alto'],
    5 => ['nombre' => 'Lechuga', 'costo_unitario' => 0.01, 'categoria' => 'medio'],
    6 => ['nombre' => 'Tomate', 'costo_unitario' => 0.008, 'categoria' => 'medio'],
    7 => ['nombre' => 'Mantequilla', 'costo_unitario' => 0.06, 'categoria' => 'medio'],
    8 => ['nombre' => 'Aji chino', 'costo_unitario' => 0.04, 'categoria' => 'medio'],
    9 => ['nombre' => 'Salsa anticuchera', 'costo_unitario' => 0.04, 'categoria' => 'medio'],
    10 => ['nombre' => 'Papa', 'costo_unitario' => 0.006, 'categoria' => 'bajo'],
    11 => ['nombre' => 'Quinua', 'costo_unitario' => 0.03, 'categoria' => 'bajo'],
    12 => ['nombre' => 'Mani', 'costo_unitario' => 0.025, 'categoria' => 'bajo'],
    13 => ['nombre' => 'Tahini', 'costo_unitario' => 0.07, 'categoria' => 'bajo'],
    14 => ['nombre' => 'Pasta de curry', 'costo_unitario' => 0.12, 'categoria' => 'bajo'],
    15 => ['nombre' => 'Salsa thai', 'costo_unitario' => 0.05, 'categoria' => 'bajo'],
    16 => ['nombre' => 'Especias chinas', 'costo_unitario' => 0.15, 'categoria' => 'bajo'],
];
// [lote_id, insumo_id, stock g, dias_vida]
$def = [
    [1, 1, 15000, 2], [2, 2, 3000, 0], [3, 4, 1000, 0], [4, 10, 5000, -1], [5, 5, 1000, 5], [6, 3, 3000, 4],
    [7, 8, 5000, 10], [8, 7, 9000, 10], [9, 16, 2000, 30], [10, 13, 9000, 30], [11, 12, 3000, 30], [12, 9, 3000, 10],
    [13, 11, 3000, 30], [14, 14, 3000, 30], [15, 15, 3000, 30], [16, 6, 6000, 6], [17, 10, 20000, 10],
];
$lotes = [];
foreach ($def as [$id, $ins, $stock, $dias]) {
    $lotes[] = ['lote_id' => $id, 'insumo_id' => $ins, 'stock_actual' => (float)$stock,
                'estado_visual' => 'bueno', 'cadena_frio' => $ins === 4 ? 'no_cumple' : 'cumple', 'dias_vida' => $dias];
}

$hoy = '2026-11-19';   // jueves
$repo = new RepositorioMemoria($lotes, $insumos);
$sistema = new Sistema($repo);

echo "\nPlan de 3 días (formulario sin cambios)\n";
$entrada = ['tipo' => 'plan_3_dias', 'observaciones' => 'prueba', 'tipo_dia' => [], 'lotes' => []];
$res = $sistema->ejecutar($entrada, $hoy);
$por = [];
foreach ($res['resultados'] as $r) {
    $por[$r['lote']['insumo'] . '#' . $r['lote']['lote_id']] = $r;
    printf("      %-22s -> %-16s FC %.3f  %s\n", $r['lote']['insumo'] . ' (lote ' . $r['lote']['lote_id'] . ')',
        $r['accion'] ?? 'sin accion', $r['fc'], implode(' | ', $r['filas']));
}
verificar('Lengua (lote 1): promocionar con A*', ($por['Lengua de res#1']['accion'] ?? '') === 'promocionar');
verificar('Pollo (lote 2): menú de emergencia con DFS', ($por['Pollo#2']['accion'] ?? '') === 'menu_emergencia');
verificar('Calamar (lote 3): descartar', ($por['Calamar#3']['accion'] ?? '') === 'descartar');
verificar('Papa vencida (lote 4): sin acción y con alerta', isset($por['Papa#4']) && $por['Papa#4']['accion'] === null && $por['Papa#4']['alertas'] !== []);
verificar('Lechuga (lote 5): comprar', ($por['Lechuga#5']['accion'] ?? '') === 'comprar');
verificar('Trucha (lote 6): mantener', ($por['Trucha#6']['accion'] ?? '') === 'mantener');
verificar('se registró 1 consulta con sus decisiones', count($repo->consultas) === 1 && count($repo->decisiones) >= 6);
verificar('cada decisión guarda las reglas disparadas', count($repo->reglasDecision) === count($repo->decisiones));
verificar('toda decisión trae explicación', !in_array('', array_column($repo->decisiones, 'explicacion'), true));
verificar('ahorro total > 0', $res['ahorro_total'] > 0);

echo "\nFormulario: el usuario marca el Pollo en mal estado y agrega un feriado mañana\n";
$repo2 = new RepositorioMemoria($lotes, $insumos);
$entrada = ['tipo' => 'emergencia', 'tipo_dia' => ['2026-11-20' => 'feriado'],
            'lotes' => [2 => ['stock' => 3000, 'visual' => 'malo', 'frio' => 'cumple']]];
$res = (new Sistema($repo2))->ejecutar($entrada, $hoy);
$acc = [];
foreach ($res['resultados'] as $r) {
    $acc[$r['lote']['insumo']] = $r['accion'];
}
echo '      ' . json_encode($acc) . "\n";
verificar('emergencia: solo lotes que vencen hoy o vencidos', count($res['resultados']) === 3);
verificar('Pollo en mal estado -> descartar (R8 + R19)', ($acc['Pollo'] ?? '') === 'descartar');
verificar('el feriado quedó en el calendario', ($repo2->calendario['2026-11-20'] ?? '') === 'feriado');

echo "\nFormulario con datos inválidos\n";
foreach ([
    'tipo de día inexistente' => ['tipo_dia' => ['2026-11-19' => 'domingazo']],
    'stock negativo'          => ['lotes' => [1 => ['stock' => -5, 'visual' => 'bueno', 'frio' => 'cumple']]],
    'estado visual inválido'  => ['lotes' => [1 => ['stock' => 5, 'visual' => 'excelente', 'frio' => 'cumple']]],
    'lote nuevo ya vencido'   => ['nuevo_lote' => ['insumo_id' => '2', 'fecha_vencimiento' => '2026-11-01', 'cantidad' => 10, 'visual' => 'bueno', 'frio' => 'cumple']],
] as $nombre => $extra) {
    try {
        (new Sistema(new RepositorioMemoria($lotes, $insumos)))->ejecutar(['tipo' => 'plan_3_dias'] + $extra, $hoy);
        verificar("rechaza: {$nombre}", false);
    } catch (InvalidArgumentException) {
        verificar("rechaza: {$nombre}", true);
    }
}

echo "\nLote nuevo desde el formulario\n";
$repo3 = new RepositorioMemoria($lotes, $insumos);
$res = (new Sistema($repo3))->ejecutar(['tipo' => 'plan_3_dias', 'nuevo_lote' => [
    'insumo_id' => '4', 'fecha_vencimiento' => '2026-11-22', 'cantidad' => 5000, 'visual' => 'bueno', 'frio' => 'cumple']], $hoy);
verificar('el lote nuevo aparece en los resultados', in_array(101, array_map(static fn($r) => $r['lote']['lote_id'], $res['resultados']), true));

echo "\n" . ($fallos === 0 ? "Todas las pruebas pasaron.\n" : "{$fallos} prueba(s) fallaron.\n");
exit($fallos === 0 ? 0 : 1);