<?php

declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * ReorganizeSurveyAndMeasureModel
 *
 * Schema side of the "5 assi invece di 10 pilastri" reorganization. This drops
 * the redundant grouping models after the data has been moved by the
 * ReorganizeSurveyMeasureModelCommand. STRICT ORDER per brand DB:
 *
 *   1) bin/cake reorganize_survey_measure_model   (data transform, idempotent)
 *   2) bin/cake migrations migrate                 (this migration, schema drops)
 *
 * Fully guarded (hasColumn/hasTable + dynamic FK name resolution) so it is
 * generic across brand DBs (5t, Brescia, RER and other brands). FK constraint names vary
 * per DB, so they are discovered via information_schema.KEY_COLUMN_USAGE.
 *
 * down() is a best-effort STRUCTURAL rollback only: it recreates the tables and
 * columns but CANNOT restore the moved data (section-questions, axis-measures,
 * re-sequenced weights and moved i18n rows are not reverted).
 */
class ReorganizeSurveyAndMeasureModel extends AbstractMigration
{
    /**
     * Apply the schema changes.
     *
     * @return void
     */
    public function up(): void
    {
        // 1) measures.weight (nullable integer): the data command fills the values.
        if (!$this->table('measures')->hasColumn('weight')) {
            $this->table('measures')
                ->addColumn('weight', 'integer', ['null' => true, 'default' => null])
                ->update();
        }

        // 2) Drop questions_surveys.section_id (FK if any, then column).
        $this->dropForeignKeysReferencing('questions_surveys', 'section_id');
        if ($this->table('questions_surveys')->hasColumn('section_id')) {
            $this->table('questions_surveys')->removeColumn('section_id')->update();
        }

        // 3) Drop questions.section_id (FK if any, then column).
        $this->dropForeignKeysReferencing('questions', 'section_id');
        if ($this->table('questions')->hasColumn('section_id')) {
            $this->table('questions')->removeColumn('section_id')->update();
        }

        // 4) Drop measures.pillar_id (FK if any, then column).
        // The MeasuresTable existsIn/belongsTo on Pillars was removed in B1 so
        // saves won't throw once the column is gone.
        $this->dropForeignKeysReferencing('measures', 'pillar_id');
        if ($this->table('measures')->hasColumn('pillar_id')) {
            $this->table('measures')->removeColumn('pillar_id')->update();
        }

        // 5) Drop the sections table — ONLY after the section_id columns are gone,
        // and only when the data has been migrated (safety guard).
        if ($this->hasTable('sections')) {
            if ($this->sectionsMigrated()) {
                $this->table('sections')->drop()->update();
            } else {
                throw new \RuntimeException(
                    'Refusing to drop `sections`: COUNT(questions type=section) < COUNT(sections). ' .
                    'Run `bin/cake reorganize_survey_measure_model` first.'
                );
            }
        }

        // 6) Drop the pillars table (after measures.pillar_id is gone).
        if ($this->hasTable('pillars')) {
            $this->table('pillars')->drop()->update();
        }
    }

    /**
     * Best-effort STRUCTURAL rollback. Recreates the tables/columns but does NOT
     * restore the moved data.
     *
     * @return void
     */
    public function down(): void
    {
        // Recreate sections table.
        if (!$this->hasTable('sections')) {
            $this->table('sections')
                ->addColumn('name', 'string', ['limit' => 255, 'null' => false])
                ->addColumn('weight', 'integer', ['default' => 0, 'null' => true])
                ->create();
        }

        // Recreate pillars table.
        if (!$this->hasTable('pillars')) {
            $this->table('pillars')
                ->addColumn('name', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('description', 'text', ['null' => true])
                ->create();
        }

        // Re-add questions.section_id (nullable, no FK to avoid orphan failures).
        if (!$this->table('questions')->hasColumn('section_id')) {
            $this->table('questions')
                ->addColumn('section_id', 'integer', ['null' => true, 'default' => null])
                ->addIndex(['section_id'])
                ->update();
        }

        // Re-add questions_surveys.section_id (nullable).
        if (!$this->table('questions_surveys')->hasColumn('section_id')) {
            $this->table('questions_surveys')
                ->addColumn('section_id', 'integer', ['null' => true, 'default' => null])
                ->update();
        }

        // Re-add measures.pillar_id (nullable).
        if (!$this->table('measures')->hasColumn('pillar_id')) {
            $this->table('measures')
                ->addColumn('pillar_id', 'integer', ['null' => true, 'default' => null])
                ->update();
        }

        // Remove measures.weight.
        if ($this->table('measures')->hasColumn('weight')) {
            $this->table('measures')->removeColumn('weight')->update();
        }
    }

    /**
     * Drop every foreign key on $table that includes $column, resolving the
     * constraint name dynamically (names vary per DB). Safe if none exist.
     *
     * @param string $table Table name.
     * @param string $column Column name.
     * @return void
     */
    private function dropForeignKeysReferencing(string $table, string $column): void
    {
        if (!$this->table($table)->hasColumn($column)) {
            return;
        }

        $rows = $this->fetchAll(
            'SELECT DISTINCT kcu.CONSTRAINT_NAME ' .
            'FROM information_schema.KEY_COLUMN_USAGE kcu ' .
            'WHERE kcu.TABLE_SCHEMA = DATABASE() ' .
            "AND kcu.TABLE_NAME = '" . $table . "' " .
            "AND kcu.COLUMN_NAME = '" . $column . "' " .
            'AND kcu.REFERENCED_TABLE_NAME IS NOT NULL'
        );

        foreach ($rows as $row) {
            $name = $row['CONSTRAINT_NAME'] ?? null;
            if ($name) {
                $this->execute('ALTER TABLE `' . $table . '` DROP FOREIGN KEY `' . $name . '`');
            }
        }
    }

    /**
     * Safety guard: only allow dropping `sections` when the data transform has
     * produced at least as many type='section' questions as there are sections.
     *
     * @return bool
     */
    private function sectionsMigrated(): bool
    {
        $sections = (int)($this->fetchRow('SELECT COUNT(*) AS c FROM sections')['c'] ?? 0);
        $sectionQuestions = (int)(
            $this->fetchRow("SELECT COUNT(*) AS c FROM questions WHERE type = 'section'")['c'] ?? 0
        );

        return $sectionQuestions >= $sections;
    }
}
