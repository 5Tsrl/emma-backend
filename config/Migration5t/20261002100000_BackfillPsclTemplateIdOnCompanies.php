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

/**
 * 5t platform only: backfills `companies.pscl_template_id` with the production
 * PSCL template 802 ("10 pilastri") and sets it as the column DEFAULT, so old
 * companies always get the first template.
 *
 * Guarded: if pscl.id 802 is not a version_tag='template' row in the DB being
 * migrated (dev/demo/test DBs), the migration is a no-op.
 */
class BackfillPsclTemplateIdOnCompanies extends AbstractMigration
{
    private const TEMPLATE_ID = 802;

    public function up(): void
    {
        $templateId = self::TEMPLATE_ID;
        $row = $this->fetchRow(
            "SELECT id FROM pscl WHERE version_tag = 'template' AND id = {$templateId}"
        );
        if (!is_array($row) || empty($row['id'])) {
            return;
        }

        $this->execute(
            "UPDATE companies SET pscl_template_id = {$templateId} WHERE pscl_template_id IS NULL"
        );
        $this->table('companies')
            ->changeColumn('pscl_template_id', 'integer', [
                'default' => $templateId,
                'null' => true,
                'signed' => true,
            ])
            ->update();
    }

    /**
     * Backfilled data is NOT reversible (original NULLs are not recorded);
     * only the column default is dropped back to NULL.
     */
    public function down(): void
    {
        $this->table('companies')
            ->changeColumn('pscl_template_id', 'integer', [
                'default' => null,
                'null' => true,
                'signed' => true,
            ])
            ->update();
    }
}
