<?php


namespace Linzbauer\ShardCache\Exceptions;


use Exception;
use Linzbauer\ShardCache\CacheHandler;
use Linzbauer\ShardCache\ShardCache;

/**
 * Class DuplicateCache
 * @package Linzbauer\ShardCache\Exceptions
 */
final class DuplicateCache extends Exception
{
    protected $message =
        "A ShardCache instance with the same name and prefix has already " .
        "been created. Please pass the existing instance around instead of " .
        "creating a new reference to the same cache store to ensure " .
        "referential equality between identical objects loaded from cache.";
}
