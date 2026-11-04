<?php

namespace RealsoftGt\OnneB\Mapping;

/**
 * Nombre limpio y lo que se le quitó.
 */
class CleanedName
{
    /**
     * @param  ?string  $label  Texto final entre paréntesis o corchetes, normalmente el laboratorio.
     * @param  ?array{count: int, display: string, singular: ?string, plural: ?string, text: string}  $content  Contenido del empaque declarado en el nombre.
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $label = null,
        public readonly ?array $content = null,
    ) {}
}
