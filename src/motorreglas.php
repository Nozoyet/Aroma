<?php
declare(strict_types=1);

/**
 * Motor de inferencia: aplica las reglas R1 a R22 a un lote, con encadenamiento hacia adelante
 * y factores de certeza (FC).
 *
 * - La LÓGICA de cada regla está aquí (funciones PHP).
 * - El FC de cada regla y su estado activa/inactiva vienen de la tabla `regla`.
 * - Los umbrales vienen de `parametro_politica`.
 * - Las reglas se evalúan por fases. Una fase solo depende de las anteriores, así que
 *   una pasada por fase basta (no hay ciclos). Dentro de una fase se respeta el orden R1..R22.
 *
 * Contexto que recibe inferir() en $ctx:
 *   'cobertura_dias'    float|INF   cobertura del insumo del lote (vw_cobertura_insumo)
 *   'tipo_dia_proximos' string[]    tipo de día de hoy, mañana, ... (calendario_dia)
 *   'platos'            array       platos que usan el insumo (vecinos en el grafo), por plato_id:
 *                                   ['nombre', 'demanda_total', 'fc_demanda', 'porciones_posibles']
 *                                   demanda_total = porciones esperadas en el horizonte.
 */
final class MotorReglas
{
    private const FASES = ['clasificacion', 'inocuidad', 'calidad', 'riesgo', 'acciones'];

    /** Si un lote tiene varias acciones, la principal sigue este orden (la inocuidad manda). */
    private const PRIORIDAD = ['descartar', 'menu_emergencia', 'promocionar', 'comprar', 'mantener'];

    /** Días hacia adelante que R21 revisa para detectar fin de semana o feriado (hoy y mañana). */
    private const DIAS_TIPO_DIA = 2;

    /** @var array<string, array{fase: string, eval: \Closure}> */
    private array $reglas;

    /**
     * @param array<string, array{fc_regla: float, activa: bool}> $reglasBd  contenido de la tabla `regla`
     * @param array<string, float> $parametros                                contenido de `parametro_politica`
     */
    public function __construct(private array $reglasBd, private array $parametros = [])
    {
        $this->reglas = $this->definirReglas();
        foreach (array_keys($this->reglas) as $id) {
            if (!isset($this->reglasBd[$id])) {
                throw new RuntimeException("Falta la regla {$id} en la tabla regla.");
            }
        }
    }

    /**
     * Infiere sobre un lote (fila de Repositorio::lotes()).
     *
     * @return array{
     *   hechos: array, acciones: array<string,float>, accion_principal: ?string, fc_principal: float,
     *   platos_promocion: int[], traza: array<int,array>, alertas: string[]
     * }
     */
    public function inferir(array $lote, array $ctx): array
    {
        $h = new Hechos();

        // Datos de entrada: certeza total (1.0), los ingresó o confirmó una persona
        foreach (['dias_vida', 'categoria_riesgo', 'estado_visual', 'cadena_frio'] as $dato) {
            $h->asignar($dato, $lote[$dato], 1.0);
        }
        $h->asignar('cobertura_dias', (float)($ctx['cobertura_dias'] ?? INF), 1.0);
        $h->asignar('tipo_dia', array_slice($ctx['tipo_dia_proximos'] ?? [], 0, self::DIAS_TIPO_DIA), 1.0);

        $acciones = [];
        $salidas  = [];
        $traza    = [];

        foreach (self::FASES as $fase) {
            foreach ($this->reglas as $id => $def) {
                if ($def['fase'] !== $fase || !$this->reglasBd[$id]['activa']) {
                    continue;
                }
                $r = ($def['eval'])($h, $ctx);
                if ($r === null) {
                    continue;
                }
                $fc = (float)$this->reglasBd[$id]['fc_regla'] * $h->minFc($r['usa'], ...$r['extra']);

                if ($r['conclusion'] === 'accion_recomendada') {
                    $a = $r['valor'];
                    $acciones[$a] = isset($acciones[$a]) ? Hechos::combinar($acciones[$a], $fc) : $fc;
                } else {
                    $h->asignar($r['conclusion'], $r['valor'], $fc);
                }
                foreach ($r['salida'] as $clave => $valor) {
                    $salidas[$clave] = $valor;
                }
                $traza[] = [
                    'regla'      => $id,
                    'fase'       => $fase,
                    'conclusion' => $r['conclusion'],
                    'valor'      => $r['valor'],
                    'fc'         => round($fc, 4),
                ];
            }

            // Sin evidencia de que el lote no es apto, se considera apto (hecho por defecto)
            if ($fase === 'inocuidad' && !$h->tiene('apto_consumo')) {
                $h->asignar('apto_consumo', 'si', 1.0);
            }
        }

        $principal = null;
        foreach (self::PRIORIDAD as $a) {
            if (isset($acciones[$a])) {
                $principal = $a;
                break;
            }
        }

        return [
            'hechos'           => $h->todos(),
            'acciones'         => array_map(static fn(float $v): float => round($v, 4), $acciones),
            'accion_principal' => $principal,
            'fc_principal'     => $principal === null ? 0.0 : round($acciones[$principal], 4),
            'platos_promocion' => $salidas['platos_promocion'] ?? [],
            'traza'            => $traza,
            'alertas'          => $this->alertas($h, $acciones, $ctx),
        ];
    }

