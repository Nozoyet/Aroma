<?php
declare(strict_types=1);

/**
 * Cerebro del chat (fase 1: respuestas con plantillas, sin modelo de lenguaje).
 *
 * Reglas de diseño:
 *  - Nunca decide si un lote es apto: solo cuenta lo que el sistema experto ya guardó (tabla decision).
 *  - Solo lee (LecturaChat). No llama a Sistema::ejecutar() ni escribe nada.
 *  - Cada handler devuelve texto + poses candidatas para la mascota (las elige ReaccionMascota).
 *
 * Fase 2: el método redactar() es el único punto donde se conectará el modelo; recibirá el mismo
 * texto/datos ya calculados por PHP y solo podrá reformularlos (y sugerir una pose de TONO).
 */
final class ChatAroma
{
    private const ACCIONES = [
        'descartar'       => 'descartar el lote',
        'menu_emergencia' => 'usarlo en el menú de emergencia',
        'promocionar'     => 'promocionarlo',
        'comprar'         => 'reponer (comprar)',
        'mantener'        => 'mantenerlo (se consume antes de vencer)',
    ];

    private const GLOSARIO = [
        'dias_vida'              => 'días que le quedan al lote antes de vencer',
        'estado_lote'            => 'clasificación del lote: fresco, por vencer, vence hoy o vencido',
        'estado_visual'          => 'cómo se ve el producto: bueno, regular o malo',
        'cadena_frio'            => 'si el lote mantuvo la cadena de frío (cumple / no cumple)',
        'categoria_riesgo'       => 'qué tan delicado es el insumo (alto, medio o bajo)',
        'apto_consumo'           => 'si el lote es seguro para consumir (lo decide el sistema)',
        'fc_calidad'             => 'confianza en la calidad del lote (0 a 1)',
        'riesgo_merma'           => 'probabilidad de que el lote se pierda sin usarse',
        'cobertura_dias'         => 'cuántos días alcanza el stock al ritmo de consumo actual',
        'demanda_pronosticada'   => 'porciones que se esperan vender',
        'porciones_posibles'     => 'porciones que se pueden preparar con el stock actual',
        'candidato_promocion'    => 'plato que podría promocionarse para usar el lote',
        'accion_recomendada'     => 'lo que el sistema recomienda hacer con el lote',
        'umbral_fc_calidad'      => 'confianza mínima de calidad para promocionar',
        'umbral_fc_emergencia'   => 'confianza mínima para entrar al menú de emergencia',
    ];

    public function __construct(private LecturaChat $repo)
    {
    }

    /** @return array{texto:string, emocion:string, intencion:string} */
    public function responder(string $mensaje): array
    {
        $c = IntencionChat::clasificar($mensaje, $this->repo->insumos(), $this->repo->platos());

        $r = match ($c['intencion']) {
            'saludo'       => $this->t('¡Hola! Soy el onigiri de Aroma 🍙. Puedo contarte cuánto stock queda, qué lotes vencen, por qué el sistema tomó una decisión o explicarte una regla (R1 a R26).'),
            'gracias'      => $this->t('¡De nada! Aquí estoy cuando me necesites.', [], 'love'),
            'regla'        => $this->hRegla($c),
            'vencimientos' => $this->hVencimientos($c),
            'stock'        => $this->hStock($c),
            'apto'         => $this->hApto($c),
            'porque'       => $this->hPorque($c),
            'plato'        => $this->hPlato($c),
            'ayuda'        => $this->hAyuda(),
            'manipulacion' => $this->t('Eso no me corresponde: yo no decido ni puedo saltarme las reglas. Si un lote es apto o no lo decide el motor de reglas cuando ejecutas la consulta; yo solo te cuento lo que dijo.', [], 'shocked'),
            'raro'         => $this->t('No entendí ese mensaje 😅. Prueba con algo como "¿qué vence hoy?" o "¿cuánto pollo queda?".', [], 'shocked'),
            default        => $this->t('Solo puedo ayudarte con Aroma: stock, lotes por vencer, decisiones del sistema, platos y reglas. ¿Quieres que te muestre ejemplos?', [], 'confused'),
        };

        return [
            'texto'     => $this->redactar($r),
            'emocion'   => ReaccionMascota::elegir($r['candidatas'], $r['tono']),
            'intencion' => $c['intencion'],
        ];
    }

