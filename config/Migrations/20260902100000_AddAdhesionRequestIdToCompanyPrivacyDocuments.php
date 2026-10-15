<?php

declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Consente di caricare il "Modulo trattamento dati personali dei dipendenti" già da
 * `in_review`, non solo da `approved` (vedi docs/dev-notes/rer-privacy-consent.md,
 * sezione "Upload da in_review"): `company_id` non esiste ancora a quel punto, quindi
 * il documento va agganciato temporaneamente alla richiesta di adesione stessa tramite
 * la nuova colonna `adhesion_request_id`, e ri-agganciato all'azienda al momento
 * dell'approvazione (AdhesionRequestsController::enable()).
 *
 * Invariante risultante: esattamente una tra `company_id` e `adhesion_request_id` è
 * valorizzata — `adhesion_request_id` valorizzata ⇔ documento in attesa di
 * approvazione; `company_id` valorizzata ⇔ documento definitivo. `company_id` diventa
 * quindi nullable (Step 2 aggiunge la regola "esattamente uno dei due" a livello ORM).
 *
 * `adhesion_request_id` è CASCADE: se la richiesta di adesione viene eliminata prima
 * di essere approvata (rifiutata e poi cancellata), il documento pendente non ha più
 * senso di esistere e va rimosso con lei. Fondamentale che venga NULLATA al momento
 * del backfill in enable() — altrimenti l'eliminazione futura di una VECCHIA richiesta
 * già approvata trascinerebbe con sé, in cascata, il documento REALE e ancora in uso
 * di un'azienda viva (vedi D4 nel piano/dev-note).
 *
 * Usa up()/down() espliciti: Phinx non sa invertire da solo un changeColumn dentro
 * change().
 */
class AddAdhesionRequestIdToCompanyPrivacyDocuments extends AbstractMigration
{
    /**
     * Up Method.
     *
     * @return void
     */
    public function up(): void
    {
        $table = $this->table('company_privacy_documents');
        $table->addColumn('adhesion_request_id', 'integer', [
            'null' => true,
            'default' => null,
            'after' => 'company_id',
        ]);
        $table->changeColumn('company_id', 'integer', [
            'null' => true,
            'default' => null,
        ]);
        $table->update();

        // MySQL consente nativamente più NULL in un indice unique: nessuna opzione
        // speciale a livello di DB, il rischio di "due righe pendenti confuse per un
        // duplicato" è solo a livello di validazione ORM (gestito nello Step 2).
        $table->addIndex(
            ['adhesion_request_id'],
            ['unique' => true, 'name' => 'company_privacy_documents_adhesion_request_id_unique']
        );
        $table->addForeignKey(
            'adhesion_request_id',
            'adhesion_requests',
            'id',
            ['delete' => 'CASCADE', 'update' => 'NO_ACTION']
        );
        $table->update();
    }

    /**
     * Down Method.
     *
     * @return void
     */
    public function down(): void
    {
        $table = $this->table('company_privacy_documents');
        $table->dropForeignKey('adhesion_request_id');
        $table->removeIndexByName('company_privacy_documents_adhesion_request_id_unique');
        $table->removeColumn('adhesion_request_id');
        // Fallisce se esistono righe ancora pendenti (company_id null): atteso, un
        // rollback di questa migrazione non ha senso finché esistono documenti in attesa.
        $table->changeColumn('company_id', 'integer', ['null' => false]);
        $table->update();
    }
}
