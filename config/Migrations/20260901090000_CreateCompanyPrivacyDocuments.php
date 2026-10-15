<?php

declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Crea `company_privacy_documents`: il "Modulo trattamento dati personali dei
 * dipendenti" firmato dal legale rappresentante dell'azienda — UNA riga per azienda,
 * riusata per tutte le survey/anni successivi (requisito 3 della compliance privacy
 * RER "EMMA RE-R", vedi docs/dev-notes/rer-privacy-consent.md).
 *
 * Storage fuori webroot (Service/CompanyPrivacyDocumentService::folderPath()), stesso
 * pattern di AdhesionDocumentService: il documento contiene dati personali e non deve
 * essere raggiungibile via URL diretto, solo tramite le azioni di upload/download
 * dedicate (AdhesionRequestsController::employeePrivacyDocument(),
 * SurveysController::employeePrivacyDocument()).
 *
 * Niente colonna `uploaded_by`: a differenza di quanto ipotizzato in fase di piano,
 * l'upload è autenticato SOLO dal token (mai da una sessione utente, vedi
 * AdhesionRequestsController::employeePrivacyDocument()) — esattamente come il PDF di
 * riepilogo firmato dell'adesione, che infatti non traccia un uploader (vedi
 * `signed_pdf_*` in adhesion_requests). Aggiungere una colonna sempre NULL per un
 * caso che il resto del modulo già gestisce senza sarebbe rumore, non un dato reale.
 */
class CreateCompanyPrivacyDocuments extends AbstractMigration
{
    /**
     * Change Method.
     *
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('company_privacy_documents');

        $table->addColumn('company_id', 'integer', [
            'null' => false,
        ]);
        $table->addColumn('filename', 'string', [
            'limit' => 255,
            'null' => false,
        ]);
        $table->addColumn('path', 'string', [
            'limit' => 500,
            'null' => false,
        ]);
        $table->addColumn('created', 'datetime', [
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('modified', 'datetime', [
            'default' => null,
            'null' => true,
        ]);

        // Un solo documento per azienda: la seconda POST alla stessa azienda AGGIORNA
        // questa riga (sostituzione), non ne crea una seconda.
        $table->addIndex(['company_id'], ['unique' => true]);
        $table->addForeignKey(
            'company_id',
            'companies',
            'id',
            ['delete' => 'CASCADE', 'update' => 'NO_ACTION']
        );

        $table->create();
    }
}
