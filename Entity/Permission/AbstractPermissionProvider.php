<?php declare(strict_types=1);

namespace Lturi\ApiFramework\Entity\Permission;

abstract class AbstractPermissionProvider {

    public abstract function isPermitted($classFrom): bool;

    public abstract function isRelationPermitted($classFrom, $propertyName, $classTo): bool;
}
