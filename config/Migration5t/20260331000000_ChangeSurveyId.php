<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class ChangeSurveyId extends AbstractMigration
{
    public function up(): void
    {
        // Move all survey-question links from survey 2237 to survey 2230
        $this->execute("UPDATE questions_surveys SET survey_id = 2230 WHERE survey_id = 2237;");

        // Update the survey id from 2237 to 2230
        $this->execute("UPDATE surveys SET id = 2230 WHERE id = 2237;");

    }

    public function down(): void
    {
        // Revert all survey-question links from survey 2230 back to 2237
        $this->execute("UPDATE questions_surveys SET survey_id = 2237 WHERE survey_id = 2230;");

        // Revert: change id from 2230 back to 2237
        $this->execute("UPDATE surveys SET id = 2237 WHERE id = 2230;");
    }
}
