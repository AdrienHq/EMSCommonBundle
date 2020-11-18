<?php

namespace EMS\CommonBundle\Storage\Factory;

use EMS\CommonBundle\Storage\Service\S3Storage;
use EMS\CommonBundle\Storage\Service\StorageInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class S3Factory implements StorageFactoryInterface
{
    /** @var string */
    const STORAGE_TYPE = 's3';
    /** @var string */
    const STORAGE_CONFIG_CREDENTIALS = 'credentials';
    /** @var string */
    const STORAGE_CONFIG_BUCKET = 'bucket';
    /** @var LoggerInterface */
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function createService(array $parameters): ?StorageInterface
    {
        $config = $this->resolveParameters($parameters);

        $credentials = $config[self::STORAGE_CONFIG_CREDENTIALS] ?? null;
        $bucket = $config[self::STORAGE_CONFIG_BUCKET] ?? null;

        if ($credentials === null || $bucket === null) {
            @trigger_error('You should consider to migrate you storage service configuration to the EMS_STORAGES variable', \E_USER_DEPRECATED);
            return null;
        }

        return new S3Storage($this->logger, $credentials, $bucket, $config[self::STORAGE_CONFIG_READ_ONLY], $config[self::STORAGE_CONFIG_SKIP]);
    }

    public function getStorageType(): string
    {
        return self::STORAGE_TYPE;
    }

    /**
     * @param array<string, mixed> $parameters
     * @return array{type: string, credentials: null|array, bucket: null|string, read-only: bool, skip: bool}
     */
    private function resolveParameters(array $parameters): array
    {
        $resolver = new OptionsResolver();
        $resolver
            ->setDefaults([
                self::STORAGE_CONFIG_TYPE => self::STORAGE_TYPE,
                self::STORAGE_CONFIG_CREDENTIALS => null,
                self::STORAGE_CONFIG_BUCKET => null,
                self::STORAGE_CONFIG_READ_ONLY => false,
                self::STORAGE_CONFIG_SKIP => false,
            ])
            ->setRequired(self::STORAGE_CONFIG_TYPE)
            ->setAllowedTypes(self::STORAGE_CONFIG_TYPE, 'string')
            ->setAllowedTypes(self::STORAGE_CONFIG_CREDENTIALS, ['null', 'array'])
            ->setAllowedTypes(self::STORAGE_CONFIG_BUCKET, ['null', 'string'])
            ->setAllowedValues(self::STORAGE_CONFIG_TYPE, [self::STORAGE_TYPE])
            ->setAllowedValues(self::STORAGE_CONFIG_READ_ONLY, [true, false])
            ->setAllowedValues(self::STORAGE_CONFIG_SKIP, [true, false])
        ;
        /** @var array{type: string, credentials: null|array, bucket: null|string, read-only: bool, skip: bool} $resolvedParameter */
        $resolvedParameter = $resolver->resolve($parameters);
        return $resolvedParameter;
    }
}
