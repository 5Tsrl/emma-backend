<?php

declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * Aggiunge a `users` la colonna `privacy_consent_ack_date` (vedi
 * docs/dev-notes/rer-privacy-consent.md): timestamp dell'ACK, una tantum per
 * utente, della modale di consenso privacy mostrata al login (RER). `NULL` =
 * mai confermato.
 *
 * Deliberatamente NON `users.tos_date`: `tos_date` è l'accettazione dei Termini
 * di Servizio in fase di registrazione, con chiamanti già esistenti nei plugin
 * Testcommuters/Abbonamenti che non vanno toccati. `privacy_consent_ack_date`
 * è un campo semanticamente distinto (ack una tantum della modale di consenso
 * privacy, non del ToS) scritto da `UsersController::acknowledgePrivacyConsent()`.
 */
class AddPrivacyConsentAckDateToUsers extends AbstractMigration
{
    /**
     * Up.
     *
     * @return void
     */
    public function up(): void
    {
        $table = $this->table('users');

        if (!$table->hasColumn('privacy_consent_ack_date')) {
            $table->addColumn('privacy_consent_ack_date', 'datetime', [
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
        $table = $this->table('users');
        if ($table->hasColumn('privacy_consent_ack_date')) {
            $table->removeColumn('privacy_consent_ack_date');
        }
        $table->update();
    }
}
