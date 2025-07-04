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

use Aws\Credentials\Credentials;
use Aws\LocationService\LocationServiceClient;
use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Log\Log;
use Exception;

class awsGeocoder extends Geocoder
{
    public function __construct()
    {
        parent::__construct();
    }

    public function geocode($origin, $focusLat = null, $focusLon = null)
    {
        try {
            $coords = $this->awsGeocode($origin, $focusLat, $focusLon);
            if (isset($coords['lat']) && isset($coords['lon'])) {
                return $coords;
            } else {
                //Se non ha geocodificato l'indirizzo geocodifico il cap / città
                $q = trim("{$origin['city']} {$origin['postal_code']}");
                $coords = Cache::read($q);
                if ($coords === null) {
                    $coords = $this->awsGeocode($q, $focusLat, $focusLon);
                    Cache::write($q, $coords);
                }

                return $coords;
            }
        } catch (Exception $e) {
            Log::write('debug', 'Geocode->> Geocoding failed http error');

            return false;
        }
    }

    private function awsGeocode($origin, $focusLat = null, $focusLon = null, $structured = true)
    {
        $accessKey = Configure::read('Geocoding.accessKey');
        $secretKey = Configure::read('Geocoding.secretKey');
        $region = Configure::read('Geocoding.region');
        $placeIndexName = Configure::read('Geocoding.placeIndexName');

        // Set your AWS credentials
        $credentials = new Credentials($accessKey, $secretKey);

        // Create an instance of the LocationServiceClient
        $locationService = new LocationServiceClient([
            'region' => $region, // e.g., us-west-2
            'credentials' => $credentials,
        ]);

        // Specify the address you want to geocode
        $address = $this->composeAddress($origin);

        // Perform geocoding request
        try {
            //code...


            $result = $locationService->searchPlaceIndexForText([
                'IndexName' => $placeIndexName, // Specify the name of your place index
                'Text' => $address,
                'BiasPosition' => [
                    $focusLat,
                    $focusLon,
                ],
            ]);

            // Extract coordinates from the result

            $coordinates = $result['Results'][0]['Place']['Geometry']['Point'];
            if (! $coordinates) {
                return $origin;
            }
            $origin['lon']  = $coordinates[0];
            $origin['lat']  = $coordinates[1];
            //Se possibile aggiorno anche il codice postale che mi serve per l'esportazione ufficiale
            if (isset($result['Results'][0]['Place']['PostalCode'])) {
                $origin['postal_code'] = $result['Results'][0]['Place']['PostalCode'];
                Log::write('debug', " Geocoding aggiunge il codice postale {$origin['postal_code']})");
            }
            if (isset($result['Results'][0]['Place']['Municipality'])) {
                $origin['city'] = $result['Results'][0]['Place']['Municipality'];
                Log::write('debug', " Geocoding aggiunge la città {$origin['city']})");
            }        
            if (isset($result['Results'][0]['Place']['SubRegion'])) {
                if (array_search($result['Results'][0]['Place']['SubRegion'], $this->province)) {
                    $origin['province'] = array_search($result['Results'][0]['Place']['SubRegion'], $this->province);
                } else {
                    $origin['province'] = substr($result['Results'][0]['Place']['SubRegion'], 0, 2);
                }
                Log::write('debug', " Geocoding aggiunge la provincia {$origin['province']})");
            }   
            return $origin;
        } catch (\Throwable $th) {
            if (is_array($origin) && isset($origin['address'])) {
                Log::write('debug', "] Geocoding failed {$origin['address']}, empty features");
                //var_dump("Geocoding failed, empty features");
                throw new Exception("Geocoding {$origin['address']} failed - not found {$origin['address']}");
            } else {
                Log::write('debug', "] Geocoding failed {$origin}, empty features");
                //var_dump("Geocoding failed, empty features");
                throw new Exception("Geocoding {$origin} failed - not found {$origin}");
            }
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
