<?php


namespace ShardCache\Cacheables;


use ShardCache\EntityStat;
use ShardCache\EntityStatEntry;
use ShardCache\ShardCache;

/**
 * Class Repository
 * @package ShardCache\Cacheables
 */
abstract class Repository
{
    /**
     * @var ShardCache
     */
    protected $shardCache;

    public function __construct(ShardCache $shardCache)
    {
        $this->shardCache = $shardCache;
    }

    abstract public function fetchEntityByGuid(string $guid): ?Entity;

    /**
     * @return Entity[]
     */
    abstract public function fetchAllEntities(): array;

    /**
     * @param Entity[] $entities
     */
    public function finalizeEntities(array $entities): void
    {
    }

    /**
     * @return EntityStat|EntityStatEntry[]
     */
    abstract public function getEntityStat(): EntityStat;
}