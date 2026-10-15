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
use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Http\Client;
use Cake\Log\Log;
use Exception;

class googleGeocoder extends Geocoder
{
    protected $district = [
        'BORGO TRENTO' => ['lat' => 45.5542017443, 'lon' => 10.2220894301],
        'BRESCIA ANTICA' => ['lat' => 45.5365046423, 'lon' => 10.2270562939],
        'BUFFALORA-BETTOLE' => ['lat' => 45.5008979475, 'lon' => 10.2765105955],
        'CAIONVICO' => ['lat' => 45.5262612022, 'lon' => 10.2832494754],
        'CASAZZA' => ['lat' => 45.5758280183, 'lon' => 10.2275687502],
        'CENTRO STORICO NORD' => ['lat' => 45.5425164309, 'lon' => 10.2170498231],
        'CENTRO STORICO SUD' => ['lat' => 45.5350131500, 'lon' => 10.2153691018],
        'CHIESANUOVA' => ['lat' => 45.5237529025, 'lon' => 10.1771269699],
        'CHIUSURE' => ['lat' => 45.5552141684, 'lon' => 10.1813631553],
        'CROCIFISSA DI ROSA' => ['lat' => 45.5475529569, 'lon' => 10.2290991643],
        'DON BOSCO' => ['lat' => 45.5284458889, 'lon' => 10.2050363941],
        'FIUMICELLO' => ['lat' => 45.5453796321, 'lon' => 10.1919661971],
        'FOLZANO' => ['lat' => 45.5001943361, 'lon' => 10.2037041737],
        'FORNACI' => ['lat' => 45.5035381069, 'lon' => 10.1730353931],
        'LAMARMORA' => ['lat' => 45.5210787128, 'lon' => 10.2111576709],
        'MOMPIANO' => ['lat' => 45.5686843302, 'lon' => 10.2498420383],
        'PORTA CREMONA-VOLTA' => ['lat' => 45.5171587706, 'lon' => 10.2243813334],
        'PORTA MILANO' => ['lat' => 45.5423568868, 'lon' => 10.2053165091],
        'PORTA VENEZIA' => ['lat' => 45.5429476032, 'lon' => 10.2613888964],
        'PRIMO MAGGIO' => ['lat' => 45.5373116766, 'lon' => 10.1895034450],
        'SAN BARTOLOMEO' => ['lat' => 45.5675699050, 'lon' => 10.2204240856],
        'SANT\'EUFEMIA' => ['lat' => 45.5236958911, 'lon' => 10.2699357987],
        'SANT\'EUSTACCHIO' => ['lat' => 45.5532959355, 'lon' => 10.2099836439],
        'SAN POLO-CASE' => ['lat' => 45.5032675470, 'lon' => 10.2498890051],
        'SAN POLO CIMABUE' => ['lat' => 45.5216675680, 'lon' => 10.2536413376],
        'SAN POLO PARCO' => ['lat' => 45.5178942549, 'lon' => 10.2379518193],
        'SAN ROCCHINO' => ['lat' => 45.5538799444, 'lon' => 10.2449075756],
        'SANPOLINO' => ['lat' => 45.5122426251, 'lon' => 10.2678911776],
        'URAGO MELLA' => ['lat' => 45.5663732220, 'lon' => 10.1972876135],
        'VILLAGGIO PREALPINO' => ['lat' => 45.5832602846, 'lon' => 10.2307903765],
        'VILLAGGIO BADIA' => ['lat' => 45.5462932590, 'lon' => 10.1599222815],
        'VILLAGGIO SERENO' => ['lat' => 45.5109792304, 'lon' => 10.1917531391],
        'VILLAGGIO VIOLINO' => ['lat' => 45.5414887017, 'lon' => 10.1672150438],
    ];

    public function __construct()
    {
        parent::__construct();
        if (empty($this->url)) {
            $this->url = 'https://maps.googleapis.com/maps/api/geocode/json';
        }
    }

    public function geocodeNoHash($origin, $focusLat = null, $focusLon = null): array|false
    {
        if ($origin instanceof Origin) {
            $origin = $origin->toArray();
        }
        try {
            $coords = $this->googleGeocode($origin, $focusLat, $focusLon);
            if (isset($coords['lat']) && isset($coords['lon'])) {
                return $coords;
            } else {
                //Se non ha geocodificato l'indirizzo geocodifico il cap / città
                $q = trim("{$origin['city']} {$origin['postal_code']}");
                $coords = Cache::read($q);
                if ($coords === null) {
                    $coords = $this->googleGeocode($q, $focusLat, $focusLon);
                    Cache::write($q, $coords);
                }

                return $coords;
            }
        } catch (Exception $e) {
            Log::write('debug', 'Geocode->> Geocoding failed http error');

            return false;
        }
    }

