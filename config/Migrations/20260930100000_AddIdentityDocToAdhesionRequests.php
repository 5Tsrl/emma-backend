<?php

declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Aggiunge a `adhesion_requests` i metadati del documento di identità FACOLTATIVO del
 * referente (bug 6): consigliato a chi non firma digitalmente. Il file vive nella cartella
 * privata della richiesta (`private_uploads/.../adesioni/{id}/identita_*`), mai sotto webroot.
 * Tutte le colonne sono nullable: NULL = nessun documento caricato. Nessun backfill.
 *
 * Vedi docs/dev-notes/adesione-flow.md (sezione "Documento di identità").
 */
class AddIdentityDocToAdhesionRequests extends AbstractMigration
{
    /**
     * Up Method.
     *
     * @return void
     */
    public function up(): void
    {
        $table = $this->table('adhesion_requests');
        $table->addColumn('identity_doc_filename', 'string', [
            'limit' => 255,
            'null' => true,
            'default' => null,
            'after' => 'signed_pdf_uploaded_at',
        ]);
        $table->addColumn('identity_doc_size', 'integer', [
            'null' => true,
            'default' => null,
            'after' => 'identity_doc_filename',
        ]);
        $table->addColumn('identity_doc_mime', 'string', [
            'limit' => 100,
            'null' => true,
            'default' => null,
            'after' => 'identity_doc_size',
        ]);
        $table->addColumn('identity_doc_uploaded_at', 'datetime', [
            'null' => true,
            'default' => null,
            'after' => 'identity_doc_mime',
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
        $columns = ['identity_doc_uploaded_at', 'identity_doc_mime', 'identity_doc_size', 'identity_doc_filename'];
        foreach ($columns as $column) {
            if ($table->hasColumn($column)) {
                $table->removeColumn($column);
            }
        }
        $table->update();
    }
}
