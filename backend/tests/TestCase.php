<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    private static ?\PDO $suiteLockConnection = null;

    protected function setUp(): void
    {
        $this->acquireTestDatabaseSuiteLock();

        parent::setUp();

        // Feature fixtures create tenant-scoped rows directly without HTTP
        // middleware. Run test data operations under the explicit platform
        // context so forced row level security accepts them; middleware
        // snapshot/restore re-establishes the exact request context on its
        // own, while RlsEnforcementTest verifies the database-level
        // isolation with the platform context deliberately disabled.
        if (config('database.default') === 'pgsql') {
            \Illuminate\Support\Facades\DB::statement("SELECT set_config('app.platform_admin', 'true', false)");
        }
    }

    /**
     * Serialize a whole test process against any other test process sharing
     * the same PostgreSQL test database.
     *
     * Two overlapping suite invocations used to race inside RefreshDatabase's
     * migrate:fresh (DROP ... CASCADE), producing PostgreSQL deadlocks,
     * "relation does not exist" cascades and interleaved fixture data. A
     * session-level advisory lock identified by the test database name and
     * held for the process lifetime makes a later invocation wait for the
     * earlier one to exit. The lock is acquired on a dedicated PDO connection
     * (not the recycled framework connection) and is released by PostgreSQL
     * itself when the owning connection closes at process exit, so stale
     * locks cannot outlive a crashed process.
     *
     * Runs before parent::setUp() so the lock is already held before the app
     * boots and RefreshDatabase performs any DDL.
     */
    private function acquireTestDatabaseSuiteLock(): void
    {
        if (self::$suiteLockConnection !== null) {
            return; // Already held by this process; re-acquiring is a no-op.
        }

        $driver = getenv('DB_CONNECTION');
        $database = getenv('DB_DATABASE');

        if ($driver !== 'pgsql' || $database === false || $database === '' || $database === ':memory:') {
            return;
        }

        try {
            $dsn = sprintf(
                'pgsql:host=%s;port=%d;dbname=%s',
                getenv('DB_HOST') ?: '127.0.0.1',
                (int) (getenv('DB_PORT') ?: 5432),
                $database
            );

            $lock = new \PDO(
                $dsn,
                (string) (getenv('DB_USERNAME') ?: ''),
                (string) (getenv('DB_PASSWORD') ?: ''),
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );

            $statement = $lock->prepare('select pg_advisory_lock(hashtext(?))');
            $statement->execute(['erp-cloud-test-bootstrap|'.$database]);

            self::$suiteLockConnection = $lock;
        } catch (\Throwable) {
            // The lock is a reliability hardening measure only; a test
            // environment whose credentials are unavailable must proceed
            // exactly as it did before this guard existed.
        }
    }
}
