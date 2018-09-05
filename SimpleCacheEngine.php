<?php
/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link          https://cakephp.org CakePHP(tm) Project
 * @since         3.7.0
 * @license       https://opensource.org/licenses/mit-license.php MIT License
 */
namespace Cake\Cache;

use Psr\SimpleCache\CacheInterface;

/**
 * Wrapper for Cake engines that allow them to support
 * the PSR16 Simple Cache Interface
 *
 * @since 3.7.0
 * @link https://www.php-fig.org/psr/psr-16/
 */
class SimpleCacheEngine implements CacheInterface
{
    /**
     * The wrapped cache engine object.
     *
     * @param \Cake\Cache\CacheEngine
     */
    protected $inner;

    /**
     * Constructor
     *
     * @param \Cake\Cache\CacheEngine $inner The decorated engine.
     */
    public function __construct($inner)
    {
        $this->inner = $inner;
    }

    /**
     * Check key for validity.
     *
     * @param string $key Key to check.
     * @return void
     * @throws \Psr\SimpleCache\InvalidArgumentException when key is not valid.
     */
    protected function checkKey($key)
    {
        if (!is_string($key) || strlen($key) === 0) {
            throw new InvalidArgumentException('Cache keys must be non-empty strings.');
        }
    }

    /**
     * Fetches a value from the cache.
     *
     * @param string $key The unique key of this item in the cache.
     * @param mixed $default Default value to return if the key does not exist.
     * @return mixed The value of the item from the cache, or $default in case of cache miss.
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if the $key string is not a legal value.
     */
    public function get($key, $default = null)
    {
        $this->checkKey($key);
        $result = $this->inner->read($key);
        if ($result === false) {
            return $default;
        }

        return $result;
    }

    /**
     * Persists data in the cache, uniquely referenced by a key with an optional expiration TTL time.
     *
     * @param string $key The key of the item to store.
     * @param mixed $value The value of the item to store, must be serializable.
     * @param null|int|\DateInterval $ttl Optional. The TTL value of this item. If no value is sent and
     *   the driver supports TTL then the library may set a default value
     *   for it or let the driver take care of that.
     * @return bool True on success and false on failure.
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if the $key string is not a legal value.
     */
    public function set($key, $value, $ttl = null)
    {
        $this->checkKey($key);
        if ($ttl !== null) {
            $restore = $this->inner->getConfig('duration');
            $this->inner->setConfig('duration', $ttl);
        }
        try {
            $result = $this->inner->write($key, $value);

            return (bool)$result;
        } finally {
            if (isset($restore)) {
                $this->inner->setConfig('duration', $restore);
            }
        }
    }

    /**
     * Delete an item from the cache by its unique key.
     *
     * @param string $key The unique cache key of the item to delete.
     * @return bool True if the item was successfully removed. False if there was an error.
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if the $key string is not a legal value.
     */
    public function delete($key)
    {
        $this->checkKey($key);

        return $this->inner->delete($key);
    }

    /**
     * Wipes clean the entire cache's keys.
     *
     * @return bool True on success and false on failure.
     */
    public function clear()
    {
        return $this->inner->clear(false);
    }

    /**
     * Obtains multiple cache items by their unique keys.
     *
     * @param iterable $keys A list of keys that can obtained in a single operation.
     * @param mixed $default Default value to return for keys that do not exist.
     * @return iterable A list of key => value pairs. Cache keys that do not exist or are stale will have $default as value.
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if $keys is neither an array nor a Traversable,
     *   or if any of the $keys are not a legal value.
     */
    public function getMultiple($keys, $default = null)
    {
        $results = $this->inner->readMany($keys);
        foreach ($results as $key => $value) {
            if ($value === false) {
                $results[$key] = $default;
            }
        }

        return $results;
    }

    /**
     * Persists a set of key => value pairs in the cache, with an optional TTL.
     *
     * @param iterable $values A list of key => value pairs for a multiple-set operation.
     * @param null|int|\DateInterval $ttl Optional. The TTL value of this item. If no value is sent and
     *   the driver supports TTL then the library may set a default value
     *   for it or let the driver take care of that.
     * @return bool True on success and false on failure.
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if $values is neither an array nor a Traversable,
     *   or if any of the $values are not a legal value.
     */
    public function setMultiple($values, $ttl = null)
    {
        if ($ttl !== null) {
            $restore = $this->inner->getConfig('duration');
            $this->inner->setConfig('duration', $ttl);
        }
        try {
            return $this->inner->writeMany($values);
        } finally {
            if (isset($restore)) {
                $this->inner->setConfig('duration', $restore);
            }
        }
    }

    /**
     * Deletes multiple cache items in a single operation.
     *
     * @param iterable $keys A list of string-based keys to be deleted.
     * @return bool True if the items were successfully removed. False if there was an error.
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if $keys is neither an array nor a Traversable,
     *   or if any of the $keys are not a legal value.
     */
    public function deleteMultiple($keys)
    {
        $result = $this->inner->deleteMany($keys);
        foreach ($result as $key => $success) {
            if ($success === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determines whether an item is present in the cache.
     *
     * NOTE: It is recommended that has() is only to be used for cache warming type purposes
     * and not to be used within your live applications operations for get/set, as this method
     * is subject to a race condition where your has() will return true and immediately after,
     * another script can remove it making the state of your app out of date.
     *
     * @param string $key The cache item key.
     * @return bool
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if the $key string is not a legal value.
     */
    public function has($key)
    {
        return $this->get($key) !== null;
    }
}
