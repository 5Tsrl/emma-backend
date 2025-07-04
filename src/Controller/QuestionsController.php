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


namespace App\Controller;

use Cake\Core\Configure;
use Cake\Database\Query;
use Cake\I18n\FrozenTime;
use Cake\I18n\I18n;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;
use Exception;

/**
 * Questions Controller
 *
 * @property \App\Model\Table\QuestionsTable $Questions
 * @method \App\Model\Entity\Question[]|\Cake\Datasource\ResultSetInterface paginate($object = null, array $settings = [])
 */
class QuestionsController extends AppController
{
    private $Answers;
    private $Surveys;

    /**
     * Initialize.
     */
    public function initialize(): void
    {
        parent::initialize();
        $this->Authentication->allowUnauthenticated([
            'getTranslations',
        ]);
        if ($this->components()->has('Security')) {
            $this->Security->setConfig(
                'unlockedActions',
                [
                    'getTranslations',
                ]
            );
        }
        $this->Authorization->skipAuthorization();
        $this->loadComponent('Paginator');
    }

    /**
     * Index method
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        $this->allowRolesOnly(['admin', 'moma','moma_area']);

        $query = $this->Questions->find()
            ->contain('Surveys', function (Query $q) {
                return $q
                ->select(['id','name','year']);
                // ->limit(2000);
            })
            ->contain('Answers', function (Query $q) {
                return $q
                ->select(['question_id','count' => $q->func()->count('question_id')])
                ->group(['question_id']);
            })
            ->select(['id','name', 'description', 'options']);

        // $this->set(compact('questions'));
        // $this->viewBuilder()->setOption('serialize', ['questions']);
        $questions = $this->paginate($query);
        $pagination = $this->Paginator->getPagingParams();
        $this->set(compact('questions', 'pagination'));
        $this->viewBuilder()->setOption('serialize', ['questions','pagination']);
    }

    public function list()
    {
        $this->allowRolesOnly(['admin', 'moma', 'moma_area']);

        $questions = $this->Questions->find()
            ->select(['id','name', 'description','moma_area'])
            ->order(['name' => 'ASC']);
        $this->Authorization->applyScope($questions);
        $this->set(compact('questions'));
        $this->viewBuilder()->setOption('serialize', ['questions']);
    }

    /**
     * View method
     *
     * @param string|null $id Question id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $this->allowRolesOnly(['admin', 'moma','moma_area']);

        $question = $this->Questions->get($id, [
            //'contain' => ['Answers'],
        ]);

        $this->set(compact('question'));
        $this->viewBuilder()->setOption('serialize', ['question']);
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $this->allowRolesOnly(['admin', 'moma','moma_area']);

        $question = $this->Questions->newEmptyEntity();
        if ($this->request->is('post')) {
            $question = $this->Questions->patchEntity($question, $this->request->getData());
            if ($this->Questions->save($question)) {
                if (!$this->request->is('json')) {
                    $this->Flash->success(__('The question has been saved.'));

                    return $this->redirect(['action' => 'index']);
                } else {
                    $this->set(compact('question'));
                    $this->viewBuilder()->setOption('serialize', ['question']);
                }
            }
            $this->Flash->error(__('The question could not be saved. Please, try again.'));
        }
        $this->set(compact('question'));
    }

    /**
     * Edit method
     *
     * @param string|null $id Question id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     *
     * DA RIVEDERE - NON FUNZIONA SULL'EDIT (FA SEMPRE ADD), INOLTRE LA FIRMA DEL METODO LATO VUE
     * E' CAMBIATA (NON PASSO L'ID NELL'URL)
     * AL MOMENTO USO editFromSurvey() (veid sotto)
     */
    public function edit($id = null)
    {
        $this->allowRolesOnly(['admin', 'moma','moma_area']);

        if (!empty($id)) {
            $question = $this->Questions->find()
                ->where(['id' => $id])
                ->contain(['Surveys'])
                ->first();

            if (
                (isset($question['survey']['opening_date']) && $question['survey']['opening_date'] > date('Y-m-d H:i:s')) ||
                (isset($question['survey']['closing_date']) && $question['survey']['closing_date'] < date('Y-m-d H:i:s'))
            ) {
                throw new Exception('Survey is closed');
            }
        } else {
            $question = $this->Questions->newEntity($this->request->getData(), ['associated' => ['Surveys._joinData']]);
        }

        //Devo salvare prima la domanda da sola, se no non salva l'associazione
        $this->Questions->save($question);

        if ($this->request->is(['patch', 'post', 'put'])) {
            if (is_array($this->request->getData('options'))) {
                $question->options = json_encode($this->request->getData('options'));
            }
            if (!empty($this->request->getData('_joinData'))) {
                $jd = $this->request->getData('_joinData');
                if (!empty($jd['survey_id'])) {
                    // $question->surveys = ['_ids' => [$jd['survey_id']]]; - non funziona in nessun variante
                    $survey = $this->Questions->Surveys->findById($jd['survey_id'])->first();
                    $this->Questions->Surveys->link($question, [$survey]);
                    $question->surveys[0]->_joinData->section_id = $question->section_id;
                }
            }

            if ($this->Questions->save($question, ['associated' => ['Surveys._joinData']])) {
                if (!$this->request->is('json')) {
                    $this->Flash->success(__('The question has been saved.'));

                    return $this->redirect(['action' => 'index']);
                } else {
                    $this->set(compact('question'));
                    $this->viewBuilder()->setOption('serialize', ['question']);

                    return;
                }
            }
            if (!$this->request->is('json')) {
                $this->Flash->error(__('The question could not be saved. Please, try again.'));
            } else {
                throw new Exception('Errore durante il salvataggio della domanda');
            }
        }
        $this->set(compact('question'));
    }

