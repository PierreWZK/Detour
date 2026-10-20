<?php

namespace App\Detour\Search;

use DomainException;

/** Trajet trop long pour une recherche autour de la route (le long trajet est un bonus du cahier des charges) */
final class TripTooLong extends DomainException {}
