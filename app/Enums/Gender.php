<?php

namespace App\Enums;

enum Gender: string
{
    case LAKI_LAKI = 'Laki-Laki';
    case PEREMPUAN = 'Perempuan';

    /**
     * Label singkat (L / P).
     */
    public function shortCode(): string
    {
        return match ($this) {
            self::LAKI_LAKI => 'L',
            self::PEREMPUAN => 'P',
        };
    }
}
