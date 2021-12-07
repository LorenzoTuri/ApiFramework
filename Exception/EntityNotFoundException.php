<?php declare(strict_types=1);

namespace Lturi\ApiFramework\Exception;

use Exception;
use JetBrains\PhpStorm\Pure;
use Throwable;

class EntityNotFoundException extends Exception {
    /**
     * EntityNotFoundException constructor.
     * @param string $entityName
     * @param int $code
     * @param Throwable|null $previous
     */
    #[Pure]
    public function __construct(
        $entityName = "",
        $code = 0,
        Throwable $previous = null
    ) {
        parent::__construct(
            "Entity {$entityName} not found",
            $code,
            $previous
        );
    }
}