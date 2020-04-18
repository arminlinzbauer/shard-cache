<?php


namespace Linzbauer\ShardCache\Cacheables;


use Linzbauer\ShardCache\EntityStat;
use Linzbauer\ShardCache\EntityStatEntry;
use Linzbauer\ShardCache\ShardCache;

/**
 * Class Repository
 * @package Linzbauer\ShardCache\Cacheables
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
     * @return EntityStat|EntityStatEntry[]
     */
    abstract public function getEntityStat(): EntityStat;
}