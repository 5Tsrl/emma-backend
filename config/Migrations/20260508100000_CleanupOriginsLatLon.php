<?php

/*
 * EMMA(tm) - Employer Mobility Management Application
 * Copyright (C) 2026 5T Torino / Regione Piemonte
 * Licensed under the EUPL-1.2 or later.
 */

declare(strict_types=1);

use Migrations\AbstractMigration;

class CleanupOriginsLatLon extends AbstractMigration
{
    /**
     * Up Method.
     *
     * 1. Verifies that every origins row with non-null lat/lon already has a
     *    valid geohash value.  Aborts if any such row is missing its geohash so
     *    that no positional data is silently lost.
     * 2. Sets the address column to NULL for all rows.
     * 3. Drops the lat and lon columns.
     *
     * @return void
     * @throws \RuntimeException when rows with lat/lon but without geohash are found.
     */
    public function up(): void
    {
        $this->execute(
            'UPDATE origins SET geohash = ST_GeoHash(lon, lat, 12)'
            . ' WHERE lat IS NOT NULL AND lon IS NOT NULL'
            . ' AND lat BETWEEN -90 AND 90'
            . ' AND lon BETWEEN -180 AND 180'
        );


/*       // Step 1 – integrity check: every geocoded row must already carry a geohash.
        $missing = (int)$this->fetchRow(
            'SELECT COUNT(*) AS cnt FROM origins'
            . ' WHERE lat IS NOT NULL AND lon IS NOT NULL'
            . ' AND (geohash IS NULL OR geohash = \'\')'
        )['cnt'];

        if ($missing > 0) {
            throw new \RuntimeException(
                sprintf(
                    'CleanupOriginsLatLon aborted: %d origins row(s) have lat/lon but no'
                    . ' geohash. Run the GeocodeOrigins command (or AddGeoHash migration)'
                    . ' to fill in geohash values before applying this migration.',
                    $missing
                )
            );
        } */

      // Step 2 – wipe the address column.
        $this->execute('UPDATE origins SET address = NULL');

      // Step 3 – drop lat and lon.
        $this->table('origins')
        ->removeColumn('lat')
        ->removeColumn('lon')
        ->update();
    }

    /**
     * Down Method.
     *
     * Restores the lat and lon columns (data cannot be recovered; they will be
     * NULL).  The address column is left NULL as well because its previous
     * values were discarded.
     *
     * @return void
     */
    public function down(): void
    {
        $this->table('origins')
        ->addColumn('lat', 'float', [
        'null' => true,
        'default' => null,
        'after' => 'district',
        ])
        ->addColumn('lon', 'float', [
        'null' => true,
        'default' => null,
        'after' => 'lat',
        ])
        ->update();
    }
}
