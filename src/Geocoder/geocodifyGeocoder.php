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
use Cake\Core\Configure;
use Cake\Http\Client;
use Cake\Log\Log;
use Exception;

class geocodifyGeocoder extends Geocoder
{
    //Metto un geocoder di default se non l'hanno definito

    public function __construct()
    {
        parent::__construct();
        if (empty($this->url)) {
            $this->url = 'https://api.geocodify.com/v2/geocode?';
        }
    }

    public function geocode($origin, $focusLat = null, $focusLon = null)
    {
        try {
            $q = $this->composeAddress($origin);

            $coords = $this->geocodifyGeoCode($q);
            if (isset($coords['lat']) && isset($coords['lon'])) {
                return $coords;
            } else {
                //Se non ha geocodificato l'indirizzo geocodifico il cap / città
                $q = trim("{$origin['city']} {$origin['postal_code']}");
                $coords = Cache::read($q);
                if ($coords === null) {
                    $coords = $this->geocodifyGeoCode($q);
                    Cache::write($q, $coords);
                }

                return $coords;
            }
        } catch (Exception $e) {
            Log::write('debug', 'Geocode->> Geocoding failed http error');

            return false;
        }
    }

    private function geocodifyGeoCode($address)
    {
        $http = new Client();
        $origin = [];

        $this->throttler(Configure::read('Geocoding.Throttle'), 1);
        $response = $http->get("{$this->url}&q=$address");

        if ($response->isOk()) {
            $res = $response->getJson();
            if (isset($res['response']) && !empty($res['response']['features'])) {
                $origin['lon'] = $res['response']['features'][0]['geometry']['coordinates'][0];
                $origin['lat'] = $res['response']['features'][0]['geometry']['coordinates'][1];

                //Se possibile aggiorno anche il codice postale che mi serve per l'esportazione ufficiale
                if (isset($res['response']['features'][0]['geometry']['properties']['postcode'])) {
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
