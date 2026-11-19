<?php
declare(strict_types=1);

/**
 * Repositorio simulado en memoria: permite probar Sistema completo sin MySQL.
 * Replica lo que hacen las vistas (dias_vida ya viene fijo en los datos de ejemplo).
 */
final class RepositorioMemoria extends Repositorio
{
    public array $decisiones = [];
    public array $reglasDecision = [];
    public array $consultas = [];
    public array $calendario = [];
    private int $siguienteLote = 100;

    /** @param array $lotes filas con: lote_id, insumo_id, stock_actual, estado_visual, cadena_frio, dias_vida */
    public function __construct(private array $datosLotes, private array $datosInsumos)
    {
        // No se llama a parent::__construct(): no hay conexión
    }

    private function recetasBd(): array
    {
        return recetas_ejemplo();
    }

    public function lotes(): array
    {
        $salida = [];
        foreach ($this->datosLotes as $l) {
            $i = $this->datosInsumos[$l['insumo_id']];
            $salida[] = [
                'lote_id' => $l['lote_id'], 'insumo_id' => $l['insumo_id'], 'insumo' => $i['nombre'],
                'categoria_riesgo' => $i['categoria'], 'stock_actual' => (float)$l['stock_actual'],
                'estado_visual' => $l['estado_visual'], 'cadena_frio' => $l['cadena_frio'],
                'dias_vida' => $l['dias_vida'], 'estado_lote' => '', 'valor_lote' => $l['stock_actual'] * $i['costo_unitario'],
            ];
        }
        usort($salida, static fn($a, $b) => $a['dias_vida'] <=> $b['dias_vida']);
        return $salida;
    }

    private function consumoDiario(): array
    {
        $prom = [1 => 12, 2 => 6, 3 => 16, 4 => 9, 5 => 7, 6 => 18];
        $c = [];
        foreach ($this->recetasBd() as $f) {
            $c[$f['insumo_id']] = ($c[$f['insumo_id']] ?? 0) + $prom[$f['plato_id']] * $f['cantidad_por_porcion'];
        }
        return $c;
    }

    public function coberturaPorInsumo(): array
    {
        $stock = [];
        foreach ($this->datosLotes as $l) {
            $stock[$l['insumo_id']] = ($stock[$l['insumo_id']] ?? 0) + $l['stock_actual'];
        }
        $consumo = $this->consumoDiario();
        $salida = [];
        foreach ($stock as $id => $s) {
            $c = $consumo[$id] ?? null;
            $salida[$id] = ['insumo' => $this->datosInsumos[$id]['nombre'], 'stock_total_g' => (float)$s,
                'consumo_diario_g' => $c, 'cobertura_dias' => $c ? $s / $c : INF];
        }
        return $salida;
    }

    public function recetas(): array
    {
        return $this->recetasBd();
    }

    public function porcionesPosibles(): array
    {
        $stock = array_map(static fn($c) => $c['stock_total_g'], $this->coberturaPorInsumo());
        $g = new Grafo($this->recetasBd());
        $salida = [];
        foreach ([1, 2, 3, 4, 5, 6] as $p) {
            $salida[$p] = $g->kmax($p, $stock);
        }
        return $salida;
    }

    public function insumos(): array
    {
        return $this->datosInsumos;
    }

    public function reglas(): array
    {
        return reglas_ejemplo();
    }

    public function parametros(): array
    {
        return ['horizonte_dias' => 3, 'max_porciones_promo' => 10, 'descuento_promo' => 0.10, 'umbral_fc_calidad' => 0.60,
                'umbral_rescate' => 0.70, 'max_platos_menu' => 3, 'umbral_fc_emergencia' => 0.50];
    }

    public function tiposDia(string $desde, int $dias): array
    {
        $lista = [];
        for ($i = 0; $i < $dias; $i++) {
            $d = (new DateTimeImmutable($desde))->modify("+{$i} day");
            $f = $d->format('Y-m-d');
            $lista[] = ['fecha' => $f, 'origen' => 'memoria',
                        'tipo_dia' => $this->calendario[$f] ?? ((int)$d->format('N') >= 6 ? 'fin_de_semana' : 'laboral')];
        }
        return $lista;
    }

    public function estadisticasDemanda(string $hasta, int $dias = 90): array
    {
        $prom = [1 => 12, 2 => 6, 3 => 16, 4 => 9, 5 => 7, 6 => 18];
        $s = [];
        foreach ($prom as $p => $v) {
            for ($dow = 0; $dow < 7; $dow++) {
                $s[$p][$dow] = ['prom' => (float)$v, 'desv' => 2.0, 'n' => 10];
            }
        }
        return $s;
    }

    public function usuarioPorDefecto(): int
    {
        return 1;
    }

    public function guardarCalendario(string $fecha, string $tipoDia, ?string $nota = null): void
    {
        $this->calendario[$fecha] = $tipoDia;
    }

    public function actualizarLote(int $loteId, float $stock, string $estadoVisual, bool $cadenaFrioCumple): void
    {
        foreach ($this->datosLotes as &$l) {
            if ($l['lote_id'] === $loteId) {
                $l['stock_actual'] = $stock;
                $l['estado_visual'] = $estadoVisual;
                $l['cadena_frio'] = $cadenaFrioCumple ? 'cumple' : 'no_cumple';
            }
        }
    }

    public function insertarLote(int $insumoId, ?int $proveedorId, string $fechaIngreso, string $fechaVencimiento,
        float $cantidad, string $estadoVisual = 'bueno', bool $cadenaFrioCumple = true): int
    {
        $dias = (int)(new DateTimeImmutable($fechaIngreso))->diff(new DateTimeImmutable($fechaVencimiento))->format('%r%a');
        $this->datosLotes[] = ['lote_id' => ++$this->siguienteLote, 'insumo_id' => $insumoId, 'stock_actual' => $cantidad,
            'estado_visual' => $estadoVisual, 'cadena_frio' => $cadenaFrioCumple ? 'cumple' : 'no_cumple', 'dias_vida' => $dias];
        return $this->siguienteLote;
    }

    public function crearConsulta(int $usuarioId, string $tipo, string $fechaBase, ?string $observaciones = null): int
    {
        $this->consultas[] = compact('usuarioId', 'tipo', 'fechaBase', 'observaciones');
        return count($this->consultas);
    }

    public function guardarDecision(array $d): int
    {
        $this->decisiones[] = $d;
        return count($this->decisiones);
    }

    public function guardarReglasDisparadas(int $decisionId, array $traza): void
    {
        $this->reglasDecision[$decisionId] = array_values(array_unique(array_column($traza, 'regla')));
    }

    public function transaccion(callable $fn): mixed
    {
        return $fn($this);
    }
}