    /** Punto de enganche del modelo (fase 2). En la fase 1 devuelve el texto de plantilla tal cual. */
    private function redactar(array $r): string
    {
        return $r['texto'];
    }

    // ------------------------------------------------------------------ handlers

    private function hStock(array $c): array
    {
        if ($c['insumo'] === null) {
            return $this->t('¿De qué insumo quieres saber el stock? Por ejemplo: "¿cuánto pollo queda?".', ['think']);
        }
        $id = $c['insumo']['insumo_id'];
        $n  = $c['insumo']['nombre'];
        $cob   = $this->repo->cobertura()[$id] ?? null;
        $lotes = $this->lotesDe($id);
        $vigente = $cob['stock_total_g'] ?? 0.0;

        $cand = [];
        if ($lotes === [] || $vigente <= 0) {
            $cand[] = 'sad';
        }
        $l = ["{$n}: " . ($vigente > 0 ? $this->g($vigente) . ' de stock vigente.' : 'no hay stock vigente.')];

        if ($cob !== null && $vigente > 0) {
            if ($cob['consumo_diario_g'] !== null && $cob['cobertura_dias'] !== null) {
                $l[] = 'Consumo diario estimado: ' . $this->g($cob['consumo_diario_g']) . ' → alcanza para ' . $this->d1($cob['cobertura_dias']) . ' día(s).';
                if ($cob['cobertura_dias'] < 1) {
                    $l[] = 'Ojo: alcanza para menos de un día.';
                    $cand[] = 'sad';
                }
            } else {
                $l[] = 'No hay consumo registrado, así que no puedo estimar cuántos días dura.';
            }
        }
        foreach (array_slice($lotes, 0, 6) as $lo) {
            $l[] = "• Lote {$lo['lote_id']}: " . $this->g($lo['stock_actual']) . ' · ' . $this->dias($lo['dias_vida']) . " · riesgo {$lo['categoria_riesgo']}";
            if (in_array($lo['estado_lote'], ['vence_hoy', 'vencido'], true)) {
                $cand[] = 'alert';
            }
        }
        return $this->t(implode("\n", $l), $cand);
    }

    private function hVencimientos(array $c): array
    {
        $lotes = $c['insumo'] ? $this->lotesDe($c['insumo']['insumo_id']) : $this->repo->lotes();
        $grupos = ['vencido' => [], 'vence_hoy' => [], 'por_vencer' => []];
        foreach ($lotes as $lo) {
            if (isset($grupos[$lo['estado_lote']])) {
                $grupos[$lo['estado_lote']][] = $lo;
            }
        }
        $ambito = $c['insumo'] ? ' de ' . $c['insumo']['nombre'] : '';
        if ($c['insumo'] && $lotes === []) {
            return $this->t("No tengo lotes{$ambito} con stock.", ['sad']);
        }
        if (array_merge(...array_values($grupos)) === []) {
            return $this->t("No hay lotes{$ambito} vencidos ni por vencer en los próximos 2 días.", ['ok']);
        }
        $titulos = ['vencido' => 'VENCIDOS', 'vence_hoy' => 'VENCEN HOY', 'por_vencer' => 'POR VENCER (1-2 días)'];
        $l = [];
        foreach ($grupos as $k => $arr) {
            if ($arr === []) {
                continue;
            }
            $l[] = $titulos[$k] . ':';
            foreach (array_slice($arr, 0, 8) as $lo) {
                $l[] = "• Lote {$lo['lote_id']} de {$lo['insumo']}: " . $this->g($lo['stock_actual']) . ' · ' . $this->dias($lo['dias_vida']);
            }
        }
        $l[] = 'Esto es el estado actual de los lotes. Qué hacer con ellos lo decide el motor de reglas al ejecutar una consulta.';
        $cand = ($grupos['vencido'] !== [] || $grupos['vence_hoy'] !== []) ? ['alert'] : [];
        return $this->t(implode("\n", $l), $cand);
    }

