<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Aggiunge `surveys.office_id` (nullable, indicizzato).
 *
 * Necessario per modellare i questionari dinamici Azienda/Scuola/Uni scope
 * 'sede' (una survey per sede) in modo omogeneo alle survey scope 'generale'
 * (office_id NULL, una per azienda) — stessa convenzione già usata da
 * `company_survey_history.office_id`. Vedi SurveysTable::findOrCreateCompanyQuestionnaire()
 * e docs/dev-notes/company-questionnaires-dynamic-conversion.md.
 *
 * Additiva e retrocompatibile: le survey esistenti (questionari "normali")
 * restano con office_id NULL, nessun comportamento cambia.
 */
class AddOfficeIdToSurveys extends AbstractMigration
{
    /**
     * Up Method.
     *
     * @return void
     */
    public function up(): void
    {
        $this->table('surveys')
            ->addColumn('office_id', 'integer', [
                'after' => 'company_id',
                'default' => null,
                'null' => true,
                'signed' => true,
            ])
            ->addIndex(['office_id'])
            ->update();
    }

    /**
     * Down Method.
     *
     * @return void
     */
    public function down(): void
    {
        $this->table('surveys')
            ->removeIndex(['office_id'])
            ->removeColumn('office_id')
            ->update();
    }
}
