<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Actions Model
 *
 * @property \App\Model\Table\MeasuresTable&\Cake\ORM\Association\BelongsTo $Measures
 * @method \App\Model\Entity\Action newEmptyEntity()
 * @method \App\Model\Entity\Action newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\Action[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Action get($primaryKey, $options = [])
 * @method \App\Model\Entity\Action findOrCreate($search, ?callable $callback = null, $options = [])
 * @method \App\Model\Entity\Action patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\Action[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\Action|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\Action saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\Action[]|\Cake\Datasource\ResultSetInterface|false saveMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\Action[]|\Cake\Datasource\ResultSetInterface saveManyOrFail(iterable $entities, $options = [])
 * @method \App\Model\Entity\Action[]|\Cake\Datasource\ResultSetInterface|false deleteMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\Action[]|\Cake\Datasource\ResultSetInterface deleteManyOrFail(iterable $entities, $options = [])
 */
class ActionsTable extends Table
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

        $this->setTable('actions');
        $this->setDisplayField('title');
        $this->setPrimaryKey('id');

        $this->belongsTo('Pscl', [
            'foreignKey' => 'pscl_id',
            'joinType' => 'INNER',
        ]);
        $this->belongsTo('Measures', [
            'foreignKey' => 'measure_id',
            'joinType' => 'INNER',
        ]);

        $this->addBehavior('Timestamp');
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
            ->integer('pscl_id')
            ->requirePresence('pscl_id', 'create')
            ->notEmptyString('pscl_id');

        $validator
            ->integer('measure_id')
            ->notEmptyString('measure_id');

        $validator
            ->scalar('title')
            ->maxLength('title', 255)
            ->requirePresence('title', 'create')
            ->notEmptyString('title');

        $validator
            ->scalar('description');

        $validator
            ->scalar('detailed_description');

        $validator
            ->integer('level')
            ->allowEmptyString('level');

        $validator
            ->integer('priority')
            ->allowEmptyString('priority');

        $validator
            ->boolean('benefits_employees_time');

        $validator
            ->boolean('benefits_employees_costs');

        $validator
            ->boolean('benefits_employees_safety');

        $validator
            ->boolean('benefits_employees_comfort');

        $validator
            ->boolean('benefits_employees_social');

        $validator
            ->boolean('benefits_employees_other');

        $validator
            ->scalar('benefits_employees_other_text')
            ->maxLength('benefits_employees_other_text', 255)
            ->allowEmptyString('benefits_employees_other_text');

        $validator
            ->boolean('benefits_company_regolarita');

        $validator
            ->boolean('benefits_company_dedizione');

        $validator
            ->boolean('benefits_company_image');

        $validator
            ->boolean('benefits_company_other');

        $validator
            ->scalar('benefits_company_other_text')
            ->maxLength('benefits_company_other_text', 255)
            ->allowEmptyString('benefits_company_other_text');

        $validator
            ->boolean('benefits_community_emissions')
            ->allowEmptyString('benefits_community_emissions');

        $validator
            ->boolean('benefits_community_congestion')
            ->allowEmptyString('benefits_community_congestion');

        $validator
            ->boolean('benefits_community_safety')
            ->allowEmptyString('benefits_community_safety');

        $validator
            ->boolean('benefits_community_other')
            ->allowEmptyString('benefits_community_other');

        $validator
            ->scalar('benefits_community_other_text')
            ->maxLength('benefits_community_other_text', 255)
            ->allowEmptyString('benefits_community_other_text');

        $validator
            ->scalar('usage_methods')
            ->requirePresence('usage_methods', 'create')
            ->allowEmptyString('usage_methods');

        $validator
            ->scalar('target_audience')
            ->requirePresence('target_audience', 'create')
            ->allowEmptyString('target_audience');

        $validator
            ->scalar('timeline')
            ->requirePresence('timeline', 'create')
            ->allowEmptyString('timeline');

        $validator
            ->scalar('economic_resources')
            ->requirePresence('economic_resources', 'create')
            ->allowEmptyString('economic_resources');

        $validator
            ->scalar('human_resources')
            ->requirePresence('human_resources', 'create')
            ->allowEmptyString('human_resources');

        $validator
            ->scalar('instrumental_resources')
            ->requirePresence('instrumental_resources', 'create')
            ->allowEmptyString('instrumental_resources');

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
        $rules->add($rules->existsIn('pscl_id', 'Pscl'), ['errorField' => 'pscl_id']);
        $rules->add($rules->existsIn('measure_id', 'Measures'), ['errorField' => 'measure_id']);

        return $rules;
    }
}
