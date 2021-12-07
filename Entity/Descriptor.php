<?php declare(strict_types=1);

namespace Lturi\ApiFramework\Entity;

use Doctrine\Common\Annotations\AnnotationReader;
use Fhaculty\Graph\Graph;
use Lturi\ApiFramework\Entity\Annotation\Entity;
use Lturi\ApiFramework\Entity\Permission\AbstractPermissionProvider;
use ReflectionClass;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\PropertyInfo\Type;
use function Symfony\Component\String\u;

/**
 * Describe an array of classes to generate a graph of allowable properties.
 * Example, if the object (class1) is made like:
 * {
 *      "name": "" (string),
 *      "relation": {class2} (relation to another object)
 * }
 * And the other (class2) is made like:
 * {
 *      "name": "" (string),
 *      "position": 0 (int)
 * }
 * -- If the PermissionProvider allows every class, then the graph is composed like:
 * NODE {class1} -> {name} -> {value}
 * NODE {class1} -> {relation} -> {class2}
 * NODE {class2} -> {name} -> {value}
 * NODE {class2} -> {position} -> {value}
 * -- If the PermissionProvider allows only class1, then the graph is:
 * NODE {class1} -> {name} -> {value}
 * -- If the PermissionProvider allows only class2, then the graph is:
 * NODE {class2} -> {name} -> {value}
 * NODE {class2} -> {position} -> {value}
 * -- {value} is a generic node, available for everyone
 */
class Descriptor {
    /** @var AbstractPermissionProvider */
    protected AbstractPermissionProvider $permissionProvider;
    /** @var PropertyInfoExtractor */
    protected PropertyInfoExtractor $propertyInfo;

    public function __construct(
        AbstractPermissionProvider $permissionProvider,
        PropertyInfoExtractor $propertyInfo = null,
    ) {
        $this->permissionProvider = $permissionProvider;
        if (!$propertyInfo) {
            $phpDocExtractor = new PhpDocExtractor();
            $reflectionExtractor = new ReflectionExtractor();
            $listExtractors = [$reflectionExtractor];
            $typeExtractors = [$phpDocExtractor, $reflectionExtractor];
            $descriptionExtractors = [$phpDocExtractor];
            $accessExtractors = [$reflectionExtractor];
            $propertyInitializableExtractors = [$reflectionExtractor];
            $this->propertyInfo = new PropertyInfoExtractor(
                $listExtractors,
                $typeExtractors,
                $descriptionExtractors,
                $accessExtractors,
                $propertyInitializableExtractors
            );
        }
    }

    public function generateGraph(array $classes): Graph
    {
        $reader = new AnnotationReader();
        $graph = new Graph();
        $simpleValue = $graph->createVertex('value');

        // Let's first create al vertices, in order to be able to get them while creating edges
        foreach ($classes as $class) {
            if ($this->permissionProvider->isPermitted($class)) {
                $vertex = $graph->createVertex($class);

                $reflectionClass = new ReflectionClass($class);
                /** @var Entity $entityDescription */
                $entityDescription = $reader->getClassAnnotation($reflectionClass, Entity::class);

                $vertex->setAttribute("name", $entityDescription->name ?? u($class)->camel()->toString());
                $vertex->setAttribute("class", $class);
            }
        }

        // Now create the edges
        foreach ($classes as $class) {
            $vertex = $graph->hasVertex($class) ? $graph->getVertex($class) : null;
            if ($vertex) {
                $properties = $this->propertyInfo->getProperties($class);
                foreach ($properties as $propertyName) {
                    $properties = $this->propertyInfo->getTypes($class, $propertyName);
                    /** @var Type $property */
                    $property = $this->sumPropertyTypes($properties);

                    if ($property && $property->getClassName()) {
                        // Object relation type
                        if ($this->permissionProvider->isRelationPermitted($class, $propertyName, $property->getClassName())) {
                            $edge = $vertex->createEdgeTo($simpleValue);
                            $edge->setAttribute("name", $propertyName);
                            $edge->setAttribute("class", $property->getClassName());
                            $edge->setAttribute("type", "relation");
                        }
                    } else {
                        $collectionValueType =  $property ?
                            $property->getCollectionValueType():
                            null;

                        if ($property && ((
                            $property->getBuiltinType() === Type::BUILTIN_TYPE_ARRAY ||
                            $property->isCollection()
                        ) && (
                            $collectionValueType &&
                            $collectionValueType->getClassName()
                        ))) {
                            // Array|Collection type
                            if ($this->permissionProvider->isRelationPermitted($class, $propertyName, $collectionValueType->getClassName())) {
                                $edge = $vertex->createEdgeTo($simpleValue);
                                $edge->setAttribute("name", $propertyName);
                                $edge->setAttribute("class", $collectionValueType->getClassName());
                                $edge->setAttribute("type", "collection");
                            }
                        } else {
                            // Value type
                            $edge = $vertex->createEdgeTo($simpleValue);
                            $edge->setAttribute("name", $propertyName);
                            $edge->setAttribute("class", null);
                            $edge->setAttribute("type", "value");
                        }
                    }
                }
            }
        }
        return $graph;
    }

    private function sumPropertyTypes($properties): ?Type
    {
        if ($properties) {
            $builtinType = null;
            $nullable = false;
            $class = null;
            $collection = false;
            $collectionKeyType = null;
            $collectionValueType = null;

            foreach ($properties as $property) {
                $builtinType = $builtinType ?? $property->getBuiltinType();
                $nullable = $nullable ?? $property->isNullable();
                $class = $class ?? $property->getClassName();
                $collection = $collection ?? $property->isCollection();
                $collectionKeyType = $collectionKeyType ?? $property->getCollectionKeyType();
                $collectionValueType = $collectionValueType ?? $property->getCollectionValueType();
            }
            if ($builtinType) {
                return new Type(
                    $builtinType,
                    $nullable,
                    $class,
                    $collection,
                    $collectionKeyType,
                    $collectionValueType
                );
            }
        }
        return null;
    }
}