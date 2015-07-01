<?php

namespace Lewestopher\RateLimiter;

class RateLimiter
{
    /**
     * The namespace to construct cache keys on for rate limiting.
     *
     * Allows you to define multiple rate limiters for your API without crowding
     * your Cache namespace.
     *
     * @var string
     */
    private $namespace;

    /**
     * Contains the Caching engine used to store keys relevant to rate limited
     * requests.  Constructed based on CacheInterface.php.
     *
     * @var object
     */
    private $cacheEngine;

    /**
     * The limit key to construct your cache key based on.
     *
     * Your limit key is customizable for the needs of your application.  Some
     * might find that they need to rate limit by IP, in which case the use of
     * $_SERVER['REMOTE_ADDR'] would be deemed acceptable.  Others might need
     * to limit requests on a user by user basis, in which case a User ID would
     * be deemed acceptable.
     *
     * @var string
     */
    private $limitKey;

    /*
     * Contains configuration options relevant to the RateLimiter and Cache
     * Engine.
     *
     * @var array
     */
    private $_config;

    private $_defaultConfig = [
        'className' => null,
        'namespace' => 'rate-limited-'
    ];

    public function __construct($limitKey, $config = [])
    {
        $this->limitKey = $namespace . $limitKey;
        $this->_config = array_merge($this->_defaultConfig, $config);
        // 2. Construct our instance of our CacheEngine and store it in $cacheEngine
        $this->cacheEngine = new $this->_config['className'];
        // 3. Check if our cache engine implements our interface with:
        // in_array('CacheInterface', class_implements($this->cacheEngine));
        if(!in_array('CacheInterface', class_implements($this->cacheEngine))) {
            throw new NotImplementedException;
        }
        // If not throw exception
    }

    public function limitRequests($allowedNumber, $minutes, $callable = null)
    {
        $requests = 0;
        // Before 1: call _incrementRequestCount() to increment our count of requests


        // 1. Get a list of all possible limit keys ranging from the current time
        // in the negative direction in 1 minute steps until we reach the max range of $minutes
        $possibleKeys = $this->_getLimitKeys($minutes);

        // 2. Iterate this list of possible minute keys, each time calling ->read
        // on our cache object to see if that key exists.  If it does, we take the
        // value of that key and add it to our $requestCount.
        foreach ($possibleKeys as $key) {
            $value = $this->cacheEngine->read($key);
            if ($value) {
                $requests += $value;
            }
            if ($requests > $allowedNumber) {
                ($callable) ? $callable() : throw new RateExceededException;
                break;
            }
        }

        $this->_incrementRequestCount();
        // 3. If $requestCount is ever greater than $allowedNumber, we break the loop
        // iteration and throw a RateExceededException.
    }

    protected function _getLimitKeys($minutes)
    {
        $keys = [];
        $now = time();

        for ($time = $now - $minutes * 60; $time <= $now; $time += 60) {
            $keys[] = $this->limitKey . date('dHi', $time);
        }

        return $keys;
    }

    protected function _incrementRequestCount()
    {
        // 2. Concatenate the formatted time string with our prefix stored by
        // the constructor function.
        $currentKey = $this->_getCurrentKey();

        // 3. Check to see if the newly made and concatenated key exists in our
        // cache.
        $currentExistingKey = $this->cacheEngine->read($currentKey);

        // 4a. If the key does not exist in cache, we Cache->write() a new cache
        // key with a value of 1.
        if($currentExistingKey) {
            $this->cacheEngine->increment($currentKey);
        } else {
            $this->cacheEngine->write($currentKey, 1);
        }

        // 4b. If the key does exist in cache, then we increment the cache key to
        // the next number.
    }

    protected function _getCurrentKey()
    {
        return $this->limitKey . date('dHi', time());
    }
}
