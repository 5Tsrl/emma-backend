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

use Cake\Cache\Cache;
use Cake\Http\Client;
use Cake\Log\Log;
use Exception;

class komootGeocoder extends Geocoder
{
    //Metto un geocoder di default se non l'hanno definito

    public function __construct()
    {
        parent::__construct();
        if (empty($this->url)) {
            $this->url = 'https://photon.komoot.io/api/';
        }
    }

    public function geocode($origin, $focusLat = null, $focusLon = null)
    {
        try {
            $q = $this->composeAddress($origin);

            $coords = $this->komootGeoCode($q, $focusLat, $focusLon);
            if (isset($coords['lat']) && isset($coords['lon'])) {
                return $coords;
            } else {
                //Se non ha geocodificato l'indirizzo geocodifico il cap / città
                $q = trim("{$origin['city']} {$origin['postal_code']}");
                $coords = Cache::read($q);
                if ($coords === null) {
                    $coords = $this->komootGeoCode($q, $focusLat, $focusLon);
                    Cache::write($q, $coords);
                }

                return $coords;
            }
        } catch (Exception $e) {
            Log::write('debug', 'Geocode->> Geocoding failed http error');

            return false;
        }
    }

    private function komootGeoCode($address, $focusLat = null, $focusLon = null)
    {
        $http = new Client();
        $origin = [];
        if (is_null($focusLat) && is_null($focusLon)) {
            $response = $http->get("{$this->url}?q=$address&limit=1");
        } else {
            $response = $http->get("{$this->url}?q=$address&lat=$focusLat&lon=$focusLon&limit=1");
        }

        if ($response->isOk()) {
            $res = $response->getJson();
            if (isset($res['features']) && !empty($res['features'])) {
                $origin['lon'] = $res['features'][0]['geometry']['coordinates'][0];
                $origin['lat'] = $res['features'][0]['geometry']['coordinates'][1];
                //Se possibile aggiorno anche il codice postale che mi serve per l'esportazione ufficiale
                if (isset($res['features'][0]['geometry']['properties']['postcode'])) {
                    $origin['postal_code'] = $res['features'][0]['geometry']['properties']['postcode'];
                    Log::write('debug', " Geocoding aggiunge il codice postale {$origin['postal_code']})");
                }
                Log::write('debug', "] Geocoding success $address ({$origin['lon']},{$origin['lat']})");
                //var_dump("Geocoding success ({$origin['lon']},{$origin['lat']})");
                return $origin;
            } else {
                Log::write('debug', "] Geocoding failed $address, empty features");
                //var_dump("Geocoding failed, empty features");
                throw new Exception("Geocoding $address failed - not found $address");
            }
        } else {
            Log::write('debug', "Geocoding failed $address http error");
            //var_dump("Geocoding failed http error");
            throw new Exception("Geocoding $address failed - http error");
        }
    }

    //Compose complete address starting from the single parts
    private function composeAddress($origin)
    {
        if (isset($origin['address'])) {
            $a = $origin['address'];
        } else {
            $a = '';
        }

        if (isset($origin['city'])) {
            $c = $origin['city'];
        } else {
            $c = '';
        }

        if (isset($origin['postal_code'])) {
            $p = $origin['postal_code'];
        } else {
            $p = '';
        }

        return "$a, $c $p";
    }
}
