<?php

namespace App\Domain\Content\Services;

use RuntimeException;

/** Error de un campo de bloque; BlockSchema lo convierte en ValidationException. */
final class BlockFieldError extends RuntimeException {}