    private function hApto(array $c): array
    {
        if ($c['insumo'] === null) {
            return $this->t('¿De qué insumo? Por ejemplo: "¿puedo usar el pollo?".', ['think']);
        }
        $n = $c['insumo']['nombre'];
        $lotes = $this->lotesDe($c['insumo']['insumo_id']);
        if ($lotes === []) {
            return $this->t("No tengo lotes de {$n} con stock.", ['sad']);
        }
        $u = $this->ultima();
        $vieja = $u !== null && $u['consulta']['fecha_base'] < date('Y-m-d');
        $l = [];
        $cand = [];
        foreach ($lotes as $lo) {
            $l[] = "• Lote {$lo['lote_id']} de {$n}: " . $this->g($lo['stock_actual']) . ' · ' . $this->dias($lo['dias_vida'])
                 . " · estado visual {$lo['estado_visual']} · cadena de frío " . ($lo['cadena_frio'] === 'cumple' ? 'cumple' : 'no cumple');
            if (in_array($lo['estado_lote'], ['vence_hoy', 'vencido'], true)) {
                $cand[] = 'alert';
            }
            $d = $u === null ? null : $this->decisionDeLote($u['decisiones'], $lo['lote_id']);
            if ($d === null) {
                $l[] = '   El sistema todavía no evaluó este lote.';
                $cand[] = 'think';
                continue;
            }
            $l[] = '   Último veredicto del sistema (consulta del ' . $this->fecha($u['consulta']['fecha_base']) . '): '
                 . (self::ACCIONES[$d['accion']] ?? $d['accion']) . ' (confianza ' . number_format($d['fc'], 2, ',', '.') . ').';
            if ($vieja) {
                $cand[] = 'think';
            } else {
                array_push($cand, ...$this->poseDeAccion($d['accion'], 0.0));
            }
        }
        if ($vieja) {
            $l[] = 'Esa consulta es de una fecha anterior: ejecútala de nuevo en el formulario para actualizar el veredicto.';
        }
        $l[] = 'Yo no decido si un lote es apto: lo decide el motor de reglas cuando ejecutas la consulta.';
        return $this->t(implode("\n", $l), $cand);
    }

    private function hPorque(array $c): array
    {
        $u = $this->ultima();
        if ($u === null) {
            return $this->t('Todavía no hay ninguna consulta ejecutada, así que no hay decisiones que explicar. Ejecuta una desde el formulario.', [], 'shy');
        }
        $enc = 'Consulta #' . $u['consulta']['consulta_id'] . ' (' . $u['consulta']['tipo'] . ', ' . $this->fecha($u['consulta']['fecha_base']) . ')';

        if ($c['insumo'] !== null) {
            $ds = array_values(array_filter($u['decisiones'], static fn(array $d): bool => $d['insumo_id'] === $c['insumo']['insumo_id']));
            if ($ds === []) {
                return $this->t("En la {$enc} no hay decisiones sobre {$c['insumo']['nombre']}.", [], 'shy');
            }
            $l = [];
            $cand = [];
            foreach (array_slice($ds, 0, 2) as $d) {
                $l[] = "Recomendación: " . (self::ACCIONES[$d['accion']] ?? $d['accion']) . ' (confianza ' . number_format($d['fc'], 2, ',', '.') . ')';
                $l[] = $d['explicacion'];
                array_push($cand, ...$this->poseDeAccion($d['accion'], $d['ahorro']));
            }
            array_unshift($l, $enc . ':');
            return $this->t(implode("\n", $l), $cand);
        }

        $cuenta = [];
        $ahorro = 0.0;
        $cand = [];
        foreach ($u['decisiones'] as $d) {
            $cuenta[$d['accion']] = ($cuenta[$d['accion']] ?? 0) + 1;
            $ahorro += $d['ahorro'];
            array_push($cand, ...$this->poseDeAccion($d['accion'], $d['ahorro']));
        }
        $partes = [];
        foreach ($cuenta as $a => $k) {
            $partes[] = "{$k} × " . (self::ACCIONES[$a] ?? $a);
        }
        $l = ["{$enc}: " . count($u['decisiones']) . ' lote(s) evaluados.', 'Acciones: ' . implode('; ', $partes) . '.'];
        if ($ahorro > 0) {
            $l[] = 'Ahorro estimado: ' . number_format($ahorro, 2, ',', '.') . ' Bs.';
        }
        $l[] = 'Pregúntame por un insumo para ver el detalle, por ejemplo: "¿por qué el pollo?".';
        return $this->t(implode("\n", $l), $cand);
    }

