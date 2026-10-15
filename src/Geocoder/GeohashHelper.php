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
namespace App\Geocoder;

use Cake\Core\Configure;
use Sk\Geohash\Geohash;

/**
 * Shared geohash encode/decode helper.
 *
 * Encoding wraps `Sk\Geohash\Geohash::encode()` (the same library used by
 * `Geocoder::geocode()` and `OriginsTable::beforeSave()`), so callers don't
 * have to instantiate the underlying object or repeat the precision lookup.
 *
 * Decoding ports verbatim the algorithm previously inlined inside
 * `templates/Origins/kml/index.php`, providing a single reusable PHP-side
 * decoder. Whenever possible, prefer the MySQL `ST_PointFromGeoHash()` path
 * for query-level decoding (see `EmployeesTable::fetchCityHaversineRows()`).
 */
class GeohashHelper
{
    /**
     * Encode a (lat, lon) pair into a geohash string.
     *
     * Precision defaults to the value configured under `Geohash.precision`
     * (7 chars => ~150 m accuracy, which matches the rest of the codebase).
     *
     * @param float $lat Latitude in decimal degrees.
     * @param float $lon Longitude in decimal degrees.
     * @param int|null $precision Optional precision override.
     * @return string
     */
    public static function encode(float $lat, float $lon, ?int $precision = null): string
    {
        $precision = $precision ?? (int)Configure::read('Geohash.precision', 7);
        $h = new Geohash();

        return $h->encode($lat, $lon, $precision);
    }

    /**
     * Decode a geohash into its center point.
     *
     * Returns an associative array `['lat' => float, 'lon' => float]` or
     * `null` when the input is empty or contains invalid characters.
     *
     * @param string|null $geohash The geohash string to decode.
     * @return array|null
     */
    public static function decode(?string $geohash): ?array
    {
        if (empty($geohash)) {
            return null;
        }

        $base32 = '0123456789bcdefghjkmnpqrstuvwxyz';
        $lat = [-90.0, 90.0];
        $lon = [-180.0, 180.0];
        $isEvenBit = true;

        $geohash = strtolower(trim($geohash));
        foreach (str_split($geohash) as $char) {
            $charIndex = strpos($base32, $char);
            if ($charIndex === false) {
                return null;
            }

            foreach ([16, 8, 4, 2, 1] as $mask) {
                if ($isEvenBit) {
                    $mid = ($lon[0] + $lon[1]) / 2;
                    if (($charIndex & $mask) !== 0) {
                        $lon[0] = $mid;
                    } else {
                        $lon[1] = $mid;
                    }
                } else {
                    $mid = ($lat[0] + $lat[1]) / 2;
                    if (($charIndex & $mask) !== 0) {
                        $lat[0] = $mid;
                    } else {
                        $lat[1] = $mid;
                    }
                }
                $isEvenBit = !$isEvenBit;
            }
        }

        return [
            'lat' => ($lat[0] + $lat[1]) / 2,
            'lon' => ($lon[0] + $lon[1]) / 2,
        ];
    }
}
