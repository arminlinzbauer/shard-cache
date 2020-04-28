<?php


namespace ShardCache\Cacheables;


use ShardCache\ShardCache;

/**
 * Class Entity
 * @package ShardCache\Cacheables
 */
abstract class Entity
{
    /** @var string */
    private $guid;
    /** @var string */
    protected $shardCache;

    public function __construct(ShardCache $shardCache, string $guid)
    {
        $this->shardCache = $shardCache->getName();
        $this->guid = strtolower($guid);
    }

    public function setShardCache(ShardCache $shardCache): void
    {
        $save = $shardCache->getName() !== $this->shardCache;
        $this->shardCache = $shardCache->getName();
        $this->saveChanges($save);
    }

    public function getGuid(): string
    {
        return $this->guid;
    }

    public function getShardCache(): ShardCache
    {
        return ShardCache::getInstance($this->shardCache);
    }

    public function saveChanges(bool $save = true): void
    {
        !$save || $this->getShardCache()->saveChanges();
    }
}