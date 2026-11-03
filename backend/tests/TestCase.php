<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Serialize the database bootstrap of a test process against any other
     * test process sharing the same PostgreSQL test database.
     *
     * Two overlapping suite invocations used to race inside RefreshDatabase's
     * migrate:fresh (DROP ... CASCADE) producing PostgreSQL deadlocks and
     * undefined-table cascades. A session-level advisory lock (identified by
     * the connection's database name, identical for every worker of the same
     * database) makes a later invocation politely wait for the earlier one to
     * finish instead of corrupting its schema. PostgreSQL releases session
     * advisory locks automatically when the owning connection closes at
     * process exit, so no explicit unlock is needed; the same session
     * re-acquiring it per test is a recursive no-op.
     */
    protected function refreshDatabase()
    {
        $connection = DB::connection();

        if ($connection->getDriverName() === 'pgsql') {
            $database = $connection->getDatabaseName();
            $connection->statement('select pg_advisory_lock(hashtext(?))', ['erp-cloud-test-bootstrap|'.$database]);
        }

        parent::refreshDatabase();

        // Held intentionally: unreleased until process exit so a queued suite
        // waits for the entire earlier suite, not merely its migration phase.
    }
}
