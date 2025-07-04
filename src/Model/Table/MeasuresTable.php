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

use Cake\Database\Schema\TableSchemaInterface;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Measures Model
 *
 * @property \App\Model\Table\PillarsTable&\Cake\ORM\Association\BelongsTo $Pillars
 * @method \App\Model\Entity\Measure newEmptyEntity()
 * @method \App\Model\Entity\Measure newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\Measure[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Measure get($primaryKey, $options = [])
 * @method \App\Model\Entity\Measure findOrCreate($search, ?callable $callback = null, $options = [])
 * @method \App\Model\Entity\Measure patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\Measure[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\Measure|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\Measure saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\Measure[]|\Cake\Datasource\ResultSetInterface|false saveMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\Measure[]|\Cake\Datasource\ResultSetInterface saveManyOrFail(iterable $entities, $options = [])
 * @method \App\Model\Entity\Measure[]|\Cake\Datasource\ResultSetInterface|false deleteMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\Measure[]|\Cake\Datasource\ResultSetInterface deleteManyOrFail(iterable $entities, $options = [])
 */
class MeasuresTable extends Table
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

        $this->setTable('measures');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->belongsTo('Pillars', [
        'foreignKey' => 'pillar_id',
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
        ->scalar('slug')
        ->maxLength('slug', 50)
        ->allowEmptyString('slug');

        $validator
        ->scalar('name')
        ->maxLength('name', 255)
        ->allowEmptyString('name');

        $validator
        ->scalar('description')
        ->allowEmptyString('description');

        $validator
        ->scalar('img')
        ->maxLength('img', 255)
        ->allowEmptyString('img');

        $validator
        ->scalar('target')
        ->allowEmptyString('target');

        $validator
        ->integer('type')
        ->allowEmptyString('type');

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
        $rules->add($rules->existsIn(['pillar_id'], 'Pillars'), ['errorField' => 'pillar_id']);

        return $rules;
    }

    // Definisco questo campo come json così è trasparente la conversione
    protected function _initializeSchema(TableSchemaInterface $schema): TableSchemaInterface
    {
        $schema->setColumnType('inputs', 'json');

        return $schema;
    }
}
