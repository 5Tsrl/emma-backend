<?php

declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Aggiunge a `adhesion_requests` la colonna `survey_template_id` (int, nullable): il
 * questionario template (`surveys.version_tag = 'template'`) scelto da uno dei due
 * valutatori. Alla doppia approvazione, se l'azienda è stata CREATA dalla richiesta,
 * `AdhesionRequestsController::enable()` ne clona un questionario per l'anno corrente.
 *
 * Indice semplice, NIENTE foreign key: un template eliminato dopo la scelta non deve
 * bloccare la cancellazione del questionario né la richiesta (l'approvazione fallirebbe
 * con un 422 chiaro, riprovabile senza template) — vedi docs/dev-notes/adesione-flow.md.
 */
class AddSurveyTemplateIdToAdhesionRequests extends AbstractMigration
{
    /**
     * Up Method.
     *
     * @return void
     */
    public function up(): void
    {
        $table = $this->table('adhesion_requests');
        $table->addColumn('survey_template_id', 'integer', [
            'null' => true,
            'default' => null,
            'after' => 'company_created',
        ]);
        $table->addIndex(['survey_template_id'], ['name' => 'idx_adhesion_requests_survey_template']);
        $table->update();
    }

    /**
     * Down Method.
     *
     * @return void
     */
    public function down(): void
    {
        $table = $this->table('adhesion_requests');
        if ($table->hasIndexByName('idx_adhesion_requests_survey_template')) {
            $table->removeIndexByName('idx_adhesion_requests_survey_template');
        }
        if ($table->hasColumn('survey_template_id')) {
            $table->removeColumn('survey_template_id');
        }
        $table->update();
    }
}