    /** Situaciones que las reglas actuales no resuelven: se avisan, no se ocultan. */
    private function alertas(Hechos $h, array $acciones, array $ctx): array
    {
        $alertas = [];
        $sinPlatos = ($ctx['platos'] ?? []) === [];

        if ($h->es('estado_lote', 'vencido') && !isset($acciones['descartar'])) {
            $alertas[] = 'Lote vencido de riesgo ' . $h->valor('categoria_riesgo')
                . ' sin regla de descarte: revisar manualmente.';
        }
        if ($h->es('riesgo_merma', 'alto')
            && !isset($acciones['descartar'], $acciones['menu_emergencia'], $acciones['promocionar'])) {
            $alertas[] = 'Lote en riesgo de merma sin acción posible (estado visual '
                . $h->valor('estado_visual') . '): validar con el chef.';
        }
        if ((isset($acciones['menu_emergencia']) || isset($acciones['promocionar'])) && $sinPlatos) {
            $alertas[] = 'El insumo no aparece en ninguna receta activa: no hay platos para aprovecharlo.';
        }
        return $alertas;
    }

    // ------------------------------------------------------------------
    // DEFINICIÓN DE LAS REGLAS R1 A R22 (coinciden con la tabla `regla`)
    // ------------------------------------------------------------------

