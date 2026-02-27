<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a pipeline job detects the asset was cancelled by the user.
 * This exception is caught gracefully — no retry, no failure logging.
 */
class PipelineCancelledException extends RuntimeException
{
    //
}
