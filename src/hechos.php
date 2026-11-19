<?php
declare(strict_types=1);

/**
 * Memoria de hechos de UN lote durante la inferencia.
 * Cada hecho guarda su valor y su factor de certeza (FC, entre 0 y 1).
 */
final class Hechos
{
    /** @var array<string, array{valor: mixed, fc: float}> */
    private array $datos = [];

    /** FC de dos evidencias a favor de la misma conclusión: a + b·(1 − a). */
    public static function combinar(float $a, float $b): float
    {
        return $a + $b * (1.0 - $a);
    }

    /**
     * Agrega un hecho.
     * - Si ya existe con el MISMO valor, las evidencias se combinan.
     * - Si ya existe con OTRO valor, se conserva el de mayor FC.
     */
    public function asignar(string $nombre, mixed $valor, float $fc): void
    {
        if (!isset($this->datos[$nombre])) {
            $this->datos[$nombre] = ['valor' => $valor, 'fc' => $fc];
            return;
        }
        if ($this->datos[$nombre]['valor'] === $valor) {
            $this->datos[$nombre]['fc'] = self::combinar($this->datos[$nombre]['fc'], $fc);
            return;
        }
        if ($fc > $this->datos[$nombre]['fc']) {
            $this->datos[$nombre] = ['valor' => $valor, 'fc' => $fc];
        }
    }

    public function tiene(string $nombre): bool
    {
        return isset($this->datos[$nombre]);
    }

    public function valor(string $nombre): mixed
    {
        return $this->datos[$nombre]['valor'] ?? null;
    }

    /** ¿El hecho existe y vale exactamente $valor? */
    public function es(string $nombre, mixed $valor): bool
    {
        return $this->tiene($nombre) && $this->datos[$nombre]['valor'] === $valor;
    }

    /**
     * Aporte del hecho al FC de una conclusión.
     * Caso especial: fc_calidad ya ES una certeza (0.9, 0.7, 0.5), por eso aporta su valor.
     */
    public function fc(string $nombre): float
    {
        if (!$this->tiene($nombre)) {
            return 0.0;
        }
        $fc = $this->datos[$nombre]['fc'];
        if ($nombre === 'fc_calidad') {
            return min((float)$this->datos[$nombre]['valor'], $fc);
        }
        return $fc;
    }

    /** FC más bajo entre los hechos usados en una condición (más certezas extra). */
    public function minFc(array $nombres, float ...$extra): float
    {
        $valores = array_map(fn(string $n): float => $this->fc($n), $nombres);
        $valores = array_merge($valores, $extra);
        return $valores === [] ? 1.0 : min($valores);
    }

    /** @return array<string, array{valor: mixed, fc: float}> */
    public function todos(): array
    {
        return $this->datos;
    }
}