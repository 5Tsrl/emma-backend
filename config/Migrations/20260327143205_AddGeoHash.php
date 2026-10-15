<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class AddGeoHash extends AbstractMigration
{
    public function up(): void
    {
        $this->table('origins')
            ->addColumn('geohash', 'string', [
                'limit' => 16,
                'null' => true,
                'default' => null,
                'after' => 'lon',
            ])
            ->update();

        $this->execute(
            'UPDATE origins SET geohash = ST_GeoHash(lon, lat, 12)'
            . ' WHERE lat IS NOT NULL AND lon IS NOT NULL'
            . ' AND lat BETWEEN -90 AND 90'
            . ' AND lon BETWEEN -180 AND 180'
        );
    }

    public function down(): void
    {
        $this->table('origins')
            ->removeColumn('geohash')
            ->update();
    }
}
