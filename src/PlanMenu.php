<?php
declare(strict_types=1);

/**
 * Menú de 3 días: qué preparar cada día y cuántas porciones.
 *
 *   porciones objetivo = ventas esperadas (regresión por mínimos cuadrados)
 *                      + porciones en promoción que eligió A* (lotes por vencer con exceso)
 *                      o, el día 1, las porciones del menú de emergencia (DFS) si superan lo esperado
 *
 * El menú NO se recorta por el stock de hoy (el local repone mercadería). En cambio se recorre el horizonte
 * día por día: cada plato consume sus insumos (primero los lotes que vencen antes) y, si el stock no alcanza,
 * la diferencia se informa como lista de compras de ese día.
 */
final class PlanMenu
{
    /**
     * @param string[] $fechas     fechas del horizonte (Y-m-d)
     * @param array    $tipos      Repositorio::tiposDia()
     * @param array    $resultados resultados por lote de Sistema (con items que traen plato_id y porciones)
     * @return array<int, array>   un elemento por día
     */
    public static function construir(
        Grafo $grafo, IDemanda $demanda, array $fechas, array $tipos, array $resultados, array $par,
        ?array $soloPlatos = null
    ): array {
        $descuento = (float)($par['descuento_promo'] ?? 0.10);

        // Lotes que realmente se pueden usar (ni descartados ni vencidos), el que vence antes primero
        $pool = [];
        foreach ($resultados as $r) {
            $l = $r['lote'];
            if (($r['accion'] ?? null) === 'descartar' || $l['dias_vida'] < 0) {
                continue;
            }
            $pool[] = ['insumo_id' => $l['insumo_id'], 'stock' => (float)$l['stock_actual'], 'vida' => $l['dias_vida']];
        }
        usort($pool, static fn(array $a, array $b): int => $a['vida'] <=> $b['vida']);

        $dias = [];
        foreach ($fechas as $d => $fecha) {
            // Stock utilizable este día: los lotes que aún no vencieron
            $disp = [];
            foreach ($pool as $p) {
                if ($p['vida'] >= $d) {
                    $disp[$p['insumo_id']] = ($disp[$p['insumo_id']] ?? 0.0) + $p['stock'];
                }
            }

            // Objetivos del día
            $objetivo = [];
            foreach ($grafo->platos() as $platoId => $_n) {
                if ($soloPlatos !== null && !in_array($platoId, $soloPlatos, true)) {
                    continue;
                }
                $est = $demanda->estimar($platoId, $fecha);
                $objetivo[$platoId] = ['esperadas' => (int)round($est['porciones']), 'fc' => $est['fc'],
                                       'promo' => 0, 'emergencia' => 0];
            }
            foreach ($resultados as $r) {
                foreach ($r['items'] as $it) {
                    if (!isset($it['plato_id']) || (int)$it['dia'] !== $d + 1 || !isset($objetivo[$it['plato_id']])) {
                        continue;
                    }
                    if ($it['accion'] === 'promocionar') {
                        $objetivo[$it['plato_id']]['promo'] += (int)$it['porciones'];
                    } elseif ($it['accion'] === 'menu_emergencia') {
                        $objetivo[$it['plato_id']]['emergencia'] += (int)$it['porciones'];
                    }
                }
            }

            // Primero lo que ayuda a no perder mercadería, luego lo más vendido
            $ids = array_keys($objetivo);
            usort($ids, static function (int $a, int $b) use ($objetivo): int {
                $pa = ($objetivo[$a]['promo'] + $objetivo[$a]['emergencia']) > 0 ? 0 : 1;
                $pb = ($objetivo[$b]['promo'] + $objetivo[$b]['emergencia']) > 0 ? 0 : 1;
                return [$pa, $objetivo[$b]['esperadas']] <=> [$pb, $objetivo[$a]['esperadas']];
            });

            $filas = [];
            $comprar = [];   // insumo_id => gramos que faltan para cumplir el menú de este día
            $totalPorciones = 0;
            $ingreso = 0.0;
            foreach ($ids as $platoId) {
                $o = $objetivo[$platoId];
                $meta = max($o['esperadas'] + $o['promo'], $o['emergencia']);
                if ($meta <= 0) {
                    continue;
                }
                $sug = $meta;

                // Consumir insumos (primero los lotes que vencen antes); lo que no alcance se compra
                foreach ($grafo->receta($platoId) as $insumoId => $peso) {
                    $falta = $sug * $peso;
                    $hay = $disp[$insumoId] ?? 0.0;
                    if ($falta > $hay + 1e-9) {
                        $comprar[$insumoId] = ($comprar[$insumoId] ?? 0.0) + ($falta - $hay);
                    }
                    $disp[$insumoId] = max(0.0, $hay - $falta);
                    foreach ($pool as &$p) {
                        if ($falta <= 0.0) {
                            break;
                        }
                        if ($p['insumo_id'] === $insumoId && $p['vida'] >= $d && $p['stock'] > 0.0) {
                            $toma = min($p['stock'], $falta);
                            $p['stock'] -= $toma;
                            $falta -= $toma;
                        }
                    }
                    unset($p);
                }

                $promo = min($o['promo'], $sug);
                $precio = $grafo->precioPlato($platoId);
                $ingresoFila = $precio * ($sug - $promo) + $precio * (1 - $descuento) * $promo;

                $filas[] = [
                    'plato_id'   => $platoId,
                    'nombre'     => $grafo->nombrePlato($platoId),
                    'preparar'   => $sug,
                    'esperadas'  => $o['esperadas'],
                    'promo'      => $promo,
                    'descuento'  => $descuento,
                    'emergencia' => $o['emergencia'] > $o['esperadas'] ? $o['emergencia'] : 0,
                    'confianza'  => Explicador::confianza((float)$o['fc']),
                    'ingreso'    => round($ingresoFila, 2),
                ];
                $totalPorciones += $sug;
                $ingreso += $ingresoFila;
            }

            $dias[] = [
                'dia' => $d + 1, 'fecha' => $fecha, 'tipo_dia' => $tipos[$d]['tipo_dia'] ?? 'laboral',
                'platos' => $filas, 'total_porciones' => $totalPorciones, 'ingreso' => round($ingreso, 2),
                'comprar' => array_map(static fn(int $id, float $g): array => ['insumo' => $grafo->nombreInsumo($id), 'gramos' => $g],
                                       array_keys($comprar), array_values($comprar)),
            ];
        }
        return $dias;
    }
}
