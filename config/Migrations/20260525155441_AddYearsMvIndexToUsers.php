<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class AddYearsMvIndexToUsers extends AbstractMigration
{
    public function up(): void
    {
        if (!$this->hasTable('users')) {
            return;
        }

        // Operational opt-out for resource-constrained servers.
        // Set SKIP_MV_INDEX_YEARS=1 in the env if a previous attempt crashed
        // MySQL (e.g. emma_brescia 2026-05-28: MySQL killed during the index
        // sort/insert on the large users table). The migration is then a
        // safe no-op; create the index manually later in a maintenance
        // window — see the comment block at the bottom of this file.
        if ($this->shouldSkip()) {
            $this->logSkip('SKIP_MV_INDEX_YEARS=1 set in env. Skipping idx_users_years_mv.');
            return;
        }

        // Engine guard: multi-valued JSON indexes need MySQL >= 8.0.17 and do
        // not exist on MariaDB at any version. Skip with a notice on engines
        // that can't build the index — the JSON_CONTAINS predicate still
        // works, just without index acceleration.
        if (!$this->engineSupportsMultiValuedIndex()) {
            $this->logSkip('Engine does not support multi-valued JSON indexes. Skipping idx_users_years_mv.');
            return;
        }

        // Skip if the index already exists (idempotent re-runs).
        if ($this->indexExists()) {
            return;
        }

        // Long-running DDL on large tables can exceed the connection's
        // wait_timeout and trigger "MySQL server has gone away" (SQLSTATE
        // [HY000] 2006). Bump session-level timeouts so the index build
        // can complete. These are connection-scoped and reset when the
        // migration disconnects.
        //
        // CAVEAT: these bumps only protect against client-side idle
        // timeouts. If the server itself crashes mid-build (typically
        // OOM on a low-memory host or insufficient innodb_buffer_pool_size),
        // no SET SESSION saves you. In that case, set SKIP_MV_INDEX_YEARS=1
        // and tune the server before retrying.
        $this->bumpSessionTimeouts();

        // Pre-flight row count so the operator sees what they're committing to.
        $row = $this->fetchRow('SELECT COUNT(*) AS c FROM users');
        $count = (int)($row['c'] ?? 0);
        $this->logSkip(sprintf(
            'Building idx_users_years_mv on %d users rows. Large tables can take minutes.',
            $count
        ));

        // Multi-valued JSON index (MySQL 8.0.17+) over the elements of
        // users.years. CAST AS CHAR(10) ARRAY is deliberately a string cast
        // (not UNSIGNED) because:
        //   - 382 rows store [""] (empty string year)
        //   - 1 row stores ["TUTTI"] (5-char non-numeric)
        //   - another brand DB has 7,173 multi-year users that this index
        //     covers correctly (a generated primary_year column would only
        //     capture years[0], silently dropping the others as data
        //     accumulates).
        // CHAR(10) gives headroom over the 4-char year and the 5-char
        // "TUTTI" sentinel.
        //
        // ALGORITHM=COPY, LOCK=SHARED: Ubuntu's mysql-server-8.0.45
        // (and likely nearby 8.0.x builds) has a SIGSEGV in
        // ddl::Key_sort_buffer::deep_copy during the parallel online DDL
        // builder when constructing a multi-valued JSON index — confirmed
        // on emma_brescia, identical crash on direct mysql shell. COPY
        // rebuilds the table side-by-side instead, avoiding the buggy
        // parallel builder. LOCK=SHARED keeps reads available during the
        // rebuild. Slightly slower than INPLACE on large tables but safe
        // across the affected MySQL builds; on the largest target (~226k
        // users in 5t_moma_angelcake) the rebuild still completes in
        // seconds. Operators who confirm a fixed MySQL build can switch
        // to ALGORITHM=INPLACE.
        $this->execute(
            "ALTER TABLE users
             ADD INDEX idx_users_years_mv ((CAST(years AS CHAR(10) ARRAY))),
             ALGORITHM=COPY, LOCK=SHARED"
        );
    }

    public function down(): void
    {
        if (!$this->hasTable('users')) {
            return;
        }

        if ($this->shouldSkip()) {
            return;
        }

        if (!$this->engineSupportsMultiValuedIndex()) {
            return;
        }

        if (!$this->indexExists()) {
            return;
        }

        $this->bumpSessionTimeouts();
        $this->execute('DROP INDEX `idx_users_years_mv` ON `users`');
    }

    private function shouldSkip(): bool
    {
        $val = getenv('SKIP_MV_INDEX_YEARS');
        if ($val === false || $val === '') {
            return false;
        }
        return !in_array(strtolower($val), ['0', 'false', 'no', 'off'], true);
    }

    private function engineSupportsMultiValuedIndex(): bool
    {
        $row = $this->fetchRow('SELECT VERSION() AS v');
        $version = (string)($row['v'] ?? '');
        if (stripos($version, 'mariadb') !== false) {
            return false;
        }
        $numeric = preg_replace('/[^\d.].*$/', '', $version);
        return version_compare($numeric, '8.0.17', '>=');
    }

    private function indexExists(): bool
    {
        $row = $this->fetchRow(
            "SELECT COUNT(*) AS c
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'users'
               AND INDEX_NAME = 'idx_users_years_mv'"
        );
        return (int)($row['c'] ?? 0) > 0;
    }

    private function bumpSessionTimeouts(): void
    {
        $this->execute('SET SESSION wait_timeout = 28800');
        $this->execute('SET SESSION interactive_timeout = 28800');
        $this->execute('SET SESSION net_write_timeout = 600');
        $this->execute('SET SESSION net_read_timeout = 600');
        $this->execute('SET SESSION lock_wait_timeout = 31536000');
    }

    private function logSkip(string $msg): void
    {
        $out = $this->getOutput();
        if ($out !== null) {
            $out->writeln('<info>[AddYearsMvIndexToUsers] ' . $msg . '</info>');
        }
    }
}

