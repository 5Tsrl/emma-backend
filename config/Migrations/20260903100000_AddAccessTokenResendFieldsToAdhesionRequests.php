<?php

declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Aggiunge a `adhesion_requests` le colonne di audit/cooldown per la rigenerazione del
 * link di adesione (vedi docs/dev-notes/rer-privacy-consent.md, sezione "Rigenerazione
 * del token"):
 *
 *  - `access_token_resent_at`: ancora del cooldown anti-doppio-click, valorizzata SOLO
 *    dopo un invio riuscito (un tentativo il cui invio email fallisce non deve bloccare
 *    un retry immediato).
 *  - `access_token_resend_count`: contatore di audit, incrementato ad ogni invio
 *    riuscito, self-service o da backoffice.
 *  - `access_token_resent_by`: NULL per un resend self-service (referente col link),
 *    valorizzato con l'id dell'utente di backoffice che ha forzato la rigenerazione.
 *    Stesso tipo/stile delle colonne `admin_decided_by`/`moma_decided_by` già esistenti
 *    su questa tabella (char(36), lo stesso formato degli id utente UUID).
 */
class AddAccessTokenResendFieldsToAdhesionRequests extends AbstractMigration
{
    /**
     * Up Method.
     *
     * @return void
     */
    public function up(): void
    {
        $table = $this->table('adhesion_requests');
        $table->addColumn('access_token_resent_at', 'datetime', [
            'null' => true,
            'default' => null,
            'after' => 'access_token_expires',
        ]);
        $table->addColumn('access_token_resend_count', 'integer', [
            'null' => false,
            'default' => 0,
            'after' => 'access_token_resent_at',
        ]);
        $table->addColumn('access_token_resent_by', 'char', [
            'limit' => 36,
            'null' => true,
            'default' => null,
            'after' => 'access_token_resend_count',
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
        foreach (['access_token_resent_at', 'access_token_resend_count', 'access_token_resent_by'] as $column) {
            if ($table->hasColumn($column)) {
                $table->removeColumn($column);
            }
        }
        $table->update();
    }
}
