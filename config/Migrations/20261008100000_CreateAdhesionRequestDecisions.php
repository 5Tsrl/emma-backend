<?php

declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Storico dei voti dei valutatori di una richiesta di adesione (#538).
 *
 * Dal #538 i due valutatori (admin / moma_area) possono CAMBIARE il proprio voto finché la
 * richiesta è `in_review`: le colonne `admin_*`/`moma_*` di `adhesion_requests` conservano
 * il voto CORRENTE, questa tabella accoda una riga per ogni voto nuovo o modificato
 * (chi, quando, esito, motivazione). Solo schema: nessun backfill dei dati esistenti.
 *
 * Vedi docs/dev-notes/adesione-flow.md (sezione "Round 5 (#538)").
 */
class CreateAdhesionRequestDecisions extends AbstractMigration
{
    /**
     * Up Method.
     *
     * @return void
     */
    public function up(): void
    {
        $this->table('adhesion_request_decisions')
            ->addColumn('adhesion_request_id', 'integer', ['null' => false])
            ->addColumn('actor', 'string', ['limit' => 10, 'null' => false])
            ->addColumn('decision', 'string', ['limit' => 10, 'null' => false])
            ->addColumn('reason', 'text', ['null' => true, 'default' => null])
            ->addColumn('decided_by', 'string', ['limit' => 36, 'null' => true, 'default' => null])
            ->addColumn('decided_at', 'datetime', ['null' => false])
            ->addIndex(['adhesion_request_id'], ['name' => 'idx_adhesion_request_decisions_request'])
            ->create();
    }

    /**
     * Down Method.
     *
     * @return void
     */
    public function down(): void
    {
        $this->table('adhesion_request_decisions')->drop()->save();
    }
}
