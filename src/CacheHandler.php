<?php


namespace ShardCache;

/**
 * Interface CacheHandler
 * @package ShardCache
 */
interface CacheHandler
{
    /**
     * @param string $name
     * @param mixed $data
     * @param int|null $expiresIn seconds
     */
    public function set(string $name, $data, int $expiresIn = null): void;

    /**
     * @param string $name
     */
    public function delete(string $name): void;

    /**
     * @param string $name
     * @return mixed
     */
    public function get(string $name);
}