    private function definirReglas(): array
    {
        $umbralCal = (float)($this->parametros['umbral_fc_calidad'] ?? 0.60);
        $umbralEme = (float)($this->parametros['umbral_fc_emergencia'] ?? 0.50);

        // Constructor del resultado de una regla que se dispara
        $c = static fn(string $conclusion, mixed $valor, array $usa, array $extra = [], array $salida = []): array
            => ['conclusion' => $conclusion, 'valor' => $valor, 'usa' => $usa, 'extra' => $extra, 'salida' => $salida];

        $buena = static fn(Hechos $h): bool =>
            $h->es('estado_visual', 'bueno') && $h->es('cadena_frio', 'cumple');

        return [
            // ---- Clasificación del lote ----
            'R1' => ['fase' => 'clasificacion', 'eval' => fn(Hechos $h, array $x) =>
                $h->valor('dias_vida') > 2 ? $c('estado_lote', 'fresco', ['dias_vida']) : null],
            'R2' => ['fase' => 'clasificacion', 'eval' => fn(Hechos $h, array $x) =>
                $h->valor('dias_vida') >= 1 && $h->valor('dias_vida') <= 2
                    ? $c('estado_lote', 'por_vencer', ['dias_vida']) : null],
            'R3' => ['fase' => 'clasificacion', 'eval' => fn(Hechos $h, array $x) =>
                $h->valor('dias_vida') === 0 ? $c('estado_lote', 'vence_hoy', ['dias_vida']) : null],
            'R4' => ['fase' => 'clasificacion', 'eval' => fn(Hechos $h, array $x) =>
                $h->valor('dias_vida') < 0 ? $c('estado_lote', 'vencido', ['dias_vida']) : null],

            // ---- Inocuidad ----
            'R5' => ['fase' => 'inocuidad', 'eval' => fn(Hechos $h, array $x) =>
                $h->es('estado_lote', 'vencido') && $h->es('categoria_riesgo', 'alto')
                    ? $c('apto_consumo', 'no', ['estado_lote', 'categoria_riesgo']) : null],
            'R6' => ['fase' => 'inocuidad', 'eval' => fn(Hechos $h, array $x) =>
                $h->es('estado_lote', 'vencido') && $h->es('categoria_riesgo', 'medio')
                    ? $c('apto_consumo', 'no', ['estado_lote', 'categoria_riesgo']) : null],
            'R7' => ['fase' => 'inocuidad', 'eval' => fn(Hechos $h, array $x) =>
                $h->es('cadena_frio', 'no_cumple') && $h->es('categoria_riesgo', 'alto')
                    ? $c('apto_consumo', 'no', ['cadena_frio', 'categoria_riesgo']) : null],
            'R8' => ['fase' => 'inocuidad', 'eval' => fn(Hechos $h, array $x) =>
                $h->es('estado_visual', 'malo') ? $c('apto_consumo', 'no', ['estado_visual']) : null],

            // ---- Calidad del lote (el valor del hecho ES la certeza de calidad) ----
            'R9'  => ['fase' => 'calidad', 'eval' => fn(Hechos $h, array $x) =>
                $h->es('estado_lote', 'fresco') && $buena($h)
                    ? $c('fc_calidad', 0.9, ['estado_lote', 'estado_visual', 'cadena_frio']) : null],
            'R10' => ['fase' => 'calidad', 'eval' => fn(Hechos $h, array $x) =>
                $h->es('estado_lote', 'por_vencer') && $buena($h)
                    ? $c('fc_calidad', 0.7, ['estado_lote', 'estado_visual', 'cadena_frio']) : null],
            'R11' => ['fase' => 'calidad', 'eval' => fn(Hechos $h, array $x) =>
                $h->es('estado_lote', 'vence_hoy') && $buena($h)
                    ? $c('fc_calidad', 0.5, ['estado_lote', 'estado_visual', 'cadena_frio']) : null],

            // ---- Riesgo de merma ----
            'R12' => ['fase' => 'riesgo', 'eval' => fn(Hechos $h, array $x) =>
                $h->es('estado_lote', 'vence_hoy') && $h->es('apto_consumo', 'si')
                    ? $c('riesgo_merma', 'alto', ['estado_lote', 'apto_consumo']) : null],
            'R13' => ['fase' => 'riesgo', 'eval' => fn(Hechos $h, array $x) =>
                $h->es('estado_lote', 'por_vencer') && $h->es('apto_consumo', 'si')
                && $h->valor('cobertura_dias') > $h->valor('dias_vida')
                    ? $c('riesgo_merma', 'alto', ['estado_lote', 'apto_consumo', 'cobertura_dias', 'dias_vida']) : null],
            'R14' => ['fase' => 'riesgo', 'eval' => fn(Hechos $h, array $x) =>
                $h->es('estado_lote', 'por_vencer') && $h->valor('cobertura_dias') <= $h->valor('dias_vida')
                    ? $c('riesgo_merma', 'bajo', ['estado_lote', 'cobertura_dias', 'dias_vida']) : null],
            'R15' => ['fase' => 'riesgo', 'eval' => fn(Hechos $h, array $x) =>
                $h->es('estado_lote', 'fresco') ? $c('riesgo_merma', 'bajo', ['estado_lote']) : null],

            // ---- Acciones ----
            'R16' => ['fase' => 'acciones', 'eval' => fn(Hechos $h, array $x) =>
                $h->es('riesgo_merma', 'alto') && $h->es('apto_consumo', 'si')
                && $h->tiene('fc_calidad') && $h->valor('fc_calidad') >= $umbralCal
                && ($x['platos'] ?? []) !== []
                    ? $c('candidato_promocion', array_keys($x['platos']), ['riesgo_merma', 'apto_consumo', 'fc_calidad']) : null],
            'R17' => ['fase' => 'acciones', 'eval' => function (Hechos $h, array $x) use ($c): ?array {
                if (!$h->tiene('candidato_promocion')) {
                    return null;
                }
                $aptos = [];
                foreach ($h->valor('candidato_promocion') as $platoId) {
                    $p = $x['platos'][$platoId];
                    if ($p['demanda_total'] < $p['porciones_posibles']) {   // la demanda no consume el lote
                        $aptos[$platoId] = $p;
                    }
                }
                if ($aptos === []) {
                    return null;
                }
                $fcDemanda = min(array_map(static fn(array $p): float => (float)$p['fc_demanda'], $aptos));
                return $c('accion_recomendada', 'promocionar', ['candidato_promocion'], [$fcDemanda],
                          ['platos_promocion' => array_keys($aptos)]);
            }],
            'R18' => ['fase' => 'acciones', 'eval' => fn(Hechos $h, array $x) =>
                $h->es('estado_lote', 'vence_hoy') && $h->es('apto_consumo', 'si')
                && $h->tiene('fc_calidad') && $h->valor('fc_calidad') >= $umbralEme
                    ? $c('accion_recomendada', 'menu_emergencia', ['estado_lote', 'apto_consumo', 'fc_calidad']) : null],
            'R19' => ['fase' => 'acciones', 'eval' => fn(Hechos $h, array $x) =>
                $h->es('apto_consumo', 'no') ? $c('accion_recomendada', 'descartar', ['apto_consumo']) : null],
            'R20' => ['fase' => 'acciones', 'eval' => fn(Hechos $h, array $x) =>
                $h->valor('cobertura_dias') < 1 && $h->es('estado_lote', 'fresco')
                    ? $c('accion_recomendada', 'comprar', ['cobertura_dias', 'estado_lote']) : null],
            'R21' => ['fase' => 'acciones', 'eval' => fn(Hechos $h, array $x) =>
                array_intersect($h->valor('tipo_dia'), ['fin_de_semana', 'feriado']) !== []
                && $h->valor('cobertura_dias') < 2
                    ? $c('accion_recomendada', 'comprar', ['tipo_dia', 'cobertura_dias']) : null],
            'R22' => ['fase' => 'acciones', 'eval' => fn(Hechos $h, array $x) =>
                $h->es('riesgo_merma', 'bajo') && $h->valor('cobertura_dias') >= 1
                    ? $c('accion_recomendada', 'mantener', ['riesgo_merma', 'cobertura_dias']) : null],
        ];
    }
}