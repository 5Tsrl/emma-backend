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
use Cake\Database\Schema\TableSchemaInterface;
use Cake\Datasource\EntityInterface;
use Cake\Event\EventInterface;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Offices Model
 *
 * @property \App\Model\Table\CompaniesTable&\Cake\ORM\Association\BelongsTo $Companies
 * @method \App\Model\Entity\Office newEmptyEntity()
 * @method \App\Model\Entity\Office newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\Office[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Office get($primaryKey, $options = [])
 * @method \App\Model\Entity\Office findOrCreate($search, ?callable $callback = null, $options = [])
 * @method \App\Model\Entity\Office patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\Office[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\Office|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\Office saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\Office[]|\Cake\Datasource\ResultSetInterface|false saveMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\Office[]|\Cake\Datasource\ResultSetInterface saveManyOrFail(iterable $entities, $options = [])
 * @method \App\Model\Entity\Office[]|\Cake\Datasource\ResultSetInterface|false deleteMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\Office[]|\Cake\Datasource\ResultSetInterface deleteManyOrFail(iterable $entities, $options = [])
 */
class OfficesTable extends Table
{
    /**
     * Initialize method
     *
     * @param array $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('offices');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->belongsTo('Companies', [
            'foreignKey' => 'company_id',
        ]);
        $this->hasMany('Timetables');
        $this->hasMany('Monitorings', [
            'foreignKey' => 'office_id',
            'dependent' => true,
            'cascadeCallbacks' => true,
        ]);
        $this->hasMany('Company_types');
        $this->hasMany('Pscl', [
            'foreignKey' => 'office_id',
        ]);
        $this->hasMany('CompanySurveyHistory', [
            'foreignKey' => 'office_id',
            'dependent' => true,
        ]);
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
            ->integer('id')
            ->allowEmptyString('id', null, 'create');

        $validator
            ->scalar('name')
            ->maxLength('name', 255)
            ->allowEmptyString('name');

        $validator
            ->scalar('address')
            ->maxLength('address', 255)
            ->allowEmptyString('address');

        $validator
            ->scalar('cap')
            ->maxLength('cap', 6)
            ->allowEmptyString('cap');

        $validator
            ->scalar('city')
            ->maxLength('city', 255)
            ->allowEmptyString('city');

        $validator
            ->scalar('province')
            ->maxLength('province', 2)
            ->allowEmptyString('province');

        return $validator;
    }

    /**
     * Returns a rules checker object that will be used for validating
     * application integrity.
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['company_id'], 'Companies'));

        return $rules;
    }

    public function afterSaveCommit(EventInterface $event, EntityInterface $entity, \ArrayObject $options): void
    {
        Cache::clearGroup('employee_stats');
    }

    public function afterDeleteCommit(EventInterface $event, EntityInterface $entity, \ArrayObject $options): void
    {
        Cache::clearGroup('employee_stats');
    }

    public function getIdsByCompanyId($company_id)
    {
        $offices = $this->find()->where([
            'company_id' => $company_id,
        ])->toArray();
        if (empty($offices)) {
            $offices = [['id' => -1]]; // così non può vedere niente
        }

        return array_map(function ($office) {
            return $office['id'];
        }, $offices);
    }

    // Definisco questo campo come json così è trasparente la conversione

    protected function _initializeSchema(TableSchemaInterface $schema): TableSchemaInterface
    {
        $schema->setColumnType('pascal_measures', 'json');
        $schema->setColumnType('aggregated_survey', 'json');

        return $schema;
    }

    /**
     * Automatically geocodes an office's coordinates if needed.
     *
     * Returns false when:
     * - The geocoding engine is unset or not a valid string
     * - Address, city, or province are empty/incomplete
     * - Coordinates already exist and $force is false
     *
     * On success, sets lat/lon and office_code (if empty). Never rethrows exceptions.
     *
     * @param \App\Model\Entity\Office $office The office to geocode
     * @param bool $force If true, geocodes even when coordinates exist
     * @return bool True if geocoding was attempted and succeeded, false otherwise
     */
    public function geocodeOffice(\App\Model\Entity\Office $office, bool $force = false): bool
    {
        // Get the geocoding engine via overridable method for testability
        $engine = $this->getGeocodingEngine();

        // No-op: engine not configured
        if (!$engine) {
            return false;
        }

        // No-op: required address fields are incomplete
        if (empty($office->address) || empty($office->city) || empty($office->province)) {
            return false;
        }

        // No-op: coordinates already exist and force is false
        if (!$force && !empty($office->lat) && !empty($office->lon)) {
            return false;
        }

        // Attempt geocoding
        try {
            $origin = new Origin([
                'address' => $office->address,
                'cap' => $office->cap,
                'city' => $office->city,
                'province' => $office->province,
            ]);

            $result = $office->geocode($origin);

            // -1 is the "not found" placeholder Office::geocode() starts from; anything
            // non-numeric or out of range is also a failure. Never store those.
            $lat = $result['lat'] ?? null;
            $lon = $result['lon'] ?? null;
            if (
                !is_numeric($lat) || !is_numeric($lon)
                || (float)$lat === -1.0 || (float)$lon === -1.0
                || abs((float)$lat) > 90 || abs((float)$lon) > 180
            ) {
                return false;
            }

            // Success: set coordinates
            $office->lat = $result['lat'];
            $office->lon = $result['lon'];

            // Set default office_code if empty
            if (empty($office->office_code) && !empty($office->id)) {
                $office->office_code = "SEDE-{$office->id}";
            }

            return true;
        } catch (\Throwable $e) {
            // Log and suppress; never fail the request
            \Cake\Log\Log::error(
                "Geocoding failed for office {$office->id}: " . $e->getMessage(),
                ['scope' => ['geocoding']]
            );

            return false;
        }
    }

    /**
     * Get the configured geocoding engine name.
     *
     * Overridable for testing; returns the engine name if set and valid, null otherwise.
     *
     * @return string|null
     */
    protected function getGeocodingEngine(): ?string
    {
        // Same coercion pattern as OriginsTable
        $configuredEngine = \Cake\Core\Configure::read('Geocoding.Engine', 'komootGeocoder');
        $engine = is_string($configuredEngine) && $configuredEngine !== ''
            ? $configuredEngine
            : null;

        return $engine;
    }
}
