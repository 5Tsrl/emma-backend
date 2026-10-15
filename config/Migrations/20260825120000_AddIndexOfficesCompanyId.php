<?php

declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Adds a non-unique BTREE index on `offices.company_id`.
 *
 * `offices` had no index at all on `company_id` other than the implicit
 * scan via `PRIMARY` (`id`), despite it being the join column for
 * `CompaniesController::list()` (`LEFT JOIN offices ON offices.company_id
 * = companies.id`) and essentially every company-scoped query in the app.
 * `EXPLAIN` against `demo_5t_moma_angelcake` showed a full table scan on
 * `offices` (`type=ALL, key=null`) plus `Using temporary; Using filesort`
 * from the `list()` query's `GROUP BY`/`ORDER BY`. Harmless on the small
 * demo dataset, but this shape degrades non-linearly as `offices` grows.
 *
 * A plain index, not a foreign key: `demo_5t_moma_angelcake` has 13
 * `offices` rows whose `company_id` doesn't match any `companies.id`
 * (orphan rows from historical data), so adding an FK constraint here
 * would fail / require a data cleanup out of scope for this fix.
 */
class AddIndexOfficesCompanyId extends AbstractMigration
{
    /**
     * Add a non-unique BTREE index on `offices.company_id`.
     *
     * @return void
     */
    public function up(): void
    {
        if (!$this->hasTable('offices')) {
            return;
        }

        $table = $this->table('offices');
        if (!$table->hasIndex(['company_id'])) {
            $table->addIndex(['company_id'], ['name' => 'idx_offices_company_id']);
            $table->update();
        }
    }

    /**
     * Drop the idx_offices_company_id index.
     *
     * @return void
     */
    public function down(): void
    {
        if (!$this->hasTable('offices')) {
            return;
        }

        $table = $this->table('offices');
        if ($table->hasIndex(['company_id'])) {
            $table->removeIndexByName('idx_offices_company_id');
            $table->update();
        }
    }
}
