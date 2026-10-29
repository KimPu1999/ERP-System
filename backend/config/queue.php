<?php

return ['default' => env('QUEUE_CONNECTION', 'redis'), 'connections' => ['sync' => ['driver' => 'sync'], 'redis' => ['driver' => 'redis', 'connection' => 'default', 'queue' => env('REDIS_QUEUE', 'default'), 'retry_after' => 90, 'block_for' => null]], 'batching' => ['database' => env('DB_CONNECTION', 'pgsql'), 'table' => 'job_batches'], 'failed' => ['driver' => 'database-uuids', 'database' => env('DB_CONNECTION', 'pgsql'), 'table' => 'failed_jobs']];
