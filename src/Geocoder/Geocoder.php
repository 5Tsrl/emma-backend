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

use App\Model\Entity\Origin;
use Cake\Core\Configure;
use Sk\Geohash\Geohash;

//Classe base da cui ereditano i vari geocoder
abstract class Geocoder
{
    protected string|null $url;

    protected array $province = [];

    public function __construct()
    {
        $this->url = Configure::read('Geocoding.Url');
        require __DIR__ . '/province.php';
        $this->province = $province;
    }

    abstract public function geocodeNoHash(Origin $origin, $focusLat = null, $focusLon = null): array|false;

    public function geocode(Origin $origin, $focusLat = null, $focusLon = null): array | false
    {
        $coords = $this->geocodeNoHash($origin, $focusLat, $focusLon);
        if ($coords !== false) {
            $h = new Geohash();
            $coords['geohash'] = $h->encode($coords['lat'], $coords['lon'], Configure::read('Geohash.precision', 7));
        }

        return $coords;
    }

    protected function throttler(int $requestsInSeconds, int $throttlerID)
    {

        // Use FLOCK() to create a system global lock (it's crash-safe:))
        $fp = fopen(sys_get_temp_dir() . "/$throttlerID", 'w+');

        // exclusive lock will blocking wait until obtained
        if (flock($fp, LOCK_EX)) {
            // Sleep for a while (requestsInSeconds should be 1 or higher)
            $time_to_sleep = (int)(999999999 / $requestsInSeconds);
            time_nanosleep(0, $time_to_sleep);

            flock($fp, LOCK_UN); // unlock
        }

        fclose($fp);
    }
}
