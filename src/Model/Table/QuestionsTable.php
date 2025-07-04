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

use App\Model\Entity\Question;
use Cake\Core\Configure;
use Cake\Database\Schema\TableSchemaInterface;
use Cake\Datasource\ConnectionManager;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use Exception;

/**
 * Questions Model
 *
 * @property \App\Model\Table\AnswersTable&\Cake\ORM\Association\HasMany $Answers
 * @method \App\Model\Entity\Question newEmptyEntity()
 * @method \App\Model\Entity\Question newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\Question[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Question get($primaryKey, $options = [])
 * @method \App\Model\Entity\Question findOrCreate($search, ?callable $callback = null, $options = [])
 * @method \App\Model\Entity\Question patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\Question[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\Question|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\Question saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\Question[]|\Cake\Datasource\ResultSetInterface|false saveMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\Question[]|\Cake\Datasource\ResultSetInterface saveManyOrFail(iterable $entities, $options = [])
 * @method \App\Model\Entity\Question[]|\Cake\Datasource\ResultSetInterface|false deleteMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\Question[]|\Cake\Datasource\ResultSetInterface deleteManyOrFail(iterable $entities, $options = [])
 */
class QuestionsTable extends Table
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

        $this->setTable('questions');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');
        //NB: Le options non le posso tradurre così, se no poi non ho risposte consistenti
        //devo usare la funzione __() in modo da memorizzare il valore giusto e mostrare quello tradotto
        $this->addBehavior('Translate', ['fields' => ['description', 'long_description','options']]);

        $this->hasMany('Answers', [
            'foreignKey' => 'question_id',
        ]);
        $this->belongsTo('Sections');

        //https://book.cakephp.org/4/en/orm/associations.html#using-the-through-option
        $this->belongsToMany('Surveys', [
            'through' => 'QuestionsSurveys',
            ],);
        $this->hasMany('QuestionsSurveys', [
            'foreignKey' => 'question_id',
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
            ->maxLength('name', 20)
            ->allowEmptyString('name');

        $validator
            ->scalar('description')
            ->maxLength('description', 255)
            ->allowEmptyString('description');

        // $validator
        //     ->scalar('long_description')
        //     ->allowEmptyString('long_description');

        return $validator;
    }

    // Definisco questo campo come json così è trasparente la conversione

    protected function _initializeSchema(TableSchemaInterface $schema): TableSchemaInterface
    {
        $schema->setColumnType('options', 'json');
        $schema->setColumnType('conditions', 'json');

        return $schema;
    }

    /**
     * importQuestions
     * prende un'array con i titoli delle domande (tipicamente dal file excel, in formato limesurvey)
     * e genera le righe nella tabella questions (solo se la domanda non c'è già)
     * Se ci sono delle opzioni "spiattellate" si più colonne popola la colonna options
     *
     * @param array $qs
     * @return void
     */
    public function importQuestions(array $qs): void
    {
        $old_q = '';
        foreach ($qs as &$q) {
            if (empty($q['name'])) {
                continue;
            }
            $q['type'] = 'multiple';
            $q['name'] = trim(strtolower($q['name']));
            if ($old_q != $q['name']) {
                $old_q = trim(strtolower($q['name']));
                $q['type'] = 'single';
            }
            $q['descr'] = trim($q['descr']);
            $result = $this->generateNewQuestion($q);
        }
    }

    private function generateNewQuestion($q)
    {
        $result = $this->splitQuestionOption($q['descr']);
        //Cerco, se esiste quella domanda nel db (e recupero l'entity)
        $query = $this->findByName($q['name']);
        $qEntity = $query->first();
        //Se non la trovo esco subito
        if (empty($qEntity)) {
            //Preparo la domanda per il db (se non esiste)
            $qEntity = new Question([
                'name' => $q['name'],
                'description' => $result['question'],
            ]);
        }

        $qEntity->type = $q['type'];
        $this->addOptions($qEntity, $result['option']);

        //Salvo
        if ($this->save($qEntity)) {
            $qEntity = null;

            return $result;
        } else {
            $qEntity = null;

            return -1;
        }
    }

    public function splitQuestionOption($q)
    {
        $result = [];
        $re = '/^([^\[\b\n]+)(\[([^\]]+)\])?/im';   //https://regex101.com/

        preg_match($re, $q, $matches);

        //Se la stringa finisce con un trattino lo tolgo
        $m = $matches[1];
        if (!is_null($m)) {
            $l = strlen($m);
            if (substr($m, $l - 1, 1) == '-') {
                $m = substr($m, 0, $l - 1);
            }
        }

        $result['question'] = trim($m);    //Trim non dovrebbe essere necessario, ma non si sa mai
        $result['option'] = '';
        if (isset($matches[3])) {
            $result['option'] = $matches[3];
        }

        return $result;
    }

    /**
     * addOptions - Aggiunge un'opzione ad una domanda
     *
     * @param [type] $qEntity
     * @param [type] $option
     * @return void
     */
    private function addOptions(&$qEntity, $option)
    {
        //Aggiungo l'opzione nuova
        if (!empty($option)) {
            if (!empty($qEntity->options)) {
                $opts = $qEntity->options;
                //Devo controllare che quell'opzione non ci sia già
                if (!in_array($option, $opts)) {
                    $opts[] = $option;
                }
            } else {
                $opts[] = $option;
            }

            $qEntity->options = $opts;
        }
    }

    /**
     * normalizeOptions- converte le options di una domanda di tipo multi in una domanda di tipo array
     *
     * @param [type] $id
     * @return void
     */
    public function normalizeOptions($id)
    {
        //Carico le opzioni della domanda $id
        $q = $this->get($id);

        //Genero un array con struttura facendo reverse engineering
        // option1 -> [alternative1, alternative2]
        // option2 -> [alternative1, alternative2]
        // option3 -> [alternative1, alternative2]
        $badArray = (array)$q->options;
        $goodArray = [];
        foreach ($badArray as $b) {
            $bo = $b; //Converto in un'array associativa
            if (is_array($bo)) {
                foreach ($bo as $boKey => $boElement) {
                    //Devo evitare l'elemento 'Altro', perchè contiene risposte libere
                    if (strtolower($boKey) != 'altro') {
                        //Se l'elemento corrente non esiste nell'array buona lo aggiungo
                        if (!array_key_exists($boKey, $goodArray)) {
                            $goodArray[$boKey] = [];
                        }

                        //Se l'opzione esistente non esiste nell'opzione corrente la aggiungo
                        if (!in_array($boElement, $goodArray[$boKey])) {
                            $goodArray[$boKey][] = $boElement;
                        }
                    }
                }
            }
        }

        //Salvo a db il json_encode della normalizzazione
        $q->options = $goodArray;
        $q->type = 'array';
        if (!$this->save($q)) {
            throw new Exception("Impossibile salvare le opzioni modificate per la domanda $id");
        }
    }

    public function unused($survey_id)
    {
        $connection = ConnectionManager::get('default');
        $results = $connection->execute('SELECT question_id as id FROM questions_surveys WHERE survey_id = :survey_id', [
            'survey_id' => $survey_id,
        ])->fetchAll('assoc');
        $conditions = ['name IS NOT' => null];
        $special = array_merge(Configure::read('Questions_spos'), Configure::read('Questions'));
        if (!empty($results)) {
            $conditions['id NOT IN'] = array_merge(array_map(function ($row) {
                return $row['id'];
            }, $results), $special);
        }

        return $this->find('translations')->where($conditions)->contain([
            'QuestionsSurveys' => function ($query) use ($survey_id) {
                return $query->find('translations')->where(['survey_id' => $survey_id]);
            },
        ])->limit(1000)->toArray();
    }

    public function special($survey_id)
    {
        $connection = ConnectionManager::get('default');
        $results = $connection->execute('SELECT question_id as id FROM questions_surveys WHERE survey_id = :survey_id', [
            'survey_id' => $survey_id,
        ])->fetchAll('assoc');
        $conditions = ['name IS NOT' => null];
        // $special= array_merge(Configure::read("Questions_spos"),Configure::read("Questions"));
        if (!empty($results)) {
            $conditions['id NOT IN'] = array_merge(array_map(function ($row) {
                return $row['id'];
            }, $results));
        }
        $conditions['id IN'] = Configure::read('Questions');

        return $this->find('translations')->where($conditions)->contain([
            'QuestionsSurveys' => function ($query) use ($survey_id) {
                return $query->find('translations')->where(['survey_id' => $survey_id]);
            },
        ])->toArray();
    }

    public function isCompulsory($question_id, $survey_id)
    {
        $connection = ConnectionManager::get('default');
        $results = $connection->execute('SELECT compulsory FROM questions_surveys WHERE survey_id = :survey_id AND question_id = :question_id', [
            'survey_id' => $survey_id,
            'question_id' => $question_id,
        ])->fetchAll('assoc');
        if (empty($results)) {
            throw new \Exception('Domanda non trovata');
        }

        return $results[0]['compulsory'] == 1; // se c'è è una sola
    }

    public function isOwnedBy($question_id, $user_id)
    {
        $connection = ConnectionManager::get('default');
        $results = $connection->execute('SELECT id FROM questions WHERE id = :id AND creator_id = :creator_id', [
            'id' => $question_id,
            'creator_id' => $user_id,
        ])->fetchAll('assoc');

        return !empty($results);
    }
    // change state moma_area field
    public function changeState($id, $state)
    {
        $question = $this->get($id);
        $question->moma_area = $state;
        $this->save($question);
        return $question;
    }
}
