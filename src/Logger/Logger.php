<?php


namespace Linzbauer\ShardCache\Logger;

/**
 * ShardCache-Compatible Logger Interface
 * @package Linzbauer\ShardCache\Logger
 */
interface Logger
{
    public function log(string $message): void;
}
