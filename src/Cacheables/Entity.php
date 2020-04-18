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
     * @var ShardCache
     */
    protected $shardCache;

    public function __construct(ShardCache $shardCache)
    {
        $this->shardCache = $shardCache;
    }

    abstract public function getGuid(): string;
}