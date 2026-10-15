<?php

declare(strict_types=1);

use Migrations\AbstractMigration;

class CleanupOrphanCompanySurveyHistory extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Elimina le righe di company_survey_history diventate orfane prima che
     * CompaniesTable/OfficesTable dichiarassero l'associazione hasMany
     * dependent verso questa tabella (le eliminazioni precedenti non
     * propagavano). Da qui in avanti la pulizia è automatica alla
     * eliminazione di un'azienda o di una sede.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     *
     * @return void
     */
    public function change()
    {
        // Righe la cui azienda non esiste più.
        $this->execute("
            DELETE csh FROM company_survey_history csh
            LEFT JOIN companies c ON c.id = csh.company_id
            WHERE c.id IS NULL
        ");

        // Righe di sede/aggregata la cui sede non esiste più (le righe
        // 'generale' hanno office_id NULL e non sono toccate).
        $this->execute("
            DELETE csh FROM company_survey_history csh
            LEFT JOIN offices o ON o.id = csh.office_id
            WHERE csh.office_id IS NOT NULL AND o.id IS NULL
        ");
    }
}
