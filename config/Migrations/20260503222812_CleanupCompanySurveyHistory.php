<?php

declare(strict_types=1);

use Migrations\AbstractMigration;

class CleanupCompanySurveyHistory extends AbstractMigration
{
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     * @return void
     */
    public function change()
    {
        
        //Prendo solo la riga più recente
        $this->execute("
            DELETE FROM company_survey_history
            WHERE id NOT IN (
                SELECT id FROM (
                    SELECT id,
                           ROW_NUMBER() OVER (
                               PARTITION BY company_id, type
                               ORDER BY created DESC, id DESC
                           ) AS rn
                    FROM company_survey_history
                ) ranked
                WHERE rn = 1
            )
        ");

        //Imposto l'anno in base alla data di creazione
        $this->execute("update  company_survey_history set year = year(created)");

        //Aggiungo office_id = primo office_id trovato per ogni company_id
        $this->execute("
            UPDATE company_survey_history csh
            JOIN (
               SELECT company_id, MIN(id) AS office_id
                FROM offices
                GROUP BY company_id
            ) AS sub ON csh.company_id = sub.company_id and ( csh.type='sede' or csh.type='label')
            SET csh.office_id = sub.office_id
        ");
    }
}
