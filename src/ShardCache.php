<?php

/** @noinspection PhpPrivateFieldCanBeLocalVariableInspection */

/** @noinspection PhpUnused */


namespace Linzbauer\ShardCache;

use InvalidArgumentException;
use Linzbauer\ShardCache\Cacheables\Entity;
use Linzbauer\ShardCache\Cacheables\Repository;
use Linzbauer\ShardCache\Exceptions\DuplicateCache;
use Linzbauer\ShardCache\Logger\Logger;
use Linzbauer\ShardCache\Logger\NullLogger;
use stdClass;

/**
 * Class ShardCache
 * @package Linzbauer\ShardCache
 */
final class ShardCache
{
    /**
     * @var stdClass
     */
    private static $instances = [];
    /**
     * @var Logger
     */
    private $logger;
    /**
     * @var CacheProxy
     */
    private $cacheHandler;
    /**
     * @var array
     */
    private $memoryCache;
    /**
     * @var bool
     */
    private $saveOnChange = true;
    /**
     * @var callable
     */
    private $dataLayerStatFunction;
    /**
     * @var Repository[]
     */
    private $repositories;

    /**
     * ShardCache constructor.
     * @param string $name
     * @param CacheHandler $cacheHandler
     * @param Logger|null $logger
     * @param string $namespace
     * @throws DuplicateCache
     */
    public function __construct(string $name, CacheHandler $cacheHandler, ?Logger $logger, string $namespace = '')
    {
        $cacheHandler = new CacheProxy($cacheHandler, $name, $namespace);
        $this->setCacheHandler($cacheHandler);
        $this->setLogger($logger);
        $this->repositories = [];

        if (in_array($cacheHandler->getName(), self::$instances)) {
            throw new DuplicateCache();
        } else {
            array_push(self::$instances, $cacheHandler->getName());
        }

        if ($memoryCache = $this->cacheHandler->get()) {
            $this->memoryCache = $memoryCache;
        } else {
            $this->memoryCache = $this->getCacheSkeleton();
            $this->rebuild();
        }
    }

    /**
     * @param CacheProxy $cacheHandler
     */
    private function setCacheHandler(CacheProxy $cacheHandler): void
    {
        $this->cacheHandler = $cacheHandler;
    }

    /**
     * @param Logger $logger
     */
    private function setLogger(Logger $logger): void
    {
        if ($logger === null) {
            $logger = new NullLogger();
        }
        $this->logger = $logger;
    }

    /**
     * @return stdClass
     */
    private function getCacheSkeleton(): stdClass
    {
        $skeleton = new stdClass();
        $skeleton->entities = [];
        $skeleton->namespaces = [];
        return $skeleton;
    }

    public function rebuild(): void
    {
        $this->disableSaveOnChange();
        $this->memoryCache = $this->getCacheSkeleton();
        foreach ($this->repositories as $repository) {
            $repository->fetchAllEntities();
        }
        $this->enableSaveOnChange();
        $this->save();
    }

    private function disableSaveOnChange(): void
    {
        $this->saveOnChange = false;
    }

    private function enableSaveOnChange(): void
    {
        $this->saveOnChange = true;
    }

    public function save(): void
    {
        $this->cacheHandler->set($this->memoryCache);
    }

    public function registerRepository(string $entityName, Repository $repository): void
    {
        $this->repositories[strtolower($entityName)] = $repository;
    }

    /**
     * @param callable $callback Should take $guid:string as argument and must return instance of {@see EntityStat}
     */
    public function registerDataLayerStatFunction(callable $callback): void
    {
        $this->dataLayerStatFunction = $callback;
    }

    public function purge(): void
    {
        $this->cacheHandler->delete();
        $this->rebuild();
    }

