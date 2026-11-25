<?php
declare(strict_types=1);

/** Lo único que el chat puede leer. Implementada por RepositorioChat (MySQL) y por un doble en las pruebas. */
interface LecturaChat
{
    /** @return array<int, array{insumo_id:int, nombre:string}> */
    public function insumos(): array;

    /** @return array<int, array{plato_id:int, nombre:string}> */
    public function platos(): array;

    /** Filas de vw_lote_estado (lotes con stock > 0). */
    public function lotes(): array;

    /** [insumo_id => ['insumo','stock_total_g','consumo_diario_g'(?float),'cobertura_dias'(?float)]] */
    public function cobertura(): array;

    public function porcionesPlato(int $platoId): ?int;

    /** @return array<int, array{insumo:string, gramos:float}> */
    public function ingredientesPlato(int $platoId): array;

    /** @return array{regla_id:string, grupo:string, condicion:string, conclusion:string, fc_regla:float, activa:bool}|null */
    public function regla(string $id): ?array;

    /** @return array{consulta_id:int, fecha_hora:string, tipo:string, fecha_base:string}|null */
    public function ultimaConsulta(): ?array;

    /** Decisiones guardadas de una consulta (con insumo_id e insumo del lote). */
    public function decisionesDeConsulta(int $consultaId): array;
}
