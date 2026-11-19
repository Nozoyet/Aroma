<?php
declare(strict_types=1);

/**
 * Orquestador: recibe lo que el usuario llenó en el formulario, lo guarda en la BD, y para
 * cada lote ejecuta   reglas → (DFS | A* | compra | descarte | mantener)  → explicación → historial.
 * Todo ocurre dentro de UNA transacción: o se guarda la consulta completa o nada.
 *
 * $entrada (viene del formulario, ya como arrays):
 *   'tipo'         'plan_3_dias' | 'emergencia'  (emergencia = solo lotes que vencen hoy o ya vencidos)
 *   'observaciones' texto libre (opcional)
 *   'tipo_dia'     [fecha => 'laboral'|'fin_de_semana'|'feriado']      para hoy y los días del horizonte
 *   'lotes'        [lote_id => ['stock' => g, 'visual' => bueno|regular|malo, 'frio' => cumple|no_cumple]]
 *   'nuevo_lote'   (opcional) ['insumo_id', 'fecha_vencimiento', 'cantidad', 'visual', 'frio']
 */
final class Sistema
{
    private const TIPOS_DIA = ['laboral', 'fin_de_semana', 'feriado'];
    private const VISUAL    = ['bueno', 'regular', 'malo'];
    private const FRIO      = ['cumple', 'no_cumple'];
    private const VENTANA_HISTORIAL_DIAS = 90;

    public function __construct(private Repositorio $repo)
    {
    }

    public function ejecutar(array $entrada, ?string $hoy = null): array
    {
        $hoy ??= date('Y-m-d');
        $tipo = (string)($entrada['tipo'] ?? 'plan_3_dias');
        if (!in_array($tipo, ['plan_3_dias', 'emergencia'], true)) {
            throw new InvalidArgumentException('Tipo de consulta no válido.');
        }
        $this->validar($entrada, $hoy);

        return $this->repo->transaccion(fn() => $this->procesar($entrada, $tipo, $hoy));
    }

    // ------------------------------------------------------------------
    // Validación de lo que escribió el usuario
    // ------------------------------------------------------------------

