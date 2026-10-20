<?php

namespace App\Support;

/** Formats d'affichage à la française : 1,66 €, 1,749 €/L, +3,40 € */
final class Format
{
    public static function euros(float $value): string
    {
        return number_format($value, 2, ',', "\u{202F}").' €';
    }

    public static function signedEuros(float $value): string
    {
        return ($value > 0 ? '+' : ($value < 0 ? '−' : '')).self::euros(abs($value));
    }

    public static function pricePerLiter(float $value): string
    {
        return number_format($value, 3, ',', "\u{202F}").' €/L';
    }

    public static function km(float $value): string
    {
        return number_format($value, $value < 10 ? 1 : 0, ',', "\u{202F}").' km';
    }
}
