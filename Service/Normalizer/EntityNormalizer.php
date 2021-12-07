<?php declare(strict_types=1);

namespace Lturi\ApiFramework\Service\Normalizer;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorResolverInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

class EntityNormalizer extends ObjectNormalizer implements DenormalizerAwareInterface
{
    protected EntityManagerInterface $entityManager;

    use DenormalizerAwareTrait;

    /**
     * @param EntityManagerInterface $entityManager
     * @param ClassMetadataFactoryInterface|null $classMetadataFactory
     * @param NameConverterInterface|null $nameConverter
     * @param PropertyAccessorInterface|null $propertyAccessor
     * @param PropertyTypeExtractorInterface|null $propertyTypeExtractor
     *
     * @param ClassDiscriminatorResolverInterface|null $classDiscriminatorResolver
     * @param callable|null $objectClassResolver
     * @param array $defaultContext
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        ClassMetadataFactoryInterface $classMetadataFactory = null,
        NameConverterInterface $nameConverter = null,
        PropertyAccessorInterface $propertyAccessor = null,
        PropertyTypeExtractorInterface $propertyTypeExtractor = null,
        ClassDiscriminatorResolverInterface $classDiscriminatorResolver = null,
        callable $objectClassResolver = null,
        array $defaultContext = []
    ) {
        $this->entityManager = $entityManager;

        parent::__construct(
            $classMetadataFactory,
            $nameConverter,
            $propertyAccessor,
            $propertyTypeExtractor,
            $classDiscriminatorResolver,
            $objectClassResolver,
            $defaultContext
        );
    }

    public function supportsDenormalization($data, string $type, string $format = null): bool
    {
        return (
            is_array($data) &&
            isset($data['id']) &&
            $data["id"]
        );
    }

    /**
     * @param $data
     * @param string $type
     * @param string|null $format
     * @param array<mixed> $context
     *
     * @return array<mixed>|object
     * @throws ExceptionInterface
     */
    public function denormalize($data, string $type, string $format = null, array $context = []): object|array
    {
        $entity = $this->entityManager->find($type, $data["id"]);
        unset($data["id"]);

        $context[AbstractNormalizer::OBJECT_TO_POPULATE] = $entity;
        return $this->denormalizer->denormalize($data, $type, $format, $context);
    }
}
