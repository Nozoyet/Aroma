<?php
declare(strict_types=1);

/**
 * Módulo de explicación: convierte la traza del motor y los resultados de DFS/A* en texto
 * legible ("¿por qué se recomienda esto?"). No decide nada, solo narra lo que ocurrió.
 * Cada método devuelve una lista de líneas de texto plano.
 */
final class Explicador
{
    /** @param array<string, array{condicion: string, conclusion: string}> $reglasBd tabla `regla` */
    public function __construct(private array $reglasBd, private Grafo $grafo)
    {
    }

    /** Datos del lote, reglas disparadas (con su condición en la BD) y acciones candidatas. */
    public function lote(array $lote, array $inf): array
    {
        $dias = $lote['dias_vida'];
        $vida = $dias < 0 ? 'vencido hace ' . (-$dias) . ' día(s)' : ($dias === 0 ? 'vence hoy' : "vence en {$dias} día(s)");

        $l = [sprintf('Lote %d de %s: %s g, %s, riesgo %s, estado visual %s, cadena de frío %s.',
            $lote['lote_id'], $lote['insumo'], number_format($lote['stock_actual'], 0, '.', ''), $vida,
            $lote['categoria_riesgo'], $lote['estado_visual'], str_replace('_', ' ', $lote['cadena_frio']))];

        if ($inf['traza'] !== []) {
            $l[] = 'Reglas disparadas (en orden):';
            foreach ($inf['traza'] as $t) {
                $r = $this->reglasBd[$t['regla']] ?? ['condicion' => '', 'conclusion' => ''];
                $l[] = sprintf('  %s: SI %s ENTONCES %s  [FC %.2f]', $t['regla'], $r['condicion'], $r['conclusion'], $t['fc']);
            }
        }
        if ($inf['acciones'] !== []) {
            arsort($inf['acciones']);
            $txt = [];
            foreach ($inf['acciones'] as $a => $fc) {
                $txt[] = sprintf('%s (FC %.2f)', $a, $fc);
            }
            $l[] = 'Acciones candidatas: ' . implode(', ', $txt)
                . '. Se elige "' . $inf['accion_principal'] . '" por prioridad (la inocuidad va primero).';
        }
        foreach ($inf['alertas'] as $a) {
            $l[] = 'ALERTA: ' . $a;
        }
        return $l;
    }

    public function dfs(array $r): array
    {
        $l = [sprintf('Búsqueda DFS en el grafo: %d nodos explorados, %d hojas evaluadas.', $r['nodos'], $r['hojas']),
              sprintf('Meta: rescatar al menos %.0f %% del lote (%.2f de %.2f Bs).', $r['umbral'] * 100, $r['meta'], $r['valor_lote'])];
        foreach ($r['menu'] as $platoId => $k) {
            $l[] = sprintf('  - %s: %d porciones', $this->grafo->nombrePlato($platoId), $k);
        }
        $l[] = sprintf('Se rescatan %.2f Bs (%.1f %%) usando %.0f g; sobran %.0f g (%.2f Bs de merma).',
            $r['valor_rescatado'], $r['porcentaje'] * 100, $r['gramos_usados'], $r['gramos_sobrante'], $r['valor_merma']);
        if ($r['advertencia'] !== null) {
            $l[] = 'ADVERTENCIA: ' . $r['advertencia'];
        }
        return $l;
    }

    public function aEstrella(array $r): array
    {
        $l = [sprintf('Búsqueda A*: %d nodos expandidos, %d generados%s.', $r['expandidos'], $r['generados'],
                $r['heuristica'] ? '' : ' (sin heurística)'),
              sprintf('Sin intervenir se perderían %.2f Bs. Con el plan: %.2f Bs (descuentos %.2f + merma %.0f g). Ahorro: %.2f Bs.',
                $r['costo_sin_intervencion'], $r['costo_total'], $r['descuentos'], $r['merma_final_g'], $r['ahorro'])];
        foreach ($r['plan'] as $p) {
            $l[] = sprintf('  - Día %d: promocionar %d porciones de %s (descuento total %.2f Bs)',
                $p['dia'], $p['porciones'], $this->grafo->nombrePlato($p['plato_id']), $p['descuento']);
        }
        if ($r['plan'] === []) {
            $l[] = '  - No hace falta promocionar: la demanda esperada ya consume el lote.';
        }
        return $l;
    }