    /**
     * Delete method
     *
     * @param string|null $id Question id.
     * @return \Cake\Http\Response|null|void Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->allowRolesOnly(['admin']);

        $this->request->allowMethod(['post', 'delete']);
        $question = $this->Questions->get($id);
        // search if the question is used in any conditions of other questions where conditions is a json
        $conditions = $this->Questions->find()
            ->where(function ($exp) use ($id) {
                return $exp->in('JSON_UNQUOTE(JSON_EXTRACT(conditions, "$.question"))', [$id]);
            });
        // debug($conditions); die;
        $conditionsCount = $conditions->count();
        $this->Flash->success(__("Cleared conditions for {$conditionsCount} questions."));
        // // save conditions as empty
        foreach ($conditions as $condition) {
            $condition->conditions = '';
            $this->Questions->save($condition);
        }
        
        // empty conditions of the questionsurveys
        $questionSurveys = $this->Questions->QuestionsSurveys->find()
            ->where(function ($exp) use ($id) {
                return $exp->in('JSON_UNQUOTE(JSON_EXTRACT(conditions, "$.question"))', [$id]);
            });
        // count the number of questionsurveys with conditions
        $questionSurveysCount = $questionSurveys->count();
        $this->Flash->success(__("Cleared conditions for {$questionSurveysCount} questionsurveys."));
        foreach ($questionSurveys as $questionSurvey) {
            $questionSurvey->conditions = '';
            $this->Questions->QuestionsSurveys->save($questionSurvey);
        }
        
        
        if ($this->Questions->delete($question)) {
            $this->Flash->success(__('The question has been deleted.'));
        } else {
            $this->Flash->error(__('The question could not be deleted. Please, try again.'));
        }

        return $this->redirect(['action' => 'index']);
    }

    /**
     * converte le domande da normalized a multiple
     *
     * NON USATO
     */
    /*public function convert_multi($id)
    {
      $this->Questions->normalizeOptions($id);
      $this->autoRender = false;
      $this->Flash->success('Domanda Multi convertita in tipo Array');

      return $this->redirect($this->referer());
    }*/

    /**
     * restituisce le domande (complete di opzioni) utilizzate come filtri delle origin
     */
    public function originFilters()
    {
        $this->allowRolesOnly(['admin', 'moma','moma_area']);
        $questions = $this->request->getQuery('q');
        $questionsArray = explode(',', $questions);
        $survey_id = $this->request->getQuery('survey_id');        if ($survey_id == 'null' || $survey_id == 'undefined') {
            $filters = $this->Questions
            ->find()
            ->where([
                'id IN' => $questionsArray,
            ])
            ->toArray();
        } else {
            $filters = $this->Questions
            ->find()
            ->contain([
                'QuestionsSurveys' => function ($query) use ($survey_id) {
                    return $query->find('translations')->where(['survey_id' => $survey_id]);
                },
            ])
            ->where([
                'id IN' => $questionsArray,
            ])
            ->toArray();
        }

        $this->set('filters', array_map(function ($filter) use ($survey_id) {
            if ($survey_id == 'null' || $survey_id == 'undefined') {
                $filter['options'] = $filter['options'];
            } else {
                if (!empty($filter->questions_surveys[0]['options'])){
                    $filter['options'] = $filter->questions_surveys[0]['options'];
                }                
            }
            if ($filter['type'] == 'multiple') {
                // ottieni le effettive opzioni
                $o = $filter['options'][0];
                if (is_array($o) || is_object($o)) {
                    $opts = [];
                    foreach ($filter['options'] as $o) {
                        $opts = array_merge($opts, array_keys((array)$o));
                    }
                    $filter['options'] = array_unique($opts);
                }
            }

            return $filter;
        }, $filters));
        $this->viewBuilder()->setOption('serialize', ['filters']);
    }

    public function unused($survey_id)
    {
        $this->allowRolesOnly(['admin', 'moma']);
        $this->set('questions', $this->Questions->unused($survey_id));
        $this->viewBuilder()->setOption('serialize', ['questions']);
    }

    public function special($survey_id)
    {
        $this->allowRolesOnly(['admin', 'moma']);
        $this->set('questions', $this->Questions->special($survey_id));
        $this->viewBuilder()->setOption('serialize', ['questions']);
    }

    /**
     * @param mixed $id  -> id della domanda per cui voglio recuperare le traduzioni
     * @return \App\Controller\json version of the translations
     */
    public function getTranslations($id, $survey_id)
    {
        $question = $this->Questions->find('translations')
            ->where(['id' => $id])
            ->first();

        $t = $question->_translations;
        //TODO: Generalizzare per altre lingue, al momento funziona sono con l'inglese
        if (!empty($t['en']->options) && !is_array($t['en']->options)) {
            $t['en']->options = json_decode($t['en']->options);
        }

        $this->QuestionSurveys = TableRegistry::getTableLocator()->get('QuestionsSurveys');
        $qs = $this->QuestionSurveys->find('translations')
            ->where(['survey_id' => $survey_id])
            ->where(['question_id' => $id])
            ->first();
        $tqs = $qs->_translations;
        if (!empty($tqs['en']->options) && !is_array($tqs['en']->options)) {
            $tqs['en']->options = json_decode($tqs['en']->options);
        }
        $this->set(compact('t'));
        $this->set(compact('tqs'));
        $this->viewBuilder()->setOption('serialize', ['t','tqs']);
    }

