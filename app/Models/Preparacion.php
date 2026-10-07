<?php

namespace App\Models;

class Preparacion
{
    public function __construct(
        private readonly string $nombre,
        private readonly int $duracion
    ) {
    }

    public function getNombre(): string
    {
        return $this->nombre;
    }

    public function getDuracion(): int
    {
        return $this->duracion;
    }
}
