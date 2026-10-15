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

class emiliaRomagnaGeocoder extends Geocoder
{
    private $baseUrl;
    private $username;
    private $password;
    private $handle;
    private $coordType;

    public function __construct()
    {
        parent::__construct();

        // Set default values or read from configuration
        $this->baseUrl = Configure::read('Geocoding.url') ?: 'https://servizigis.regione.emilia-romagna.it/normalizzatore/eGeoCoding';
        $this->username = Configure::read('Geocoding.accessKey') ?: 'usrEMMA';
        $this->password = Configure::read('Geocoding.secretKey') ?: null;
        $this->coordType = Configure::read('Geocoding.coordType') ?: 'WGS84';

        // Initialize handle from cache or get new one
        $this->handle = Cache::read('emilia_romagna_geocoder_handle', 'long');
    }

    public function geocodeNoHash($origin, $focusLat = null, $focusLon = null): array|false
    {
        if ($origin instanceof Origin) {
            $origin = $origin->toArray();
        }
        try {
            $coords = $this->emiliaRomagnaGeocode($origin);
            // if retry is needed due to 500 error
            if ($coords === [-1, -1]) {
                $coords = $this->emiliaRomagnaGeocode($origin);
            }
            if (isset($coords['lat']) && isset($coords['lon'])) {
                return $coords;
            } else {
                // Se non ha geocodificato l'indirizzo geocodifico il cap / città
                $q = trim("{$origin['city']} {$origin['postal_code']}");
                $coords = Cache::read($q);
                if ($coords === null) {
                    $coords = $this->emiliaRomagnaGeocode([
                        'city' => $origin['city'],
                        'postal_code' => $origin['postal_code'],
                    ]);
                    Cache::write($q, $coords);
                }

                return $coords;
            }
        } catch (Exception $e) {
            Log::write('debug', 'Geocode->> Emilia-Romagna Geocoding failed: ' . $e->getMessage());

            return false;
        }
    }

    private function emiliaRomagnaGeocode($origin): array|false
    {
        // Ensure we have a valid handle
        if (!$this->ensureHandle()) {
            throw new Exception('Failed to authenticate with Emilia-Romagna geocoding service');
        }

        $http = new Client();

        // Prepare the request body for address normalization
        $requestBody = [
            'Norm_IndirizzoInputParams' => [
                'p_Toponimo' => $origin['address'] ?? '',
                // 'p_Civico' => $this->extractCivicNumber($origin['address'] ?? ''),
                'p_Comune' => $origin['city'] ?? '',
                'p_Provincia' => $origin['province'] ?? '',
                'p_tipo_coord' => $this->coordType,
                'p_Handle' => $this->handle,
            ],
        ];
        // prepare the request body for cap
        $requestBodyCap = [
            'Norm_Comune1InputParams' => [
                'p_Toponimo' => $this->extractToponym($origin['address'] ?? ''),
                'p_Handle' => $this->handle,
            ],
        ];

        // Build request URL
        $url = $this->baseUrl . '?' . http_build_query([
            'serviceType' => 'DBServices',
            'serviceName' => 'Normalizzatore',
            'message' => 'Norm_Indirizzo',
        ]);
        // Build request URL for cap
        $urlCap = $this->baseUrl . '?' . http_build_query([
            'serviceType' => 'DBServices',
            'serviceName' => 'Normalizzatore',
            'message' => 'Norm_Comune1',
        ]);

        // Set headers
        $headers = [
            'Content-Type' => 'application/json;charset=UTF-8',
            'Accept-Encoding' => 'gzip, deflate',
            'soapAction' => $this->baseUrl . '?serviceType=DBServices&serviceName=Normalizzatore&message=Norm_Indirizzo',
            'User-Agent' => 'Apache-HttpClient/4.1.1 (java 1.5)',
        ];
        // Set headers for cap
        $headersCap = [
            'Content-Type' => 'application/json;charset=UTF-8',
            'Accept-Encoding' => 'gzip, deflate',
            'soapAction' => $this->baseUrl . '?serviceType=DBServices&serviceName=Normalizzatore&message=Norm_Comune1',
            'User-Agent' => 'Apache-HttpClient/4.1.1 (java 1.5)',
        ];

        try {
            $this->throttler(Configure::read('Geocoding.Throttle') ?: 100, 10);

            $response = $http->post($url, json_encode($requestBody), [
                'headers' => $headers,
            ]);
            // prepare response for cap
            $responseCap = $http->post($urlCap, json_encode($requestBodyCap), [
                'headers' => $headersCap,
            ]);

            if ($response->isOk()) {
                $result = $response->getJson();
                if ($response->isOk()) {
                    $resultCap = $responseCap->getJson();
                } else {
                    $resultCap = null;
                }
                // Parse and return the geocoding result
                return $this->parseGeocodeResponse($result, $resultCap, $origin);
            }
            // else if 500 error, try to get a new handle and retry once
            elseif ($response->getStatusCode() == 500) {
                Log::write('debug', 'Emilia-Romagna Geocoding received 500 error, trying to get new handle and retry');
                if ($this->getNewHandle()) {
                    // Retry the request with the new handle
                    return [-1,-1];
                } else {
                    throw new Exception('Failed to re-authenticate with Emilia-Romagna geocoding service after 500 error');
                }
            } else {
                Log::write('debug', 'Emilia-Romagna Geocoding failed with HTTP status: ' . $response->getStatusCode());
                throw new Exception('Geocoding failed - HTTP error ' . $response->getStatusCode());
            }
        } catch (\Throwable $th) {
            Log::write('debug', 'Emilia-Romagna Geocoding failed: ' . $th->getMessage());
            throw new Exception('Geocoding failed - ' . $th->getMessage());
        }
    }

