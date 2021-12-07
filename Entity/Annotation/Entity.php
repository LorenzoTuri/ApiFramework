<?php

namespace Lturi\ApiFramework\Entity\Annotation;

use Doctrine\Common\Annotations\Annotation\Target;

/**
 * @Annotation
 * @Target("CLASS")
 */
class Entity
{
    /**
     * @var string name of the entity, default camelCase of the class (with namespace)
     */
    public string $name;
    /**
     * @var string path to the entity, default kebabCase of the class (with namespace)
     */
    public string $path;
}