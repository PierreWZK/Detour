<?php

namespace App\Detour;

/** Les carburants proposés ; la valeur est le préfixe des champs du jeu de données (`gazole_prix`, `e10_maj`…) */
enum Fuel: string
{
    case Gazole = 'gazole';
    case E10 = 'e10';
    case SP98 = 'sp98';
    case E85 = 'e85';
    case GPL = 'gplc';

    public function label(): string
    {
        return match ($this) {
            self::Gazole => 'Gazole',
            self::E10 => 'SP95-E10',
            self::SP98 => 'SP98',
            self::E85 => 'E85',
            self::GPL => 'GPL',
        };
    }
}