    private function ensureHandle()
    {
        if ($this->handle) {
            // TODO: Check if handle is still valid
            return true;
        }

        return $this->getNewHandle();
    }

    private function getNewHandle()
    {
        $http = new Client();

        $requestBody = [
            'GetHandleInputParams' => [
                'p_Username' => $this->username,
                'p_Userpassword' => $this->password,
            ],
        ];

        $url = $this->baseUrl . '?' . http_build_query([
            'serviceType' => 'DBServices',
            'serviceName' => 'Normalizzatore',
            'message' => 'GetHandle',
        ]);

        $headers = [
            'Content-Type' => 'application/json;charset=UTF-8',
            'Accept-Encoding' => 'gzip, deflate',
            'soapAction' => $this->baseUrl . '?serviceType=DBServices&serviceName=Normalizzatore&message=GetHandle',
            'User-Agent' => 'Apache-HttpClient/4.1.1 (java 1.5)',
        ];

        try {
            $response = $http->post($url, json_encode($requestBody), [
                'headers' => $headers,
            ]);

            if ($response->isOk()) {
                $result = $response->getJson();

                // Parse handle from response
                if (isset($result['getHandleOutput']['getHandleOutputParams']['p_Handle'])) {
                    $this->handle = $result['getHandleOutput']['getHandleOutputParams']['p_Handle'];

                    // Cache handle for 1 hours
                    Cache::write('emilia_romagna_geocoder_handle', $this->handle, 'long');

                    Log::write('debug', 'Emilia-Romagna Geocoder: New handle obtained');

                    return true;
                } else {
                    Log::write('debug', 'Emilia-Romagna Geocoder: Failed to get handle from response');

                    return false;
                }
            } else {
                Log::write('debug', 'Emilia-Romagna Geocoder: Authentication failed with HTTP status: ' . $response->getStatusCode());

                return false;
            }
        } catch (\Throwable $th) {
            Log::write('debug', 'Emilia-Romagna Geocoder: Authentication failed: ' . $th->getMessage());

            return false;
        }
    }

