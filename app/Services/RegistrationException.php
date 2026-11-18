<?php

namespace App\Services;

use RuntimeException;

/** A user-facing reason why a registration or check-in action was refused. */
class RegistrationException extends RuntimeException {}
