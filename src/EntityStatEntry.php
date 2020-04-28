<?php


namespace ShardCache;


/**
 * Class EntityStatEntry
 * @package ShardCache
 */
final class EntityStatEntry
{
    /**
     * @var string
     */
    private $guid;
    /**
     * @var string
     */
    private $entityName;

    public function __construct(string $guid, string $entityName)
    {
        $this->setGuid($guid);
        $this->setEntityName($entityName);
    }

    /**
     * @return string
     */
    public function getGuid(): string
    {
        return $this->guid;
    }

    /**
     * @param string $guid
     */
    public function setGuid(string $guid): void
    {
        $this->guid = strtolower($guid);
    }

    /**
     * @return string
     */
    public function getEntityName(): string
    {
        return $this->entityName;
    }

    /**
     * @param string $entityName
     */
    public function setEntityName(string $entityName): void
    {
        $this->entityName = strtolower($entityName);
    }


}