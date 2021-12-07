<?php declare(strict_types=1);

namespace Lturi\ApiFramework\Exception;

use Exception;
use JetBrains\PhpStorm\Pure;
use Throwable;

class UnrecognizableFilterException extends Exception {
    #[Pure]
    public function __construct($filterType, $code = 0, Throwable $previous = null)
    {
        parent::__construct(
            "Unrecognizable filter {$filterType}",
            $code,
            $previous
        );
    }
}