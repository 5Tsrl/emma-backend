<?php

declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Crea la tabella `adhesion_requests` per il "Modulo di adesione EMMA RE-R".
 *
 * Scelta di design (JSON-driven): l'insieme dei campi del form varia per tema e
 * cambierà nel tempo, quindi la tabella NON ha una colonna per ogni campo. L'intera
 * submission tematizzata vive nella colonna `data` (json), così aggiungere/rinominare/
 * rimuovere campi in qualsiasi tema non richiede una migrazione. Solo i pochi campi
 * su cui il backend interroga, fa join o opera vengono promossi a colonne reali
 * (denormalizzate).
 */
class CreateAdhesionRequests extends AbstractMigration
{
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     *
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('adhesion_requests');

        // Stato della richiesta: pending/approved/rejected. Stringa (convenzione del
        // repo, niente ENUM MySQL), indicizzata per filtrare la lista di backoffice.
        $table->addColumn('status', 'string', [
            'limit' => 20,
            'default' => 'pending',
            'null' => false,
        ]);

        // Brand/tema da cui proviene la submission: permette al backoffice di
        // renderizzare il set di campi corretto e registra la versione del contratto.
        $table->addColumn('theme', 'string', [
            'limit' => 50,
            'default' => null,
            'null' => true,
        ]);

        // Denormalizzato da `data` per il territory matching (larghezza come
        // companies.city varchar(45)).
        $table->addColumn('reference_city', 'string', [
            'limit' => 45,
            'default' => null,
            'null' => true,
        ]);

        // Denormalizzato da `data` per il territory matching (come areas.province).
        $table->addColumn('reference_province', 'string', [
            'limit' => 2,
            'default' => null,
            'null' => true,
        ]);

        // Intera submission del form: tutti i campi tematici (rappresentante legale,
        // denominazione/forma giuridica/sede/CF/P.IVA/registro imprese/ATECO/PEC/
        // email/telefoni azienda, cognome/nome/CF/email/telefono MM, district_companies,
        // dichiarazioni accept_*, luogo, ...). La variazione per tema è gestita qui.
        $table->addColumn('data', 'json', [
            'null' => false,
        ]);

        // Audit / collegamenti.
        $table->addColumn('ip', 'string', [
            'limit' => 45,
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('user_id', 'char', [
            'limit' => 36,
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('company_id', 'integer', [
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('reject_reason', 'text', [
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('processed_by', 'char', [
            'limit' => 36,
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('processed_at', 'datetime', [
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('created', 'datetime', [
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('modified', 'datetime', [
            'default' => null,
            'null' => true,
        ]);

        $table->addIndex(['status']);
        $table->addIndex(['reference_city']);
        $table->addIndex(['reference_province']);
        $table->addIndex(['user_id']);
        $table->addIndex(['company_id']);

        $table->create();
    }
}
