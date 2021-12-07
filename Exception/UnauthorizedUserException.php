<?php declare(strict_types=1);

namespace Lturi\ApiFramework\Exception;

use Exception;
use JetBrains\PhpStorm\Pure;
use Throwable;

class UnauthorizedUserException extends Exception {
    #[Pure]
    public function __construct($code = 0, Throwable $previous = null)
    {
        parent::__construct("Unauthorized user", $code, $previous);
    }
}