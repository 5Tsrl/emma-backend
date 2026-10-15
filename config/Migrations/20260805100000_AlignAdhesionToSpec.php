<?php

declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Allinea il modulo di adesione alla specifica funzionale ITL/RER
 * ("Flusso di registrazione e abilitazione delle organizzazioni", v0.1 del 1/7/2026).
 *
 * Tre scostamenti strutturali da colmare:
 *  1. doppia approvazione INDIPENDENTE (un admin E un moma_area) invece della singola;
 *  2. elenco di N persone da abilitare invece del singolo mobility manager;
 *  3. PDF di riepilogo da firmare digitalmente e ricaricare, con stato di attesa.
 *
 * Le colonne `status`/`processed_by`/`processed_at`/`reject_reason` NON vengono rimosse:
 * diventano campi derivati (valorizzati dalla decisione che *finalizza* la richiesta), così
 * il backoffice esistente e i test e2e già verdi continuano a funzionare senza modifiche.
 */
class AlignAdhesionToSpec extends AbstractMigration
{
    /**
     * Up.
     *
     * @return void
     */
    public function up(): void
    {
        $table = $this->table('adhesion_requests');

        // --- Step 1: tipo organizzazione -------------------------------------------
        // Mappa su company_types.survey_template ('azienda'|'scuola'), già seminato in
        // config/Seeds/CompanyTypesSeed.php: nessuna nuova tassonomia introdotta.
        if (!$table->hasColumn('organization_type')) {
            $table->addColumn('organization_type', 'string', [
                'limit' => 20,
                'default' => 'azienda',
                'null' => false,
                'comment' => 'scuola | azienda (specifica §5)',
            ]);
        }
        if (!$table->hasColumn('company_type_id')) {
            $table->addColumn('company_type_id', 'integer', [
                'default' => null,
                'null' => true,
                'comment' => 'FK logica a company_types, usata alla creazione azienda',
            ]);
        }

        // --- Step 2: PDF di riepilogo + firma digitale ------------------------------
        if (!$table->hasColumn('pdf_generated_at')) {
            $table->addColumn('pdf_generated_at', 'datetime', ['default' => null, 'null' => true]);
        }
        if (!$table->hasColumn('signed_pdf_filename')) {
            $table->addColumn('signed_pdf_filename', 'string', [
                'limit' => 255, 'default' => null, 'null' => true,
            ]);
        }
        if (!$table->hasColumn('signed_pdf_size')) {
            $table->addColumn('signed_pdf_size', 'integer', ['default' => null, 'null' => true]);
        }
        if (!$table->hasColumn('signed_pdf_mime')) {
            $table->addColumn('signed_pdf_mime', 'string', [
                'limit' => 100, 'default' => null, 'null' => true,
            ]);
        }
        if (!$table->hasColumn('signature_format')) {
            $table->addColumn('signature_format', 'string', [
                'limit' => 10, 'default' => null, 'null' => true,
                'comment' => 'pades (.pdf) | cades (.p7m)',
            ]);
        }
        if (!$table->hasColumn('signed_pdf_uploaded_at')) {
            $table->addColumn('signed_pdf_uploaded_at', 'datetime', ['default' => null, 'null' => true]);
        }

        // --- Accesso del referente anonimo -----------------------------------------
        // Il referente che compila il form NON ha un account: per tornare a caricare il
        // PDF firmato riceve via email un link con questo token monouso.
        if (!$table->hasColumn('access_token')) {
            $table->addColumn('access_token', 'string', [
                'limit' => 64, 'default' => null, 'null' => true,
            ]);
        }
        if (!$table->hasColumn('access_token_expires')) {
            $table->addColumn('access_token_expires', 'datetime', ['default' => null, 'null' => true]);
        }

        // --- Step 3: doppia approvazione indipendente -------------------------------
        foreach (['admin', 'moma'] as $actor) {
            if (!$table->hasColumn($actor . '_decision')) {
                $table->addColumn($actor . '_decision', 'string', [
                    'limit' => 10, 'default' => null, 'null' => true,
                    'comment' => 'approved | rejected (null = non ancora deciso)',
                ]);
            }
            if (!$table->hasColumn($actor . '_decided_by')) {
                $table->addColumn($actor . '_decided_by', 'char', [
                    'limit' => 36, 'default' => null, 'null' => true,
                ]);
            }
            if (!$table->hasColumn($actor . '_decided_at')) {
                $table->addColumn($actor . '_decided_at', 'datetime', ['default' => null, 'null' => true]);
            }
            if (!$table->hasColumn($actor . '_reason')) {
                $table->addColumn($actor . '_reason', 'text', ['default' => null, 'null' => true]);
            }
        }

        // --- Fase 3: esito della validazione territoriale ---------------------------
        // Se il comune indicato non combacia con nessuna `areas`, nessun moma_area vedrà
        // la richiesta: con la doppia approvazione obbligatoria resterebbe bloccata per
        // sempre. Non si rifiuta l'invio (sarebbe una lacuna di configurazione a carico
        // del cittadino), ma lo si rende visibile in backoffice.
        if (!$table->hasColumn('territory_unmatched')) {
            $table->addColumn('territory_unmatched', 'boolean', [
                'default' => false,
                'null' => false,
            ]);
        }

        $table->update();

        // Indici (aggiunti dopo l'update così le colonne esistono già).
        if (!$table->hasIndex(['access_token'])) {
            $table->addIndex(['access_token'])->update();
        }

        // --- Nuova tabella: persone da abilitare -----------------------------------
        // Tabella figlia e non JSON dentro `data` perché su queste righe si fanno query:
        // validazione duplicati contro `users`, creazione di N utenti in transazione e
        // soprattutto il legame people.user_id -> users.id necessario all'undo di delete().
        if (!$this->hasTable('adhesion_request_people')) {
            $people = $this->table('adhesion_request_people');
            $people->addColumn('adhesion_request_id', 'integer', ['null' => false]);
            $people->addColumn('first_name', 'string', ['limit' => 255, 'null' => false]);
            $people->addColumn('last_name', 'string', ['limit' => 255, 'null' => false]);
            $people->addColumn('email', 'string', ['limit' => 255, 'null' => false]);
            $people->addColumn('cf', 'string', ['limit' => 16, 'null' => false]);
            // Popolato solo alla doppia approvazione, quando l'utente viene creato.
            $people->addColumn('user_id', 'char', ['limit' => 36, 'default' => null, 'null' => true]);
            $people->addColumn('created', 'datetime', ['default' => null, 'null' => true]);
            $people->addColumn('modified', 'datetime', ['default' => null, 'null' => true]);

            $people->addIndex(['adhesion_request_id']);
            $people->addIndex(['user_id']);
            $people->addIndex(['adhesion_request_id', 'email'], ['unique' => true]);
            $people->addIndex(['adhesion_request_id', 'cf'], ['unique' => true]);
            $people->addForeignKey(
                'adhesion_request_id',
                'adhesion_requests',
                'id',
                ['delete' => 'CASCADE', 'update' => 'NO_ACTION']
            );
            $people->create();
        }

        // --- Rimappatura degli stati ------------------------------------------------
        // 'pending' non esiste più: diventa 'in_review' (in valutazione). Sono solo dati
        // di sviluppo — ADHESION_ENABLED è false di default e attivo solo su rerfe — quindi
        // non c'è rischio in produzione.
        $this->execute("UPDATE adhesion_requests SET status = 'in_review' WHERE status = 'pending'");
    }

    /**
     * Down.
     *
     * @return void
     */
    public function down(): void
    {
        $this->execute("UPDATE adhesion_requests SET status = 'pending' WHERE status IN ('in_review', 'pdf_pending')");

        if ($this->hasTable('adhesion_request_people')) {
            $this->table('adhesion_request_people')->drop()->update();
        }

        $table = $this->table('adhesion_requests');
        $columns = [
            'organization_type', 'company_type_id',
            'pdf_generated_at', 'signed_pdf_filename', 'signed_pdf_size', 'signed_pdf_mime',
            'signature_format', 'signed_pdf_uploaded_at',
            'access_token', 'access_token_expires',
            'admin_decision', 'admin_decided_by', 'admin_decided_at', 'admin_reason',
            'moma_decision', 'moma_decided_by', 'moma_decided_at', 'moma_reason',
            'territory_unmatched',
        ];
        foreach ($columns as $column) {
            if ($table->hasColumn($column)) {
                $table->removeColumn($column);
            }
        }
        $table->update();
    }
}
