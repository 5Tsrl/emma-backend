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

class nominatimGeoCoder extends Geocoder
{
    //Metto un geocoder di default se non l'hanno definito

    public function __construct()
    {
        parent::__construct();
        if (empty($this->url)) {
            $this->url = 'https://nominatim.geocoding.ai/search';
        }
    }

    public function geocode($origin, $focusLat = null, $focusLon = null)
    {
        try {
            $coords = $this->nominatimGeoCode($origin);
            if (isset($coords['lat']) && isset($coords['lon'])) {
                return $coords;
            } else {
                //Se non ha geocodificato l'indirizzo geocodifico il cap / città
                $q = '';
                if (isset($origin['city'])) {
                    $q = trim($origin['city']) . ' ';
                }
                if (isset($origin['postal_code'])) {
                    $q .= trim($origin['postal_code']);
                }

                $coords = Cache::read($q);
                if (!isset($coords['lon']) || !isset($coords['lat']) || $coords === null || $coords['lon'] == -1 || $coords['lat'] == -1) {
                    $origin2 = $origin;
                    unset($origin2['address']);
                    $coords = $this->nominatimGeoCode($origin2);
                    Cache::write($q, $coords);
                }

                return $coords;
            }
        } catch (Exception $e) {
            Log::write('debug', 'Geocode->> Geocoding failed error' . $e->getMessage());

            return ['lat' => -1, 'lon' => -1];
        }
    }

    private function nominatimGeoCode($origin)
    {
        $http = new Client();
        $address = $this->composeAddress($origin);
        $qry = [
            'country' => 'IT',
            'limit' => 1,
            'format' => 'json',
            'accept-language' => 'it',
            'addressdetails' => 1,

        ];
        if (isset($origin['q'])) {
            $qry['q'] = $origin['q'];
        } else {
            if (isset($origin['address'])) {
                $qry['street'] = $origin['address'];
            }
            if (isset($origin['city'])) {
                $qry['city'] = $origin['city'];
            }
            if (isset($origin['postal_code'])) {
                $qry['postalcode'] = $origin['postal_code'];
            }
            if (isset($origin['province'])) {
                $qry['county'] = $origin['province'];
            }
            if (isset($qry['county']) && isset($this->province[$qry['county']])) {
                $qry['county'] = $this->province[$qry['county']];
            }
        }
        $this->throttler(Configure::read('Geocoding.Throttle'), 1);
        $response = $http->get($this->url, $qry);

        if ($response->isOk()) {
            $res = $response->getJson();
            if (is_array($res) && count($res) == 1) {
                $res = $res[0];
                $origin['lon'] = $res['lon'];
                $origin['lat'] = $res['lat'];
                //Se possibile aggiorno anche il codice postale che mi serve per l'esportazione ufficiale
                if (isset($res['address']['postcode'])) {
                    $origin['postal_code'] = $res['address']['postcode'];
                    Log::write('debug', " Geocoding aggiunge il codice postale {$origin['postal_code']})");
                }
                if (isset($res['address']['city'])) {
                    $origin['city'] = $res['address']['city'];
                    Log::write('debug', " Geocoding aggiunge la città {$origin['city']})");
                }
                if (isset($res['address']['county']) && empty($origin['province'])) {
                    if (array_search($res['address']['county'], $this->province)) {
                        $origin['province'] = array_search($res['address']['county'], $this->province);
                    } else {
                        $origin['province'] = substr($res['address']['county'], 0, 2);
                    }
                    Log::write('debug', " Geocoding aggiunge la provincia {$origin['province']})");
                }
                Log::write('debug', "] Geocoding success $address ({$origin['lon']},{$origin['lat']})");
                //var_dump("Geocoding success ({$origin['lon']},{$origin['lat']})");
                return $origin;
            } else {
                Log::write('debug', "] Geocoding failed $address, empty features");
                //var_dump("Geocoding failed, empty features");
                return [];
            }
        } else {
            $code = $response->getStatusCode();
            if ($code == 429) { //Too many requests
                $this->throttler(1, 1);
            }
            Log::write('debug', "Geocoding failed $address http error $code\n");
            throw new Exception("Geocoding $address failed - http error $code\n");
        }
    }

    //Compose complete address starting from the single parts
    private function composeAddress($origin)
    {
        $res = '';
        if (isset($origin['q'])) {
            return $origin['q'];
        }

        if (isset($origin['address'])) {
            $a = $origin['address'];
            $res = $a;
        } else {
            $a = '';
        }

        if (isset($origin['postal_code'])) {
            $p = $origin['postal_code'];
            $res .= " ($p) ";
        } else {
            $p = '';
        }

        if (isset($origin['city'])) {
            $c = $origin['city'];
            $res .= " $c ";
        } else {
            $c = '';
        }

        if (isset($origin['province'])) {
            $pr = $origin['province'];
            $res .= " ($pr) ";
        } else {
            $pr = '';
        }

        return trim($res);
    }
}