    private function parseGeocodeResponse($result, $resultCap, $origin)
    {
        // The actual response has a different structure and casing
        if (isset($result['norm_IndirizzoOutput']['norm_IndirizzoOutputRecordsetArray'])) {
            $rows = $result['norm_IndirizzoOutput']['norm_IndirizzoOutputRecordsetArray'];

            // Normalize all keys to uppercase to handle inconsistent casing
            $rows = array_map(function ($row) {
                return array_change_key_case($row, CASE_UPPER);
            }, $rows);

            // Sort by GR_AFFIDABILITA (street reliability) and then GR_AFFIDABILITA_CIV (civic reliability)
            // Lower values are better. 0 is max reliability.
            usort($rows, function ($a, $b) {
                if ($a['GR_AFFIDABILITA'] != $b['GR_AFFIDABILITA']) {
                    return $a['GR_AFFIDABILITA'] <=> $b['GR_AFFIDABILITA'];
                }

                return $a['GR_AFFIDABILITA_CIV'] <=> $b['GR_AFFIDABILITA_CIV'];
            });

            $bestResult = $rows[0];

            // Check if coordinates are present, otherwise, we can't use this result
            if (empty($bestResult['CIVICO_X']) || empty($bestResult['CIVICO_Y'])) {
                // then use CENTR_X and CENTR_Y if available
                if (empty($bestResult['CENTR_X']) || empty($bestResult['CENTR_Y'])) {
                    Log::write('debug', 'Geocoding for ' . $this->composeAddress($origin) . ' returned a result but without coordinates.');

                    return $origin;
                } else {
                    $latLon = $this->convertUTMToLatLon((float)$bestResult['CENTR_X'], (float)$bestResult['CENTR_Y']);
                }
            } else {
                $latLon = $this->convertUTMToLatLon((float)$bestResult['CIVICO_X'], (float)$bestResult['CIVICO_Y']);
            }

            // get cap from cap response if available
            if ($resultCap && isset($resultCap['norm_Comune1Output']['norm_Comune1OutputRecordsetArray'][0]['cAP'])) {
                $bestResult['postal_code'] = $resultCap['norm_Comune1Output']['norm_Comune1OutputRecordsetArray'][0]['cAP'];
            }

            $origin['lat'] = $latLon['lat'];
            $origin['lon'] = $latLon['lon'];
            $origin['address'] = trim(($bestResult['DUG'] ?? '') . ' ' . ($bestResult['DENOMINAZIONE'] ?? '')) . ', ' . ($bestResult['NUM_CIVICO'] ?? '');
            $origin['house_number'] = $bestResult['NUM_CIVICO'] ?? null;
            $origin['street_name'] = trim(($bestResult['DUG'] ?? '') . ' ' . ($bestResult['DENOMINAZIONE'] ?? ''));
            $origin['city'] = $bestResult['COMUNE'] ?? null;
            $origin['postal_code'] = $bestResult['postal_code'] ?? null;
            $origin['country'] = 'Italia';

            // Try to match with province list. Compare normalized strings to handle:
            // - Case differences (service returns uppercase)
            // - Encoding artifacts (service returns FORLI'-CESENA with apostrophe instead of ì)
            // - Whitespace differences
            $provinceRaw = trim($bestResult['PROVINCIA'] ?? '');
            $provinceNormalized = $this->normalizeProvinceName($provinceRaw);
            $matchedCode = null;
            foreach ($this->province as $code => $name) {
                if ($this->normalizeProvinceName($name) === $provinceNormalized) {
                    $matchedCode = $code;
                    break;
                }
            }
            if ($matchedCode !== null) {
                $origin['province'] = $matchedCode;
            } else {
                Log::write('debug', "Emilia-Romagna Geocoder: unrecognized province name '{$provinceRaw}', leaving province unset.");
                $origin['province'] = null;
            }

            // Confidence score calculation based on documentation:
            // GR_AFFIDABILITA and GR_AFFIDABILITA_CIV: 0 is best, higher is worse.
            // We convert it to a 0.0 to 1.0 scale where 1.0 is best.
            // Assuming max value for reliability is around 5 as a guess.
            $streetConfidence = 1 - ($bestResult['GR_AFFIDABILITA'] / 5);
            $civicConfidence = 1 - ($bestResult['GR_AFFIDABILITA_CIV'] / 2); // Max value is 2
            $origin['confidence'] = ($streetConfidence + $civicConfidence) / 2;

            $origin['geocoding_engine'] = 'emiliaRomagna';
            $origin['full_response'] = json_encode($bestResult);

            // Log success
            Log::write('debug', 'Geocoding successful for address: ' . $this->composeAddress($origin));

            return $origin;
        } else {
            // Log error
            $errorMessage = 'Geocoding failed. No "norm_IndirizzoOutputRecordsetArray" in response.';
            if (isset($result['norm_IndirizzoOutput']['outputInfo']['message'])) {
                $errorMessage .= ' Message: ' . $result['norm_IndirizzoOutput']['outputInfo']['message'];
            }
            Log::write('debug', $errorMessage . ' Response: ' . json_encode($result));

            return $origin;
        }
    }

