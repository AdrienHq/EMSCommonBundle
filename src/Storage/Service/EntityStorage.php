<?php

namespace EMS\CommonBundle\Storage\Service;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Common\Persistence\ObjectManager;
use EMS\CommonBundle\Entity\AssetStorage;
use EMS\CommonBundle\Repository\AssetStorageRepository;
use GuzzleHttp\Psr7\Stream;
use Psr\Http\Message\StreamInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EntityStorage implements StorageInterface
{
    /** @var ObjectManager */
    private $manager;
    /** @var AssetStorageRepository */
    private $repository;

    public function __construct(Registry $doctrine)
    {
        $this->manager = $doctrine->getManager();

        //TODO: Quick fix, should be done using Dependency Injection, as it would prevent the RuntimeException!
        $repository = $this->manager->getRepository('EMSCommonBundle:AssetStorage');
        if (!$repository instanceof  AssetStorageRepository) {
            throw new \RuntimeException(sprintf(
                '%s has a repository that should be of type %s. But %s is given.',
                EntityStorage::class,
                AssetStorage::class,
                get_class($repository)
            ));
        }
        $this->repository = $repository;
    }

    public function head(string $hash): bool
    {
        return $this->repository->head($hash);
    }

    public function getSize(string $hash): int
    {
        $size = $this->repository->getSize($hash);
        if ($size === null) {
            throw new NotFoundHttpException($hash);
        }
        return $size;
    }


    public function create(string $hash, string $filename): bool
    {
        $entity = $this->createEntity($hash);

        $content = \file_get_contents($filename);
        $size = \filesize($filename);

        if ($content === false || $size === false) {
            throw new FileNotFoundException($hash);
        }

        $entity->setSize($size);
        $entity->setContents($content);
        $entity->setConfirmed(true);
        $this->manager->persist($entity);
        $this->manager->flush();
        return true;
    }

    private function createEntity(string $hash): AssetStorage
    {
        $entity = $this->repository->findByHash($hash);
        if ($entity === null) {
            $entity = new AssetStorage();
            $entity->setHash($hash);
        }
        return $entity;
    }

    public function read(string $hash, bool $confirmed = true): StreamInterface
    {
        $entity = $this->repository->findByHash($hash, $confirmed);
        if ($entity === null) {
            throw new NotFoundHttpException($hash);
        }
        $contents = $entity->getContents();

        if (is_resource($contents)) {
            return new Stream($contents);
        }
        $resource = fopen('php://memory', 'w+');
        if ($resource === false) {
            throw new NotFoundHttpException($hash);
        }
        fwrite($resource, $contents);

        rewind($resource);
        return new Stream($resource);
    }

    public function health(): bool
    {
        try {
            return ($this->repository->count([]) >= 0);
        } catch (\Exception $e) {
        }
        return false;
    }

    public function __toString(): string
    {
        return EntityStorage::class;
    }

    public function remove(string $hash): bool
    {
        return $this->repository->removeByHash($hash);
    }

    public function initUpload(string $hash, int $size, string $name, string $type): bool
    {
        $entity = $this->repository->findByHash($hash, false);
        if ($entity === null) {
            $entity = $this->createEntity($hash);
        }

        $entity->setSize(0);
        $entity->setContents('');
        $entity->setConfirmed(false);

        $this->manager->persist($entity);
        $this->manager->flush();

        return true;
    }

    public function finalizeUpload(string $hash): bool
    {
        $entity = $this->repository->findByHash($hash, false);
        if ($entity !== null) {
            $entity->setConfirmed(true);
            $entity->setSize(strlen((string) $entity->getContents()));
            $this->manager->persist($entity);
            $this->manager->flush();
            return true;
        }
        return false;
    }

    public function addChunk(string $hash, string $chunk, ?string $context = null): bool
    {
        $entity = $this->repository->findByHash($hash, false);
        if ($entity !== null) {
            $contents = $entity->getContents();
            if (is_resource($contents)) {
                $contents = stream_get_contents($contents);
            }

            $entity->setContents($contents . $chunk);

            $entity->setSize($entity->getSize() + strlen($chunk));
            $this->manager->persist($entity);
            $this->manager->flush();
            return true;
        }
        return false;
    }
}
