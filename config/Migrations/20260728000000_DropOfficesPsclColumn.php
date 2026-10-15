<?php

declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * DropOfficesPsclColumn
 *
 * Follow-up to the "Flat PSCL plan format" work: the legacy `offices.PSCL`
 * JSON column (used for the `year = 'TUTTI'` — "all years" — case) is dropped
 * once its content has been moved into a proper `pscl` row (`office_id`,
 * `year IS NULL`) by `bin/cake migrate_office_pscl_to_plan`. STRICT ORDER per
 * brand DB, same rationale as `ReorganizeSurveyAndMeasureModel` above:
 *
 *   1) bin/cake migrate_office_pscl_to_plan   (data transform, idempotent)
 *   2) bin/cake migrations migrate             (this migration, column drop)
 *
 * Refuses to drop the column if any office with a non-null `PSCL` has no
 * corresponding migrated `pscl` row yet (safety guard, same pattern as
 * `ReorganizeSurveyAndMeasureModel::sectionsMigrated()`).
 *
 * down() only re-adds the (now empty) column — it CANNOT restore the moved
 * JSON content, which now lives in `pscl.plan` rows instead.
 */
class DropOfficesPsclColumn extends AbstractMigration
{
    /**
     * @return void
     */
    public function up(): void
    {
        if (!$this->hasTable('offices') || !$this->table('offices')->hasColumn('PSCL')) {
            return;
        }

        if (!$this->officePsclMigrated()) {
            throw new \RuntimeException(
                'Refusing to drop `offices.PSCL`: some offices with a non-null PSCL have no ' .
                'matching migrated pscl row yet. Run `bin/cake migrate_office_pscl_to_plan` first.'
            );
        }

        $this->table('offices')->removeColumn('PSCL')->update();
    }

    /**
     * @return void
     */
    public function down(): void
    {
        if ($this->hasTable('offices') && !$this->table('offices')->hasColumn('PSCL')) {
            $this->table('offices')
                ->addColumn('PSCL', 'json', ['null' => true, 'default' => null])
                ->update();
        }
    }

    /**
     * Safety guard: every office with a non-null PSCL must have a matching
     * `pscl` row (`office_id`, `year IS NULL`) already in the flat format
     * (`plan[0].plan_format >= 2` — `plan` is stored as a JSON array, so
     * index 0 is `$[0]`, not `$."0"`).
     *
     * @return bool
     */
    private function officePsclMigrated(): bool
    {
        $officesWithPscl = (int)(
            $this->fetchRow('SELECT COUNT(*) AS c FROM offices WHERE PSCL IS NOT NULL')['c'] ?? 0
        );
        if ($officesWithPscl === 0) {
            return true;
        }

        $migrated = (int)($this->fetchRow(
            'SELECT COUNT(DISTINCT o.id) AS c ' .
            'FROM offices o ' .
            'JOIN pscl p ON p.office_id = o.id AND p.year IS NULL ' .
            "WHERE o.PSCL IS NOT NULL AND JSON_EXTRACT(p.plan, '$[0].plan_format') >= 2"
        )['c'] ?? 0);

        return $migrated >= $officesWithPscl;
    }
}
