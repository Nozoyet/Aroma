<?php
declare(strict_types=1);

/**
 * Prueba de Grafo, DemandaPromedio, DFS y A* sin base de datos.
 * Ejecutar:  php pruebas/probar_algoritmos.php
 */
require_once __DIR__ . '/../src/autoload.php';
require_once __DIR__ . '/datos_ejemplo.php';

$fallos = 0;
function verificar(string $d, bool $ok): void
{
    global $fallos;
    echo ($ok ? '  [OK]    ' : '  [FALLA] ') . $d . "\n";
    $fallos += $ok ? 0 : 1;
}
function casi(float $a, float $b, float $t = 0.005): bool
{
    return abs($a - $b) <= $t;
}

$grafo = new Grafo(recetas_ejemplo());
$par   = ['umbral_rescate' => 0.70, 'max_platos_menu' => 3, 'max_porciones_promo' => 10, 'descuento_promo' => 0.10];
$grande = [7 => 99999.0, 16 => 99999.0, 8 => 99999.0, 10 => 99999.0, 12 => 99999.0, 13 => 99999.0,
           15 => 99999.0, 5 => 99999.0, 6 => 99999.0, 9 => 99999.0, 3 => 99999.0, 11 => 99999.0, 14 => 99999.0];

echo "\nGrafo\n";
verificar('vecinos de Pollo (2) = P3 y P6', array_keys($grafo->vecinos(2)) === [3, 6]);
verificar('vecinos de Lengua (1) = P1 y P2', array_keys($grafo->vecinos(1)) === [1, 2]);
verificar('kmax P3 con Ají=90 g -> 6 porciones', $grafo->kmax(3, [2 => 99999.0, 7 => 99999.0, 8 => 90.0]) === 6);

echo "\nDemandaPromedio\n";
$dem = new DemandaPromedio([1 => [0 => ['prom' => 10.0, 'desv' => 2.0, 'n' => 10], 1 => ['prom' => 10.0, 'desv' => 8.0, 'n' => 10],
                                   2 => ['prom' => 10.0, 'desv' => 0.0, 'n' => 2]]]);
$lunes = '2026-11-16';
verificar('desv 2 sobre prom 10 -> fc 0.8', casi($dem->estimar(1, $lunes)['fc'], 0.8));
verificar('martes con desv 8 -> fc acotado a 0.3', casi($dem->estimar(1, '2026-11-17')['fc'], 0.3));
verificar('miércoles con 2 observaciones -> fc 0.3', casi($dem->estimar(1, '2026-11-18')['fc'], 0.3));
verificar('sin datos -> 0 porciones', $dem->estimar(9, $lunes)['porciones'] === 0.0);

echo "\nDFS - Pollo vence hoy: 3000 g, Ají disponible = 90 g\n";
$stock = $grande;
$stock[8] = 90.0;
$r = (new Dfs($grafo))->resolver(2, 3000.0, 0.045, $stock, $par);
echo "      menu: " . json_encode($r['menu']) . " | rescatado {$r['valor_rescatado']} Bs ({$r['porcentaje']}) | nodos {$r['nodos']}, hojas {$r['hojas']}\n";
verificar('hay solución', $r['resuelto']);
verificar('menu = P3 x6 + P6 x6', $r['menu'] === [3 => 6, 6 => 6]);
verificar('rescata 135 Bs (100 %)', casi($r['valor_rescatado'], 135.0) && casi($r['porcentaje'], 1.0, 0.0001));

echo "\nDFS - Lengua vence hoy: 3000 g, Tahini disponible = 100 g (meta inalcanzable)\n";
$stock = $grande;
$stock[13] = 100.0;
$stock[1] = 3000.0;
$r = (new Dfs($grafo))->resolver(1, 3000.0, 0.06, $stock, $par);
echo "      menu: " . json_encode($r['menu']) . " | rescatado {$r['valor_rescatado']} Bs ({$r['porcentaje']}) | nodos {$r['nodos']}, hojas {$r['hojas']}\n";
verificar('no hay solución', !$r['resuelto']);
verificar('mejor parcial = P1 x5', $r['menu'] === [1 => 5]);
verificar('rescata 75 Bs (41.7 %) y la meta era 126 Bs', casi($r['valor_rescatado'], 75.0) && casi($r['meta'], 126.0));
verificar('merma 1750 g = 105 Bs', casi($r['gramos_sobrante'], 1750.0, 0.5) && casi($r['valor_merma'], 105.0));
verificar('trae advertencia', $r['advertencia'] !== null);

echo "\nA* - Lengua 15000 g, demanda base 4000 / 4250 / 5000 g por día\n";
$stock = $grande;
$stock[1] = 15000.0;
$base = [4000.0, 4250.0, 5000.0];
$con  = (new AEstrella($grafo))->planificar(1, 15000.0, 0.06, $base, [1, 2], $stock, $par, true);
$sin  = (new AEstrella($grafo))->planificar(1, 15000.0, 0.06, $base, [1, 2], $stock, $par, false);
echo '      plan: ' . json_encode($con['plan']) . "\n";
echo "      costo {$con['costo_total']} Bs vs {$con['costo_sin_intervencion']} Bs sin intervenir | con h: {$con['expandidos']} expandidos / {$con['generados']} generados | sin h: {$sin['expandidos']} / {$sin['generados']}\n";
verificar('costo del plan = 87.5 Bs', casi($con['costo_total'], 87.5));
verificar('sin intervención se pierden 105 Bs', casi($con['costo_sin_intervencion'], 105.0));
verificar('promociona 7 porciones de P2 (1750 g)', count($con['plan']) === 1 && $con['plan'][0]['plato_id'] === 2 && $con['plan'][0]['porciones'] === 7);
verificar('ahorro 17.5 Bs y merma final 0 g', casi($con['ahorro'], 17.5) && $con['merma_final_g'] < 1.0);
verificar('con y sin heurística dan el mismo costo (optimalidad)', casi($con['costo_total'], $sin['costo_total']));
verificar('la heurística reduce nodos expandidos', $con['expandidos'] < $sin['expandidos']);

echo "\nA* - el stock ya queda cubierto por la demanda: no hace falta promoción\n";
$r = (new AEstrella($grafo))->planificar(1, 10000.0, 0.06, $base, [1, 2], $stock, $par);
verificar('plan vacío, costo 0', $r['plan'] === [] && casi($r['costo_total'], 0.0));

echo "\nA* - sin candidatos (ningún plato disponible)\n";
$r = (new AEstrella($grafo))->planificar(1, 15000.0, 0.06, $base, [], $stock, $par);
verificar('plan vacío y se pierden 105 Bs', $r['plan'] === [] && casi($r['costo_total'], 105.0));

echo "\n" . ($fallos === 0 ? "Todas las pruebas pasaron.\n" : "{$fallos} prueba(s) fallaron.\n");
exit($fallos === 0 ? 0 : 1);