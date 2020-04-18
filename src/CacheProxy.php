<?php


namespace Linzbauer\ShardCache;

/**
 * Class CacheProxy
 * @package Linzbauer\ShardCache
 */
final class CacheProxy
{
    /**
     * @var string
     */
    private $name;
    /**
     * @var CacheHandler
     */
    private $cacheHandler;
    /**
     * @var string
     */
    private $suffix = '';

    /**
     * CacheProxy constructor.
     * @param string $name
     * @param CacheHandler $cacheHandler
     * @param string $suffix
     */
    public function __construct(
        CacheHandler $cacheHandler,
        string $name,
        string $suffix = ''
    ) {
        $this->cacheHandler = $cacheHandler;
        $this->name = $name;
        $this->suffix = $suffix;
    }

    /**
     * @param $data
     * @param int|null $expiresIn
     */
    public function set($data, int $expiresIn = null): void
    {
        $this->cacheHandler->set($this->getName(), $data, $expiresIn);
    }

    /**
     *
     */
    public function delete(): void
    {
        $this->cacheHandler->delete($this->getName());
    }

    /**
     * @return mixed
     */
    public function get()
    {
        return $this->cacheHandler->get($this->getName());
    }

    /**
     * Add suffix to cache name
     * @return string
     */
    public function getName(): string
    {
        return $this->name . '_' . $this->suffix . '$$ShardCache';
    }
}
