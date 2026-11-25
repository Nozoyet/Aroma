<?php
declare(strict_types=1);

/**
 * Contrato del módulo de demanda. La versión 1 usa DemandaPromedio; la versión 2 agregará
 * DemandaMinimosCuadrados con esta misma interfaz, sin tocar el resto del sistema.
 */
interface IDemanda
{
    /**
     * @param string $fecha  día a estimar (Y-m-d)
     * @return array{porciones: float, fc: float}  porciones esperadas y certeza de la estimación (0 a 1)
     */
    public function estimar(int $platoId, string $fecha): array;
}
