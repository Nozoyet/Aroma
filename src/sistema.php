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
            $lineas = $expl->lote($lote, $inf);

            // Acciones a registrar: cada una es [accion, fc, lote_id, plato_id, dia, porciones, costo, ahorro]
            $filas = [];      // texto para la pantalla
            $registros = [];  // filas para la tabla decision
            $principal = $inf['accion_principal'];

            if ($principal === 'menu_emergencia') {
                $r = $dfs->resolver($insumoId, $lote['stock_actual'], $costoG, $stockTotal, $par);
                array_push($lineas, ...$expl->dfs($r));
                foreach ($r['menu'] as $platoId => $k) {
                    $filas[] = sprintf('%s: %d porciones', $grafo->nombrePlato($platoId), $k);
                    $registros[] = ['accion' => 'menu_emergencia', 'fc' => $inf['fc_principal'], 'plato_id' => $platoId,
                        'porciones' => $k, 'ahorro' => $k * $grafo->gramos($platoId, $insumoId) * $costoG];
                }
                if ($r['menu'] === []) {
                    $filas[] = 'No se puede armar un menú con el stock actual.';
                    $registros[] = ['accion' => 'menu_emergencia', 'fc' => $inf['fc_principal'], 'costo' => $r['valor_lote']];
                }
                if ($r['advertencia'] !== null) {
                    $inf['alertas'][] = $r['advertencia'];
                }
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
                array_push($lineas, ...$expl->aEstrella($r));
                foreach ($r['plan'] as $p) {
                    $filas[] = sprintf('Día %d: promocionar %d porciones de %s', $p['dia'], $p['porciones'], $grafo->nombrePlato($p['plato_id']));
                    $registros[] = ['accion' => 'promocionar', 'fc' => $inf['fc_principal'], 'plato_id' => $p['plato_id'],
                        'dia_plan' => $p['dia'], 'porciones' => $p['porciones'], 'costo' => $p['descuento'],
                        'ahorro' => $p['gramos'] * $costoG - $p['descuento']];
                }
                if ($r['plan'] === []) {
                    $filas[] = 'No hace falta promocionar: la demanda esperada consume el lote.';
                    $registros[] = ['accion' => 'mantener', 'fc' => $inf['fc_principal']];
                    $principal = 'mantener';
                }
            } elseif ($principal === 'descartar') {
                $filas[] = sprintf('Descartar el lote (pérdida de %.2f Bs).', $lote['valor_lote']);
                $registros[] = ['accion' => 'descartar', 'fc' => $inf['fc_principal'], 'costo' => $lote['valor_lote']];
            } elseif ($principal === 'mantener') {
                $filas[] = 'Mantener: el lote se consume antes de vencer.';
                $registros[] = ['accion' => 'mantener', 'fc' => $inf['fc_principal']];
            }

            // Compra: acción principal o complementaria (p. ej. descartar y además reponer), una por insumo
            if (isset($inf['acciones']['comprar']) && !isset($comprasHechas[$insumoId])) {
                $c = $cobertura[$insumoId] ?? null;
                $objetivo = (float)($par['cobertura_objetivo_dias'] ?? 2.0);
                if ($c !== null && $c['consumo_diario_g'] !== null) {
                    $gramos = max(0.0, round(($c['consumo_diario_g'] * $objetivo - $c['stock_total_g']) / 100) * 100);
                    if ($gramos > 0) {
                        $comprasHechas[$insumoId] = true;
                        array_push($lineas, ...$expl->compra($gramos, $c['stock_total_g'], $c['consumo_diario_g'], $objetivo));
                        $filas[] = sprintf('Comprar aprox. %.0f g de %s.', $gramos, $lote['insumo']);
                        $registros[] = ['accion' => 'comprar', 'fc' => $inf['acciones']['comprar'], 'costo' => $gramos * $costoG];
                        $principal ??= 'comprar';
                    }
                }
            }

            // Guardar en el historial (solo si hay algo que registrar)
            $ids = [];
            $texto = implode("\n", $lineas);
            foreach ($registros as $reg) {
                $decisionId = $this->repo->guardarDecision($reg + [
                    'consulta_id' => $consultaId, 'lote_id' => $lote['lote_id'], 'explicacion' => $texto,
                ]);
                $this->repo->guardarReglasDisparadas($decisionId, $inf['traza']);
                $ids[] = $decisionId;
            }

            $resultados[] = [
                'lote'        => $lote,
                'accion'      => $principal,
                'fc'          => $inf['fc_principal'],
                'filas'       => $filas,
                'alertas'     => $inf['alertas'],
                'explicacion' => $lineas,
                'decisiones'  => $ids,
                'ahorro'      => array_sum(array_column($registros, 'ahorro')),
            ];
        }

        return [
            'consulta_id' => $consultaId,
            'fecha'       => $hoy,
            'tipo'        => $tipo,
            'tipos_dia'   => $tipos,
            'resultados'  => $resultados,
            'ahorro_total' => round(array_sum(array_column($resultados, 'ahorro')), 2),
        ];
    }
}