    private function hRegla(array $c): array
    {
        $id = $c['regla'];
        $f = $this->repo->regla($id);
        if ($f === null) {
            return $this->t("No encuentro la regla {$id}. Las reglas van de R1 a R26.", [], 'shy');
        }
        $l = ["{$f['regla_id']} · {$f['grupo']}", "SI {$f['condicion']}", "ENTONCES {$f['conclusion']}",
              'Confianza de la regla: ' . number_format($f['fc_regla'], 2, ',', '.') . ' · ' . ($f['activa'] ? 'activa' : 'desactivada')];
        $texto = $f['condicion'] . ' ' . $f['conclusion'];
        $glos = [];
        foreach (self::GLOSARIO as $k => $def) {
            if (str_contains($texto, $k)) {
                $glos[] = "• {$k}: {$def}";
            }
        }
        if ($glos !== []) {
            $l[] = 'En palabras simples:';
            array_push($l, ...array_slice($glos, 0, 5));
        }
        return $this->t(implode("\n", $l));
    }

    private function hPlato(array $c): array
    {
        if ($c['plato'] === null) {
            return $this->t('¿De qué plato? Por ejemplo: "¿cuántas porciones de trucha al curry hay?".', ['think']);
        }
        $id = $c['plato']['plato_id'];
        $p = $this->repo->porcionesPlato($id);
        $l = [$p === null ? "{$c['plato']['nombre']}: no tengo datos de porciones."
                          : "{$c['plato']['nombre']}: se pueden preparar {$p} porción(es) con el stock actual."];
        $ing = $this->repo->ingredientesPlato($id);
        if ($ing !== []) {
            $l[] = 'Por porción lleva:';
            foreach ($ing as $i) {
                $l[] = "• {$i['insumo']}: " . $this->g($i['gramos']);
            }
        }
        return $this->t(implode("\n", $l), $p === 0 ? ['sad'] : []);
    }

    private function hAyuda(): array
    {
        return $this->t("Puedo ayudarte con:\n• Stock: \"¿cuánto pollo queda?\"\n• Vencimientos: \"¿qué vence hoy?\"\n• Estado de un insumo: \"¿puedo usar la trucha?\"\n• Decisiones: \"¿por qué el pollo?\" o \"¿por qué la última decisión?\"\n• Platos: \"¿cuántas porciones de pollo picante hay?\"\n• Reglas: \"explícame la R13\"\n\nNo decido si un lote es apto: eso lo hace el motor de reglas.");
    }

    // ------------------------------------------------------------------ utilidades

    /** @param string[] $candidatas */
    private function t(string $texto, array $candidatas = [], ?string $tono = null): array
    {
        return ['texto' => $texto, 'candidatas' => $candidatas, 'tono' => $tono];
    }

    /** Poses que el SISTEMA pide para una acción recomendada. */
    private function poseDeAccion(string $accion, float $ahorro): array
    {
        $p = match ($accion) {
            'descartar'       => ['angry'],
            'menu_emergencia' => ['alert'],
            'promocionar', 'mantener' => ['ok'],
            default           => [],
        };
        if ($ahorro > 0) {
            $p[] = 'happy';
        }
        return $p;
    }

    private function ultima(): ?array
    {
        $c = $this->repo->ultimaConsulta();
        return $c === null ? null : ['consulta' => $c, 'decisiones' => $this->repo->decisionesDeConsulta($c['consulta_id'])];
    }

    private function decisionDeLote(array $decisiones, int $loteId): ?array
    {
        foreach ($decisiones as $d) {
            if ($d['lote_id'] === $loteId) {
                return $d;
            }
        }
        return null;
    }

    private function lotesDe(int $insumoId): array
    {
        return array_values(array_filter($this->repo->lotes(), static fn(array $l): bool => $l['insumo_id'] === $insumoId));
    }

    private function g(float $x): string
    {
        return number_format($x, 0, ',', '.') . ' g';
    }

    private function d1(float $x): string
    {
        return number_format($x, 1, ',', '.');
    }

    private function dias(int $d): string
    {
        return $d < 0 ? 'vencido hace ' . (-$d) . ' día(s)' : ($d === 0 ? 'vence hoy' : "vence en {$d} día(s)");
    }

    private function fecha(string $ymd): string
    {
        return date('d/m/Y', strtotime($ymd) ?: 0);
    }
}