    public function compra(float $gramos, float $stock, float $consumoDiario, float $objetivoDias): array
    {
        return [sprintf('Stock %.0f g con consumo de %.0f g/día; para cubrir %.1f días conviene comprar %.0f g.',
            $stock, $consumoDiario, $objetivoDias, $gramos)];
    }

    // ------------------------------------------------------------------
    // Justificaciones en lenguaje sencillo (lo que lee el chef en pantalla)
    // ------------------------------------------------------------------

    /** FC en palabras: el chef no necesita un porcentaje para decidir. */
    public static function confianza(float $fc): string
    {
        return $fc >= 0.75 ? 'alta' : ($fc >= 0.40 ? 'media' : 'baja');
    }

    public static function bs(float $v): string
    {
        return number_format($v, 2, '.', '') . ' Bs';
    }

    /** 1500 -> "1.5 kg"; 450 -> "450 g". */
    public static function peso(float $g): string
    {
        if ($g >= 1000) {
            return rtrim(rtrim(number_format($g / 1000, 2, '.', ''), '0'), '.') . ' kg';
        }
        return number_format($g, 0, '.', '') . ' g';
    }

    /** $dia = 1 es hoy. */
    public static function diaTxt(int $dia): string
    {
        return [1 => 'hoy', 2 => 'mañana', 3 => 'pasado mañana'][$dia] ?? 'en ' . ($dia - 1) . ' días';
    }

    private static function unir(array $partes): string
    {
        if (count($partes) <= 1) {
            return implode('', $partes);
        }
        return implode(', ', array_slice($partes, 0, -1)) . ' y ' . end($partes);
    }

    private static function porciones(int $k): string
    {
        return $k . ($k === 1 ? ' porción' : ' porciones');
    }

    /** Ingrediente que impide preparar más porciones (el más "holgado" entre los platos posibles). */
    private function limitante(int $insumoId, array $stockTotal): ?string
    {
        $mejor = null;
        $mejorK = -1;
        foreach (array_keys($this->grafo->vecinos($insumoId)) as $platoId) {
            $k = PHP_INT_MAX;
            $quien = null;
            foreach ($this->grafo->receta($platoId) as $otro => $peso) {
                if ($otro === $insumoId) {
                    continue;
                }
                $cabe = (int)floor(($stockTotal[$otro] ?? 0.0) / $peso + 1e-9);
                if ($cabe < $k) {
                    $k = $cabe;
                    $quien = $otro;
                }
            }
            if ($quien !== null && $k > $mejorK) {
                $mejorK = $k;
                $mejor = $quien;
            }
        }
        return $mejor === null ? null : $this->grafo->nombreInsumo($mejor);
    }

    public function motivoMenu(array $lote, array $r, array $stockTotal): array
    {
        $ins = $lote['insumo'];
        $falta = $this->limitante($lote['insumo_id'], $stockTotal);

        if ($r['menu'] === []) {
            return [
                "{$ins} vence hoy, pero con los ingredientes que hay no se puede preparar ningún plato que lo use.",
                'Si no se usa hoy se pierden ' . self::bs($r['valor_lote']) . ($falta ? ". Falta {$falta}." : '.'),
            ];
        }

        $partes = [];
        foreach ($r['menu'] as $platoId => $k) {
            $partes[] = self::porciones($k) . ' de ' . $this->grafo->nombrePlato($platoId);
        }
        $total = $r['gramos_usados'] + $r['gramos_sobrante'];
        $frases = [
            "{$ins} vence hoy y está en buen estado, por eso conviene usarlo esta noche.",
            'Preparando ' . self::unir($partes) . ' se aprovecha el ' . round($r['porcentaje'] * 100) . ' % del lote ('
                . self::peso($r['gramos_usados']) . ' de ' . self::peso($total) . ', unos ' . self::bs($r['valor_rescatado']) . ').',
        ];
        if (!$r['resuelto']) {
            $frases[] = 'No se llega al ' . round($r['umbral'] * 100) . ' % que se buscaba'
                . ($falta ? ": falta {$falta} para preparar más porciones" : ': los demás ingredientes no alcanzan')
                . '. Quedarían ' . self::peso($r['gramos_sobrante']) . ' sin usar (unos ' . self::bs($r['valor_merma']) . ' de pérdida).';
        }
        return $frases;
    }

