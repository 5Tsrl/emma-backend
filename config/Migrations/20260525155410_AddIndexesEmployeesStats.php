<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class AddIndexesEmployeesStats extends AbstractMigration
{
    public function up(): void
    {
        if (!$this->hasTable('employees')) {
            return;
        }

        // Long-running DDL on large tables can exceed the connection's wait_timeout
        // and trigger "MySQL server has gone away" (SQLSTATE[HY000] 2006).
        // Bump session-level timeouts so the index build can complete. These are
        // connection-scoped and reset when the migration disconnects.
        $this->bumpSessionTimeouts();

        $table = $this->table('employees');

        $indexes = [
            'idx_employees_office' => ['office_id'],
            'idx_employees_user' => ['user_id'],
            'idx_employees_office_user' => ['office_id', 'user_id'],
            'idx_employees_office_gender' => ['office_id', 'gender'],
            'idx_employees_office_orario' => ['office_id', 'orario'],
            'idx_employees_office_role' => ['office_id', 'role_description'],
            'idx_employees_office_origin' => ['office_id', 'origin_id'],
            'idx_employees_office_dob' => ['office_id', 'dob'],
        ];

        $changed = false;
        foreach ($indexes as $name => $cols) {
            if (!$table->hasIndex($cols)) {
                $table->addIndex($cols, ['name' => $name]);
                $changed = true;
            }
        }

        if ($changed) {
            $table->update();
        }
    }

    public function down(): void
    {
        if (!$this->hasTable('employees')) {
            return;
        }

        $this->bumpSessionTimeouts();

        // Drop by index name (not column list) so the rollback always targets
        // the exact indexes this migration created, even if a later migration
        // added something with the same column list.
        $indexNames = [
            'idx_employees_office',
            'idx_employees_user',
            'idx_employees_office_user',
            'idx_employees_office_gender',
            'idx_employees_office_orario',
            'idx_employees_office_role',
            'idx_employees_office_origin',
            'idx_employees_office_dob',
        ];

        foreach ($indexNames as $name) {
            $row = $this->fetchRow(
                "SELECT COUNT(*) AS c FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'employees'
                   AND INDEX_NAME = '" . $name . "'"
            );
            if ((int)($row['c'] ?? 0) > 0) {
                $this->execute("DROP INDEX `$name` ON `employees`");
            }
        }
    }

    private function bumpSessionTimeouts(): void
    {
        $this->execute('SET SESSION wait_timeout = 28800');         // 8 h connection idle
        $this->execute('SET SESSION interactive_timeout = 28800');  // 8 h client idle
        $this->execute('SET SESSION net_write_timeout = 600');      // 10 min between writes
        $this->execute('SET SESSION net_read_timeout = 600');       // 10 min between reads
        $this->execute('SET SESSION lock_wait_timeout = 31536000'); // metadata-lock wait
    }
}
