<?php

return ['default' => env('CACHE_STORE', 'redis'), 'stores' => ['array' => ['driver' => 'array', 'serialize' => false], 'redis' => ['driver' => 'redis', 'connection' => env('REDIS_CACHE_CONNECTION', 'cache'), 'lock_connection' => env('REDIS_CACHE_LOCK_CONNECTION', 'default')]], 'prefix' => env('CACHE_PREFIX', 'enterprise_erp_cache')];
