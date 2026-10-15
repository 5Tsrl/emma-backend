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

namespace App\Model\Entity;

use App\Geocoder\GeohashHelper;
use Cake\Cache\Cache;
use Cake\ORM\Entity;

class Origin extends Entity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * Note that when '*' is set to true, this allows all unspecified fields to
     * be mass assigned. For security purposes, it is advised to set '*' to false
     * (or remove it), and explicitly make individual fields accessible as needed.
     *
     * @var array
     */
    protected $_accessible = [
        '*' => true,
    ];

    /**
     * Virtual fields exposed on serialization.
     *
     * `lat` and `lon` are decoded on the fly from the persisted `geohash`
     * column (the underlying lat/lon columns were dropped by the
     * 20260508100000_CleanupOriginsLatLon migration). This keeps non-Vue API
     * consumers working without changes — the frontend decodes geohash
     * client-side via the latlon-geohash package, but raw curl / SDK reads
     * still get the legacy keys.
     *
     * @var array
     */
    protected $_virtual = ['lat', 'lon'];

    /**
     * Virtual accessor: decode latitude from the stored geohash.
     *
     * If a transient `lat` value has been set on the entity (e.g. by a
     * client payload that still carries the legacy key) it wins, so that
     * `beforeSave()` can still encode the incoming value into geohash.
     * Otherwise the value is decoded from `$this->geohash`.
     *
     * @param mixed $value Raw stored value (transient if any).
     * @return float|null
     */
    protected function _getLat($value): ?float
    {
        if ($value !== null && $value !== '') {
            return (float)$value;
        }
        $decoded = GeohashHelper::decode($this->geohash ?? null);

        return $decoded === null ? null : $decoded['lat'];
    }

    /**
     * Virtual accessor: decode longitude from the stored geohash.
     *
     * @param mixed $value Raw stored value (transient if any).
     * @return float|null
     */
    protected function _getLon($value): ?float
    {
        if ($value !== null && $value !== '') {
            return (float)$value;
        }
        $decoded = GeohashHelper::decode($this->geohash ?? null);

        return $decoded === null ? null : $decoded['lon'];
    }

    //Riscrivo la getVisible per estrarre anche le colonne dei campi collegati
    //https://api.cakephp.org/4.0/trait-Cake.Datasource.EntityTrait.html#getVisible

    public function getVisible(): array
    {
        //TODO: Inserire una cache
        //TODO: trasformare in una chiamata ricorsiva
        $fields = parent::getVisible();
        foreach ($fields as $k => $f) {
            if ($this->$f instanceof Entity) {
                $subFields = $this->$f->getVisible();
                foreach ($subFields as $s) {
                    if ($this->$f->$s instanceof Entity) {
                        $subFields2 = $this->$f->$s->getVisible();
                        foreach ($subFields2 as $s2) {
                            $fields[] = "$f.$s.$s2";
                        }
                    } else {
                        $fields[] = "$f.$s";
                    }
                }
                unset($fields[$k]);
            }
        }

        return $fields;
    }

    //data una origin aggiunge il quartiere a cui appartiene usando il
    //KML contenuto in sitedir/quertieri.kml

    public function addDistrict(): void
    {
        // Virtual fields can be unreliable through isset(); rely on the
        // persisted geohash column (decoded via GeohashHelper) instead.
        $coords = GeohashHelper::decode($this->geohash ?? null);
        if ($coords !== null && !empty($this->city)) {
            $this->city = trim(strtolower($this->city));
            $quartieri = Cache::read('quartieri_' . strtolower($this->city));
            if (!$quartieri) {
                //$quartieri = $this->loadDistrictFromKML($this->city);
                $quartieri = $this->loadDistrictsFromGeoJSON($this->city);
                Cache::write('quartieri_' . strtolower($this->city), $quartieri);
            }
            $quartiere = $this->findDistrict($coords['lat'], $coords['lon'], $quartieri);
            $this->district = $quartiere;
        }
    }

    /**
     * Carica i quartieri dal KML
     */
    function loadDistrictFromKML($city = 'brescia')
    {
        $file = WWW_ROOT . 'districts/' . strtolower($city) . '.kml';
        if (!file_exists($file)) {
            throw new \Exception("File KML dei quartieri $city non trovato: $file");
        }
        $xml = simplexml_load_file($file);
        $quartieri = [];

        foreach ($xml->Document->Folder->Placemark as $placemark) {
            $nome = (string)$placemark->ExtendedData->SchemaData->SimpleData[3]; // QUARTIERE
            $coords = (string)$placemark->MultiGeometry->Polygon->outerBoundaryIs->LinearRing->coordinates;

            // parsing coordinate
            $points = [];
            $pairs = preg_split('/\s+/', trim($coords));
            foreach ($pairs as $pair) {
                [$lon, $lat] = explode(',', $pair);
                $points[] = [(float)$lon, (float)$lat];
            }

            $quartieri[] = [
                'nome' => $nome,
                'polygon' => $points,
            ];
        }

        return $quartieri;
    }

    /**
     * Carica i quartieri da un file GeoJSON
     */
    function loadDistrictsFromGeoJSON(string $city = 'brescia'): array
    {
        $file = WWW_ROOT . 'districts/' . strtolower($city) . '.geojson';
        if (!file_exists($file)) {
            throw new \Exception("File GeoJSON dei quartieri $city non trovato: $file");
        }
        $json = json_decode(file_get_contents($file), true);
        $quartieri = [];

        foreach ($json['features'] as $feature) {
            $props = $feature['properties'];
            $geom  = $feature['geometry'];

            if ($geom['type'] !== 'Polygon') {
                continue; // gestibile anche MultiPolygon se serve
            }

            // GeoJSON: coordinates = [ [ [lon,lat], [lon,lat], ... ] ]
            $polygon = $geom['coordinates'][0];

            $quartieri[] = [
                'id'      => $props['ID'],
                'nome'    => $props['QUARTIERE'],
                'polygon' => $polygon,
            ];
        }

        return $quartieri;
    }

    /**
     * Trova il quartiere dato un punto
     */
    function findDistrict($lat, $lon, $quartieri)
    {
        foreach ($quartieri as $q) {
            if ($this->pointInPolygon($lat, $lon, $q['polygon'])) {
                return $q['nome'];
            }
        }

        return null;
    }

    /**
     * Funzione point-in-polygon (algoritmo ray casting)
     */
    function pointInPolygon($lat, $lon, $polygon)
    {
        $inside = false;
        $n = count($polygon);
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            $xi = $polygon[$i][0]; // lon
            $yi = $polygon[$i][1]; // lat
            $xj = $polygon[$j][0];
            $yj = $polygon[$j][1];

            $intersect = (($yi > $lat) != ($yj > $lat)) &&
                ($lon < ($xj - $xi) * ($lat - $yi) / ($yj - $yi + 1e-12) + $xi);
            if ($intersect) {
                $inside = !$inside;
            }
        }

        return $inside;
    }
}
