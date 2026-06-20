<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Thrown when a certificate PDF cannot be rendered. The message may contain
 * renderer internals (e.g. Node stderr); it is logged server-side and never
 * shown to the end user (see bootstrap/app.php).
 */
class CertificateRenderingException extends RuntimeException
{
    public static function fromGenerator(string $message, ?Throwable $previous = null): self
    {
        return new self($message, 0, $previous);
    }
}