/*
 * MANUAL INDEX CREATION (after SKIP_MV_INDEX_YEARS opt-out)
 * =========================================================
 *
 * If you ran this migration with SKIP_MV_INDEX_YEARS=1 (or it was skipped by
 * the engine guard), you can install the index by hand later in a maintenance
 * window. Steps:
 *
 *   1. Verify MySQL has enough resources for the build:
 *        - innodb_buffer_pool_size sized for the users table
 *        - tmp_table_size and max_heap_table_size raised if many multi-year rows
 *        - sort_buffer_size raised for the index sort phase
 *      A crash during the previous attempt is almost always memory exhaustion.
 *
 *   2. Open a mysql shell with elevated session settings:
 *        SET SESSION wait_timeout = 28800;
 *        SET SESSION interactive_timeout = 28800;
 *        SET SESSION net_write_timeout = 600;
 *        SET SESSION net_read_timeout = 600;
 *        SET SESSION lock_wait_timeout = 31536000;
 *
 *   3. Build the index using ALGORITHM=COPY (avoids the parallel-DDL crash
 *      seen on mysql-server-8.0.45-0ubuntu0.24.04.1):
 *        ALTER TABLE users
 *          ADD INDEX idx_users_years_mv ((CAST(years AS CHAR(10) ARRAY))),
 *          ALGORITHM=COPY, LOCK=SHARED;
 *
 *      If your MySQL build is known good (e.g. >= 8.0.46 or 8.4), you can
 *      use the faster online form instead:
 *        CREATE INDEX idx_users_years_mv
 *          ON users ((CAST(years AS CHAR(10) ARRAY)));
 *
 *   4. Verify:
 *        SHOW INDEX FROM users WHERE Key_name = 'idx_users_years_mv';
 *        EXPLAIN SELECT id FROM users WHERE JSON_CONTAINS(years, '"2025"');
 *      The EXPLAIN should show key = idx_users_years_mv (or MEMBER OF in 8.0.30+).
 */
