<?php

declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Aggiunge a `adhesion_requests` due colonne per la compliance privacy RER
 * "EMMA RE-R" (vedi docs/dev-notes/rer-privacy-consent.md):
 *
 *  - `accept_privacy_informativa`: presa visione dell'informativa privacy regionale al
 *    momento della compilazione del modulo di adesione (requisito 2a). Colonna reale
 *    (non una chiave dentro `data`, a differenza di accept_gdpr/accept_guidelines):
 *    scelta deliberata per renderla interrogabile/auditabile senza decodificare il json.
 *
 *  - `company_doc_token`: token PERMANENTE (mai azzerato) per il caricamento del
 *    "Modulo trattamento dati personali dei dipendenti" (requisito 3). Necessario
 *    perché `access_token` viene azzerato non appena il referente carica il PDF di
 *    riepilogo firmato (AdhesionRequestsController::uploadSigned(), Step 2) — MOLTO
 *    prima che la richiesta ottenga le due approvazioni indipendenti e quindi un
 *    `company_id` (AdhesionRequestsController::enable(), Step 4). Le due condizioni
 *    "token valido" e "company_id presente" non si sovrappongono MAI su
 *    `access_token`: un token permanente separato, popolato con lo STESSO valore già
 *    generato per `access_token` in submit(), è l'unico modo di onorare la scelta di
 *    prodotto "/adesione/completa/:token resta il punto di accesso permanente al
 *    documento aziendale" senza rompere l'invalidazione a uso singolo del token
 *    originale (che resta invariata per il suo scopo originale).
 */
class AddEmployeePrivacyDocFieldsToAdhesionRequests extends AbstractMigration
{
    /**
     * Up.
     *
     * @return void
     */
    public function up(): void
    {
        $table = $this->table('adhesion_requests');

        if (!$table->hasColumn('accept_privacy_informativa')) {
            $table->addColumn('accept_privacy_informativa', 'boolean', [
                'default' => false,
                'null' => false,
            ]);
        }
        if (!$table->hasColumn('company_doc_token')) {
            $table->addColumn('company_doc_token', 'string', [
                'limit' => 64, 'default' => null, 'null' => true,
            ]);
        }

        $table->update();

        if (!$table->hasIndex(['company_doc_token'])) {
            $table->addIndex(['company_doc_token'])->update();
        }
    }

    /**
     * Down.
     *
     * @return void
     */
    public function down(): void
    {
        $table = $this->table('adhesion_requests');
        foreach (['accept_privacy_informativa', 'company_doc_token'] as $column) {
            if ($table->hasColumn($column)) {
                $table->removeColumn($column);
            }
        }
        $table->update();
    }
}
