<?php

declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * AddMomaOptionControlToQuestionsSurveys
 *
 * Per-survey/per-question admin grant letting a `moma` user hide individual
 * canonical answer options at render time (never delete/reorder the
 * canonical option list, only hide options).
 *
 *   - `moma_can_edit_options`: admin grant, defaults to false/0. Only ever
 *     toggled via `SurveysController::toggleQuestionOptionsPermission()`.
 *   - `disabled_options`: JSON list of canonical option INDEXES a moma user
 *     has chosen to disable. For `type='array'` questions this is an object
 *     keyed by group index (e.g. `{"0":[2],"1":[0,4]}`); for `single`/
 *     `multiple` it is a flat array (e.g. `[1,3]`). Only ever written via
 *     `SurveysController::setQuestionDisabledOptions()`.
 *
 * Fully guarded (hasColumn) so it is safe to re-run/idempotent, matching the
 * style of `20260619160000_ReorganizeSurveyAndMeasureModel.php`.
 */
class AddMomaOptionControlToQuestionsSurveys extends AbstractMigration
{
    /**
     * Apply the schema changes.
     *
     * @return void
     */
    public function up(): void
    {
        $table = $this->table('questions_surveys');

        if (!$table->hasColumn('moma_can_edit_options')) {
            $table->addColumn('moma_can_edit_options', 'boolean', [
                'default' => false,
                'null' => false,
                'after' => 'compulsory_answer',
                'comment' => 'Admin grant: allows a moma user to hide (never delete) ' .
                    'canonical answer options for this question in this survey',
            ]);
        }

        if (!$table->hasColumn('disabled_options')) {
            $table->addColumn('disabled_options', 'json', [
                'default' => null,
                'null' => true,
                'after' => 'moma_can_edit_options',
                'comment' => 'Canonical option indexes hidden by moma at render time; ' .
                    'flat array for single/multiple, {group_index: [indexes]} for array',
            ]);
        }

        $table->update();
    }

    /**
     * Revert the schema changes.
     *
     * @return void
     */
    public function down(): void
    {
        $table = $this->table('questions_surveys');

        if ($table->hasColumn('disabled_options')) {
            $table->removeColumn('disabled_options');
        }

        if ($table->hasColumn('moma_can_edit_options')) {
            $table->removeColumn('moma_can_edit_options');
        }

        $table->update();
    }
}