    private function validar(array $e, string $hoy): void
    {
        $limite = (new DateTimeImmutable($hoy))->modify('+6 day')->format('Y-m-d');
        foreach ($e['tipo_dia'] ?? [] as $fecha => $tipo) {
            if (!is_string($fecha) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || $fecha < $hoy || $fecha > $limite) {
                throw new InvalidArgumentException("Fecha del calendario fuera de rango: {$fecha}.");
            }
            if (!in_array($tipo, self::TIPOS_DIA, true)) {
                throw new InvalidArgumentException("Tipo de día no válido para {$fecha}.");
            }
        }
        foreach ($e['lotes'] ?? [] as $id => $d) {
            if (!is_numeric($d['stock'] ?? null) || (float)$d['stock'] < 0) {
                throw new InvalidArgumentException("Stock no válido en el lote {$id}.");
            }
            if (!in_array($d['visual'] ?? '', self::VISUAL, true) || !in_array($d['frio'] ?? '', self::FRIO, true)) {
                throw new InvalidArgumentException("Estado visual o cadena de frío no válidos en el lote {$id}.");
            }
        }
        $n = $e['nuevo_lote'] ?? null;
        if (is_array($n) && ($n['insumo_id'] ?? '') !== '') {
            if (!ctype_digit((string)$n['insumo_id'])) {
                throw new InvalidArgumentException('Insumo del lote nuevo no válido.');
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($n['fecha_vencimiento'] ?? '')) || $n['fecha_vencimiento'] < $hoy) {
                throw new InvalidArgumentException('La fecha de vencimiento del lote nuevo no puede ser anterior a hoy.');
            }
            if (!is_numeric($n['cantidad'] ?? null) || (float)$n['cantidad'] <= 0) {
                throw new InvalidArgumentException('La cantidad del lote nuevo debe ser mayor que cero.');
            }
            if (!in_array($n['visual'] ?? '', self::VISUAL, true) || !in_array($n['frio'] ?? '', self::FRIO, true)) {
                throw new InvalidArgumentException('Estado visual o cadena de frío del lote nuevo no válidos.');
            }
        }
    }

    // ------------------------------------------------------------------
    // Proceso completo
    // ------------------------------------------------------------------

    private function procesar(array $e, string $tipo, string $hoy): array
    {
        // 1) Guardar lo que ingresó el usuario (el motor siempre lee de la BD)
        foreach ($e['tipo_dia'] ?? [] as $fecha => $tipoDia) {
            $this->repo->guardarCalendario($fecha, $tipoDia);
        }
        foreach ($e['lotes'] ?? [] as $id => $d) {
            $this->repo->actualizarLote((int)$id, (float)$d['stock'], $d['visual'], $d['frio'] === 'cumple');
        }
        $n = $e['nuevo_lote'] ?? null;
        if (is_array($n) && ($n['insumo_id'] ?? '') !== '') {
            $this->repo->insertarLote((int)$n['insumo_id'], null, $hoy, $n['fecha_vencimiento'],
                (float)$n['cantidad'], $n['visual'], $n['frio'] === 'cumple');
        }

        // 2) Cargar la memoria de trabajo desde la BD
        $reglas     = $this->repo->reglas();
        $par        = $this->repo->parametros();
        $horizonte  = max(1, (int)($par['horizonte_dias'] ?? 3));
        $lotes      = $this->repo->lotes();
        $cobertura  = $this->repo->coberturaPorInsumo();
        $porciones  = $this->repo->porcionesPosibles();
        $insumos    = $this->repo->insumos();
        $tipos      = $this->repo->tiposDia($hoy, $horizonte);
        $grafo      = new Grafo($this->repo->recetas());
        $demanda    = new DemandaPromedio($this->repo->estadisticasDemanda($hoy, self::VENTANA_HISTORIAL_DIAS));

        $fechas = array_column($tipos, 'fecha');
        $stockTotal = [];
        foreach ($cobertura as $insumoId => $c) {
            $stockTotal[$insumoId] = $c['stock_total_g'];
        }

        // Ventas esperadas de cada día del horizonte (módulo de demanda), de mayor a menor
        $ventas = [];
        foreach ($fechas as $i => $fecha) {
            $fila = [];
            foreach ($grafo->platos() as $platoId => $nombre) {
                $fila[] = ['plato' => $nombre, 'porciones' => (int)round($demanda->estimar($platoId, $fecha)['porciones'])];
            }
            usort($fila, static fn(array $a, array $b): int => $b['porciones'] <=> $a['porciones']);
            $ventas[$i + 1] = $fila;
        }

        $motor = new MotorReglas($reglas, $par);
        $expl  = new Explicador($reglas, $grafo);
        $dfs   = new Dfs($grafo);
        $astar = new AEstrella($grafo);

        $consultaId = $this->repo->crearConsulta(
            $this->repo->usuarioPorDefecto(), $tipo, $hoy,
            ($e['observaciones'] ?? '') === '' ? null : mb_substr((string)$e['observaciones'], 0, 300)
        );

        // 3) Inferir y decidir lote por lote
        $resultados = [];
        $comprasHechas = [];
        foreach ($lotes as $lote) {
            if ($tipo === 'emergencia' && $lote['dias_vida'] > 0) {
                continue;
            }
            $insumoId = $lote['insumo_id'];
            $costoG   = $insumos[$insumoId]['costo_unitario'] ?? 0.0;
            $dias     = max(1, min($horizonte, $lote['dias_vida'] + 1));   // días en que el lote aún se puede usar

            // Platos vecinos del insumo en el grafo, con su demanda esperada hasta el vencimiento
            $platos = [];
            foreach ($grafo->vecinos($insumoId) as $platoId => $_peso) {
                $total = 0.0;
                $fcMin = 1.0;
                for ($d = 0; $d < $dias; $d++) {
                    $est = $demanda->estimar($platoId, $fechas[$d]);
                    $total += $est['porciones'];
                    $fcMin = min($fcMin, $est['fc']);
                }
                $platos[$platoId] = [
                    'nombre' => $grafo->nombrePlato($platoId), 'demanda_total' => $total,
                    'fc_demanda' => $fcMin, 'porciones_posibles' => $porciones[$platoId] ?? 0,
                ];
            }
            $ctx = [
                'cobertura_dias'    => $cobertura[$insumoId]['cobertura_dias'] ?? INF,
                'tipo_dia_proximos' => array_column($tipos, 'tipo_dia'),
                'platos'            => $platos,
            ];
            $inf = $motor->inferir($lote, $ctx);
            $tecnico   = $expl->lote($lote, $inf);   // detalle técnico (reglas disparadas), opcional en pantalla
            $items     = [];   // lo que se muestra: ['cantidad', 'nombre', 'nota']
            $metricas  = [];   // cifras clave: ['etiqueta', 'valor']
            $motivo    = [];   // justificación en lenguaje sencillo (todas las acciones del lote)
            $motivos   = [];   // ... separada por acción, para el plan por días
            $metricasPorAccion = [];
            $registros = [];   // filas para la tabla decision
            $alertas   = $inf['alertas'];
            $principal = $inf['accion_principal'];
            $descuento = (float)($par['descuento_promo'] ?? 0.10);

            if ($principal === 'menu_emergencia') {
                $r = $dfs->resolver($insumoId, $lote['stock_actual'], $costoG, $stockTotal, $par);
                array_push($tecnico, ...$expl->dfs($r));
                $motivo = $expl->motivoMenu($lote, $r, $stockTotal);
                foreach ($r['menu'] as $platoId => $k) {
                    $items[] = ['cantidad' => $k . ($k === 1 ? ' porción' : ' porciones'),
                                'nombre' => $grafo->nombrePlato($platoId), 'nota' => '', 'dia' => 1, 'accion' => 'menu_emergencia'];
                    $registros[] = ['accion' => 'menu_emergencia', 'fc' => $inf['fc_principal'], 'plato_id' => $platoId,
                        'dia_plan' => 1, 'porciones' => $k, 'ahorro' => $k * $grafo->gramos($platoId, $insumoId) * $costoG];
                }
                if ($r['menu'] === []) {
                    $registros[] = ['accion' => 'menu_emergencia', 'fc' => $inf['fc_principal'], 'dia_plan' => 1, 'costo' => $r['valor_lote']];
                }
                $metricas = [
                    ['etiqueta' => 'Se aprovecha', 'valor' => round($r['porcentaje'] * 100) . ' % del lote'],
                    ['etiqueta' => 'Valor rescatado', 'valor' => Explicador::bs($r['valor_rescatado'])],
                    ['etiqueta' => 'Sobrante', 'valor' => Explicador::peso($r['gramos_sobrante'])],
                ];
                $motivos['menu_emergencia'] = $motivo;
                $metricasPorAccion['menu_emergencia'] = $metricas;
            } elseif ($principal === 'promocionar') {
                $base = [];
                for ($d = 0; $d < $dias; $d++) {
                    $g = 0.0;
                    foreach ($grafo->vecinos($insumoId) as $platoId => $peso) {
                        $g += $demanda->estimar($platoId, $fechas[$d])['porciones'] * $peso;
                    }
                    $base[] = $g;
                }
                $r = $astar->planificar($insumoId, $lote['stock_actual'], $costoG, $base,
                    $inf['platos_promocion'], $stockTotal, $par);
                array_push($tecnico, ...$expl->aEstrella($r));
                $motivo = $expl->motivoPromocion($lote, $r, $costoG, $descuento);
                foreach ($r['plan'] as $p) {
                    $items[] = ['cantidad' => $p['porciones'] . ($p['porciones'] === 1 ? ' porción' : ' porciones'),
                                'nombre' => $grafo->nombrePlato($p['plato_id']),
                                'nota' => Explicador::diaTxt($p['dia']) . ' · ' . round($descuento * 100) . ' % de descuento',
                                'dia' => $p['dia'], 'accion' => 'promocionar'];
                    $registros[] = ['accion' => 'promocionar', 'fc' => $inf['fc_principal'], 'plato_id' => $p['plato_id'],
                        'dia_plan' => $p['dia'], 'porciones' => $p['porciones'], 'costo' => $p['descuento'],
                        'ahorro' => $p['gramos'] * $costoG - $p['descuento']];
                }
                if ($r['plan'] === []) {
                    $registros[] = ['accion' => 'mantener', 'fc' => $inf['fc_principal']];
                    $principal = 'mantener';
                } else {
                    $metricas = [
                        ['etiqueta' => 'Sin hacer nada se pierde', 'valor' => Explicador::bs($r['costo_sin_intervencion'])],
                        ['etiqueta' => 'Con la promoción cuesta', 'valor' => Explicador::bs($r['costo_total'])],
                        ['etiqueta' => 'Ahorro', 'valor' => Explicador::bs($r['ahorro'])],
                    ];
                    $motivos['promocionar'] = $motivo;
                    $metricasPorAccion['promocionar'] = $metricas;
                }
            } elseif ($principal === 'descartar') {
                $motivo = $expl->motivoDescarte($lote, $inf);
                $items[] = ['cantidad' => 'Descartar el lote', 'nombre' => $lote['insumo'],
                            'nota' => 'pérdida de ' . Explicador::bs($lote['valor_lote']), 'dia' => 1, 'accion' => 'descartar'];
                $registros[] = ['accion' => 'descartar', 'fc' => $inf['fc_principal'], 'dia_plan' => 1, 'costo' => $lote['valor_lote']];
                $motivos['descartar'] = $motivo;
            } elseif ($principal === 'mantener') {
                $motivo = $expl->motivoMantener($lote);
                $registros[] = ['accion' => 'mantener', 'fc' => $inf['fc_principal']];
            } elseif ($principal === null) {
                $motivo = $expl->motivoSinAccion($lote);
                $alertas = [];   // el motivo ya explica el caso
            }

            // Compra: acción principal o complementaria (p. ej. descartar y además reponer), una por insumo
            if (isset($inf['acciones']['comprar']) && !isset($comprasHechas[$insumoId])) {
                $c = $cobertura[$insumoId] ?? null;
                $objetivo = (float)($par['cobertura_objetivo_dias'] ?? 2.0);
                if ($c !== null && $c['consumo_diario_g'] !== null) {
                    $gramos = max(0.0, round(($c['consumo_diario_g'] * $objetivo - $c['stock_total_g']) / 100) * 100);
                    if ($gramos > 0) {
                        $comprasHechas[$insumoId] = true;
                        array_push($tecnico, ...$expl->compra($gramos, $c['stock_total_g'], $c['consumo_diario_g'], $objetivo));
                        $motivoCompra = $expl->motivoCompra($lote, $inf, $gramos, $c, $objetivo);
                        array_push($motivo, ...$motivoCompra);
                        $motivos['comprar'] = $motivoCompra;
                        $items[] = ['cantidad' => 'Comprar ≈ ' . Explicador::peso($gramos), 'nombre' => $lote['insumo'], 'nota' => '',
                                    'dia' => 1, 'accion' => 'comprar'];
                        $registros[] = ['accion' => 'comprar', 'fc' => $inf['acciones']['comprar'], 'dia_plan' => 1, 'costo' => $gramos * $costoG];
                        $principal ??= 'comprar';
                    }
                }
            }

            // Guardar en el historial: la explicación guardada es la misma que lee el usuario
            $ids = [];
            $texto = implode("\n", $motivo);
            foreach ($registros as $reg) {
                $decisionId = $this->repo->guardarDecision($reg + [
                    'consulta_id' => $consultaId, 'lote_id' => $lote['lote_id'], 'explicacion' => $texto,
                ]);
                $this->repo->guardarReglasDisparadas($decisionId, $inf['traza']);
                $ids[] = $decisionId;
            }

            $resultados[] = [
                'lote'       => $lote,
                'accion'     => $principal,
                'fc'         => $inf['fc_principal'],
                'confianza'  => $principal === null ? null : Explicador::confianza($inf['fc_principal']),
                'items'      => $items,
                'metricas'   => $metricas,
                'metricas_por_accion' => $metricasPorAccion,
                'fc_por_accion' => $inf['acciones'],
                'motivo'     => $motivo,
                'motivos'    => $motivos,
                'alertas'    => $alertas,
                'tecnico'    => $tecnico,
                'decisiones' => $ids,
                'ahorro'     => array_sum(array_column($registros, 'ahorro')),
            ];
        }

        return [
            'consulta_id' => $consultaId,
            'fecha'       => $hoy,
            'tipo'        => $tipo,
            'tipos_dia'   => $tipos,
            'resultados'  => $resultados,
            'plan_dias'   => $tipo === 'plan_3_dias' ? self::armarPlanDias($resultados, $tipos, $ventas) : [],
            'ahorro_total' => round(array_sum(array_column($resultados, 'ahorro')), 2),
        ];
    }

    /**
     * Reordena las decisiones por día para el "plan de 3 días".
     * Cada día trae: los lotes que vencen ese día y los bloques de acción (cada bloque tiene la forma
     * de un resultado por lote, así la vista los dibuja igual). Los lotes ya vencidos se listan en el día 1.
     * El menú de emergencia, los descartes y las compras van en el día 1; las promociones, en el día que eligió A*.
     *
     * @param array $resultados resultados por lote de procesar()
     * @param array $tipos      Repositorio::tiposDia(): fecha y tipo de día de cada día del horizonte
     * @param array $ventas     [día => [['plato', 'porciones'], ...]] ventas esperadas de cada día
     */
    public static function armarPlanDias(array $resultados, array $tipos, array $ventas = []): array
    {
        $etiquetas = ['Hoy', 'Mañana', 'Pasado mañana'];
        $orden = ['menu_emergencia' => 0, 'descartar' => 1, 'promocionar' => 2, 'comprar' => 3];
        $estado = ['menu_emergencia' => 'menú de emergencia', 'descartar' => 'se descarta', 'promocionar' => 'se promociona',
                   'comprar' => 'se repone con una compra', 'mantener' => 'no requiere acción'];

        $dias = [];
        foreach ($tipos as $i => $t) {
            $dias[$i + 1] = ['dia' => $i + 1, 'fecha' => $t['fecha'], 'etiqueta' => $etiquetas[$i] ?? 'Día ' . ($i + 1),
                             'tipo_dia' => $t['tipo_dia'], 'vencen' => [], 'bloques' => [], 'ventas' => $ventas[$i + 1] ?? []];
        }

        foreach ($resultados as $r) {
            $l = $r['lote'];

            $diaVence = max(0, $l['dias_vida']) + 1;
            if (isset($dias[$diaVence])) {
                $dias[$diaVence]['vencen'][] = [
                    'insumo' => $l['insumo'], 'gramos' => $l['stock_actual'], 'vencido' => $l['dias_vida'] < 0,
                    'estado' => $estado[$r['accion'] ?? ''] ?? 'revisar a mano',
                ];
            }

            $porClave = [];
            foreach ($r['items'] as $it) {
                $porClave[$it['dia'] . '|' . $it['accion']][] = $it;
            }
            foreach ($porClave as $clave => $items) {
                [$dia, $accion] = explode('|', $clave);
                if (!isset($dias[(int)$dia])) {
                    continue;
                }
                $dias[(int)$dia]['bloques'][] = [
                    'lote'      => $l,
                    'accion'    => $accion,
                    'items'     => $items,
                    'metricas'  => $r['metricas_por_accion'][$accion] ?? [],
                    'motivo'    => $r['motivos'][$accion] ?? $r['motivo'],
                    'alertas'   => $accion === $r['accion'] ? $r['alertas'] : [],
                    'confianza' => Explicador::confianza((float)($r['fc_por_accion'][$accion] ?? $r['fc'])),
                    'tecnico'   => $r['tecnico'],
                ];
            }
        }

        foreach ($dias as &$d) {
            usort($d['bloques'], static fn(array $a, array $b): int =>
                [$orden[$a['accion']], $a['lote']['insumo']] <=> [$orden[$b['accion']], $b['lote']['insumo']]);
        }
        unset($d);
        return array_values($dias);
    }
}