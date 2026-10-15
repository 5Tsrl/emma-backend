<?php

declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Requisito 3 della compliance privacy RER "EMMA RE-R" (vedi
 * docs/dev-notes/rer-privacy-consent.md): conferma di lettura del "Modulo trattamento
 * dati personali dei dipendenti" da parte del singolo partecipante.
 *
 * `survey_participants` (non `answers`) è la tabella corretta: è l'unica riga già
 * esistente per singolo partecipante+survey PRIMA che qualunque risposta venga
 * salvata (creata da SurveyParticipantsTable::add()/addAnonParticipant()), quindi è
 * l'unico posto a cui agganciare il flag quando SurveysController::completed() lo
 * riceve. `answers` è invece UNA RIGA PER DOMANDA per partecipante: un ack "per
 * risposta" lì richiederebbe una riga dedicata a una domanda inesistente o una
 * duplicazione su ogni riga, nessuna delle due sensata per un flag once-per-fill.
 */
class AddPrivacyDocAckToSurveyParticipants extends AbstractMigration
{
    /**
     * Up.
     *
     * @return void
     */
    public function up(): void
    {
        $table = $this->table('survey_participants');

        if (!$table->hasColumn('privacy_doc_ack')) {
            $table->addColumn('privacy_doc_ack', 'boolean', [
                'default' => false,
                'null' => false,
            ]);
        }
        if (!$table->hasColumn('privacy_doc_ack_date')) {
            $table->addColumn('privacy_doc_ack_date', 'datetime', [
                'default' => null,
                'null' => true,
            ]);
        }

        $table->update();
    }

    /**
     * Down.
     *
     * @return void
     */
    public function down(): void
    {
        $table = $this->table('survey_participants');
        foreach (['privacy_doc_ack', 'privacy_doc_ack_date'] as $column) {
            if ($table->hasColumn($column)) {
                $table->removeColumn($column);
            }
        }
        $table->update();
    }
}
