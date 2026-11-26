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
 *   'lotes'        [lote_id => ['stock' => g, 'vence' => Y-m-d (opcional), 'visual' => bueno|regular|malo, 'frio' => cumple|no_cumple]]
 *   'nuevos_lotes' (opcional) lista de ['insumo_id', 'fecha_vencimiento', 'cantidad', 'visual', 'frio']; se aceptan varios
 *   'insumos'      (opcional) lista de insumo_id a analizar; vacío = todos los insumos
 */
final class Sistema
{
    private const TIPOS_DIA = ['laboral', 'fin_de_semana', 'feriado'];
    private const VISUAL    = ['bueno', 'regular', 'malo'];
    private const FRIO      = ['cumple', 'no_cumple'];
    private const VENTANA_HISTORIAL_DIAS = 90;    // respaldo: promedio por día de la semana
    private const DIAS_PRONOSTICO = 30;              // el gráfico llega a un mes; la vista puede mostrar solo los primeros 7
    private const VENTANA_REGRESION_DIAS = 180;   // mínimos cuadrados: 6 meses de historial

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
            $vence = $d['vence'] ?? '';
            if ($vence !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$vence) || !checkdate(
                    (int)substr((string)$vence, 5, 2), (int)substr((string)$vence, 8, 2), (int)substr((string)$vence, 0, 4)))) {
                throw new InvalidArgumentException("Fecha de vencimiento no válida en el lote {$id}.");
            }
        }
        foreach (self::lotesNuevos($e) as $k => $n) {
            $c = 'Lote nuevo #' . ($k + 1) . ': ';
            if (!ctype_digit((string)$n['insumo_id'])) {
                throw new InvalidArgumentException($c . 'insumo no válido.');
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($n['fecha_vencimiento'] ?? '')) || $n['fecha_vencimiento'] < $hoy) {
                throw new InvalidArgumentException($c . 'la fecha de vencimiento no puede ser anterior a hoy.');
            }
            if (!is_numeric($n['cantidad'] ?? null) || (float)$n['cantidad'] <= 0) {
                throw new InvalidArgumentException($c . 'la cantidad debe ser mayor que cero.');
            }
            if (!in_array($n['visual'] ?? '', self::VISUAL, true) || !in_array($n['frio'] ?? '', self::FRIO, true)) {
                throw new InvalidArgumentException($c . 'estado visual o cadena de frío no válidos.');
            }
        }
        foreach ($e['insumos'] ?? [] as $id) {
            if (!ctype_digit((string)$id)) {
                throw new InvalidArgumentException('Insumo seleccionado no válido.');
            }
        }
    }

    /** Lotes nuevos escritos por el usuario (se descartan las filas sin insumo elegido). */
    private static function lotesNuevos(array $e): array
    {
        $lista = $e['nuevos_lotes'] ?? (isset($e['nuevo_lote']) ? [$e['nuevo_lote']] : []);
        return array_values(array_filter($lista, static fn($n): bool => is_array($n) && ($n['insumo_id'] ?? '') !== ''));
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
            $this->repo->actualizarLote((int)$id, (float)$d['stock'], $d['visual'], $d['frio'] === 'cumple',
                ($d['vence'] ?? '') === '' ? null : (string)$d['vence']);
        }
        foreach (self::lotesNuevos($e) as $n) {
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
        // Demanda: regresión por mínimos cuadrados; si un plato tiene pocos datos usa el promedio por día
        $demanda    = new DemandaMinimosCuadrados(
            $this->repo->serieVentas($hoy, self::VENTANA_REGRESION_DIAS),
            new DemandaPromedio($this->repo->estadisticasDemanda($hoy, self::VENTANA_HISTORIAL_DIAS))
        );

        $fechas = array_column($tipos, 'fecha');
        $stockTotal = [];
        foreach ($cobertura as $insumoId => $c) {
            $stockTotal[$insumoId] = $c['stock_total_g'];
        }

        // Insumos que el usuario quiere priorizar (vacío = todos). Se analizan todos los lotes (el stock de
        // los demás ingredientes sigue contando), pero solo se muestra lo que toca a los insumos elegidos.
        $sel = array_values(array_unique(array_map('intval', $e['insumos'] ?? [])));
        $soloPlatos = null;
        if ($sel !== []) {
            $soloPlatos = [];
            foreach ($sel as $id) {
                array_push($soloPlatos, ...array_keys($grafo->vecinos($id)));
            }
            $soloPlatos = array_values(array_unique($soloPlatos));
        }

        // Ventas esperadas de cada día del horizonte (módulo de demanda), de mayor a menor
        $ventas = [];
        foreach ($fechas as $i => $fecha) {
            $fila = [];
            foreach ($grafo->platos() as $platoId => $nombre) {
                if ($soloPlatos !== null && !in_array($platoId, $soloPlatos, true)) {
                    continue;
                }
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

        // Demanda base (g) de cada insumo en cada día del horizonte. Los lotes se procesan del que vence
        // primero al que vence después (FEFO): cada lote consume primero la demanda, y lo que queda es lo
        // que verán los lotes siguientes del mismo insumo.
        $demandaRest = [];
        $demandaBase = function (int $insumoId) use ($grafo, $demanda, $fechas): array {
            $dia = [];
            foreach ($fechas as $d => $fecha) {
                $g = 0.0;
                foreach ($grafo->vecinos($insumoId) as $platoId => $peso) {
                    $g += $demanda->estimar($platoId, $fecha)['porciones'] * $peso;
                }
                $dia[$d] = $g;
            }
            return $dia;
        };

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
                $motivo = array_merge($motivo, $expl->notaCalidad($inf));
                foreach ($r['menu'] as $platoId => $k) {
                    $items[] = ['cantidad' => $k . ($k === 1 ? ' porción' : ' porciones'),
                                'nombre' => $grafo->nombrePlato($platoId), 'nota' => '', 'dia' => 1, 'accion' => 'menu_emergencia',
                                'plato_id' => $platoId, 'porciones' => $k];
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
                $demandaRest[$insumoId] ??= $demandaBase($insumoId);
                $base = array_slice($demandaRest[$insumoId], 0, $dias);
                $r = $astar->planificar($insumoId, $lote['stock_actual'], $costoG, $base,
                    $inf['platos_promocion'], $stockTotal, $par);
                array_push($tecnico, ...$expl->aEstrella($r));
                $motivo = $expl->motivoPromocion($lote, $r, $costoG, $descuento);
                $motivo = array_merge($motivo, $expl->notaCalidad($inf));
                foreach ($r['plan'] as $p) {
                    $items[] = ['cantidad' => $p['porciones'] . ($p['porciones'] === 1 ? ' porción' : ' porciones'),
                                'nombre' => $grafo->nombrePlato($p['plato_id']),
                                'nota' => Explicador::diaTxt($p['dia']) . ' · ' . round($descuento * 100) . ' % de descuento',
                                'dia' => $p['dia'], 'accion' => 'promocionar',
                                'plato_id' => $p['plato_id'], 'porciones' => $p['porciones']];
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
                $motivo = $expl->motivoSinAccion($lote, $inf, $stockTotal);
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
                        $cobTxt = $c['cobertura_dias'] < 1 ? 'menos de un día' : number_format($c['cobertura_dias'], 1, '.', '') . ' días';
                        $items[] = ['cantidad' => 'Comprar ≈ ' . Explicador::peso($gramos), 'nombre' => $lote['insumo'],
                                    'nota' => 'el stock alcanza para ' . $cobTxt, 'dia' => 1, 'accion' => 'comprar',
                                    'finde' => in_array('R21', array_column($inf['traza'], 'regla'), true)];
                        $registros[] = ['accion' => 'comprar', 'fc' => $inf['acciones']['comprar'], 'dia_plan' => 1, 'costo' => $gramos * $costoG];
                        $principal ??= 'comprar';
                    }
                }
            }

            // Este lote absorbe demanda; los lotes que vencen después del mismo insumo verán solo el resto.
            // Un lote descartado o ya vencido no vende nada.
            if ($principal !== 'descartar' && $lote['dias_vida'] >= 0) {
                $demandaRest[$insumoId] ??= $demandaBase($insumoId);
                $queda = $lote['stock_actual'];
                for ($d = 0; $d < $dias; $d++) {
                    $toma = min($queda, $demandaRest[$insumoId][$d]);
                    $demandaRest[$insumoId][$d] -= $toma;
                    $queda -= $toma;
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

        $todos = $resultados;
        if ($sel !== []) {
            $resultados = array_values(array_filter($todos, static fn(array $r): bool => in_array($r['lote']['insumo_id'], $sel, true)));
        }

        // Emergencia pedida para insumos que no tienen nada que venza hoy: explicar y sugerir el plan de 3 días
        $avisos = [];
        if ($tipo === 'emergencia' && $sel !== []) {
            $enResultado = array_map(static fn(array $r): int => $r['lote']['insumo_id'], $resultados);
            foreach ($sel as $id) {
                if (in_array($id, $enResultado, true)) {
                    continue;
                }
                $vidas = array_column(array_filter($lotes, static fn(array $l): bool => $l['insumo_id'] === $id), 'dias_vida');
                $nombre = $grafo->nombreInsumo($id);
                if ($vidas === []) {
                    $avisos[] = ['insumo_id' => $id, 'sugerir_plan' => false,
                        'texto' => "{$nombre} no tiene stock registrado, así que no hay nada que rescatar."];
                } else {
                    $min = min($vidas);
                    $cuando = $min === 1 ? 'mañana' : "en {$min} días";
                    $avisos[] = ['insumo_id' => $id, 'sugerir_plan' => true,
                        'texto' => "El menú de emergencia es solo para lotes que vencen hoy o ya vencieron, y {$nombre} "
                                 . "vence {$cuando} (hoy no corre riesgo). Para planear su uso en los próximos días, "
                                 . 'haga una consulta de plan de 3 días.'];
                }
            }
        }

        return [
            'avisos'      => $avisos,
            'insumos_sel' => array_map(static fn(int $id): string => $grafo->nombreInsumo($id), $sel),
            'consulta_id' => $consultaId,
            'fecha'       => $hoy,
            'tipo'        => $tipo,
            'tipos_dia'   => $tipos,
            'resultados'  => $resultados,
            'plan_dias'   => $tipo === 'plan_3_dias' ? self::armarPlanDias($resultados, $tipos, $ventas) : [],
            'menu_dias'   => $tipo === 'plan_3_dias' ? PlanMenu::construir($grafo, $demanda, $fechas, $tipos, $todos, $par, $soloPlatos) : [],
            'modelo_demanda' => [
                'platos' => $demanda->platosConRegresion(), 'dias' => $demanda->diasUsados(),
                'detalle' => array_map(static fn(int $id, string $n): array => ['plato' => $n] + ($demanda->resumen($id) ?? []),
                    array_keys($grafo->platos()), array_values($grafo->platos())),
            ],
            'ahorro_total' => round(array_sum(array_column($resultados, 'ahorro')), 2),
            'carta' => $tipo === 'plan_3_dias' ? $this->perspectivaCarta($grafo, $demanda, $hoy) : [],
            'pronosticos' => $tipo === 'plan_3_dias' ? $this->pronosticos($grafo, $demanda, $lotes, $sel, $hoy, $horizonte) : ['series' => [], 'defecto' => 0],
        ];
    }

    /**
     * Perspectiva a un mes de cada plato (¿conviene mantenerlo en la carta?).
     * Veredicto de tendencia: la pendiente cuenta solo si es claramente distinta de cero (|t| > 2) y mueve la venta ≥ 3 %.
     * Nivel de venta: lugar del plato entre los demás según las porciones diarias esperadas el próximo mes.
     *
     * @return array<int, array>  ordenado de mayor a menor venta esperada
     */
    private function perspectivaCarta(Grafo $grafo, DemandaMinimosCuadrados $demanda, string $hoy): array
    {
        $lista = [];
        foreach ($grafo->platos() as $platoId => $nombre) {
            $p = $demanda->perspectiva($platoId, $hoy);
            if ($p === null) {
                continue;   // pocos datos: sin regresión no hay proyección a un mes
            }
            $cambio = $p['cambio'];
            $tendencia = 'estable';
            if ($cambio !== null && $p['t'] > 2.0 && $cambio >= 3.0) {
                $tendencia = 'crece';
            } elseif ($cambio !== null && $p['t'] < -2.0 && $cambio <= -3.0) {
                $tendencia = 'baja';
            }
            $lista[] = ['plato_id' => $platoId, 'plato' => $nombre, 'tendencia' => $tendencia] + $p;
        }
        usort($lista, static fn(array $a, array $b): int => $b['proximo'] <=> $a['proximo']);
        $n = count($lista);
        foreach ($lista as $i => &$fila) {
            $fila['puesto'] = $i + 1;
            $fila['de'] = $n;
            $fila['nivel'] = $i < $n / 3 ? 'alto' : ($i < 2 * $n / 3 ? 'medio' : 'bajo');
        }
        unset($fila);
        return $lista;
    }

    /**
     * Series para el selector de pronóstico del chef: una por insumo (gramos por día) y una por plato (porciones por día).
     * Cada serie trae 28 días de historia y 30 de pronóstico: ventas reales (r), lo normal de cada día (a), tendencia (t),
     * desvío de la predicción (sd) y si es futuro (u). Los insumos elegidos por el usuario, o los que tienen un lote por vencer
     * en el horizonte, van primero y quedan preseleccionados.
     *
     * @param int[] $sel
     * @return array{series: array<int, array>, defecto: int}
     */
    private function pronosticos(Grafo $grafo, DemandaMinimosCuadrados $demanda, array $lotes, array $sel, string $hoy, int $horizonte): array
    {
        $prioridad = $sel;
        if ($prioridad === []) {
            $riesgo = [];
            foreach ($lotes as $l) {
                if ($l['dias_vida'] <= $horizonte && $l['stock_actual'] > 0) {
                    $riesgo[$l['insumo_id']] = min($riesgo[$l['insumo_id']] ?? PHP_INT_MAX, $l['dias_vida']);
                }
            }
            asort($riesgo);
            $prioridad = array_keys($riesgo);
        }
        $dia = static fn(string $f, ?float $r, float $a, float $t, float $sd, bool $u, int $dec): array => [
            'f' => $f, 'r' => $r === null ? null : round($r, $dec), 'a' => round($a, $dec), 't' => round($t, $dec),
            'sd' => round($sd, $dec), 'u' => $u,
        ];

        $porInsumo = [];
        foreach ($grafo->insumos() as $insumoId => $nombre) {
            $dias = []; $varianza = 0.0; $pendiente = 0.0; $platos = [];
            foreach ($grafo->vecinos($insumoId) as $platoId => $peso) {
                $p = $demanda->proyeccion($platoId, $hoy, 28, self::DIAS_PRONOSTICO);
                if ($p === null) {
                    continue;   // pocos datos: no hay regresión que proyectar
                }
                $platos[] = $grafo->nombrePlato($platoId);
                $varianza  += ($peso * $p['s']) ** 2;
                $pendiente += $peso * $p['pendiente'];
                foreach ($p['dias'] as $i => $d) {
                    $dias[$i] ??= ['f' => $d['fecha'], 'r' => 0.0, 'a' => 0.0, 't' => 0.0, 'sd2' => 0.0, 'u' => $d['futuro']];
                    $dias[$i]['a']   += $peso * $d['ajuste'];
                    $dias[$i]['t']   += $peso * $d['tendencia'];
                    $dias[$i]['sd2'] += ($peso * $d['sd']) ** 2;   // platos independientes
                    $dias[$i]['r']    = ($dias[$i]['r'] === null || $d['real'] === null) ? null : $dias[$i]['r'] + $peso * $d['real'];
                }
            }
            if ($platos === []) {
                continue;
            }
            $porInsumo[$insumoId] = [
                'tipo' => 'insumo', 'id' => $insumoId, 'nombre' => $nombre, 'unidad' => 'g', 'pendiente' => round($pendiente, 2),
                's' => round(sqrt($varianza), 1), 'platos' => $platos,
                'dias' => array_values(array_map(static fn(array $d): array => $dia($d['f'], $d['r'], $d['a'], $d['t'], sqrt($d['sd2']), $d['u'], 0), $dias)),
            ];
        }

        $series = [];
        foreach ($prioridad as $id) {                       // 1) insumos en riesgo o elegidos
            if (isset($porInsumo[$id])) {
                $series[] = ['grupo' => 'Insumos con lotes por vencer o elegidos'] + $porInsumo[$id];
                unset($porInsumo[$id]);
            }
        }
        uasort($porInsumo, static fn(array $a, array $b): int => strcmp($a['nombre'], $b['nombre']));
        foreach ($porInsumo as $x) {                         // 2) demás insumos
            $series[] = ['grupo' => 'Otros insumos'] + $x;
        }
        $platosOrden = $grafo->platos();
        asort($platosOrden);
        foreach ($platosOrden as $platoId => $nombre) {      // 3) platos
            $p = $demanda->proyeccion($platoId, $hoy, 28, self::DIAS_PRONOSTICO);
            if ($p === null) {
                continue;
            }
            $series[] = [
                'grupo' => 'Platos', 'tipo' => 'plato', 'id' => $platoId, 'nombre' => $nombre, 'unidad' => 'porciones',
                'pendiente' => round($p['pendiente'], 4), 's' => round($p['s'], 2), 'platos' => [],
                'dias' => array_map(static fn(array $d): array => $dia($d['fecha'], $d['real'], $d['ajuste'], $d['tendencia'], $d['sd'], $d['futuro'], 2), $p['dias']),
            ];
        }
        return ['series' => $series, 'defecto' => 0];   // la primera serie es la preseleccionada
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

            // Todas las compras del día van en UNA lista, no en una tarjeta por insumo
            $compras = array_values(array_filter($d['bloques'], static fn(array $b): bool => $b['accion'] === 'comprar'));
            if ($compras !== []) {
                $d['bloques'] = array_values(array_filter($d['bloques'], static fn(array $b): bool => $b['accion'] !== 'comprar'));
                $d['bloques'][] = [
                    'accion' => 'comprar', 'lote' => null,
                    'items'  => array_merge(...array_map(static fn(array $b): array => $b['items'], $compras)),
                    'finde'  => in_array(true, array_merge(...array_map(
                        static fn(array $b): array => array_column($b['items'], 'finde'), $compras)), true),
                ];
            }
        }
        unset($d);
        return array_values($dias);
    }
}