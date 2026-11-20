<?php

namespace App\Domain\Payments\Services;

use RuntimeException;

/** Falla de comunicación o respuesta inesperada de una pasarela. El mensaje es apto para mostrar en el panel. */
class GatewayException extends RuntimeException {}
