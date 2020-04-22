<?php


namespace Linzbauer\ShardCache\Cacheables;


use Linzbauer\ShardCache\ShardCache;

/**
 * Class Entity
 * @package Linzbauer\ShardCache\Cacheables
 */
abstract class Entity
{
    /**
     * @var string
     */
    protected $shardCache;

    public function __construct(ShardCache $shardCache)
    {
        $this->shardCache = $shardCache->getName();
    }

    public function setShardCache(ShardCache $shardCache): void
    {
        $save = $shardCache->getName() !== $this->shardCache;
        $this->shardCache = $shardCache->getName();
        $this->saveChanges($save);
    }

    abstract public function getGuid(): string;

    public function getShardCache(): ShardCache
    {
        return ShardCache::getInstance($this->shardCache);
    }

    public function saveChanges(bool $save = true): void
    {
        !$save || $this->getShardCache()->saveChanges();
    }
}