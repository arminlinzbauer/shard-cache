<?php


namespace ShardCache\Logger;

/**
 * ShardCache-Compatible Logger Interface
 * @package ShardCache\Logger
 */
interface Logger
{
    public function log(string $message): void;
}
