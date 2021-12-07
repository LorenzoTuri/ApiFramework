<?php declare(strict_types=1);

namespace Lturi\ApiFramework\Service\Normalizer;

use Fhaculty\Graph\Graph;
use Stringable;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\ContextAwareNormalizerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;

/**
 * TODO: now the full graph is returned (of permitted items), eventually only until a precise depth.
 *  This is not what this class should finally do... what it should do
 *  is to return the graph by permissions and associations... after that the depth
 *  can be removed. Also too-deep should be formatted
 */
class GraphSerializer extends AbstractNormalizer implements ContextAwareNormalizerInterface, NormalizerAwareInterface, DenormalizerAwareInterface {
    use NormalizerAwareTrait;
    use DenormalizerAwareTrait;

    const CONTEXT_GRAPH_DEFINITION = "graph.definition";
    const CONTEXT_ENTITY_DEPTH = "entity.depth";

    protected PropertyInfoExtractor $propertyInfo;

    public function __construct(
        ClassMetadataFactoryInterface $classMetadataFactory = null,
        NameConverterInterface $nameConverter = null,
        array $defaultContext = [],
        PropertyInfoExtractor $propertyInfo = null,
        $useDefaultCircularHandler = true
    ) {
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

        if ($useDefaultCircularHandler) {
            $defaultContext[AbstractNormalizer::CIRCULAR_REFERENCE_HANDLER] =  [$this, "circularReferenceHandler"];
        }
        parent::__construct($classMetadataFactory, $nameConverter, $defaultContext);
    }

    public function supportsNormalization($data, string $format = null, array $context = []): bool
    {
        $graphDefinition = $this->loadGraphDefinition($context);
        // Only normalize entity that are available in the graph
        return $data && $graphDefinition && is_object($data) &&  $graphDefinition->hasVertex(get_class($data));
    }

    public function normalize($object, string $format = null, array $context = [])
    {
        $results = null;
        $graphDefinition = $this->loadGraphDefinition($context);

        if (!$this->isCircularReference($object, $context)) {
            if (
                $graphDefinition &&
                $graphDefinition->hasVertex(get_class($object)) &&
                $vertex = $graphDefinition->getVertex(get_class($object))
            ) {
                $results = [];
                $properties = $this->propertyInfo->getProperties(get_class($object));

                foreach ($properties as $property) {
                    // Detect the correct edge by name
                    $edge = null;
                    foreach ($vertex->getEdges() as $item) {
                        if (!$edge) {
                            $edge = $item->getAttribute("name") === $property ? $item : null;
                        }
                    }

                    // Let's insert only the non-object nodes
                    if ($edge && $edge->getAttribute("class")) {
                        if (isset($context[self::CONTEXT_ENTITY_DEPTH]) &&
                            $context[self::CONTEXT_ENTITY_DEPTH] !== null
                            && $context[self::CONTEXT_ENTITY_DEPTH] <= 0) {
                            return $this->handleCircularReference($object, $format, $context);
                        }

                        $newContext = array_merge(
                            $context, [
                                self::CONTEXT_ENTITY_DEPTH =>
                                    (isset($context[self::CONTEXT_ENTITY_DEPTH]) &&
                                        $context[self::CONTEXT_ENTITY_DEPTH] !== null) ?
                                        $context[self::CONTEXT_ENTITY_DEPTH] - 1 :
                                        null
                            ]
                        );

                        $results[$property] = $this->normalizer->normalize(
                            $object->{"get" . ucfirst($property)}(),
                            $format,
                            $newContext
                        );
                    } else if ($edge) {
                        $results[$property] = $this->normalizer->normalize(
                            $object->{"get" . ucfirst($property)}(),
                            $format,
                            $context
                        );
                    }
                }
            }
        } else {
            return $this->handleCircularReference($object, $format, $context);
        }

        return $results;
    }

    public function supportsDenormalization($data, string $type, string $format = null): bool
    {
        $graphDefinition = $this->loadGraphDefinition();
        // Only normalize entity that are available in the graph
        return $data && $graphDefinition && is_array($data) && $graphDefinition->hasVertex($type);
    }
    public function denormalize($data, string $type, string $format = null, array $context = [])
    {
        $result = null;
        $graphDefinition = $this->loadGraphDefinition($context);

        if (
            $graphDefinition &&
            $graphDefinition->hasVertex($type) &&
            $vertex = $graphDefinition->getVertex($type)
        ) {
            $result = new $type();

            foreach ($data as $property => $value) {
                // Detect the correct edge by name
                $edge = null;
                foreach ($vertex->getEdges() as $item) {
                    if (!$edge) {
                        $edge = $item->getAttribute("name") === $property ? $item : null;
                    }
                }

                if ($edge) {
                    if (
                        method_exists($result, "set" . ucfirst($property)) &&
                        $edge->getAttribute("class")
                    ) {
                        if ($value === null) {
                            $result->{"set" . ucfirst($property)}(null);
                        } else {
                            $result->{"set" . ucfirst($property)}(
                                $this->denormalizer->denormalize(
                                    $value,
                                    $edge->getAttribute("class"),
                                    $format,
                                    $context
                                )
                            );
                        }
                    } else {
                        $result->{"set" . ucfirst($property)}($value);
                    }
                }
            }
        }

        return $result;
    }


    protected function circularReferenceHandler($object): bool|string
    {
        if (get_class($object)) {
            $returnValue = null;
            if (method_exists($object, "getId")) {
                $returnValue = get_class($object)."(#".($object->getId() ? $object->getId() : "null").")";
            } else if ($object instanceof Stringable) {
                $returnValue = get_class($object)."(#".(string)$object.")";
            } else {
                $returnValue = get_class($object);
            }
            return $returnValue;
        } else {
            return (string)$object;
        }
    }

    protected function loadGraphDefinition(array $context = null): ?Graph {
        if (
            $context &&
            isset($context[self::CONTEXT_GRAPH_DEFINITION]) &&
            $context[self::CONTEXT_GRAPH_DEFINITION] instanceof Graph
        ) {
            return $context[self::CONTEXT_GRAPH_DEFINITION];
        }
        if (isset($this->defaultContext[self::CONTEXT_GRAPH_DEFINITION]) &&
            $this->defaultContext[self::CONTEXT_GRAPH_DEFINITION] instanceof Graph
        ) {
            return $this->defaultContext[self::CONTEXT_GRAPH_DEFINITION];
        }
        return null;
    }
}