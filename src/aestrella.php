<?php
declare(strict_types=1);

/**
 * A* para el plan de promociones de un lote que vence en 1 o 2 días.
 *
 * Estado:  (día d, gramos que quedan del lote).
 * Acción por día: no promocionar, o promocionar un plato × N porciones (1..max_porciones_promo)
 *          con descuento; esas porciones se suman a la demanda base de ese día.
 * g(n):    descuentos acumulados + merma final valorada a costo (al llegar al último día).
 * h(n):    MAX(0, stock − demanda base restante) × MIN(descuento por gramo, costo por gramo).
 *          Nunca sobreestima: cada gramo sobrante se pierde (cuesta el costo) o se vende con
 *          promoción (cuesta al menos el menor descuento por gramo).
 * Poda:    una promoción no puede dejar sin cubrir la demanda base del día (base + promo ≤ stock).
 * Desempate: mayor g (nodos más profundos primero).
 *
 * Supuesto declarado: el lote se consume primero (FEFO) y la demanda base del insumo de cada día
 * viene de la Demanda (v1: promedio por día de la semana).
 */
final class AEstrella
{
    private const EPS = 1e-9;

    public function __construct(private Grafo $grafo)
    {
    }

    /**
     * @param float[]            $demandaBaseG   gramos del insumo que se venderán cada día (índice 0 = hoy)
     * @param int[]              $candidatos     platos que se pueden promocionar (salida de la regla R17)
     * @param array<int,float>   $stockTotal     gramos por insumo (limita los otros ingredientes)
     * @param array<string,float> $parametros    max_porciones_promo, descuento_promo
     * @param bool               $usarHeuristica false = búsqueda uniforme (solo para comparar en el informe)
     */
    public function planificar(
        int $insumoId, float $stockLote, float $costoG, array $demandaBaseG,
        array $candidatos, array $stockTotal, array $parametros, bool $usarHeuristica = true
    ): array {
        $dias      = count($demandaBaseG);
        $maxPromo  = (int)($parametros['max_porciones_promo'] ?? 10);
        $descuento = (float)($parametros['descuento_promo'] ?? 0.10);

        // Acciones de promoción posibles por plato
        $cand = [];
        foreach ($candidatos as $platoId) {
            $w = $this->grafo->gramos($platoId, $insumoId);
            if ($w <= 0) {
                continue;
            }
            $precio = $this->grafo->precioPlato($platoId);
            $cand[] = [
                'id' => $platoId, 'w' => $w, 'precio' => $precio,
                'kmax' => min($maxPromo, $this->grafo->kmax($platoId, $stockTotal, $insumoId)),
                'desc_por_g' => $precio * $descuento / $w,
            ];
        }
        $minDescG = $cand === [] ? INF : min(array_column($cand, 'desc_por_g'));
        $tasa     = min($minDescG, $costoG);

        // restante[d] = demanda base de los días d..fin
        $restante = array_fill(0, $dias + 1, 0.0);
        for ($d = $dias - 1; $d >= 0; $d--) {
            $restante[$d] = $restante[$d + 1] + $demandaBaseG[$d];
        }
        $h = fn(int $d, float $s): float => $usarHeuristica ? max(0.0, $s - $restante[$d]) * $tasa : 0.0;

        // Costo de no intervenir: se consume la demanda base y lo demás se pierde
        $sSin = $stockLote;
        foreach ($demandaBaseG as $base) {
            $sSin -= min($sSin, $base);
        }
        $costoSin = $sSin * $costoG;

        $cola = new SplPriorityQueue();
        $seq = 0;
        $generados = 1;
        $expandidos = 0;
        $mejorG = ['0|' . round($stockLote, 2) => 0.0];
        $inicio = ['d' => 0, 'g' => 0.0, 's' => $stockLote, 'plan' => []];
        // Prioridad: menor f, luego mayor g, luego el más antiguo
        $cola->insert($inicio, [-round($h(0, $stockLote), 6), 0.0, 0]);

        while (!$cola->isEmpty()) {
            $n = $cola->extract();

            if ($n['d'] === $dias) {
                return $this->resultado($n, $costoSin, $expandidos, $generados, $usarHeuristica, $cand, $costoG, $descuento);
            }
            if ($n['g'] > ($mejorG[$n['d'] . '|' . round($n['s'], 2)] ?? INF) + self::EPS) {
                continue;   // entrada vieja: se halló un camino más barato al mismo estado
            }
            $expandidos++;

            $d = $n['d'];
            $s = $n['s'];
            $base = $demandaBaseG[$d];

            // Opción 0: no promocionar. Opciones 1..: promocionar plato × N
            $opciones = [['plato' => null, 'n' => 0]];
            foreach ($cand as $c) {
                $tope = min($c['kmax'], (int)floor(($s - $base) / $c['w'] + 1e-9));
                for ($k = 1; $k <= $tope; $k++) {
                    $opciones[] = ['plato' => $c, 'n' => $k];
                }
            }

            foreach ($opciones as $op) {
                $consumo = min($s, $base);
                $costoDesc = 0.0;
                $plan = $n['plan'];
                if ($op['plato'] !== null) {
                    $consumo = $base + $op['n'] * $op['plato']['w'];
                    $costoDesc = $op['n'] * $op['plato']['precio'] * $descuento;
                    $plan[] = ['dia' => $d + 1, 'plato_id' => $op['plato']['id'], 'porciones' => $op['n']];
                }
                $s2 = $s - $consumo;
                $g2 = $n['g'] + $costoDesc;
                $h2 = $h($d + 1, $s2);
                if ($d + 1 === $dias) {
                    $g2 += $s2 * $costoG;   // merma final
                    $h2 = 0.0;
                }
                $clave = ($d + 1) . '|' . round($s2, 2);
                if (isset($mejorG[$clave]) && $mejorG[$clave] <= $g2 + self::EPS) {
                    continue;
                }
                $mejorG[$clave] = $g2;
                $generados++;
                $cola->insert(
                    ['d' => $d + 1, 'g' => $g2, 's' => $s2, 'plan' => $plan],
                    [-round($g2 + $h2, 6), $g2, -(++$seq)]
                );
            }
        }

        // No ocurre (no promocionar siempre es factible), pero se devuelve algo coherente
        return ['plan' => [], 'costo_total' => round($costoSin, 2), 'costo_sin_intervencion' => round($costoSin, 2),
                'ahorro' => 0.0, 'merma_final_g' => round($sSin, 1), 'descuentos' => 0.0,
                'expandidos' => $expandidos, 'generados' => $generados, 'heuristica' => $usarHeuristica];
    }

    private function resultado(array $n, float $costoSin, int $exp, int $gen, bool $heur, array $cand, float $costoG, float $descuento): array
    {
        $porId = [];
        foreach ($cand as $c) {
            $porId[$c['id']] = $c;
        }
        $plan = [];
        $descuentos = 0.0;
        foreach ($n['plan'] as $p) {
            $c = $porId[$p['plato_id']];
            $desc = $p['porciones'] * $c['precio'] * $descuento;
            $descuentos += $desc;
            $plan[] = $p + [
                'gramos'    => round($p['porciones'] * $c['w'], 1),
                'descuento' => round($desc, 2),
            ];
        }
        $meraFinal = $costoG > 0 ? ($n['g'] - $descuentos) / $costoG : 0.0;

        return [
            'plan'                   => $plan,
            'costo_total'            => round($n['g'], 2),
            'costo_sin_intervencion' => round($costoSin, 2),
            'ahorro'                 => round($costoSin - $n['g'], 2),
            'descuentos'             => round($descuentos, 2),
            'merma_final_g'          => round(max(0.0, $meraFinal), 1),
            'expandidos'             => $exp,
            'generados'              => $gen,
            'heuristica'             => $heur,
        ];
    }
}