<?php

namespace RealsoftGt\OnneB\Data;

/**
 * Lo que entrega un importador para la consola y el Excel de revisión.
 */
final readonly class ImportResult
{
    /**
     * @param  array<string, int|string>  $summary  Etiqueta legible => valor.
     * @param  list<array{codprod: string, descripcion: string, incidencia: string, severidad: string, detalle: string}>  $issues
     * @param  list<array{0: string, 1: string, 2: string}>  $pending  Tema, situación y acción para arrancar.
     */
    public function __construct(
        public array $summary,
        public array $issues,
        public array $pending = [],
    ) {}
}
