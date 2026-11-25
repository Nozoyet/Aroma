<?php
declare(strict_types=1);

/**
 * Lecturas del chat. Solo SELECT con sentencias preparadas y consultas FIJAS:
 * ni el usuario ni el modelo escriben SQL. Nunca lee consulta.observaciones.
 */
final class RepositorioChat implements LecturaChat
{
    public function __construct(private ?PDO $pdo = null)
    {
        $this->pdo ??= conexionChat();
    }

    public function insumos(): array
    {
        return array_map(static fn(array $f): array => ['insumo_id' => (int)$f['insumo_id'], 'nombre' => (string)$f['nombre']],
            $this->pdo->query('SELECT insumo_id, nombre FROM insumo ORDER BY nombre')->fetchAll());
    }

    public function platos(): array
    {
        return array_map(static fn(array $f): array => ['plato_id' => (int)$f['plato_id'], 'nombre' => (string)$f['nombre']],
            $this->pdo->query('SELECT plato_id, nombre FROM plato WHERE activo = 1 ORDER BY plato_id')->fetchAll());
    }

    public function lotes(): array
    {
        return array_map(static fn(array $f): array => [
            'lote_id'          => (int)$f['lote_id'],
            'insumo_id'        => (int)$f['insumo_id'],
            'insumo'           => (string)$f['insumo'],
            'categoria_riesgo' => (string)$f['categoria_riesgo'],
            'stock_actual'     => (float)$f['stock_actual'],
            'estado_visual'    => (string)$f['estado_visual'],
            'cadena_frio'      => (string)$f['cadena_frio'],
            'dias_vida'        => (int)$f['dias_vida'],
            'estado_lote'      => (string)$f['estado_lote'],
            'valor_lote'       => (float)$f['valor_lote'],
        ], $this->pdo->query('SELECT * FROM vw_lote_estado ORDER BY dias_vida ASC, valor_lote DESC')->fetchAll());
    }

    public function cobertura(): array
    {
        $salida = [];
        foreach ($this->pdo->query('SELECT * FROM vw_cobertura_insumo')->fetchAll() as $f) {
            $salida[(int)$f['insumo_id']] = [
                'insumo'           => (string)$f['insumo'],
                'stock_total_g'    => (float)$f['stock_total_g'],
                'consumo_diario_g' => $f['consumo_diario_g'] === null ? null : (float)$f['consumo_diario_g'],
                'cobertura_dias'   => $f['cobertura_dias'] === null ? null : (float)$f['cobertura_dias'],
            ];
        }
        return $salida;
    }

    public function porcionesPlato(int $platoId): ?int
    {
        $st = $this->pdo->prepare('SELECT porciones_posibles FROM vw_porciones_posibles WHERE plato_id = ?');
        $st->execute([$platoId]);
        $v = $st->fetchColumn();
        return $v === false ? null : (int)$v;
    }

    public function ingredientesPlato(int $platoId): array
    {
        $st = $this->pdo->prepare('SELECT i.nombre AS insumo, r.cantidad_por_porcion AS gramos
                                   FROM receta r JOIN insumo i ON i.insumo_id = r.insumo_id
                                   WHERE r.plato_id = ? ORDER BY r.cantidad_por_porcion DESC');
        $st->execute([$platoId]);
        return array_map(static fn(array $f): array => ['insumo' => (string)$f['insumo'], 'gramos' => (float)$f['gramos']], $st->fetchAll());
    }

    public function regla(string $id): ?array
    {
        $st = $this->pdo->prepare('SELECT regla_id, grupo, condicion, conclusion, fc_regla, activa FROM regla WHERE regla_id = ?');
        $st->execute([$id]);
        $f = $st->fetch();
        if (!$f) {
            return null;
        }
        return ['regla_id' => (string)$f['regla_id'], 'grupo' => (string)$f['grupo'], 'condicion' => (string)$f['condicion'],
                'conclusion' => (string)$f['conclusion'], 'fc_regla' => (float)$f['fc_regla'], 'activa' => (bool)$f['activa']];
    }

    public function ultimaConsulta(): ?array
    {
        $f = $this->pdo->query('SELECT consulta_id, fecha_hora, tipo, fecha_base FROM consulta ORDER BY consulta_id DESC LIMIT 1')->fetch();
        return $f ? ['consulta_id' => (int)$f['consulta_id'], 'fecha_hora' => (string)$f['fecha_hora'],
                     'tipo' => (string)$f['tipo'], 'fecha_base' => (string)$f['fecha_base']] : null;
    }

    public function decisionesDeConsulta(int $consultaId): array
    {
        $st = $this->pdo->prepare('SELECT d.decision_id, d.lote_id, l.insumo_id, i.nombre AS insumo, d.accion_recomendada,
                                          d.fc_conclusion, d.ahorro_estimado, d.explicacion
                                   FROM decision d
                                   LEFT JOIN lote l ON l.lote_id = d.lote_id
                                   LEFT JOIN insumo i ON i.insumo_id = l.insumo_id
                                   WHERE d.consulta_id = ? ORDER BY d.decision_id');
        $st->execute([$consultaId]);
        return array_map(static fn(array $f): array => [
            'decision_id' => (int)$f['decision_id'],
            'lote_id'     => $f['lote_id'] === null ? null : (int)$f['lote_id'],
            'insumo_id'   => $f['insumo_id'] === null ? null : (int)$f['insumo_id'],
            'insumo'      => (string)($f['insumo'] ?? ''),
            'accion'      => (string)$f['accion_recomendada'],
            'fc'          => (float)$f['fc_conclusion'],
            'ahorro'      => $f['ahorro_estimado'] === null ? 0.0 : (float)$f['ahorro_estimado'],
            'explicacion' => (string)$f['explicacion'],
        ], $st->fetchAll());
    }
}
