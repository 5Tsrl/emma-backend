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

use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Sections Model
 *
 * @property \Moma\Model\Table\QuestionsSurveysTable&\Cake\ORM\Association\HasMany $QuestionsSurveys
 * @method \Moma\Model\Entity\Section newEmptyEntity()
 * @method \Moma\Model\Entity\Section newEntity(array $data, array $options = [])
 * @method \Moma\Model\Entity\Section[] newEntities(array $data, array $options = [])
 * @method \Moma\Model\Entity\Section get($primaryKey, $options = [])
 * @method \Moma\Model\Entity\Section findOrCreate($search, ?callable $callback = null, $options = [])
 * @method \Moma\Model\Entity\Section patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \Moma\Model\Entity\Section[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \Moma\Model\Entity\Section|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \Moma\Model\Entity\Section saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \Moma\Model\Entity\Section[]|\Cake\Datasource\ResultSetInterface|false saveMany(iterable $entities, $options = [])
 * @method \Moma\Model\Entity\Section[]|\Cake\Datasource\ResultSetInterface saveManyOrFail(iterable $entities, $options = [])
 * @method \Moma\Model\Entity\Section[]|\Cake\Datasource\ResultSetInterface|false deleteMany(iterable $entities, $options = [])
 * @method \Moma\Model\Entity\Section[]|\Cake\Datasource\ResultSetInterface deleteManyOrFail(iterable $entities, $options = [])
 */
class SectionsTable extends Table
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

        $this->setTable('sections');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->hasMany('QuestionsSurveys', [
            'foreignKey' => 'section_id',
            'className' => 'QuestionsSurveys',
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
            ->requirePresence('name', 'create')
            ->notEmptyString('name');

        return $validator;
    }
}
