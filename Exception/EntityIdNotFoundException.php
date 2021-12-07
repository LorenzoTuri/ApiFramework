<?php declare(strict_types=1);

namespace Lturi\ApiFramework\Exception;

use Exception;
use JetBrains\PhpStorm\Pure;
use Throwable;

class EntityIdNotFoundException extends Exception {
    #[Pure]
    public function __construct(
        $id = "",
        $code = 0,
        Throwable $previous = null
    ) {
        parent::__construct("Id {$id} not found", $code, $previous);
    }
}