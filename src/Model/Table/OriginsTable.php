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

namespace App\Model\Table;

use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Http\Client;
use Cake\I18n\FrozenTime;
use Cake\Log\Log;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Validation\Validator;
use Exception;

class OriginsTable extends Table
{
    private $geocodingEngine;

    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('origins');
        $this->setPrimaryKey('id');

        $this->belongsTo('Companies');
        $this->belongsTo('Surveys');
        $this->belongsTo('Users');
        $this->hasOne('Employees');
        
        //Imposto il geocoder di default che sarà usato per geocodificare le origins
        $this->geocodingEngine = Configure::read('Geocoding.Engine', 'komootGeocoder');
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        // TODO

        return $validator;
    }

    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['survey_id'], 'Surveys'));
        //$rules->add($rules->existsIn(['company_id'], 'Companies'));
        //$rules->add($rules->existsIn(['user_id'], 'Users')); // da abilitare a regime, ora commentato per ragioni di test

        return $rules;
    }

    public function getAllNotGeocoded($company_id)
    {
        $or = $this->find()
            ->select(['id'])
            ->where([
                'OR' => [['lat IS' => null], ['lat' => -1]],
                'OR' => [['lon IS' => null], ['lon' => -1]],
                'Origins.company_id' => $company_id,
                // 'Origins.survey_id IS' => NULL,
            ])
            ->toArray();

        return array_map(function ($origin) {
            return $origin['id'];
        }, $or);
    }

    public function getAll($company_id, $survey_id = null)
    {
        $q = $this->find()
        ->select('id')
        ->where([
            'Origins.company_id' => $company_id,
        ])
        ->orderDesc('Origins.id');

        if ($survey_id) {
            $q->where(['Origins.survey_id is not' => null]);
        }

        return array_map(function ($origin) {
            return $origin['id'];
        }, $q->toArray());
    }

    public function geocode($id)
    {
        $origin = $this->get($id);
        if (!$origin) {
            return false;
        }

        //Creo dinamicamente l'oggetto geocoder, in base al parametro di configurazione
        //https://www.php.net/manual/en/functions.variable-functions.php
        $geocoderName = '\\App\\Geocoder\\' . $this->geocodingEngine;
        $geocoderClass = new $geocoderName();
        $coords = [];

        if (empty($origin['city'] && empty($origin['province']))) {
            Log::write('debug', "] Geocoding failed empty address");
            return false;
        }

        try {
            Log::write('debug', "[ Geocoding $id");

            //Focus point sulla città indicata nell'indirizzo
            //TODO: Definire un focus point sulla sede del questionario corrente, se non è specificata la città
            //Però il questionario è per company, come faccio a sapere la sede?
            $focusLat = 7.669830322265626;
            $focusLon = 45.05011899322459;
            if ($origin['city']) {
                $q = $origin['city'] . ($origin['province'] ? ", {$origin['province']}" : '');
                $focusOrigin = [
                    'city' => $origin['city'],
                    'province' => $origin['province'],
                ];
                $coords = Cache::read($q);
                if (!isset($coords['lon']) || !isset($coords['lat']) || $coords === null || $coords['lon'] == -1 || $coords['lat'] == -1) {
                    $coords = $geocoderClass->geocode($focusOrigin);
                    if ((isset($coords['lon']) && isset($coords['lat']) && $coords != null && $coords['lon'] != -1 && $coords['lat'] != -1 )) {
                        Cache::write($q, $coords);
                    }
                }
                $focusLon  = $coords['lon'] ?? 7.669830322265626;
                $focusLat  = $coords['lat'] ?? 45.05011899322459;
            }

            try {
                $old_coords = $coords;
                $coords = $geocoderClass->geocode($origin, $focusLon, $focusLat);
                if ($coords) {
                    Log::write('debug', '] Geocoding success');
                } else {
                    $coords = $old_coords;
                    Log::write('debug', '] Geocoding failed not found');

                    return false;
                }

                return $this->saveCoords($coords, $origin);
            } catch (Exception $e) {
                Log::write('debug', '] Geocoding failed http error');

                return false;
            }
        } catch (Exception $e) {
            Log::write('debug', "] Geocoding failed {$e->getMessage()}");
        }
    }

    private function saveCoords($coords, $origin)
    {
        if (empty($coords)) {
            Log::write('debug', '] Geocoding empty coords');

            return false;
        }

        $id = $origin->id;
        $origin['lat'] = $coords['lat'] ?? 0;
        $origin['lon'] = $coords['lon'] ?? 0;
        $origin['geocoded_at'] =  FrozenTime::now();
        $origin['province'] = $coords['province'] ?? null;
        $origin['city'] = $coords['city'] ?? null;
        if ($coords['postal_code']){
            $origin['postal_code'] = $coords['postal_code'] ?? null;
        }
        

        if ($this->save($origin)) {
            Log::write('debug', "] Geocoding $id SAVE OK");

            return true;
        } else {
            Log::write('debug', "] Geocoding $id SAVE FAIL");
        }

        return false;
    }

    private function nominatimGeoCode($address, $focusLat = null, $focusLon = null)
    {
        $http = new Client();
        $origin = [];
        if (is_null($focusLat) && is_null($focusLon)) {
            $response = $http->get("https://nominatim.geocoding.ai/search.php?q=$address&format=jsonv2&limit=1");
        } else {
            $response = $http->get("https://photon.komoot.io/api/?q=$address&lat=$focusLat&lon=$focusLon&limit=1");
        }

        if ($response->isOk()) {
            $res = $response->getJson();
            if (isset($res['features']) && !empty($res['features'])) {
                $origin['lon'] = $res['features'][0]['geometry']['coordinates'][0];
                $origin['lat'] = $res['features'][0]['geometry']['coordinates'][1];
                Log::write('debug', "] Geocoding success $address ({$origin['lon']},{$origin['lat']})");
                //var_dump("Geocoding success ({$origin['lon']},{$origin['lat']})");
                return $origin;
            } else {
                Log::write('debug', "] Geocoding failed $address, empty features");
                //var_dump("Geocoding failed, empty features");
                return $origin;
            }
        } else {
            Log::write('debug', "Geocoding failed $address http error");
            //var_dump("Geocoding failed http error");
            throw new Exception("Geocoding $address failed - http error");
        }
    }

    //Calcola la distanza di una origin dalla sede dell'azienda
    public function updateDistance($id){
        $origin = $this->get($id);
        if (!$origin) {
            return false;
        }
        if (empty($origin['lat']) || empty($origin['lon'])) {
            return -1;
        }

        //Estraggo le coordinate dell'ufficio dell'utente
        $user = $this->Users->find()
            ->where(['id' => $origin['user_id']])
            ->first();

        if (!$user) {
            return -1;
        }

        if (!$user['office_id']) {
            return -1;
        }


        $officeT = TableRegistry::getTableLocator()->get('Offices');
        $office = $officeT->find()
            ->where(['id' => $user['office_id']])
            ->first();
            
        if (empty($office['lat']) || empty($office['lon'])) {
            return -1;
        }
        //Calcolo la distanza harvesine tra le due coordinate
        $distance = $this->haversineGreatCircleDistance($origin['lat'], $origin['lon'], $office['lat'], $office['lon']);
        $origin['distance'] = $distance / 1000;
        $this->save($origin);
    }

    /**
     * Calculates the great-circle distance between two points, with
     * the Haversine formula.
     *
     * @param float $latitudeFrom Latitude of start point in [deg decimal]
     * @param float $longitudeFrom Longitude of start point in [deg decimal]
     * @param float $latitudeTo Latitude of target point in [deg decimal]
     * @param float $longitudeTo Longitude of target point in [deg decimal]
     * @param float $earthRadius Mean earth radius in [m]
     * @return float Distance between points in [m] (same as earthRadius)
     */
    private function haversineGreatCircleDistance(
        $latitudeFrom,
        $longitudeFrom,
        $latitudeTo,
        $longitudeTo,
        $earthRadius = 6371000
    ) {
        // convert from degrees to radians
        $latFrom = deg2rad($latitudeFrom);
        $lonFrom = deg2rad($longitudeFrom);
        $latTo = deg2rad($latitudeTo);
        $lonTo = deg2rad($longitudeTo);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));

        return $angle * $earthRadius;
    }
}
