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
 * @author    Massimo INFUNTI - https://github.com/impronta48
 * @license   https://eupl.eu/1.2/it/ EUPL-1.2 license
 */
//</editor-fold>

use Migrations\AbstractMigration;

/**
 * Adds a non-unique BTREE index on origins.geohash.
 *
 * geohash is the canonical coordinate column for origins after the
 * 20260508100000_CleanupOriginsLatLon migration; prefix lookups
 * (`geohash LIKE 'u0nd%'`) and equality joins on it are now hot paths and
 * deserve an index. Estimated build time on ~134k rows is well under a
 * minute.
 */
class AddGeohashIndexToOrigins extends AbstractMigration
{
    /**
     * Add a non-unique BTREE index on origins.geohash.
     *
     * @return void
     */
    public function up(): void
    {
        if (!$this->hasTable('origins')) {
            return;
        }

        $table = $this->table('origins');
        if (!$table->hasIndex(['geohash'])) {
            $table->addIndex(['geohash'], ['name' => 'idx_origins_geohash']);
            $table->update();
        }
    }

    /**
     * Drop the idx_origins_geohash index.
     *
     * @return void
     */
    public function down(): void
    {
        if (!$this->hasTable('origins')) {
            return;
        }

        $table = $this->table('origins');
        if ($table->hasIndex(['geohash'])) {
            $table->removeIndexByName('idx_origins_geohash');
            $table->update();
        }
    }
}
