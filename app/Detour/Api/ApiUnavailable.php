<?php

namespace App\Detour\Api;

use RuntimeException;

/** Un service externe ne répond pas ou répond mal : le message est affiché tel quel à l'utilisateur */
final class ApiUnavailable extends RuntimeException {}
