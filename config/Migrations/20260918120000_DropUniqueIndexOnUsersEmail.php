<?php
declare(strict_types=1);

//<editor-fold desc="Preamble">
/**
 * EMMA(tm) : Electronic Mobility Management Applications
 * Copyright (c) 5T Torino, Regione Piemonte, Città Metropolitana di Torino
 *
 * SPDX-License-Identifier: EUPL-1.2
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) 5T - https://5t.torino.it
 * @link      https://emma.5t.torino.it
 * @license   https://eupl.eu/1.2/it/ EUPL-1.2 license
 */
//</editor-fold>

use Migrations\AbstractMigration;

class DropUniqueIndexOnUsersEmail extends AbstractMigration
{
    public function up(): void
    {
        // Drop the unique index on users.email
        $this->execute("ALTER TABLE users DROP INDEX UNQ_users_email");
    }

    public function down(): void
    {
        // Restore the unique index on users.email
        $this->execute("ALTER TABLE users ADD UNIQUE INDEX UNQ_users_email (email)");
    }
}