    public function motivoPromocion(array $lote, array $r, float $costoG, float $descuento): array
    {
        $ins = $lote['insumo'];
        $cuando = $lote['dias_vida'] === 1 ? 'mañana' : 'en ' . $lote['dias_vida'] . ' días';

        if ($r['plan'] === []) {
            return ["{$ins} vence {$cuando}, pero con las ventas esperadas se usa completo antes de eso. No hace falta hacer promociones."];
        }
        $sobra = $costoG > 0 ? $r['costo_sin_intervencion'] / $costoG : 0.0;
        $partes = [];
        foreach ($r['plan'] as $p) {
            $partes[] = self::porciones($p['porciones']) . ' de ' . $this->grafo->nombrePlato($p['plato_id']) . ' ' . self::diaTxt($p['dia']);
        }
        $todo = $r['merma_final_g'] < 1 ? 'se vende todo el lote' : 'se vende buena parte del lote';
        $frases = [
            "{$ins} vence {$cuando} y, según las ventas de los últimos meses, sobrarían unos " . self::peso($sobra)
                . ' (' . self::bs($r['costo_sin_intervencion']) . ') si no se hace nada.',
            'Ofreciendo ' . self::unir($partes) . ' con ' . round($descuento * 100) . ' % de descuento, ' . $todo
                . ' y se ahorran ' . self::bs($r['ahorro']) . '.',
        ];
        if ($r['merma_final_g'] >= 1) {
            $frases[1] .= ' Aun así quedarían unos ' . self::peso($r['merma_final_g']) . ' sin vender.';
        }
        return $frases;
    }

    public function motivoDescarte(array $lote, array $inf): array
    {
        $ids = array_column($inf['traza'], 'regla');
        $mapa = [
            'R5' => 'ya venció y es un producto de riesgo alto',
            'R6' => 'ya venció y es un producto de riesgo medio',
            'R7' => 'no se mantuvo la cadena de frío en un producto de riesgo alto',
            'R8' => 'su estado visual es malo',
        ];
        $razones = [];
        foreach ($mapa as $id => $texto) {
            if (in_array($id, $ids, true)) {
                $razones[] = $texto;
            }
        }
        $por = $razones === [] ? 'no es apto para el consumo' : self::unir($razones);
        return ["{$lote['insumo']}: {$por}.",
                'Por seguridad alimentaria no debe usarse y se descarta (pérdida de ' . self::bs($lote['valor_lote']) . ').'];
    }

    public function motivoCompra(array $lote, array $inf, float $gramos, array $cob, float $objetivo): array
    {
        $cuanto = $cob['cobertura_dias'] < 1 ? 'menos de un día' : number_format($cob['cobertura_dias'], 1, '.', '') . ' días';
        $f1 = "Con el stock actual de {$lote['insumo']} (" . self::peso($cob['stock_total_g']) . ") alcanza para {$cuanto} de ventas.";
        if (in_array('R21', array_column($inf['traza'], 'regla'), true)) {
            $f1 .= ' Además hoy o mañana hay fin de semana o feriado, cuando se vende más.';
        }
        return [$f1, 'Conviene comprar unos ' . self::peso($gramos) . ' para cubrir '
            . rtrim(rtrim(number_format($objetivo, 1, '.', ''), '0'), '.') . ' días.'];
    }

    public function motivoMantener(array $lote): array
    {
        $d = $lote['dias_vida'];
        return [$d > 2
            ? "{$lote['insumo']} todavía tiene {$d} días de vida y no hay riesgo de que sobre. No hace falta hacer nada."
            : "{$lote['insumo']} vence en {$d} día(s), pero con las ventas normales se consume antes. No hace falta hacer nada."];
    }

    public function motivoSinAccion(array $lote): array
    {
        if ($lote['dias_vida'] < 0) {
            return ["Este lote de {$lote['insumo']} ya venció y el sistema no tiene una regla para decidir qué hacer con productos de riesgo {$lote['categoria_riesgo']}.",
                    'Revíselo manualmente.'];
        }
        if ($lote['estado_visual'] === 'regular') {
            return ["El estado visual de este lote de {$lote['insumo']} es regular y el sistema no tiene una regla para ese caso.",
                    'Revíselo manualmente.'];
        }
        return ["El sistema no encontró una acción para este lote de {$lote['insumo']}.", 'Revíselo manualmente.'];
    }
}