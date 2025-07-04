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
use Cake\Validation\Validator;

/**
 * UnionRollbacks Model
 *
 * @property \App\Model\Table\QuestionsSurveysTable&\Cake\ORM\Association\BelongsTo $QuestionsSurveys
 * @property \App\Model\Table\AnswersTable&\Cake\ORM\Association\BelongsTo $Answers
 * @method \App\Model\Entity\UnionRollback newEmptyEntity()
 * @method \App\Model\Entity\UnionRollback newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\UnionRollback[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\UnionRollback get($primaryKey, $options = [])
 * @method \App\Model\Entity\UnionRollback findOrCreate($search, ?callable $callback = null, $options = [])
 * @method \App\Model\Entity\UnionRollback patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\UnionRollback[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\UnionRollback|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\UnionRollback saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\UnionRollback[]|\Cake\Datasource\ResultSetInterface|false saveMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\UnionRollback[]|\Cake\Datasource\ResultSetInterface saveManyOrFail(iterable $entities, $options = [])
 * @method \App\Model\Entity\UnionRollback[]|\Cake\Datasource\ResultSetInterface|false deleteMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\UnionRollback[]|\Cake\Datasource\ResultSetInterface deleteManyOrFail(iterable $entities, $options = [])
 */
class UnionRollbacksTable extends Table
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

        $this->setTable('union_rollbacks');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->belongsTo('QuestionsSurveys', [
            'foreignKey' => 'questions_survey_id',
        ]);
        $this->belongsTo('Answers', [
            'foreignKey' => 'answers_id',
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
            ->dateTime('date')
            ->allowEmptyDateTime('date');

        $validator
            ->integer('remove_question_id')
            ->allowEmptyString('remove_question_id');

        $validator
            ->integer('destination_question_id')
            ->allowEmptyString('destination_question_id');

        $validator
            ->scalar('questions_survey_id')
            ->allowEmptyString('questions_survey_id');

        $validator
            ->scalar('answers_id')
            ->allowEmptyString('answers_id');

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
        $rules->add($rules->existsIn('questions_survey_id', 'QuestionsSurveys'), ['errorField' => 'questions_survey_id']);
        $rules->add($rules->existsIn('answers_id', 'Answers'), ['errorField' => 'answers_id']);

        return $rules;
    }
}
