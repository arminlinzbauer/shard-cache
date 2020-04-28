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
     * @var ShardCache[]
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
     * @var stdClass
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
        $this->memoryCache = $this->getCacheSkeleton();

        if (!empty(self::$instances[$cacheHandler->getName()])) {
            throw new DuplicateCache();
        } else {
            self::$instances[$cacheHandler->getName()] = $this;
        }
    }

    public function finishInitialization(): void
    {
        if ($memoryCache = $this->cacheHandler->get()) {
            $this->logger->log('ShardCache: Loading from cache file ' . $this->getName());
            $this->memoryCache = $memoryCache;
        } else {
            $this->logger->log('ShardCache: Fetching from database into ' . $this->getName());
            $this->rebuild();
        }
    }

    public function getName(): string
    {
        return $this->cacheHandler->getName();
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
        $entityNames = array_keys($this->repositories);
        $entities = [];
        foreach ($entityNames as $entityName) {
            $entities[$entityName] = $this->repositories[$entityName]->fetchAllEntities();
        }
        foreach ($entityNames as $entityName) {
            $this->repositories[$entityName]->finalizeEntities($entities[$entityName]);
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

    private function save(): void
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
        $this->logger->log('ShardCache: Purging cache file ' . $this->getName());
        $this->cacheHandler->delete();
        $this->rebuild();
    }

    private function forcePurge(): void
    {
        $this->logger->log('ShardCache: Force-Purging cache file ' . $this->getName());
        $this->cacheHandler->delete();
        $this->memoryCache = $this->getCacheSkeleton();
    }

    /**
     * @param string $guid
     * @param string|null ...$namespaces
     * @return Entity|null
     */
    public function requestEntity(string $guid, ?string ...$namespaces): ?Entity
    {
        $guid = strtolower($guid);
        $namespaces = empty($namespaces) ? [] : $namespaces;
        $namespaces = array_filter($namespaces);

        if (!array_key_exists($guid, $this->memoryCache->entities)) {
            $this->logger->log(
                'ShardCache: Trying to fetch entity \'' . $guid .
                '\' from database into ' . $this->getName()
            );
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
                    'ShardCache: Using individual repository\'s getEntityStat()-functions. ' .
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
                $this->logger->log('ShardCache: Entity \'' . $guid . '\' not found.');
                return null;
            }

            $entityName = strtolower($stat[$guid]);
            $this->disableSaveOnChange();
            $entity = $this->repositories[$entityName]->fetchEntityByGuid($guid);
            $this->repositories[$entityName]->finalizeEntities([$entity->getGuid() => $entity]);
            $this->enableSaveOnChange();
            $this->saveChanges();
        }

        if ($this->memoryCache->entities[$guid] === null) {
            return null;
        }

        if (empty($namespaces)) {
            return $this->memoryCache->entities[$guid];
        }

        foreach ($namespaces as $namespace) {
            $namespace = strtolower($namespace);
            if (!array_key_exists($namespace, $this->memoryCache->namespaces)) {
                continue;
            }
            if (!array_key_exists($guid, $this->memoryCache->namespaces[$namespace])) {
                $this->logger->log(
                    'ShardCache: Entity \'' . $guid . '\' not in all required namespaces (' .
                    implode(', ', $namespaces) .
                    ').'
                );
                return null;
            }
        }

        return $this->memoryCache->entities[$guid];
    }

    private function unregisterGuidFromAllNamespaces(string $guid): void
    {
        foreach ($this->memoryCache->namespaces as $namespace) {
            unset($namespace[$guid]);
        }
        $this->saveChanges();
    }

    public function saveChanges(): void
    {
        if ($this->saveOnChange) {
            $this->save();
        }
    }

    /**
     * @param string|null ...$namespaces
     * @return Entity[]
     */
    public function requestAllEntities(?string ...$namespaces): array
    {
        $namespaces = empty($namespaces) ? [] : $namespaces;
        $namespaces = array_filter($namespaces);

        if (empty($namespaces)) {
            return array_filter($this->memoryCache->entities);
        }

        $namespaceEntities = [];
        foreach ($namespaces as $namespace) {
            $namespace = strtolower($namespace);
            if (!array_key_exists($namespace, $this->memoryCache->namespaces)) {
                return [];
            }
            $guids = $this->memoryCache->namespaces[$namespace];
            $namespaceEntities[$namespace] = $guids;
        }

        if(empty($namespaceEntities)) {
            return [];
        }

        $master = $this->toGuidList($this->memoryCache->entities);
        $result = array_intersect($master, ...array_values($namespaceEntities));
        return $this->toEntityList($result);
    }

    /**
     * @param Entity[] $entityList
     * @return string[]
     */
    private function toGuidList(array $entityList): array
    {
        $entityList = array_filter($entityList);
        $output = array_map(function($entity) { return $entity->getGuid(); }, $entityList);
        return array_filter($output);
    }

    /**
     * @param string[] $guidList
     * @return Entity[]
     */
    private function toEntityList(array $guidList): array
    {
        $guidList = array_filter($guidList);
        $output = array_map(function($guid) { return $this->requestEntity($guid); }, $guidList);
        return array_filter($output);
    }

    public function registerEntity(Entity $entity, ?string $namespace = null): void
    {
        $guid = strtolower($entity->getGuid());
        if ($namespace) {
            $namespace = strtolower($namespace);
            $this->registerNamespace($namespace);
            $this->memoryCache->namespaces[$namespace][$guid] = $guid;
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
            $this->unregisterGuidFromAllNamespaces($guid);
            unset($this->memoryCache->entities[$guid]);
        }
        $this->saveChanges();
    }

    public function destroyInstance(): void
    {
        $this->forcePurge();
        $this->logger->log('ShardCache: Destroying instance ' . $this->getName());
        unset(self::$instances[$this->getName()]);
    }

    public static function unregisterInstance(string $instanceName): void
    {
        self::$instances[$instanceName]->destroyInstance();
    }

    public static function getInstance(string $instanceName): ?ShardCache
    {
        if (!empty(self::$instances[$instanceName])) {
            return self::$instances[$instanceName];
        }
        return null;
    }
}
