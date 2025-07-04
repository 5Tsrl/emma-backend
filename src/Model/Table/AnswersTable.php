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

use App\Model\Entity\Answer;
use App\Model\Entity\Question;
use Cake\Database\Schema\TableSchemaInterface;
use Cake\Log\Log;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Answers Model
 *
 * @property \App\Model\Table\QuestionsTable&\Cake\ORM\Association\BelongsTo $Questions
 * @property \App\Model\Table\SurveysTable&\Cake\ORM\Association\BelongsTo $Surveys
 * @property \App\Model\Table\UsersTable&\Cake\ORM\Association\BelongsTo $Users
 * @method \Moma\Model\Entity\Answer newEmptyEntity()
 * @method \Moma\Model\Entity\Answer newEntity(array $data, array $options = [])
 * @method \Moma\Model\Entity\Answer[] newEntities(array $data, array $options = [])
 * @method \Moma\Model\Entity\Answer get($primaryKey, $options = [])
 * @method \Moma\Model\Entity\Answer findOrCreate($search, ?callable $callback = null, $options = [])
 * @method \Moma\Model\Entity\Answer patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \Moma\Model\Entity\Answer[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \Moma\Model\Entity\Answer|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \Moma\Model\Entity\Answer saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \Moma\Model\Entity\Answer[]|\Cake\Datasource\ResultSetInterface|false saveMany(iterable $entities, $options = [])
 * @method \Moma\Model\Entity\Answer[]|\Cake\Datasource\ResultSetInterface saveManyOrFail(iterable $entities, $options = [])
 * @method \Moma\Model\Entity\Answer[]|\Cake\Datasource\ResultSetInterface|false deleteMany(iterable $entities, $options = [])
 * @method \Moma\Model\Entity\Answer[]|\Cake\Datasource\ResultSetInterface deleteManyOrFail(iterable $entities, $options = [])
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class AnswersTable extends Table
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

        $this->setTable('answers');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('Questions', [
        'foreignKey' => 'question_id',
        'className' => 'Questions',
        ]);
        $this->belongsTo('Surveys', [
        'foreignKey' => 'survey_id',
        'className' => 'Surveys',
        ]);
        $this->belongsTo('Users', [
        'foreignKey' => 'user_id',
        'className' => 'Users',
        ]);
        //TODO: Devo creare un'associazione tra Answers e Origins
        //left join origins o on (o.survey_id = a.survey_id and o.user_id = a.user_id)
        $this->belongsTo('Origins', [
        'foreignKey' => false,
        'className' => 'Origins',
        'conditions' => [
        'Answers.user_id = Origins.user_id',
        'Answers.survey_id = Origins.survey_id',
        ],
        ]);
    }

    // Definisco questo campo come json così è trasparente la conversione

    protected function _initializeSchema(TableSchemaInterface $schema): TableSchemaInterface
    {
        $schema->setColumnType('answer', 'json');

        return $schema;
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
        $rules->add($rules->existsIn(['question_id'], 'Questions'));
        $rules->add($rules->existsIn(['survey_id'], 'Surveys'));

        return $rules;
    }

    private function normalizeQ($q)
    {
        return is_string($q) ? strtolower(trim($q)) : $q;
    }

    public function importAnswers(array $answers, array $questions, array $q_descr, int $survey_id, int $user_id)
    {
        $ai = array_keys($answers);
        $num_a = count($answers);
        $aEntity = new Answer();
        $qEntity = new Question();

        $last_q = '';
        for ($i = 0; $i < $num_a; $i++) {
            //Leggo il nome della domanda corrispondente a questa risposta
            $q = $this->normalizeQ($questions[$ai[$i]]);

            if (empty($q)) {
                continue;
            }

            if ($last_q != $q) {
                $last_q = $q;
                //Cerco  nel db la domanda solo se è diversa
                $qEntity = $this->Questions
                ->findByName($q)
                ->cache("question-$q")
                ->first();
            }

            if (!empty($qEntity)) {
                $qid = $qEntity->id;
                $qType = $qEntity->type;

                switch ($qType) {
                    case 'multiple':
                        if (!empty($answers[$ai[$i]])) {
                    //Estraggo l'opzione dalla domanda, per comporre la risposta come opzione => risposta
                    //Es: 'Vieni in auto' => sì
                            $o = $this->Questions->splitQuestionOption($q_descr[$ai[$i]])['option'];
                    //Se la risposta ha opzione vuota assegno 0 come chiave
                            if (empty($o)) {
                                      $o = '0';
                            }
                    //Ricarico la risposta precedente
                    //Estraggo l'opzione dalla descrizione della domanda corrente
                    //Cerco l'opzione tra le opzioni della domanda corrente
                    //Segno come risposta: Opzione: valore
                    //TODO: eventualmente risolvere come una molti-molti
                            if ($i == 0 || $q != $this->normalizeQ($questions[$ai[$i - 1]])) {
                                  //E la prima risposta di una domanda multipla
                                  $existingAnswer = [];
                                  unset($aEntity);
                                  $aEntity = new Answer([
                                'question_id' => $qid,
                                'user_id' => sprintf('%02d:%04d', $survey_id, $user_id),
                                'survey_id' => $survey_id,
                                      ]);
                            }

                            $existingAnswer[$o] = $answers[$ai[$i]];
                            $aEntity->answer = json_encode($existingAnswer);
                        }
                        break;
                    default:
                        unset($aEntity);
                      //Preparo l'entity con la risposta
                        $aEntity = new Answer([
                        'question_id' => $qid,
                        'user_id' => sprintf('%02d:%04d', $survey_id, $user_id),
                        'survey_id' => $survey_id,
                        'answer' => $answers[$ai[$i]],
                        ]);
                }
                //Salvo e ciclo su tutte le colonne con le risposte di questo utente
                if (!$this->save($aEntity)) {
                    Log::write(LOG_ERR, "Errore durante la scrittura dell'answer: " . var_export($aEntity, true));

                    return false;
                }
            }
        }
    }

    /* public function findComplete(Query $query, array $options)
    {
      $query
    } */
}
