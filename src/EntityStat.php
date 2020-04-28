<?php


namespace ShardCache;


use ArrayObject;
use InvalidArgumentException;

/**
 * Class EntityStat
 * @package ShardCache
 */
final class EntityStat extends ArrayObject
{
    /**
     * @param string $guid
     * @return bool
     */
    public function offsetExists($guid)
    {
        if(!is_string($guid)) {
            throw new InvalidArgumentException('Argument \'$guid\' must be of type string.');
        }
        return parent::offsetExists($guid);
    }

    /**
     * @param string $guid
     * @return string Entity name
     */
    public function offsetGet($guid)
    {
        if(!is_string($guid)) {
            throw new InvalidArgumentException('Argument \'$guid\' must be of type string.');
        }
        return parent::offsetGet($guid);
    }

    /**
     * @param string $guid
     * @param EntityStatEntry $entityStatEntry
     */
    public function offsetSet($guid, $entityStatEntry)
    {
        if(!is_string($guid)) {
            throw new InvalidArgumentException('Argument \'$guid\' must be of type string.');
        }
        if(!$entityStatEntry instanceof EntityStatEntry) {
            throw new InvalidArgumentException('Argument \'$entityStatEntry\' must be of type EntityStatEntry.');
        }
        parent::offsetSet($guid, $entityStatEntry->getEntityName());
    }

    /**
     * @param string $guid
     */
    public function offsetUnset($guid)
    {
        if(!is_string($guid)) {
            throw new InvalidArgumentException('Argument \'$guid\' must be of type string.');
        }
        parent::offsetUnset($guid);
    }

    /**
     * @param EntityStatEntry $entityStatEntry
     */
    public function append($entityStatEntry)
    {
        if(!$entityStatEntry instanceof EntityStatEntry) {
            throw new InvalidArgumentException('Argument \'$entityStatEntry\' must be of type EntityStatEntry.');
        }
        $this->offsetSet($entityStatEntry->getGuid(), $entityStatEntry);
    }
}