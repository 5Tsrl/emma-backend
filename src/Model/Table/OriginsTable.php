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

use App\Model\Entity\Origin;
use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\I18n\FrozenTime;
use Cake\Log\Log;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Validation\Validator;
use Exception;
use Sk\Geohash\Geohash;

class OriginsTable extends Table
{
    private string $geocodingEngine;

    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('origins');
        $this->setPrimaryKey('id');

        $this->belongsTo('Companies');
        $this->belongsTo('Surveys', [
            'foreignKey' => 'survey_id',
        ]);
        $this->belongsTo('Users');
        $this->hasOne('Employees');

        //Imposto il geocoder di default che sarà usato per geocodificare le origins
        // ⚠️ `Configure::read('Geocoding.Engine', 'komootGeocoder')`'s default argument
        // never actually applies: config/app.php always DEFINES the key
        // (`'Geocoding.Engine' => env('GEOCODING_ENGINE', false)`), it's just `false`
        // when the env var is unset — `Configure::read()`'s fallback only kicks in when
        // the key is entirely ABSENT, not when it resolves to a falsy value. On any
        // site/env without `GEOCODING_ENGINE` set (e.g. `config/sites/default`, used by
        // CLI/tests with no `HTTP_HOST`) this assigned `false` to the typed `string`
        // property below, throwing a TypeError the moment `OriginsTable` was
        // instantiated — breaking the whole Origins feature outright on that site, not
        // just a specific action. Coerce defensively instead of trusting the default arg.
        $configuredEngine = Configure::read('Geocoding.Engine', 'komootGeocoder');
        $this->geocodingEngine = is_string($configuredEngine) && $configuredEngine !== ''
            ? $configuredEngine
            : 'komootGeocoder';
    }

    /**
     * Encode incoming lat/lon into geohash on save and clean the
     * non-persisted fields off the in-memory entity.
     *
     * The `lat` and `lon` columns no longer exist on the `origins` table —
     * they were removed by the cleanup migration
     * `20260508100000_CleanupOriginsLatLon`. The null assignments below are
     * an in-memory cleanup for entities that still carry those legacy keys
     * (e.g. payloads from older clients); the database itself only stores
     * `geohash`.
     *
     * `address` is intentionally not persisted on new origin rows for
     * privacy reasons: the column exists for legacy data but every save
     * nulls it. Downstream consumers must derive a human-readable address
     * from `city` / `postal_code` / `province` (still persisted) or reverse
     * geocode the geohash when needed.
     *
     * @param \Cake\Event\EventInterface $event The save event.
     * @param \App\Model\Entity\Origin $entity The entity being saved.
     * @param \ArrayObject $options Save options.
     * @return void
     */
    public function beforeSave($event, $entity, $options)
    {
        //Per la privacy, solo se ci sono coordinate valide, tolgo le coordinate e l'indirizzo - @impronta48 - 8/5/2026
        if (!empty($entity->lat) || !empty($entity->lon)) {
            $h = new Geohash();
            $entity->geohash = $h->encode($entity->lat, $entity->lon, Configure::read('Geohash.precision', 7));
            $entity->lat = null;
            $entity->lon = null;
            $entity->address = null;
        }
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('year')
            ->allowEmptyString('year');

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
                //Tolto il controllo su lat/lon per passare a geohash
                //'OR' => [['lat IS' => null], ['lat' => -1], ['lon IS' => null], ['lon' => -1]],
                'Origins.geohash IS' => null,
                'Origins.company_id' => $company_id,
            ])
            ->toArray();

        return array_map(function ($origin) {
            return $origin['id'];
        }, $or);
    }

    public function getAllMissingDistrict($company_id, $city)
    {
        $or = $this->find()
            ->select(['id'])
            ->where([
                //Tolto il controllo su lat/lon per passare a geohash
                //['lat IS NOT' => null],
                //['lon IS NOT' => null],
                'Origins.geohash IS NOT' => null,
                'Origins.company_id' => $company_id,
                'Origins.district IS' => null,
                'Origins.city' => $city,
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

    public function geocode($id, $force = false)
    {
        $origin = $this->get($id);
        if (!$origin) {
            return false;
        }

        //Se l'origin ha già un geohash e force non è true, non la geocodifico.
        //L'unica colonna che persiste le coordinate è geohash: appoggiarsi a
        //lat/lon (rimosse dalla migrazione 20260508100000) rendeva la guardia
        //sempre falsa e ogni save rieseguiva il geocoder.
        if (!$force && !empty($origin['geohash'])) {
            return $this->save($origin);
        }

        //Creo dinamicamente l'oggetto geocoder, in base al parametro di configurazione
        //https://www.php.net/manual/en/functions.variable-functions.php
        $geocoderName = '\\App\\Geocoder\\' . $this->geocodingEngine;
        $geocoderClass = new $geocoderName();
        $coords = [];

        if (empty($origin['city']) && empty($origin['province'])) {
            Log::write('debug', '] Geocoding failed empty address');

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
                $focusOrigin = new Origin([
                    'city' => $origin['city'],
                    'province' => $origin['province'],
                ]);
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
        // lat/lon columns no longer exist (migration 20260508100000); persist
        // coordinates exclusively via the geohash column. If the geocoder
        // didn't already provide a geohash, encode it from lat/lon here.
        $origin['geocoded_at'] =  FrozenTime::now();
        $origin['province'] = $coords['province'] ?? null;
        $origin['city'] = $coords['city'] ?? null;
        if (!empty($coords['geohash'])) {
            $origin['geohash'] = $coords['geohash'];
        } elseif (isset($coords['lat']) && isset($coords['lon'])) {
            $origin['geohash'] = \App\Geocoder\GeohashHelper::encode(
                (float)$coords['lat'],
                (float)$coords['lon']
            );
        }
        if ($coords['postal_code']) {
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

    //Calcola la distanza di una origin dalla sede dell'azienda

    public function updateDistance($id)
    {
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