    /**
     * @param string $guid
     * @param string ...$namespaces
     * @return Entity|null
     */
    public function requestEntity(string $guid, string ...$namespaces): ?Entity
    {
        $guid = strtolower($guid);

        if (!array_key_exists($guid, $this->memoryCache->entities)) {
            if (is_callable($this->dataLayerStatFunction)) {
                $stat = call_user_func($this->dataLayerStatFunction, $guid);
                if (!$stat instanceof EntityStat) {
                    throw new InvalidArgumentException(
                        'Custom DataLayerStat-function must return an instance of ' . EntityStat::class
                    );
                }
                $stat = $stat->getArrayCopy();
            } else {
                trigger_error(
                    'ShardCache: Using individual repository\'s getEntityStat()-functions. '.
                    'Providing a cumulated DataLayerStat function with ' .
                    self::class . '::registerDataLayerStatFunction(callable $callback) might result in slightly ' .
                    'improved performance by only having to query the database once per stat request.',
                    E_USER_NOTICE
                );
                $stat = [];
                foreach ($this->repositories as $repository) {
                    $stat += $repository->getEntityStat()->getArrayCopy();
                }
            }
            if (empty($stat)) {
                $this->memoryCache->entities[$guid] = null;
                $this->unregisterGuidFromAllNamespaces($guid);
                $this->saveChanges();
                return null;
            }

            $entityName = strtolower($stat[$guid]);
            $this->repositories[$entityName]->fetchEntityByGuid($guid);
            $this->saveChanges();
        }

        if ($this->memoryCache->entities[$guid] === null) {
            return null;
        }

        if (empty($namespace)) {
            return $this->memoryCache->entities[$guid];
        }

        foreach ($namespaces as $namespace) {
            if (!array_key_exists($namespace, $this->memoryCache->namespaces)) {
                continue;
            }
            if (!array_key_exists($guid, $this->memoryCache->namespaces[$namespace])) {
                return null;
            }
        }

        return $this->memoryCache->entities[$guid];
    }

    private function unregisterGuidFromAllNamespaces(string $guid): void
    {
        foreach (array_keys($this->memoryCache->namespaces) as $namespace) {
            unset($this->memoryCache->namespaces[$namespace][$guid]);
        }
        $this->saveChanges();
    }

    private function saveChanges(): void
    {
        if ($this->saveOnChange) {
            $this->save();
        }
    }

    /**
     * @param string ...$namespaces
     * @return Entity[]
     */
    public function requestAllEntities(string ...$namespaces): array
    {
        if (empty($namespaces)) {
            return array_filter($this->memoryCache->entities);
        }

        $namespaceEntities = [];
        foreach ($namespaces as $namespace) {
            if (!array_key_exists($namespace, $this->memoryCache->namespaces)) {
                continue;
            }
            $namespaceEntities[$namespace] = array_filter($this->memoryCache->namespaces[$namespace]);
        }

        if (count($namespaceEntities) <= 1) {
            return $namespaceEntities;
        }
        $master = array_filter($this->memoryCache->entities);
        return array_intersect($master, ...$namespaceEntities);
    }

    public function registerEntity(Entity $entity, ?string $namespace = null): void
    {
        $guid = strtolower($entity->getGuid());
        if ($namespace) {
            $namespace = strtolower($namespace);
            $this->registerNamespace($namespace);
            $this->memoryCache->namespaces[$namespace][$guid] = $entity;
        }
        $this->memoryCache->entities[$guid] = $entity;
        $this->saveChanges();
    }

    public function registerNamespace(string $namespace): void
    {
        $namespace = strtolower($namespace);
        if (!array_key_exists($namespace, $this->memoryCache->namespaces)) {
            $this->memoryCache->namespaces[$namespace] = [];
        }
        $this->saveChanges();
    }

    public function unregisterEntity(Entity $entity, ?string $namespace = null): void
    {
        $guid = strtolower($entity->getGuid());
        if ($namespace) {
            $namespace = strtolower($namespace);
            if ($namespace === '*') {
                $this->unregisterGuidFromAllNamespaces($guid);
            } else {
                if (array_key_exists($namespace, $this->memoryCache->namespaces)) {
                    unset($this->memoryCache->namespaces[$namespace][$guid]);
                }
            }
        } else {
            unset($this->memoryCache->entities[$guid]);
        }
        $this->saveChanges();
    }
}
