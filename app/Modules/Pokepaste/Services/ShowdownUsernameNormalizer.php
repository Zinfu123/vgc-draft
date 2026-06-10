<?php

namespace App\Modules\Pokepaste\Services;

class ShowdownUsernameNormalizer
{
    public static function normalize(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        $t = trim($name);
        if ($t === '') {
            return null;
        }

        return mb_strtolower($t, 'UTF-8');
    }
}
