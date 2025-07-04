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

use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Utility\Text;
use Cake\Validation\Validator;

/**
 * Companies Model
 *
 * @property \App\Model\Table\MomasTable&\Cake\ORM\Association\BelongsTo $Momas
 * @property \App\Model\Table\CompanyTypesTable&\Cake\ORM\Association\BelongsTo $CompanyTypes
 * @property \App\Model\Table\SurveysTable&\Cake\ORM\Association\HasMany $Surveys
 * @method \App\Model\Entity\Company newEmptyEntity()
 * @method \App\Model\Entity\Company newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\Company[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Company get($primaryKey, $options = [])
 * @method \App\Model\Entity\Company findOrCreate($search, ?callable $callback = null, $options = [])
 * @method \App\Model\Entity\Company patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\Company[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\Company|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\Company saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\Company[]|\Cake\Datasource\ResultSetInterface|false saveMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\Company[]|\Cake\Datasource\ResultSetInterface saveManyOrFail(iterable $entities, $options = [])
 * @method \App\Model\Entity\Company[]|\Cake\Datasource\ResultSetInterface|false deleteMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\Company[]|\Cake\Datasource\ResultSetInterface deleteManyOrFail(iterable $entities, $options = [])
 */
class CompaniesTable extends Table
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

        $this->setTable('companies');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->hasMany('Surveys', [
        'foreignKey' => 'company_id',
        'dependent' => true,
        ]);
        $this->hasMany('Offices', [
        'foreignKey' => 'company_id',
        'dependent' => true,
        ]);
        $this->hasMany('Users', [
        'foreignKey' => 'company_id',
        'dependent' => true,
        ]);
        $this->belongsTo('CompanyTypes', [
        'className' => 'CompanyTypes',
        'foreignKey' => 'type',
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
        ->maxLength('cap', 5)
        ->allowEmptyString('cap');

        $validator
        ->scalar('city')
        ->maxLength('city', 45)
        ->allowEmptyString('city');

        $validator
        ->scalar('province')
        ->maxLength('province', 2)
        ->allowEmptyString('province');

        $validator
        ->scalar('country')
        ->maxLength('country', 2)
        ->allowEmptyString('country');

        $validator
        ->nonNegativeInteger('num_employees')
        ->allowEmptyString('num_employees');

        $validator
        ->scalar('ateco')
        ->maxLength('ateco', 20)
        ->allowEmptyString('ateco');

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
        $rules->add($rules->existsIn(['type'], 'CompanyTypes'));

        return $rules;
    }

    //Generate slug from company name and store it in company_code
    public function getCodeOrCreate($id)
    {
        $company = $this->get($id);
        if (!empty($company->company_code)) {
            return $company->company_code;
        }

        $company_code = strtolower(Text::slug($company->name, '-'));
        $company->company_code = $company_code;
        $this->save($company);

        return $company_code;
    }
}
