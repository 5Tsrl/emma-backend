<?php

declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Adds non-unique BTREE indexes on `companies.type` and `timetables.office_id`.
 *
 * `OfficesController::index()` (`GET /offices.json`) filters on
 * `companies.type` (via the `company_type` query param) and eager-loads
 * `Timetables` for every matched office, which resolves to `WHERE
 * timetables.office_id IN (...)`. Neither column had an index. `EXPLAIN`
 * against `5t_moma_angelcake` showed a full table scan on `companies`
 * (`type=ALL, key=null, rows=748`) plus `Using temporary; Using filesort`
 * from the `ORDER BY company_id, offices.name` clause, and a full table
 * scan on `timetables` (`type=ALL, key=null, rows=1594`). Harmless on a
 * small dataset, but this shape degrades non-linearly as both tables grow
 * (927 offices currently match `company_type=4` alone, each pulling in an
 * unbounded `Timetables` hasMany).
 *
 * `timetables.office_id` gets a plain index, not a foreign key:
 * `SELECT COUNT(*) FROM timetables t LEFT JOIN offices o ON
 * t.office_id=o.id WHERE o.id IS NULL` against `5t_moma_angelcake` returns
 * **43** orphaned rows (historical data whose `office_id` doesn't match any
 * real office), so an FK constraint here would fail / require a data
 * cleanup out of scope for this fix — same rationale as the precedent
 * `idx_offices_company_id` migration.
 */
class AddIndexCompaniesTypeAndTimetablesOfficeId extends AbstractMigration
{
    /**
     * Add non-unique BTREE indexes on `companies.type` and `timetables.office_id`.
     *
     * @return void
     */
    public function up(): void
    {
        if ($this->hasTable('companies')) {
            $table = $this->table('companies');
            if (!$table->hasIndex(['type'])) {
                $table->addIndex(['type'], ['name' => 'idx_companies_type']);
                $table->update();
            }
        }

        if ($this->hasTable('timetables')) {
            $table = $this->table('timetables');
            if (!$table->hasIndex(['office_id'])) {
                $table->addIndex(['office_id'], ['name' => 'idx_timetables_office_id']);
                $table->update();
            }
        }
    }

    /**
     * Drop the idx_companies_type and idx_timetables_office_id indexes.
     *
     * @return void
     */
    public function down(): void
    {
        if ($this->hasTable('companies')) {
            $table = $this->table('companies');
            if ($table->hasIndex(['type'])) {
                $table->removeIndexByName('idx_companies_type');
                $table->update();
            }
        }

        if ($this->hasTable('timetables')) {
            $table = $this->table('timetables');
            if ($table->hasIndex(['office_id'])) {
                $table->removeIndexByName('idx_timetables_office_id');
                $table->update();
            }
        }
    }
}
