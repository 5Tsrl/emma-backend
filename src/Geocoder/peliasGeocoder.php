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

class peliasGeocoder extends Geocoder
{
    //Metto un geocoder di default se non l'hanno definito

    public function __construct()
    {
        parent::__construct();
        if (empty($this->url)) {
            $this->url = 'https://geocode.muoversinpiemonte.it/v1/search';
        }
    }

    public function geocode($origin, $focusLat = null, $focusLon = null)
    {
        try {
            $coords = $this->peliasGeoCode($origin, $focusLat, $focusLon);
            if (isset($coords['lat']) && isset($coords['lon'])) {
                return $coords;
            } else {
                //Se non ha geocodificato l'indirizzo geocodifico il cap / città
                $q = trim("{$origin['city']} {$origin['postal_code']}");
                $coords = Cache::read($q);
                if ($coords === null) {
                    $coords = $this->peliasGeoCode($q, $focusLat, $focusLon);
                    Cache::write($q, $coords);
                }

                return $coords;
            }
        } catch (Exception $e) {
            Log::write('debug', 'Geocode->> Geocoding failed http error');

            return false;
        }
    }

    private function peliasGeoCode($origin, $focusLat = null, $focusLon = null, $structured = true)
    {
        $http = new Client();
        $q_st = [
            'country' => 'italy',
            'lang' => 'it',
            'size' => '1',];
        if ($structured) {
            $q_st['address'] = $origin['address'] ?? null;
            //$q_st['locality'] = $origin['city'] ?? null;
            $q_st['postalcode'] = $origin['postal_code'] ?? null;
            $q_st['region'] = $origin['province'] ?? null;
            $response = $http->get("{$this->url}/v1/search/structured?", $q_st);
        } else {
            $q = $this->composeAddress($origin);
            if (is_null($focusLat) && is_null($focusLon)) {
                $response = $http->get("{$this->url}/v1/search?text=$q&lang=it&size=1");
            } else {
                $response = $http->get("{$this->url}/v1/search?text=$q&focus.point.lat=$focusLat&focus.point.lon=$focusLon&lang=it&size=1");
            }
        }

        if ($response->isOk()) {
            $res = $response->getJson();
            if (isset($res['features']) && !empty($res['features'])) {
                $origin['lon'] = $res['features'][0]['geometry']['coordinates'][0];
                $origin['lat'] = $res['features'][0]['geometry']['coordinates'][1];

                //Se possibile aggiorno anche il codice postale che mi serve per l'esportazione ufficiale
                // if (isset($res['features'][0]['properties']['name'])){
                //     $origin['address'] = $res['features'][0]['properties']['name'];
                //     Log::write('debug', " Geocoding aggiunge il address {$origin['address']})");
                // }
                if (isset($res['features'][0]['properties']['postalcode'])) {
                    $origin['postal_code'] = $res['features'][0]['properties']['postalcode'];
                    Log::write('debug', " Geocoding aggiunge il codice postale {$origin['postal_code']})");
                }
                if (isset($res['features'][0]['properties']['locality'])) {
                    $origin['city'] = $res['features'][0]['properties']['locality'];
                    Log::write('debug', " Geocoding aggiunge la città {$origin['city']})");
                }
                if (isset($res['features'][0]['properties']['region_a'])) {
                    $origin['province'] = $res['features'][0]['properties']['region_a'];

                    Log::write('debug', " Geocoding aggiunge la provincia {$origin['province']})");
                }
                Log::write('debug', "] Geocoding success {$origin['address']} ({$origin['lon']},{$origin['lat']})");
                //var_dump("Geocoding success ({$origin['lon']},{$origin['lat']})");
                return $origin;
            } else {
                Log::write('debug', "] Geocoding failed {$origin['address']}, empty features");
                //var_dump("Geocoding failed, empty features");
                throw new Exception("Geocoding {$origin['address']} failed - not found {$origin['address']}");
            }
        } else {
            $code = $response->getStatusCode();
            Log::write('debug', "Geocoding failed {$origin['address']} http error $code\n");
            throw new Exception("Geocoding {$origin['address']} failed - http error $code\n");
        }
    }

    //Compose complete address starting from the single parts
    private function composeAddress($origin)
    {
        $res = '';
        if (isset($origin['address'])) {
            $a = $origin['address'];
            $res = "$a, ";
        } else {
            $a = '';
        }

        if (isset($origin['city'])) {
            $c = $origin['city'];
            $res .= "$c ";
        } else {
            $c = '';
        }

        if (isset($origin['postal_code'])) {
            $p = $origin['postal_code'];
            $res .= "$p";
        } else {
            $p = '';
        }

        return $res;
    }
}