    private function extractCivicNumber($address)
    {
        // Extract house number from address
        if (preg_match('/\b(\d+[a-zA-Z]?)\b/', $address, $matches)) {
            return $matches[1];
        }

        return '';
    }

    // extract toponimo from address

    private function extractToponym($address)
    {
        // Extract toponym from address
        if (preg_match('/[a-zA-Z\s]+/', $address, $matches)) {
            return trim($matches[0]);
        }

        return '';
    }

    private function convertUTMToLatLon($x, $y)
    {
        // This is a simplified conversion - you may need a proper UTM conversion library
        // For now, return as-is and log that conversion is needed
        Log::write('debug', "UTM to Lat/Lon conversion needed for coordinates: $x, $y");

        // Placeholder conversion - implement proper UTM to WGS84 conversion
        // return [
        //     'lat' => $y / 111000, // Very rough approximation
        //     'lon' => $x / 111000
        // ];
        return [
            'lat' => $y, // Very rough approximation
            'lon' => $x,
        ];
    }

    //Compose complete address starting from the single parts

    private function composeAddress($origin)
    {
        $res = '';
        if (isset($origin['address'])) {
            $a = $origin['address'];
            $res = "$a, ";
        } elseif (isset($origin['district'])) {
            $a = $origin['district'];
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

        return trim($res);
    }

    /**
     * Normalize province name for comparison.
     * Handles encoding artifacts from the external geocoding service:
     * - Converts to lowercase (multi-byte safe)
     * - Replaces apostrophe with ì (FORLI'-CESENA → forlì-cesena)
     * - Collapses duplicate vowel+accent artifacts (forliì → forlì)
     * - Removes Italian articles/prepositions (NELL', DELLA, etc.) that the service includes
     *   but the canonical list omits (e.g., "REGGIO NELL'EMILIA" → "reggio emilia")
     * - Removes non-alphanumeric chars except hyphens and spaces
     * - Trims whitespace
     */
    private function normalizeProvinceName(string $name): string
    {
        $normalized = mb_strtolower(trim($name), 'UTF-8');
        // Remove Italian articles/prepositions that service includes but canonical list omits
        // e.g., "reggio nell'emilia" → "reggio emilia" (match article + apostrophe BEFORE apostrophe replacement)
        $normalized = preg_replace('/\b(nell|dell|della|dello|degli|delle|sull|sulla|sullo|sugli|sulle|all|alla|allo|agli|alle|dall|dalla|dallo|dagli|dalle|nell|nella|nello|negli|nelle)\'/isu', '', $normalized);
        // Replace apostrophe (encoding artifact for ì) with ì
        $normalized = str_replace("'", 'ì', $normalized);
        // Handle specific artifact: FORLI'-CESENA becomes forliì-cesena (i + ì), collapse to forlì-cesena
        // Use mb_ereg_replace for multi-byte safe replacement
        $normalized = mb_ereg_replace('iì', 'ì', $normalized);
        // Remove any remaining non-alphanumeric except hyphens and spaces
        $normalized = preg_replace('/[^\p{L}\p{N}\s-]/u', '', $normalized);
        // Collapse multiple spaces
        $normalized = preg_replace('/\s+/', ' ', $normalized);

        return trim($normalized);
    }
}
