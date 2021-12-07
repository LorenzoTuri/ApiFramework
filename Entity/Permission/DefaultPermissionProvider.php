<?php declare(strict_types=1);

namespace Lturi\ApiFramework\Entity\Permission;

use DateInterval;
use DateTimeInterface;
use JetBrains\PhpStorm\Pure;

/**
 * Default permission provider allows only classes relations by name
 * For any other more complex behaviour, create your own PermissionProvider
 */
class DefaultPermissionProvider extends AbstractPermissionProvider {
    const ALLOWABLE_TYPES = [
        DateTimeInterface::class,
        DateInterval::class
    ];

    /** @var array */
    protected array $allowableTypes;

    public function __construct(array $allowableTypes = self::ALLOWABLE_TYPES)
    {
        $this->allowableTypes = $allowableTypes;
    }

    #[Pure]
    public function isPermitted($classFrom): bool
    {
        return in_array($classFrom, $this->allowableTypes);
    }
    #[Pure]
    public function isRelationPermitted($classFrom, $propertyName, $classTo): bool
    {
        return in_array($classTo, $this->allowableTypes);
    }
}
