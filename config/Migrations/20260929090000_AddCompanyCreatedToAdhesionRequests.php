<?php

declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Aggiunge a `adhesion_requests` la colonna `company_created` (bool, nullable), che
 * traccia se `AdhesionRequestsController::enable()` ha CREATO una nuova azienda per la
 * richiesta (`true`) o ha riusato un'azienda esistente scelta dal valutatore tramite
 * `$bodyCompanyId` (`false`). Serve a `delete()` per decidere se una richiesta approvata
 * può eseguire la cascata completa di eliminazione (solo `company_created === true` E
 * nessun'altra `adhesion_requests` che referenzia la stessa azienda) — vedi
 * docs/dev-notes/adesione-flow.md.
 *
 * NIENTE BACKFILL: le righe esistenti restano NULL e ricadono sul comportamento legacy
 * (`companyIsClean()`), per non rischiare di marcare come "creata da noi" un'azienda
 * reale con dati propri (es. #58 / company 27, che ha 6 sedi).
 */
class AddCompanyCreatedToAdhesionRequests extends AbstractMigration
{
    /**
     * Up Method.
     *
     * @return void
     */
    public function up(): void
    {
        $table = $this->table('adhesion_requests');
        $table->addColumn('company_created', 'boolean', [
            'null' => true,
            'default' => null,
            'after' => 'company_id',
        ]);
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
        if ($table->hasColumn('company_created')) {
            $table->removeColumn('company_created');
        }
        $table->update();
    }
}
