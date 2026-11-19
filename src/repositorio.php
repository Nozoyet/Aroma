<?php
declare(strict_types=1);

/**
 * Repositorio: único lugar donde hay SQL. El resto del sistema recibe arrays.
 * Todas las consultas usan sentencias preparadas.
 */
final class Repositorio
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? conexion();
    }

    // ------------------------------------------------------------------
    // LECTURA: memoria de trabajo
    // ------------------------------------------------------------------

    /** Lotes con stock, con dias_vida y estado_lote calculados por la vista. */
    public function lotes(): array
    {
        $filas = $this->pdo
            ->query('SELECT * FROM vw_lote_estado ORDER BY dias_vida ASC, valor_lote DESC')
            ->fetchAll();

        return array_map(static fn(array $f): array => [
            'lote_id'          => (int)$f['lote_id'],
            'insumo_id'        => (int)$f['insumo_id'],
            'insumo'           => (string)$f['insumo'],
            'categoria_riesgo' => (string)$f['categoria_riesgo'],   // alto | medio | bajo
            'stock_actual'     => (float)$f['stock_actual'],
            'estado_visual'    => (string)$f['estado_visual'],      // bueno | regular | malo
            'cadena_frio'      => (string)$f['cadena_frio'],        // cumple | no_cumple
            'dias_vida'        => (int)$f['dias_vida'],
            'estado_lote'      => (string)$f['estado_lote'],
            'valor_lote'       => (float)$f['valor_lote'],
        ], $filas);
    }

    /**
     * Stock vigente y cobertura por insumo (clave = insumo_id).
     * cobertura_dias = INF cuando no hay consumo registrado (el stock "dura" indefinidamente).
     */
    public function coberturaPorInsumo(): array
    {
        $salida = [];
        foreach ($this->pdo->query('SELECT * FROM vw_cobertura_insumo')->fetchAll() as $f) {
            $salida[(int)$f['insumo_id']] = [
                'insumo'           => (string)$f['insumo'],
                'stock_total_g'    => (float)$f['stock_total_g'],
                'consumo_diario_g' => $f['consumo_diario_g'] === null ? null : (float)$f['consumo_diario_g'],
                'cobertura_dias'   => $f['cobertura_dias'] === null ? INF : (float)$f['cobertura_dias'],
            ];
        }
        return $salida;
    }

    /** Aristas del grafo: una fila por (plato, insumo) con su peso en gramos por porción. */
    public function recetas(): array
    {
        $sql = 'SELECT r.plato_id, p.nombre AS plato, p.precio_venta,
                       r.insumo_id, i.nombre AS insumo, r.cantidad_por_porcion, i.costo_unitario
                FROM   receta r
                JOIN   plato  p ON p.plato_id  = r.plato_id
                JOIN   insumo i ON i.insumo_id = r.insumo_id
                WHERE  p.activo = 1
                ORDER BY r.plato_id, r.insumo_id';

        return array_map(static fn(array $f): array => [
            'plato_id'             => (int)$f['plato_id'],
            'plato'                => (string)$f['plato'],
            'precio_venta'         => (float)$f['precio_venta'],
            'insumo_id'            => (int)$f['insumo_id'],
            'insumo'               => (string)$f['insumo'],
            'cantidad_por_porcion' => (float)$f['cantidad_por_porcion'],
            'costo_unitario'       => (float)$f['costo_unitario'],
        ], $this->pdo->query($sql)->fetchAll());
    }

    /** Porciones que se pueden preparar de cada plato con el stock vigente (clave = plato_id). */
    public function porcionesPosibles(): array
    {
        $salida = [];
        foreach ($this->pdo->query('SELECT plato_id, porciones_posibles FROM vw_porciones_posibles')->fetchAll() as $f) {
            $salida[(int)$f['plato_id']] = (int)$f['porciones_posibles'];
        }
        return $salida;
    }

    /** Catálogo de insumos (clave = insumo_id). */
    public function insumos(): array
    {
        $sql = 'SELECT i.insumo_id, i.nombre, i.costo_unitario, i.vida_util_dias, c.nombre AS categoria
                FROM insumo i JOIN categoria_riesgo c ON c.categoria_id = i.categoria_id';
        $salida = [];
        foreach ($this->pdo->query($sql)->fetchAll() as $f) {
            $salida[(int)$f['insumo_id']] = [
                'nombre'         => (string)$f['nombre'],
                'costo_unitario' => (float)$f['costo_unitario'],
                'vida_util_dias' => (int)$f['vida_util_dias'],
                'categoria'      => (string)$f['categoria'],
            ];
        }
        return $salida;
    }

    /** Reglas de la base de conocimiento (clave = regla_id). */
    public function reglas(): array
    {
        $salida = [];
        foreach ($this->pdo->query('SELECT regla_id, grupo, condicion, conclusion, fc_regla, activa FROM regla')->fetchAll() as $f) {
            $salida[(string)$f['regla_id']] = [
                'grupo'      => (string)$f['grupo'],
                'condicion'  => (string)$f['condicion'],
                'conclusion' => (string)$f['conclusion'],
                'fc_regla'   => (float)$f['fc_regla'],
                'activa'     => (bool)$f['activa'],
            ];
        }
        return $salida;
    }

    /** Parámetros de política (clave => valor numérico). */
    public function parametros(): array
    {
        $salida = [];
        foreach ($this->pdo->query('SELECT clave, valor FROM parametro_politica')->fetchAll() as $f) {
            $salida[(string)$f['clave']] = (float)$f['valor'];
        }
        return $salida;
    }

    /**
     * Tipo de día de $dias fechas consecutivas desde $desde (formato Y-m-d).
     * Si una fecha no está en calendario_dia se deduce: sábado o domingo = fin_de_semana.
     * Devuelve una lista ordenada: [['fecha' => ..., 'tipo_dia' => ..., 'origen' => ...], ...]
     */
    public function tiposDia(string $desde, int $dias): array
    {
        $inicio = new DateTimeImmutable($desde);
        $fin    = $inicio->modify('+' . ($dias - 1) . ' day');

        $st = $this->pdo->prepare('SELECT fecha, tipo_dia FROM calendario_dia WHERE fecha BETWEEN :d AND :h');
        $st->execute([':d' => $inicio->format('Y-m-d'), ':h' => $fin->format('Y-m-d')]);
        $guardados = [];
        foreach ($st->fetchAll() as $f) {
            $guardados[(string)$f['fecha']] = (string)$f['tipo_dia'];
        }

        $lista = [];
        for ($i = 0; $i < $dias; $i++) {
            $dia   = $inicio->modify("+{$i} day");
            $clave = $dia->format('Y-m-d');
            if (isset($guardados[$clave])) {
                $lista[] = ['fecha' => $clave, 'tipo_dia' => $guardados[$clave], 'origen' => 'calendario'];
            } else {
                $esFinde = (int)$dia->format('N') >= 6;
                $lista[] = ['fecha' => $clave, 'tipo_dia' => $esFinde ? 'fin_de_semana' : 'laboral', 'origen' => 'deducido'];
            }
        }
        return $lista;
    }

    // ------------------------------------------------------------------
    // ESCRITURA: datos que ingresa el usuario en el formulario
    // ------------------------------------------------------------------

    /** Registra o corrige el tipo de día de una fecha (feriados, por ejemplo). */
    public function guardarCalendario(string $fecha, string $tipoDia, ?string $nota = null): void
    {
        // Con sentencias preparadas reales cada marcador se usa una sola vez
        $st = $this->pdo->prepare(
            'INSERT INTO calendario_dia (fecha, tipo_dia, nota) VALUES (:f, :t, :n)
             ON DUPLICATE KEY UPDATE tipo_dia = :t2, nota = :n2'
        );
        $st->execute([':f' => $fecha, ':t' => $tipoDia, ':n' => $nota, ':t2' => $tipoDia, ':n2' => $nota]);
    }

    /** Actualiza el estado de un lote existente (stock, estado visual y cadena de frío). */
    public function actualizarLote(int $loteId, float $stock, string $estadoVisual, bool $cadenaFrioCumple): void
    {
        $st = $this->pdo->prepare(
            'UPDATE lote SET stock_actual = :s, estado_visual = :v, cadena_frio = :c WHERE lote_id = :id'
        );
        $st->execute([':s' => $stock, ':v' => $estadoVisual, ':c' => $cadenaFrioCumple ? 1 : 0, ':id' => $loteId]);
    }

    /** Registra un lote nuevo. Devuelve su lote_id. */
    public function insertarLote(
        int $insumoId, ?int $proveedorId, string $fechaIngreso, string $fechaVencimiento,
        float $cantidad, string $estadoVisual = 'bueno', bool $cadenaFrioCumple = true
    ): int {
        $st = $this->pdo->prepare(
            'INSERT INTO lote (insumo_id, proveedor_id, fecha_ingreso, fecha_vencimiento,
                               cantidad_inicial, stock_actual, estado_visual, cadena_frio)
             VALUES (:i, :p, :fi, :fv, :c, :c2, :v, :f)'
        );
        $st->execute([
            ':i' => $insumoId, ':p' => $proveedorId, ':fi' => $fechaIngreso, ':fv' => $fechaVencimiento,
            ':c' => $cantidad, ':c2' => $cantidad, ':v' => $estadoVisual, ':f' => $cadenaFrioCumple ? 1 : 0,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    // ------------------------------------------------------------------
    // ESCRITURA: historial de consultas y decisiones
    // ------------------------------------------------------------------

    /** $tipo: 'plan_3_dias' o 'emergencia'. Devuelve consulta_id. */
    public function crearConsulta(int $usuarioId, string $tipo, string $fechaBase, ?string $observaciones = null): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO consulta (usuario_id, tipo, fecha_base, observaciones) VALUES (:u, :t, :f, :o)'
        );
        $st->execute([':u' => $usuarioId, ':t' => $tipo, ':f' => $fechaBase, ':o' => $observaciones]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Guarda una decisión. Claves esperadas en $d:
     * consulta_id, accion, fc, explicacion  (obligatorias)
     * lote_id, plato_id, dia_plan, porciones, costo, ahorro  (opcionales)
     */
    public function guardarDecision(array $d): int
    {
        $st = $this->pdo->prepare(
            'INSERT INTO decision (consulta_id, lote_id, plato_id, dia_plan, accion_recomendada, porciones,
                                   fc_conclusion, costo_estimado, ahorro_estimado, explicacion)
             VALUES (:c, :l, :p, :d, :a, :po, :fc, :co, :ah, :ex)'
        );
        $st->execute([
            ':c'  => $d['consulta_id'],
            ':l'  => $d['lote_id']   ?? null,
            ':p'  => $d['plato_id']  ?? null,
            ':d'  => $d['dia_plan']  ?? null,
            ':a'  => $d['accion'],
            ':po' => $d['porciones'] ?? null,
            ':fc' => round(max(0.0, min(1.0, (float)$d['fc'])), 3),
            ':co' => isset($d['costo'])  ? round((float)$d['costo'], 2)  : null,
            ':ah' => isset($d['ahorro']) ? round((float)$d['ahorro'], 2) : null,
            ':ex' => $d['explicacion'],
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Guarda las reglas que se dispararon en una decisión (base del módulo de explicación).
     * $traza: lista de ['regla' => 'R7', 'fc' => 0.95, ...] tal como la devuelve MotorReglas.
     */
    public function guardarReglasDisparadas(int $decisionId, array $traza): void
    {
        $maxPorRegla = [];
        foreach ($traza as $t) {
            $id = (string)$t['regla'];
            $maxPorRegla[$id] = max($maxPorRegla[$id] ?? 0.0, (float)$t['fc']);
        }
        $st = $this->pdo->prepare(
            'INSERT INTO decision_regla (decision_id, regla_id, fc_aplicado) VALUES (:d, :r, :f)'
        );
        foreach ($maxPorRegla as $reglaId => $fc) {
            $st->execute([':d' => $decisionId, ':r' => $reglaId, ':f' => round(min(1.0, $fc), 3)]);
        }
    }

    /** Ejecuta $fn dentro de una transacción: todo o nada. */
    public function transaccion(callable $fn): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $resultado = $fn($this);
            $this->pdo->commit();
            return $resultado;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
}