    private function googleGeocode($origin, $focusLat = null, $focusLon = null, $structured = true): array|false
    {
        $apiKey = Configure::read('Geocoding.google.accessKey'); //APi key di Google
        if (empty($apiKey)) {
            $apiKey = Configure::read('Geocoding.accessKey'); //Uso Apikey generica se non è specifica per Google, per compatibilità con vecchie configurazioni
        }

        if (!empty($origin['q']) && empty($origin['address']) && empty($origin['city']) && empty($origin['postal_code']) && empty($origin['province']) && empty($origin['district'])) {
            $structured = false;
        }

        if (empty($apiKey)) {
            throw new Exception('Google API Key not configured');
        }
        // locate by district if address is empty
        if ($structured && empty($origin['address']) && !empty($origin['district'])) {
            $origin = $this->locateDistrict($origin);

            return $origin;
        }
        // Compose the address
        if ($structured) {
            $address = $this->composeAddress($origin);
        } else {
            $address = $origin['q'] . ' (ITALY)';
        }

        // Build the request parameters
        $params = [
            'address' => $address,
            'key' => $apiKey,
        ];

        // Add region bias (Italia)
        $params['region'] = 'it';

        // Add location bias if provided
        if ($focusLat !== null && $focusLon !== null) {
            $params['bounds'] = sprintf(
                '%f,%f|%f,%f',
                $focusLat - 0.1,
                $focusLon - 0.1,
                $focusLat + 0.1,
                $focusLon + 0.1
            );
        }

        // Make HTTP request to Google Geocoding API
        try {
            $http = new Client();
            $response = $http->get($this->url, $params);

            if (!$response->isOk()) {
                throw new Exception('Google Geocoding API request failed');
            }

            $data = $response->getJson();

            if ($data['status'] !== 'OK' || empty($data['results'])) {
                if (is_array($origin) && isset($origin['address'])) {
                    Log::write('debug', "Geocoding failed {$origin['address']}, status: {$data['status']}");
                    throw new Exception("Geocoding {$origin['address']} failed - {$data['status']}");
                } else {
                    Log::write('debug', "Geocoding failed {$origin}, status: {$data['status']}");
                    throw new Exception("Geocoding {$origin} failed - {$data['status']}");
                }
            }

            // Extract coordinates from the first result
            $result = $data['results'][0];
            $location = $result['geometry']['location'];

            if (!$location) {
                return $origin;
            }

            $origin['lat'] = $location['lat'];
            $origin['lon'] = $location['lng'];

            // Extract address components for postal code, city, and province
            foreach ($result['address_components'] as $component) {
                $types = $component['types'];

                // Postal code
                if (in_array('postal_code', $types)) {
                    $origin['postal_code'] = $component['long_name'];
                    Log::write('debug', " Geocoding aggiunge il codice postale {$origin['postal_code']})");
                }

                // City (locality or administrative_area_level_3)
                if (in_array('locality', $types) || in_array('administrative_area_level_3', $types)) {
                    $origin['city'] = $component['long_name'];
                    Log::write('debug', " Geocoding aggiunge la città {$origin['city']})");
                }

                // Province (administrative_area_level_2)
                if (in_array('administrative_area_level_2', $types)) {
                    $provinceName = $component['long_name'];

                    // Try to match with province list
                    if (array_search($provinceName, $this->province)) {
                        $origin['province'] = array_search($provinceName, $this->province);
                    } else {
                        // Try to extract province code from short_name
                        $provinceCode = $component['short_name'];
                        if (strlen($provinceCode) == 2 && isset($this->province[strtoupper($provinceCode)])) {
                            $origin['province'] = strtoupper($provinceCode);
                        } else {
                            // Fallback: use first 2 characters
                            $origin['province'] = substr($provinceName, 0, 2);
                        }
                    }
                    Log::write('debug', " Geocoding aggiunge la provincia {$origin['province']})");
                }
            }

            return $origin;
        } catch (\Throwable $th) {
            if (is_array($origin) && isset($origin['address'])) {
                Log::write('debug', "Geocoding failed {$origin['address']}, error: {$th->getMessage()}");
                throw new Exception("Geocoding {$origin['address']} failed - {$th->getMessage()}");
            } else {
                Log::write('debug', "Geocoding failed {$origin}, error: {$th->getMessage()}");
                throw new Exception("Geocoding {$origin} failed - {$th->getMessage()}");
            }
        }
    }

    //Compose complete address starting from the single parts

    private function composeAddress($origin)
    {
        $res = '';
        if (!empty($origin['address'])) {
            $a = $origin['address'];
            $res = "$a, ";
        } else {
            $a = '';
        }

        if (!empty($origin['city'])) {
            $c = $origin['city'];
            $res .= "$c ";
        } else {
            $c = '';
        }

        if (!empty($origin['postal_code'])) {
            $p = $origin['postal_code'];
            $res .= "$p";
        } else {
            $p = '';
        }

        return $res;
    }

    // locate by district if address is empty
    private function locateDistrict($origin)
    {

        // find district in the list
        if (array_key_exists($origin['district'], $this->district)) {
            // get lat/lon
            $origin['lat'] = $this->district[$origin['district']]['lat'];
            $origin['lon'] = $this->district[$origin['district']]['lon'];
            Log::write('debug', " Geocoding localizza il quartiere {$origin['district']} con lat {$origin['lat']} e lon {$origin['lon']})");
        } else {
            Log::write('debug', " Geocoding non trova il quartiere {$origin['district']})");
        }

        return $origin;
    }
}