    public function editFromSurvey()
    {
        $this->allowRolesOnly(['admin', 'moma']);

        $identity = $this->Authentication->getIdentity();
        $role = $identity->get('role');
        $userId = $identity->get('id');
        $company_id = $identity->get('company_id');
        $isMomaAzienda = $role == 'moma' && !empty($company_id);

        if ($this->request->is(['post', 'put'])) {
            $errorMsg = false;
            try {
                $data_all = $this->request->getData();
                $globalmodification = $data_all['globalmodification'];
                $data = $data_all['question'];
                $survey_id = $data['questions_surveys'][0]['survey_id'];
                $question_id = $data['id'];

                // if (!empty($data['long_description']) && !is_array($data['long_description'])) {
                //     $data['long_description'] =array('description' => $data['long_description']);
                // }
                if (!empty($data['id'])) {
                    $question = $this->Questions->get($data['id']);
                    if (empty($question)) {
                        throw new \Exception("Domanda {$data['id']} non trovata");
                    }
                    // domanda esistente, verifica che l'utente sia abilitato a modificarla
                    if ($isMomaAzienda && $question['creator_id'] != $userId) { // altimenti può sempre se ha un altro ruolo
                        // non posso modificare nulla della domanda (solo i joinData)
                        $data = [
                            'id' => $data['id'],
                            '_joinData' => $data['_joinData'],
                        ];
                    }
                } else { // nuova domanda, aggiungi il creator_id
                    $question = $this->Questions->newEmptyEntity();
                    $data['creator_id'] = $userId;
                }
                // $joinData = $data['questions_surveys'][0];
                unset($data['_joinData']); // per tranquillità
                $question = $this->Questions->patchEntity($question, $data);

                // Gestione della traduzione: mi aspetto di ricevere un array [en] => ['campo' => traduzione, 'campo2'=>traduzione]
                //TODO: Gestire più lingue, trasformando $langs in un array e questo in un loop
                $lang = 'en';
                if (isset($data['_translations'][$lang])) {
                    foreach ($data['_translations'][$lang] as $field => $value) {
                        // if ($field == 'options') {
                        //     $question->translation($lang)->$field = json_decode($value);
                        // } else {
                            $question->translation($lang)->$field = $value;
                        // }
                    }
                }
                if (isset($question['questions_surveys'][0][$lang])) {
                    foreach ($question['questions_surveys'][0][$lang] as $field => $value) {
                        // if ($field == 'options') {
                        //     $qs->translation($lang)->$field = json_encode($value);
                        // } else {
                            // $qs->_translations[$lang]->$field = $value;
                            $question['questions_surveys'][0]->translation($lang)->$field = $value;
                        // }
                    }
                }

                // salva/aggiorna il relativo record questions_surveys
                // $connection = ConnectionManager::get('default');

                // $results = $connection->execute('SELECT id FROM questions_surveys WHERE survey_id = :survey_id AND question_id = :question_id', [
                //     'survey_id' => $joinData['survey_id'],
                //     'question_id' => $question['id'],
                // ])->fetchAll('assoc');
                // $this->Questions->QuestionsSurveys->find
                // // verifica che l'id sia corretto
                // if (!empty($results)) {
                //     if (!empty($joinData['id'])) {
                //         if ($joinData['id'] != $results[0]['id']) { // check di sicurezza
                //             throw new \Exception("L'associazione con la domanda non è corretta");
                //         }
                //     }
                //     $joinData['id'] = $results[0]['id']; // in realtà è solo 1 sempre
                // }

                // if (empty($joinData['question_id'])) {  // inutile, ma per sicurezza
                //     $joinData['question_id'] = empty($question['id']) ? 1 : $question['id'];
                // }

                if (empty($question['questions_surveys'][0]['id'])) { // insert
                    // normalizza "compulsory" e "hidden"
                    // $joinData['compulsory'] = $joinData['compulsory'] ? 1 : 0;
                    // $joinData['hidden'] = $joinData['hidden'] ? 1 : 0;
                    $question = $this->insertQuestionSurvey($question, $lang);
                }
                //  else {
                //     // rimuovi compulsory e hidden (che sono gestiti a parte), salvo in pratica solo la section_id
                //     // normalizza "compulsory" e "hidden"
                //     $joinData['compulsory'] = $joinData['compulsory'] ? 1 : 0;
                //     $joinData['hidden'] = $joinData['hidden'] ? 1 : 0;
                //     $this->updateQuestionSurvey($joinData, $survey_id, $question_id, $lang, $globalmodification);
                // }

                if (!$this->Questions->save($question)) {
                    throw new \Exception('Si è verificato un errore durante il salvataggio');
                }

                //Se la domanda è proprio origine spostamenti, creo le domande che sono impostate in Questions_spos oppure fa update
                if (isset($data['id']) && $data['id'] == Configure::read('Questions.origine_spostamenti')) {
                    $q_maps = Configure::read('Questions_spos');
                    foreach ($q_maps as $q_map_id) {
                        // salva/aggiorna il relativo record questions_surveys
                        //$joinData = $data["_joinData"];

                        if (!empty($q_map_id)) {
                            // $question = $this->Questions->find('translations')->where(['id'=>$q_map_id]);
                            $question_map = $this->Questions->find('translations')->where(['id' => $q_map_id])->contain([
                                'QuestionsSurveys' => function ($query) use ($survey_id) {
                                    return $query->find('translations')->where(['survey_id' => $survey_id]);
                                },
                            ])->first();
                            if (empty($question_map)) {
                                $msg = "QuestionsUpdate: Impossibile aggiornare la domanda $q_map_id, per l'utente $userId, dell'azienda $company_id";
                                Log::write('error', $msg);
                                // throw new \Exception("Domanda {$q_map_id} non trovata");
                            } else {
                                // $connection = ConnectionManager::get('default');
                                // $results = $connection->execute('SELECT id FROM questions_surveys WHERE survey_id = :survey_id AND question_id = :question_id', [
                                //     'survey_id' => $joinData['survey_id'],
                                //     'question_id' => $question['id'],
                                // ])->fetchAll('assoc');
                                // // verifica che l'id sia corretto
                                // if (!empty($results)) {
                                //     $joinData['id'] = $results[0]['id']; // in realtà è solo 1 sempre
                                // } else {
                                //     $joinData['id'] = null;
                                // }

                                // $joinData['question_id'] = $question['id'];

                                if (empty($question_map['questions_surveys'][0]['id'])) { // insert
                                    // normalizza "compulsory" e "hidden"
                                    // $joinData['compulsory'] = 1;
                                    // $joinData['hidden'] = 1;
                                    // $connection->insert('questions_surveys', $joinData);
                                    $question_map['questions_surveys'][0] = $this->Questions->QuestionsSurveys->newEmptyEntity();
                                    $question_map['questions_surveys'][0]['section_id'] = $question['questions_surveys'][0]['section_id'];
                                    $question_map['questions_surveys'][0]['survey_id'] = $survey_id;
                                    $question_map['questions_surveys'][0]['weight'] = empty($question['questions_surveys'][0]['weight']) ? -100 : $question['questions_surveys'][0]['weight'];
                                    $question_map['questions_surveys'][0]['hidden'] = $question_map['hidden'] != null ? $question_map['hidden'] : 1;
                                    $question_map['questions_surveys'][0]['compulsory'] = $question_map['compulsory'] != null ? $question_map['compulsory'] : 0;
                                    $question_map = $this->insertQuestionSurvey($question_map, $lang);
                                } else {
                                    $question_map['questions_surveys'][0]['section_id'] = $question['questions_surveys'][0]['section_id'];
                                    $question_map['questions_surveys'][0]['weight'] = empty($question['questions_surveys'][0]['weight']) ? -100 : $question['questions_surveys'][0]['weight'];
                                }
                                // else {
                                //     // rimuovi compulsory e hidden (che sono gestiti a parte), salvo in pratica solo la section_id
                                //     $joinData['compulsory'] = 1;
                                //     $joinData['hidden'] = 1;
                                //     $this->updateQuestionSurvey($joinData, $survey_id, $question_id,$lang,$globalmodification);

                                //     if(!$globalmodification){
                                //         $this->updateQuestionSurvey($joinData, $survey_id, $question_id,$lang,$globalmodification);
                                //     }
                                //     //$connection->update('questions_surveys', $joinData, ['id' => $joinData['id']]);
                                // }

                                if (!$this->Questions->QuestionsSurveys->save($question_map['questions_surveys'][0])) {
                                    throw new \Exception('Si è verificato un errore durante il salvataggio');
                                }
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                $errorMsg = $e->getMessage();
            } catch (Exception $e) {
                $errorMsg = $e->getMessage();
            }
            $this->set('errorMsg', $errorMsg);
            $this->viewBuilder()->setOption('serialize', ['errorMsg']);
        }
    }


    //Aggiorna la tabella di passaggio questions_surveys

    private function insertQuestionSurvey($question, $lang)
    {
        // $this->QuestionSurveys = $this->getTableLocator()->get('QuestionsSurveys');

        // $qs = $this->QuestionSurveys->newEmptyEntity();
        // $question['questions_surveys'][0]->survey_id = $survey_id;
        $question['questions_surveys'][0]['question_id'] = $question['id'];
        // $question['questions_surveys'][0]->section_id = $question['section_id'];
        // $question['questions_surveys'][0]->weight = $question['weight'];
        // $question['questions_surveys'][0]->hidden = $question['hidden'];
        $question['questions_surveys'][0]->compulsory_answer = $question['compulsory_answer'];
        $question['questions_surveys'][0]['options'] = $question['options'];
        $question['questions_surveys'][0]['conditions'] = $question['conditions'];
        $question['questions_surveys'][0]['description'] = $question['description'];
        $question['questions_surveys'][0]['long_description'] = $question['long_description'];

        // $question_trans = $this->QuestionSurveys->find('translations')
        //     ->where(['id' => $joinData['id']])
        //     ->first();
        $lang = 'en';
        if (isset($question['_translations'][$lang])) {
            // if array
            if (!is_array($question['_translations'][$lang])) {
                $translations = $question['_translations'][$lang]->toArray();
            } else {
                $translations = $question['_translations'][$lang];
            }
            foreach ($translations as $field => $value) {
                // if ($field == 'options') {
                //     $qs->translation($lang)->$field = json_encode($value);
                // } else {
                    // $qs->_translations[$lang]->$field = $value;
                    $question['questions_surveys'][0]->translation($lang)->$field = $value;
                // }
            }
        }

        return $question;
        // if (!$this->QuestionSurveys->save($qs)) {
        //     $msg = "Impossibile salvare le opzioni per questo questionario";
        // } else {
        //     $msg = "Salvataggio opzioni per questo questionario ok";
        // }
        // $this->set('msg', $msg);
        // $this->viewBuilder()->setOption('serialize', ['msg']);
    }

    //Elimina le domande doppie ed inutili dal database

    public function pulisciDoppie()
    {
        //delete FROM questions where id not in  (select question_id from answers);
        $subquery = $this->Questions->find()
                           ->select(['id']);
        $subquery1 = $this->fetchTable('questions_surveys')->find()
                           ->select(['question_id']);

        // $result = $this->Questions->Answers->find()->where(['id NOT IN' => $subquery]);
        /* $result = $this->Questions->find()
                    ->where(['id NOT IN' => $subquery]); */
        //print_r($result->toArray());
        // $result = $this->Questions->find()
        //             ->where(['id NOT IN' => $subquery1]);


        try {
            $result1 = $this->Questions->deleteAll(['id NOT IN' => $subquery1]);
            $this->Flash->success("La pulizia è stata effettuata. $result1 domande cancellate non associato ad alcun questionario.");
            $result = $this->Questions->Answers->deleteAll(['question_id NOT IN' => $subquery]);
            $this->Flash->success("La pulizia è stata effettuata. $result risposte di domande cancellate.");
        } catch (\Exception $e) {
            $this->Flash->error('Errore durante la cancellazione delle domande doppie.');
        }

        $q = $this->Questions->find();
        $qs = $q->select(['name','count' => $q->func()->count('name')])->group('name')
        ->having(['count >' => 1]);
        $i = 1;
        $q_count_1 = $this->Questions->find()->select(['name','count' => $q->func()->count('name')])->group('name')
        ->having(['count >' => 1])->toArray();
        $this->Flash->success('La pulizia è stata effettuata. Il nome di ' . array_sum(array_column($q_count_1, 'count')) . ' domande duplicate fu modificato.');
        foreach ($qs as $r) {
            if ($r->name == 'Nuova Domanda' || $r->name == '2-01' || $r->name == '2.03' || $r->name == '2.01' || $r->name == 'ID: 188') {
                // $q_dups=Configure::read("Questions_dup");
                //     foreach($q_dups as $q_dup){
                //         $question= $this->Questions->get($q_dup);

                //     }
                $questions = $this->Questions->find()->where(['name ' => $r->name]);
                foreach ($questions as $question) {
                    // $i=$i+1;
                    $name_split = explode(' ', $question->description);

                    $name = '';
                    foreach ($name_split as $name_word) {
                        if (strpos($name_word, "'")) {
                            $name_word = substr($name_word, strpos($name_word, "'") + 1, 20);
                        }
                        $name_word = preg_replace('/[^A-Za-z0-9\-]/', '', $name_word);
                        if (strlen($name_word) > 3) {
                            $name = strtolower($name . $name_word);

                            if (strlen($name) < 20) {
                                $name = $name . '-';
                            } else {
                                break;
                            }
                        }
                    }
                    if (substr(substr($name, 0, 20), -1, 1) == '-') {
                        $name = substr(substr($name, 0, 20), 0, -1);
                    }

                    if (is_null($this->Questions->find()->where(['name' => substr($name, 0, 20)])->first())) {
                        $question->name = substr($name, 0, 20);
                        if (!$this->Questions->save($question)) {
                            throw new \Exception('Si è verificato un errore durante il salvataggio');
                        }
                    } else {
                        while (!is_null($this->Questions->find()->where(['name' => substr($name, 0, 19) . $i])->first())) {
                            $i = $i + 1;
                        }
                        $question->name = substr($name, 0, 19) . $i;
                        if (!$this->Questions->save($question)) {
                            throw new \Exception('Si è verificato un errore durante il salvataggio');
                        }
                        $i = 1;
                    }
                }
            } else {
                $questions = $this->Questions->find()->where(['name ' => $r->name]);
                if ($r->count > 2) {
                    foreach ($questions as $question) {
                        $question->name = substr(strtolower(str_replace(' ', '-', $r->name)), 0, 18) . $i;
                        if (!$this->Questions->save($question)) {
                            throw new \Exception('Si è verificato un errore durante il salvataggio');
                        }
                        $i = $i + 1;
                    }
                    $i = 1;
                } else {
                    $question = $questions->first();
                    $question->name = substr(strtolower(str_replace(' ', '-', $r->name)), 0, 19) . $i;
                    if (!$this->Questions->save($question)) {
                        throw new \Exception('Si è verificato un errore durante il salvataggio');
                    }
                }
            }

            // $articles->save($entity);
        }
        // $questions= $this->Questions->find()
        //             ->where([['name ' => $this->Questions->find()->identifier('name')],['id <' => $this->Questions->find()->identifier('id')]]);

        $q_ch = $this->Questions->find()->where(['name not REGEXP' => '^[a-z].*$']);
        $q_count = $this->Questions->find()->select(['count' => $this->Questions->find()->func()->count('*')])
        ->where(['name not REGEXP' => '^[a-z].*$'])->first();
        $this->Flash->success("La pulizia è stata effettuata. Il nome di $q_count->count domande fu modificato.");

        foreach ($q_ch as $question) {
            $name_split = explode(' ', $question->description);

            $name = '';
            foreach ($name_split as $name_word) {
                if (strpos($name_word, "'")) {
                    $name_word = substr($name_word, strpos($name_word, "'") + 1, 20);
                }
                $name_word = preg_replace('/[^A-Za-z0-9\-]/', '', $name_word);
                if (strlen($name_word) > 3) {
                    $name = strtolower($name . $name_word);

                    if (strlen($name) < 20) {
                        $name = $name . '-';
                    } else {
                        break;
                    }
                }
            }
            if (substr(substr($name, 0, 20), -1, 1) == '-') {
                $name = substr(substr($name, 0, 20), 0, -1);
            }

            if (is_null($this->Questions->find()->where(['name' => substr($name, 0, 20)])->first())) {
                $question->name = substr($name, 0, 20);
                if (!$this->Questions->save($question)) {
                    throw new \Exception('Si è verificato un errore durante il salvataggio');
                }
            } else {
                $i = 1;
                while (!is_null($this->Questions->find()->where(['name' => substr($name, 0, 19) . $i])->first())) {
                    $i = $i + 1;
                }
                $question->name = substr($name, 0, 19) . $i;
                if (!$this->Questions->save($question)) {
                    throw new \Exception('Si è verificato un errore durante il salvataggio');
                }
            }

            // $articles->save($entity);
        }

        return $this->redirect(['action' => 'index']);
    }

    /**
     * Union method
     *
     * @param null $remove_question_id Question id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function union($remove_question_id, $destination_question_id = null)
    {
        $this->allowRolesOnly(['admin', 'moma']);

        if ($this->request->is(['patch', 'post', 'put'])) {
            $remove_question_id = $this->request->getData('remove_question_id');
            $destination_question_id = $this->request->getData('destination_question_id');
            $this->loadModel('UnionRollback');
            if (
                empty($this->UnionRollback->find()->where(['OR' => [['destination_question_id' => $destination_question_id],
                ['remove_question_id' => $destination_question_id]]])->toarray())
                && ($remove_question_id != $destination_question_id)
            ) {
                $this->loadModel('QuestionsSurveys');
                $subquery = $this->QuestionsSurveys->find();
                $subquery
                ->select(['survey_id'])
                ->where(['question_id' => $destination_question_id]);
                $r_destination = $subquery->toArray();

                $query = $this->QuestionsSurveys->find();
                $query
                ->select(['survey_id'])
                ->where(['question_id' => $remove_question_id]);
                // debug($query);
                $r_remove = $query->toArray(); // Execute the query and get the result set
                $result = array_intersect($r_destination, $r_remove);
                if (empty($result)) {
                    $answers = $this->Questions->Answers->find()->where(['question_id' => $remove_question_id]);
                    $answers_destinations = $this->Questions->Answers->find()->where(['question_id' => $destination_question_id]);
                    if (!empty($answers->toArray()) && !empty($answers_destinations->toArray())) {
                        $query = $this->QuestionsSurveys->find();
                        $query->select([
                            'id_questions' => $query->func()->group_concat(['id' => 'identifier'])]);
                        $query->where(['question_id' => $remove_question_id]);
                        $query->group(['question_id']);

                        // Execute the query and fetch the result
                        $result = $query->toArray();

                        // Access the 'id_questions' column from the result
                        $idQuestions = $result[0]['id_questions'];
                        $query = $this->Questions->Answers->find();
                        $query->select([
                            'id_questions' => $query->func()->group_concat(['id' => 'identifier'])]);
                        $query->where(['question_id' => $remove_question_id]);
                        $query->group(['question_id']);
                        // Execute the query and fetch the result
                        $result = $query->toArray();

                        // Access the 'id_questions' column from the result
                        $idAnswers = $result[0]['id_questions'];
                        $q = $this->Questions->get($remove_question_id)->toArray();
                        $q_des = $this->Questions->get($destination_question_id);
                        $now = FrozenTime::now();
                        $union = $this->UnionRollback->newEntity([
                            'date' => $now,
                            'remove_question_id' => $remove_question_id,
                            'destination_question_id' => $destination_question_id,
                            'questions_survey_id' => $idQuestions,
                            'answers_id' => $idAnswers,
                            'remove_question' => $q,
                            'name_union_questions' => 'Remove: ' . $q['name'] . ' / Destination: ' . $q_des->name . '',
                        ]);

                        if ($this->UnionRollback->save($union)) {
                            $this->Flash->success(__('The union has been saved.'));
                        } else {
                            $this->Flash->error(__('The union could not be saved. Please, try again.'));
                        }
                        $qs_survey = $this->QuestionsSurveys->find()->where(['question_id' => $remove_question_id]);
                        foreach ($qs_survey as $qs) {
                            $qs->question_id = $destination_question_id;
                            if (!$this->QuestionsSurveys->save($qs)) {
                                throw new \Exception('Si è verificato un errore durante il salvataggio');
                            }
                        }

                        foreach ($answers as $a) {
                            $existingAnswer = $this->Questions->Answers->find()
                            ->where([
                                'question_id' => $destination_question_id,
                                'user_id' => $a->user_id,
                            ])
                            ->first();
                            if ($existingAnswer) {
                                // deleted answer
                                $this->Questions->Answers->delete($a);
                            } else {
                                // update answer
                                $a->question_id = $destination_question_id;
                                // $a->answer= array_map('trim', $a->answer);
                                if (!$this->Questions->Answers->save($a)) {
                                    throw new \Exception('Si è verificato un errore durante il salvataggio');
                                }
                            }
                        }
                        $question = $this->Questions->get($remove_question_id);
                        if ($this->Questions->delete($question)) {
                            $this->Flash->success(__('The question has been deleted.'));
                        } else {
                            $this->Flash->error(__('The question could not be deleted. Please, try again.'));
                        }
                        // delete conditions of remove question
                         // search if the question is used in any conditions of other questions where conditions is a json
                        $conditions = $this->Questions->find()
                        ->where(function ($exp) use ($remove_question_id) {
                            return $exp->in('JSON_UNQUOTE(JSON_EXTRACT(conditions, "$.question"))', [$remove_question_id]);
                        });
                        // debug($conditions); die;
                        $conditionsCount = $conditions->count();
                        $this->Flash->success(__("Cleared conditions for {$conditionsCount} questions."));
                        // // save conditions as empty
                        foreach ($conditions as $condition) {
                            $condition->conditions = '';
                            $this->Questions->save($condition);
                        }
                        
                        // empty conditions of the questionsurveys
                        $questionSurveys = $this->Questions->QuestionsSurveys->find()
                            ->where(function ($exp) use ($remove_question_id) {
                                return $exp->in('JSON_UNQUOTE(JSON_EXTRACT(conditions, "$.question"))', [$remove_question_id]);
                            });
                        // count the number of questionsurveys with conditions
                        $questionSurveysCount = $questionSurveys->count();
                        $this->Flash->success(__("Cleared conditions for {$questionSurveysCount} questionsurveys."));
                        foreach ($questionSurveys as $questionSurvey) {
                            $questionSurvey->conditions = '';
                            $this->Questions->QuestionsSurveys->save($questionSurvey);
                        }

                        return $this->redirect(['action' => 'index']);
                    } else {
                        $questions = $this->Questions->find();
                        $this->Flash->error(__('This union is not possible because the questions have not answers. Please, try again.'));
                        $this->set(compact('questions', 'remove_question_id', 'destination_question_id'));
                        $this->viewBuilder()->setOption('serialize', ['questions','remove_question_id','destination_question_id']);
                    }
                } else {
                    $questions = $this->Questions->find();
                    $this->Flash->error(__('This union is not possible because the questions have the same survey ' . implode(',', $result) . '. Please, try again.'));
                    $this->set(compact('questions', 'remove_question_id', 'destination_question_id'));
                    $this->viewBuilder()->setOption('serialize', ['questions','remove_question_id','destination_question_id']);
                }
            } else {
                $questions = $this->Questions->find();
                $this->Flash->error(__('This union already exists or the union is not valid. Please, try again.'));
                $this->set(compact('questions', 'remove_question_id', 'destination_question_id'));
                $this->viewBuilder()->setOption('serialize', ['questions','remove_question_id','destination_question_id']);
            }
        } else {
            $questions = $this->Questions->find();
            $this->set(compact('questions', 'remove_question_id', 'destination_question_id'));
            $this->viewBuilder()->setOption('serialize', ['questions','remove_question_id','destination_question_id']);
        }
    }

    /**
     * Rollback method
     *
     * @param null $destination_question_id Question id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function rollback($destination_question_id, $rollback_questions_id = null)
    {
        $this->allowRolesOnly(['admin', 'moma']);
        $this->loadModel('UnionRollback');

        if ($this->request->is(['patch', 'post', 'put'])) {
            // $remove_question_id = $this->request->getData('remove_question_id');
            $rollback_questions_id = $this->request->getData('rollback_questions_id');

            $union = $this->UnionRollback->find()->where(['id' => $rollback_questions_id])->first();
            if ($union) {
                $q = $this->Questions->newEntity($union->remove_question);
                if (!$this->Questions->save($q)) {
                    throw new \Exception('Si è verificato un errore durante il salvataggio');
                }
                foreach (explode(',', $union->questions_survey_id) as $id) {
                    $this->loadModel('QuestionsSurveys');
                    $q_survey = $this->QuestionsSurveys->find()->where(['id' => $id])->first();
                    $q_survey->question_id = $union->remove_question_id;
                    if (!$this->QuestionsSurveys->save($q_survey)) {
                        throw new \Exception('Si è verificato un errore durante il salvataggio');
                    }
                }
                foreach (explode(',', $union->answers_id) as $id) {
                    $answer = $this->Questions->Answers->find()->where(['id' => $id])->first();
                    $answer->question_id = $union->remove_question_id;
                    if (!$this->Questions->Answers->save($answer)) {
                        throw new \Exception('Si è verificato un errore durante il salvataggio');
                    }
                }

                if ($this->UnionRollback->delete($union)) {
                    $this->Flash->success(__('The union has been deleted.'));
                } else {
                    $this->Flash->error(__('The union could not be deleted. Please, try again.'));
                }

                $unionrollback = $this->UnionRollback->find();
                $union_id = $this->UnionRollback->find()->where(['destination_question_id' => $destination_question_id])->first();
                $this->set(compact('unionrollback', 'union_id', 'rollback_questions_id'));
                $this->viewBuilder()->setOption('serialize', ['unionrollback','union_id','rollback_questions_id']);
            } else {
                $unionrollback = $this->UnionRollback->find();
                $union_id = $this->UnionRollback->find()->where(['destination_question_id' => $destination_question_id])->first();
                $this->set(compact('unionrollback', 'union_id', 'destination_question_id'));
                $this->viewBuilder()->setOption('serialize', ['unionrollback','union_id','destination_question_id']);
            }
        } else {
            $unionrollback = $this->UnionRollback->find();
            $union_id = $this->UnionRollback->find()->where(['destination_question_id' => $destination_question_id])->first();
            $this->set(compact('unionrollback', 'union_id', 'rollback_questions_id'));
            $this->viewBuilder()->setOption('serialize', ['unionrollback','union_id','rollback_questions_id']);
        }
    }

    // create a funtion to correct the conditions of the questions

    public function correctConditions()
    {
        try {
            $questions = $this->Questions->find()->contain('QuestionsSurveys')
            // ->contain([
            //     'QuestionsSurveys' => function ($query) {
            //         return $query->find('translations');
            //     }
            // ]);
            ->where(['conditions is not' => null,
                    'id NOT IN' =>  Configure::read('Questions_spos'),
                    'id IS NOT' =>  Configure::read('Questions.origine_spostamenti')]);
                 $count = 0;
            foreach ($questions as $question) {
                if (empty($question->conditions)) {
                    continue;
                }
                $conditions = $question->conditions;
                if (!is_array($conditions)) {
                    $conditions = json_decode($conditions, true);
                }
                $question_condition = $this->Questions->find()->where(['id' => $conditions['question']]);
                $options = $question_condition->first()->options;
                $values = $conditions['value'];
                // from arrays options and values find indexes
                $indices = [];

                foreach ($values as $value) {
                    $index = array_search($value, $options);
                    if ($index !== false) {
                        $indices[] = $index;
                    }
                }
                if (!empty($indices)) {
                    $conditions['value'] = $indices;

                    $question->conditions = $conditions;
                    if (!$this->Questions->save($question)) {
                        throw new \Exception('Si è verificato un errore durante il salvataggio');
                    }
                    $count++;
                    foreach ($question->questions_surveys as $qs) {
                        $qs->conditions = $question->conditions;

                        if (!$this->Questions->QuestionsSurveys->save($qs)) {
                            throw new \Exception('Si è verificato un errore durante il salvataggio');
                        }
                    }
                }

                // $questions_surveys = $this->Questions->QuestionsSurveys->find('translations')->where(['question_id' => $question->id]);
            }
                 $this->Flash->success(__('The conditions have been corrected. Total: ' . $count));
        } catch (\Exception $e) {
            $this->Flash->error(__('The conditions could not be corrected. Please, try again.' . $e->getMessage()));
        }

        return $this->redirect(['action' => 'index']);
    }

    // correct empty options, description and long_description in questions_surveys

    public function correctQuestionsSurveys($id = null)
    {
        try {
            $questions_surveys = $this->Questions->QuestionsSurveys->find('translations')->where([

                    'question_id' => (int)$id,

            ]);
            $count = 0;
            foreach ($questions_surveys as $qs) {
                    $question = $this->Questions->find('translations')->where(['id' => $qs['question_id']])->first();

                    $qs['options'] = $question['options'];
                    $qs['description'] = $question['description'];
                    $qs['long_description'] = $question['long_description'];
                    // $qs['section_id'] = $question['section_id'];
                    // $qs['weight'] = $question['weight'];
                    $qs['conditions'] = $question['conditions'];
                    $lang = 'en';
                if (isset($question['_translations'][$lang])) {
                    // if array
                    if (!is_array($question['_translations'][$lang])) {
                        $translations = $question['_translations'][$lang]->toArray();
                    } else {
                        $translations = $question['_translations'][$lang];
                    }
                    foreach ($translations as $field => $value) {
                        // if ($field == 'options') {
                        //     $qs->translation($lang)->$field = json_encode($value);
                        // } else {
                            // $qs->_translations[$lang]->$field = $value;
                            $qs->translation($lang)->$field = $value;
                        // }
                    }
                }

                if (!$this->Questions->QuestionsSurveys->save($qs)) {
                    throw new \Exception('Si è verificato un errore durante il salvataggio');
                }
                    $count++;
            }
            $this->Flash->success(__('The questions surveys have been corrected. Total: ' . $count));
        } catch (\Exception $e) {
            $this->Flash->error(__('The questions surveys could not be corrected. Please, try again.' . $e->getMessage()));
        }

        return $this->redirect(['action' => 'index']);
    }

    // delete questionsurveys from deleted questions

    public function deleteQuestionsSurveys()
    {
        try {
            $questions_surveys = $this->Questions->QuestionsSurveys->find('translations')->contain('Questions')
            ->where(['Questions.id IS' => null]);
            $count = 0;
            foreach ($questions_surveys as $qs) {
                if (!$this->Questions->QuestionsSurveys->delete($qs)) {
                    throw new \Exception('Si è verificato un errore durante la cancellazione');
                }
                $count++;
            }
            $this->Flash->success(__('The questions surveys have been deleted. Total: ' . $count));
        } catch (\Exception $e) {
            $this->Flash->error(__('The questions surveys could not be deleted. Please, try again.' . $e->getMessage()));
        }

        return $this->redirect(['action' => 'index']);
    }

    // add Questions_spos to surveys that don't have it

    public function addQuestionsSpos()
    {
        try {
            $questions = $this->Questions->QuestionsSurveys->find()
            ->where(['question_id' =>  Configure::read('Questions.origine_spostamenti')]);
            $count = 0;
            foreach ($questions as $question) {
                $q_maps = Configure::read('Questions_spos');
                $survey_id = $question->survey_id;
                // find Questions_spos that are not in the survey
                foreach ($q_maps as $q_map_id) {
                    $question_map = $this->Questions->find('translations')->where(['id' => $q_map_id])->contain([
                        'QuestionsSurveys' => function ($query) use ($survey_id) {
                            return $query->find('translations')->where(['survey_id' => $survey_id]);
                        },
                    ])->first();
                    if (empty($question_map['questions_surveys'][0])) {
                        // $question_map = $this->Questions->find('translations')->where(['id'=>$q_map_id])->first();
                        $question_map['questions_surveys'][0] = $this->Questions->QuestionsSurveys->newEmptyEntity();
                        $question_map['questions_surveys'][0]['section_id'] = $question['section_id'];
                        $question_map['questions_surveys'][0]['survey_id'] = $survey_id;
                        $question_map['questions_surveys'][0]['weight'] = $question['weight'];
                        $question_map['questions_surveys'][0]['hidden'] = 1;
                        $question_map['questions_surveys'][0]['compulsory'] = 1;
                        $question_map = $this->insertQuestionSurvey($question_map, 'en');
                        if (!$this->Questions->QuestionsSurveys->save($question_map['questions_surveys'][0])) {
                            throw new \Exception('Si è verificato un errore durante il salvataggio');
                        }
                        $count++;
                    } else {
                        $question_map['questions_surveys'][0]['section_id'] = $question['section_id'];
                        $question_map['questions_surveys'][0]['survey_id'] = $survey_id;
                        if (!$this->Questions->QuestionsSurveys->save($question_map['questions_surveys'][0])) {
                            throw new \Exception('Si è verificato un errore durante il salvataggio');
                        }
                    }
                }
            }
            $this->Flash->success(__('The questions surveys have been added. Total: ' . $count));
        } catch (\Exception $e) {
            $this->Flash->error(__('The questions surveys could not be added. Please, try again.' . $e->getMessage()));
        }

        return $this->redirect(['action' => 'index']);
    }

    // correct translations of questions
    public function correctTranslations()
    {
        try {
            $lang = 'en';
            I18n::setLocale($lang);
            // $questions = $this->Questions->find()->contain([
            //         'QuestionsSurveys' => function ($query) {
            //             return $query->find();
            //         }
            //     ])->where(['options is not' => null]);
            $questions = $this->Questions->find()->contain('QuestionsSurveys')->where(['Questions.options is not' => null]);
            $count = 0;
            foreach ($questions as $question) {
                // if not array
                if (!is_array($question['options'])) {
                    $optionsArray = json_decode($question['options'], true);
                    // if array
                    if (is_array($optionsArray)) {
                        $question['options'] = implode('|', $optionsArray);
                        if (!$this->Questions->save($question)) {
                            throw new \Exception('Si è verificato un errore durante il salvataggio');
                        }
                        $count++;
                        foreach ($question->questions_surveys as $qs) {
                            $qs['options'] = $question['options'];

                            if (!$this->Questions->QuestionsSurveys->save($qs)) {
                                throw new \Exception('Si è verificato un errore durante il salvataggio');
                            }
                        }
                    }
                }
            }
            $this->Flash->success(__('The translations have been corrected. Total: ' . $count));
        } catch (\Exception $e) {
            $this->Flash->error(__('The translations could not be corrected. Please, try again.' . $e->getMessage()));
        }

        return $this->redirect(['action' => 'index']);
    }
    // change state moma_area field
    public function changeStateMomaArea()
    {
        try {
            $data = $this->request->getData();
            $state = $data['enabled'];
            $question_id = $data['question_id'];
            $question = $this->Questions->changeState($question_id, $state);
            $errorMsg = false;
        } catch (Exception $e) {
            $errorMsg = $e->getMessage();
        } 

        $this->set(compact('question', 'errorMsg'));
        $this->viewBuilder()->setOption('serialize', ['question', 'errorMsg']);
    }
}
