<?php


namespace Linzbauer\ShardCache\Logger;

/**
 * NullLogger will silently drop log messages. Can be used as a fallback
 * logging solution if no real logger implementation is provided.
 * @package Linzbauer\ShardCache\Logger
 */
final class NullLogger implements Logger
{
    /**
     * Silently drop log message
     * @param string $message
     */
    public function log(string $message): void
    {
        return;
    }
}
