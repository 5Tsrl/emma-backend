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

use App\Model\Entity\SurveyParticipant;
use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\Filesystem\Folder;
use Cake\Http\Exception\InternalErrorException;
use Cake\Http\Exception\MethodNotAllowedException;
use Cake\Http\Exception\NotFoundException;
use Cake\I18n\FrozenDate;
use Cake\I18n\I18n;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;
use Cake\Utility\Text;
use Exception;

/**
 * Surveys Controller
 *
 * @property \App\Model\Table\SurveysTable $Surveys
 * @method \App\Model\Entity\Survey[]|\Cake\Datasource\ResultSetInterface paginate($object = null, array $settings = [])
 */
class SurveysController extends AppController
{
    /**
     * Initialize.
     */
    public function initialize(): void
    {
        parent::initialize();
        $this->Authentication->allowUnauthenticated([
            'addAnonParticipant',
            'getQuestions',
            'completed',
            'getTranslations',
            'getConfig',
            'updateOfficeQuestion',
        ]);
        if ($this->components()->has('Security')) {
            $this->Security->setConfig(
                'unlockedActions',
                [
                    'addAnonParticipant',
                    'getQuestions',
                    'completed',
                    'deleteSurveysEmpty',
                ]
            );
        }
        $this->loadComponent('Paginator');
    }

    /**
     * Index method
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        $query = $this->Surveys->find();
        $query->contain([
            'Companies' => ['fields' => ['name', 'id', 'type']],
            'Users'
        ])
            ->select(['id', 'name', 'opening_date', 'closing_date', 'version_tag', 'company_id', 'year'])
            ->order(['version_tag = "template" DESC', 'Surveys.name']);

        // $identity = $this->Authentication->getIdentity();
        // $myRole = $identity->get('role');
        // $company_id = $identity->get('company_id');
        // //L'utente moma può vedere solo la sua azienda
        // if ($myRole == 'moma' && !empty($company_id)) {
        //     $query->where(['Surveys.company_id' =>  $company_id]);
        // }
        $this->Authorization->applyScope($query);

        $q = $this->request->getQuery('q');
        if (!empty($q)) {
            $query->where(['OR' => [
                ['Surveys.name LIKE' => "%$q%"],
                ['Companies.name LIKE' => "%$q%"],
            ]]);
        }
        $year = $this->request->getQuery('year');
        if (!(empty($year) || $year == 'TUTTI')) {
            $query->where(['Surveys.year' => $year]);
        }

        $all = $this->request->getQuery('all');
        if (!empty($all)) {
            $surveys = $query->all();
            $this->set(compact('surveys'));
            $this->viewBuilder()->setOption('serialize', ['surveys']);
        } else {
            $surveys = $this->paginate($query);
            $pagination = $this->Paginator->getPagingParams();
            $this->set(compact('surveys', 'pagination'));
            $this->viewBuilder()->setOption('serialize', ['surveys', 'pagination']);
        }
    }

    /**
     * View method
     *
     * @param string|null $id Survey id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $this->allowWhoCanSeeSurveyOnly($id);
        // Apply scope to the Questions association
        $survey = $this->Surveys->get($id, [
            'contain' => [
                'Companies',
                'Users',
                'Questions' => function ($q) {
                    return $this->Authorization->applyScope($q);
                }
            ],
        ]);

        $this->set(compact('survey'));
        $this->viewBuilder()->setOption('serialize', ['survey']);
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add($company_id, $survey_template = null)
    {
        $this->allowRolesOnly(['admin', 'moma']);

        if ($this->request->is('get')) {
            $company = $this->Surveys->Companies->get($company_id);
            if (empty($company)) {
                throw new Exception("L'azienda richiesta non esite");
            }
            if (empty($survey_template) || $survey_template == -1) {
                $sid = $this->Surveys->createNewEmptySurvey($company);
            } else {
                $sid = $this->Surveys->createNewSurveyFromTemplate($company, $survey_template);
            }
            $this->set('survey_id', $sid);
            $this->viewBuilder()->setOption('serialize', ['survey_id']);
        }
    }

    public function updateLogo($id)
    {
        $this->allowRolesOnly(['admin', 'moma']);

        $logo = $this->request->getData('logofile');
        if (!empty($logo) && $logo != 'undefined') {
            //Carico la il logo 'uploadedFilesAsObjects' sul server
            $error = $logo['error'];
            $fname = $logo['tmp_name'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detectedMimeType = finfo_file($finfo, $fname);
            finfo_close($finfo);

            // Check if file is PNG or JPG
            $allowedMimeTypes = ['image/png', 'image/jpeg', 'image/jpg'];
            if (!in_array($detectedMimeType, $allowedMimeTypes)) {
                $success = false;
                $this->set('success', $success);
                $msg = 'Il tipo di file non è valido. Utilizzare solo PNG o JPG.';
                $this->set(compact('msg'));
                $this->viewBuilder()->setOption('serialize', ['success', 'msg']);
                return;
            }

            if ($error == UPLOAD_ERR_OK) {

                $fullDirTemplate = ':sitedir/:logodir';
                $save_dir = Text::insert($fullDirTemplate, [
                    'sitedir' => Configure::read('sitedir'),
                    'logodir' => Configure::read('logodir'),
                ]);
                $name_on_server = $id . substr($logo['name'], -4, 4);
                $dest_fname = WWW_ROOT . $save_dir . DS . $name_on_server;
                //check if $save_dir exists, if not create it
                $folder = new Folder(WWW_ROOT . $save_dir, true, 0777);
                $e  = $folder->errors();
                if (!empty($e)) { //$save_dir is a relative path so it is checked relatively to current working directory
                    $this->Flash->error("Si è verificato un errore nella creazione della directory. Ripetere l'operazione - " . $e);

                    return;
                }

                $del = array_map('unlink', glob(WWW_ROOT . $save_dir . DS . $id . '.*'));
                if (!$del) {
                    $msg = 'Si e\' verificato un problema nella eliminazione del logo';
                }

                $copied = move_uploaded_file($logo['tmp_name'], $dest_fname);
                //Se non riesco a spostare nella cartella giusta, esco
                if (!$copied) {
                    $msg = 'Si e\' verificato un problema nel caricamento del logo';
                }
            } elseif ($error != UPLOAD_ERR_NO_FILE) {
                throw new InternalErrorException($this->phpFileUploadErrors[$error]);
            }
            if ($msg == null) {
                $msg = 'logo salvato con successo';
            }

            $this->set(compact('msg'));
            $this->viewBuilder()->setOption('serialize', ['msg']);
        }
    }

    /**
     * Edit method
     *
     * @param string|null $id Survey id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $this->allowWhoCanSeeSurveyOnly($id);

        // $survey = $this->Surveys->get($id, [
        //     'contain' => [
        //         'SurveyDeliveryConfigs', 'Questions',
        //     ],
        // ]);
        $survey = $this->Surveys->find('translations')->where(['Surveys.id' => $id])->contain([
            'Questions' => function ($query) use ($id) {
                return $query->find('translations')->contain([
                    'QuestionsSurveys' => function ($query) use ($id) {
                        return $query->find('translations')->where(['survey_id' => $id]);
                    },
                ]);
            },
            'SurveyDeliveryConfigs',
        ])->toArray()[0];

        // //I need to clean the questions using the closer result if existing
        // foreach ($survey->questions as &$q) {
        //     $q = $this->getCloserQuestion($q);
        // }

        $survey_participants_num = $this->Surveys->countParticipants($id);

        if ($this->request->is(['patch', 'post', 'put'])) {
            $data = $this->request->getData();
            $survey = $this->Surveys->patchEntity($survey, $data);
            // Gestione della traduzione: mi aspetto di ricevere un array [en] => ['campo' => traduzione, 'campo2'=>traduzione]
            //TODO: Gestire più lingue, trasformando $langs in un array e questo in un loop
            $lang = 'en';
            if (isset($data['_translations'][$lang])) {
                foreach ($data['_translations'][$lang] as $field => $value) {
                    $survey->translation($lang)->$field = $value;
                }
            }

            if ($this->Surveys->save($survey)) {
                $msg = 'Salvato con successo';
                $this->set(compact('msg'));
                $this->viewBuilder()->setOption('serialize', ['msg']);
            } else {
                throw new \Exception('Errore salvataggio survey: ' . implode($this->validationErrors));
            }
        }

        $survey['participants_num'] = $survey_participants_num;
        $this->set(compact('survey'));
        $this->viewBuilder()->setOption('serialize', ['survey']);
    }

    /**
     * Importa tutti gli utenti di un'azienda nella survey corrente users->participants
     *
     * @param int $id = survey in cui voglio importare i partecipanti
     * @return void
     */
    public function importCompanyUsers($id, $year = '2024')
    {
        $this->allowRolesOnly(['admin', 'moma']);
        try {
            $survey = $this->Surveys->get($id);
            $survey_participants_num = $this->Surveys->countParticipants($id);
            // $year = date('Y');
            if ($id && $survey['version_tag'] != 'template') {
                // $connection = ConnectionManager::get('default');
                // $connection->execute(
                //     "insert into survey_participants
                //     (id, survey_id, user_id)
                //     (select uuid(), $id, id from users
                //      where company_id={$survey['company_id']} and
                //      id not in (
                //          select user_id from survey_participants where survey_id = $id
                //      )
                //  );"
                // );
                $importParticipants = $this->Surveys->Users->find()->where(['company_id' => $survey['company_id']]);
                if ($year != 'TUTTI') {
                    $importParticipants = $importParticipants->where(
                        function ($exp) {
                            return $exp->add('JSON_CONTAINS(Users.years, :year)');
                        }
                    )
                        ->bind(':year', '"' . $year . '"');
                }
                $countusers = $importParticipants->count();
                $importParticipants = $importParticipants->notMatching('SurveyParticipants', function ($q) use ($id) {
                    return $q->where(['survey_id' => $id]);
                })->limit(1000)
                    ->each(function ($user) use ($id) {
                        $surveyParticipant = $this->Surveys->SurveyParticipants->newEmptyEntity();
                        $surveyParticipant->survey_id = (int)$id;
                        $surveyParticipant->user_id = $user->id;
                        $surveyParticipant->id = Text::uuid();
                        $this->Surveys->SurveyParticipants->save($surveyParticipant);
                    });

                $survey_participants_num = $this->Surveys->countParticipants($id);
                $w = "getStats-$id";
                Cache::delete($w, 'long');
            }

            $survey['participants_num'] = $survey_participants_num;
            $survey['import_users'] = $countusers;
            $this->set(compact('survey'));
            $this->viewBuilder()->setOption('serialize', ['survey']);
        } catch (\Exception $e) {
            $this->set('errorMsg', $e->getMessage());
            $this->viewBuilder()->setOption('serialize', ['errorMsg']);
        }
    }

    /**
     * Delete method
     *
     * @param string|null $id Survey id.
     * @return \Cake\Http\Response|null|void Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->allowWhoCanSeeSurveyOnly($id);

        $this->request->allowMethod(['post', 'delete']);
        $survey = $this->Surveys->get($id);
        if ($this->Surveys->delete($survey, ['dependent' => true])) {
            $this->Surveys->Answers->deleteAll(['survey_id' => $id]);
            if ($this->request->is('json')) {
                $errorMsg = "Questionario $id cancellato";
            } else {
                $this->Flash->success(__('The survey has been deleted.'));
            }
        } else {
            if ($this->request->is('json')) {
                throw new Exception("Impossibile cancellare il questionario $id");
            } else {
                $this->Flash->error(__('The survey could not be deleted. Please, try again.'));
            }
        }

        $this->set('errorMsg', $errorMsg);
        $this->viewBuilder()->setOption('serialize', ['errorMsg']);
    }

    public function import()
    {
        $this->allowRolesOnly(['admin']);

        $companies = $this->Surveys->Companies->find('list');
        $adv = 1;
        Cache::clear();

        if ($this->request->is('post')) {
            set_time_limit(240);
            ini_set('memory_limit', '3G');

            $this->loadModel('Questions');
            $this->loadModel('Answers');

            $attachment = $this->request->getData('limesurvey');
            $name = $attachment['name'];
            $fname = $attachment['tmp_name'];
            $error = $attachment['error'];
            if ($error != 0) {
                return $this->Flash->error(__('Errore nell\'apertura del file.'));
            }

            //Se il campo azienda è vuoto creo una nuova azienda
            if (empty($this->request->getData('company_id'))) {
                $cEntity = $this->Surveys->Companies->newEntity([
                    'name' => $name,
                ]);
                if ($this->Surveys->Companies->save($cEntity)) {
                    $company_id = $cEntity->id;
                    $company_name = $cEntity->name;
                } else {
                    return $this->Flash->error(__('Impossibile creare l\'azienda'));
                }
            } else {
                $company_id = $this->request->getData('company_id');
                $company_name = $companies->toArray()[$company_id];
            }

            //Salvo la survey
            $data = [
                'company_id' => $company_id,
                'name' => "Questionario $company_name",
                'date' => $this->request->getData('date'),
                'description' => $this->request->getData('description'),
                'version_tag' => $this->request->getData('version_tag'),
            ];
            $sEntity = $this->Surveys->newEntity($data);
            if (!$this->Surveys->save($sEntity)) {
                $this->Flash->error(__('Impossibile salvare la survey.'));
            }
            Cache::write('Survey_id', $sEntity->id);
            Cache::write("Survey-{$sEntity->id}", [
                'msg' => 'Inizio',
                'adv' => 1,
                'max' => 100,
            ]);
            $this->advance($sEntity->id, "Crezione nuova survey $company_name in corso");
            //session_write_close();

            $this->advance($sEntity->id, 'Lettura file XLS iniziata');
            move_uploaded_file($fname, WWW_ROOT . 'Moma/' . $name);
            $result = $this->Surveys->importExcel($name);
            $this->advance($sEntity->id, 'Lettura file XLS completata');

            if (empty($result)) {
                return $this->Flash->error(__('Il file importato e vuoto.'));
            }
            $q_names = $result[1];
            $q_descr = $result[2];

            //Importo le domande
            $l = count($q_names);
            $this->advance($sEntity->id, "Importazione $l domande iniziata");

            $d = 0;
            $qs = [];
            $qi = array_keys($q_names);
            for ($i = 0; $i < $l; $i++) {
                if (!is_null($q_names[$qi[$i]]) && !empty(trim($q_names[$qi[$i]]))) {
                    $qs[$d]['name'] = substr(trim(strtolower($q_names[$qi[$i]])), 0, 20);
                    if (is_null($q_descr[$qi[$i]])) {
                        $qs[$d]['descr'] = '';
                    } elseif (is_numeric($q_descr[$qi[$i]])) {
                        $qs[$d]['descr'] = $q_descr[$qi[$i]];
                    } else {
                        $qs[$d]['descr'] =  trim($q_descr[$qi[$i]]);
                    }

                    $d++;
                }
            }
            $this->Questions->importQuestions($qs);
            $this->advance($sEntity->id, "Importazione $l domande completata");

            //Importo le risposte
            $rows = count($result);
            //Aggiorno il max
            $s = Cache::read("Survey-{$sEntity->id}");
            $s['max'] += $rows + 2;
            Cache::write("Survey-{$sEntity->id}", $s);
            set_time_limit(2 * $rows);
            for ($i = 3; $i < $rows; $i++) {
                $ans = $result[$i];
                $this->Answers->importAnswers($ans, $q_names, $q_descr, $sEntity->id, $i);
                $this->advance($sEntity->id, "Importazione risposta $i / $rows in corso");
            }
            $this->advance($sEntity->id, "Importazione $rows risposte terminata");

            //Aggiungo tutte le domande alla survey dell'azienda corrente, nell'ordine in cui sono poste
            //$question_list = [];
            $last_q = null;
            foreach ($q_names as $q) {
                if (!empty($q) & $q != $last_q) {
                    $qEntity = $this->Questions->findByName($q)->first();
                    $last_q = $q;
                    if (!empty($qEntity->id)) {
                        $this->Surveys->Questions->link($sEntity, [$qEntity]);
                        //$question_list[] = $qEntity->id;
                    }
                    $this->advance($sEntity->id, "Aggiunta domanda $q alla survey in corso");
                }
            }

            $msg = "Questionario $company_name importato con successo";
            $this->advance($sEntity->id, $msg);
            $this->Flash->success($msg);
            Cache::delete('survey_id');
            Cache::delete("Survey-{$sEntity->id}");
            //session_start();
            $this->redirect(['action' => 'index']);
        }

        $this->set('companies', $companies);
    }

    public function getAdvancement(): void
    {
        $survey_id = Cache::read('Survey_id');
        if (empty($survey_id)) {
            $message = '---';
            $max = 100;
            $adv = 0;
        } else {
            $s = Cache::read("Survey-$survey_id");
            $message = $s['msg'];
            $max = $s['max'];
            $adv = $s['adv'];
        }

        $this->set('message', $message);
        $this->set('max', $max);
        $this->set('adv', $adv);
        $this->viewBuilder()->setOption('serialize', ['message', 'max', 'adv']);
    }

    private function advance($survey_id, $msg)
    {
        $s = Cache::read("Survey-$survey_id");
        $s['msg'] = $msg;
        $s['adv']++;
        Cache::write("Survey-$survey_id", $s);
        Log::write('debug', $msg);
    }

    /**
     * getQuestions - returns all the questions for a survey
     *
     * @param int $id - the id of the survey to get questions for
     * @return void
     */
    public function getQuestions($id, $lang = null)
    {
        if ($lang == 'en') {
            I18n::setLocale('en');
        }

        $survey = $this->Surveys->find('translations')->where(['Surveys.id' => $id])->contain([
            'Questions' => function ($query) use ($id) {
                return $query->find('translations')->contain([
                    'QuestionsSurveys' => function ($query) use ($id) {
                        return $query->find('translations')->where(['survey_id' => $id]);
                    },
                ]);
            },
            'SurveyDeliveryConfigs',
        ])->toArray()[0];

        //TODO: if there are the fields description, long_description and options in the survey_questions model
        //we have to use those. If they are null we will use the "default" contained in the questions table
        //Also, we have to add the translations to the survey_questions table (description, long_description, options) acting in the model
        //and adding the columns to the db table
        //long description is a json field, where description: is the base label (like the old long_description) and
        //the other values like long_description.map_title are the values for complex labels (as in map_question_string)

        //foreach question in survey if there is a specific version in the survey_questions table, use that, otherwise use the one in the questions table
        foreach ($survey->questions as &$q) {
            $q = $this->getCloserQuestion($q);
        }

        //Expand the options
        foreach ($survey->questions as &$q) {
            if (!empty($q['options'])) {
                if (!is_array($q['options']) && $lang == 'en') {
                    $q['en']['options'] = json_decode($q['options']);
                } elseif ($lang == 'en') {
                    $q['en']['options'] = $q['options'];
                }
            }
        }

        $this->set(compact('survey'));
        $this->viewBuilder()->setOption('serialize', ['survey']);
    }

    //foreach question in survey if there is a specific version in the survey_questions table, use that,
    //otherwise use the one in the questions table

    private function getCloserQuestion($q)
    {
        if (!empty($q->_joinData['description'])) {
            $q->description = $q->_joinData['description'];
        }
        if (!empty($q->_joinData['options'])) {
            $q->options = $q->_joinData['options'];
        }
        if (!empty($q->_joinData['long_description'])) {
            $q->long_description = $q->_joinData['long_description'];
        }
        if (!is_null($q->_joinData['conditions'])) {
            $q->conditions = $q->_joinData['conditions'];
        }

        return $q;
    }

    public function getTranslations($id)
    {

        $survey = $this->Surveys->find('translations')
            ->where(['id' => $id])
            ->first();

        $t = $survey->_translations;
        $this->set(compact('t'));
        $this->viewBuilder()->setOption('serialize', ['t']);
    }

    // TODO: A CHE SERVE?

    public function create_options_from_answers($id)
    {
        $survey = $this->Surveys->get($id, [
            'contain' => ['Questions'],
        ]);

        foreach ($survey->questions as $q) {
            $options = [];
            //Lavoro solo sulle domande di tipo single o multiple
            if ($q->type == 'single' || $q->type == 'multiple') {
                //Recupero l'elenco delle risposte diverse a questa domanda
                $a = $this->Surveys->Answers->find()
                    ->distinct('answer')
                    ->where(['question_id' => $q->id, 'survey_id' => $id])
                    ->order('answer')
                    ->toArray();

                //Scrivo le opzioni che ho recuperato dalle risposte
                //Non uso array_map per togliere le vuote e per performance
                foreach ($a as $e) {
                    if (!empty($e->answer) && $e->answer != 'null') {
                        $options[] = $e->answer;
                    }
                }

                $q->options = json_encode($options);

                if (!$this->Surveys->Questions->save($q)) {
                    $this->Flash->error('Errore durante il salvataggio delle opzioni nella risposta:' . $q->id);
                }
            }
        }
    }

    /**
     * toggleQuestionVisibility - cambia la visibilità o l'obbligatorietà di una domanda all'interno di una survey
     */
    public function toggleQuestionVisibility(): void
    {
        $question_id = $this->request->getData('question_id');
        $survey_id = $this->request->getData('survey_id');
        $hidden = $this->request->getData('hidden');
        if (empty($survey_id)) {
            throw new NotFoundException('Impossibile trovare un questionario vuoto');
        }

        $this->allowWhoCanSeeSurveyOnly($survey_id);

        $this->loadModel('Questions');
        // Non ha senso ... la visibilità è a livello di domanda nel questionario, tutti devono poterla modificare
        /*if(
          $this->Questions->isCompulsory($question_id, $survey_id) &&
          $isMomaAzienda &&
          !$this->Questions->isOwnedBy($question_id, $userId)
        ) {
          throw new \Exception("Non puoi modificare la visibilità di questa domanda");
        }*/

        $connection = ConnectionManager::get('default');
        $connection->update(
            'questions_surveys',
            ['hidden' => (int)$hidden],
            ['question_id' => $question_id, 'survey_id' => $survey_id]
        );

        $this->viewBuilder()->setOption('serialize', []);
    }

    public function toggleQuestionCompulsory(): void
    {
        $question_id = $this->request->getData('question_id');
        $survey_id = $this->request->getData('survey_id');
        $compulsory = $this->request->getData('compulsory');

        if (empty($survey_id)) {
            throw new NotFoundException('Impossibile trovare un questionario vuoto');
        }

        $this->allowWhoCanSeeSurveyOnly($survey_id);

        $this->loadModel('Questions');
        // Non ha senso ... l'obbligatorietà è a livello di domanda nel questionario, tutti devono poterla modificare
        /*if($isMomaAzienda &&
          !$this->Questions->isOwnedBy($question_id, $userId)
        ) {
          throw new \Exception("Non puoi modificare la obbligatorietà di questa domanda");
        }*/

        $connection = ConnectionManager::get('default');
        $connection->update(
            'questions_surveys',
            ['compulsory' => (int)$compulsory],
            ['question_id' => $question_id, 'survey_id' => $survey_id]
        );

        $this->viewBuilder()->setOption('serialize', []);
    }

    public function get_delivery_stats($survey_id)
    {
        $this->allowRolesOnly(['admin', 'moma']);

        $res = $this->Surveys->getParticipantsToBeNotifiedFor($survey_id, 'invitation');
        $this->set('res', $res);
    }

    public function getStats($survey_id)
    {
        $this->allowRolesOnly(['admin', 'moma', 'moma_area']);
        $cache = $this->request->getQuery('cache');
        if ($cache == 'clear') {
            Cache::clear('hour');
        }

        $survey = $this->Surveys->find()
            ->select(['company_id', 'sending_mode'])
            ->where(['id' => $survey_id])
            ->first();

        //Se il questionario è anonimo devo prendere il numero di dipendenti dell'azienda dichiarato
        if (!empty($survey) && $survey->sending_mode == 'a') {
            $company_id = $survey->company_id;
            if (!empty($company_id)) {
                $company = $this->Surveys->Companies->find()->where(['id' => $company_id])->first();
                $total_num = $company->survey['nr_dipendenti'];
            }
        } else {
            //Altrimenti prendo il numero di partecipanti della survey
            $total_num = $this->Surveys->SurveyParticipants->countAll($survey_id);
        }

        $w = "getStats-$survey_id";
        $res = Cache::read($w, 'hour');
        if (!empty($res) && $res['participants']['total_num'] != $total_num) {
            $res = [];
        }
        if (empty($res)) {
            $res = [
                'participants' => [
                    'total_num' => $total_num,
                    'survey_completed_num' => $this->Surveys->SurveyParticipants->countAll($survey_id, [
                        'survey_completed_at IS NOT' => null,
                    ]),
                    'survey_not_completed_num' => $this->Surveys->SurveyParticipants->countAll($survey_id, [
                        'survey_completed_at IS' => null,
                    ]),
                    'survey_completed_anonimous' => $this->Surveys->SurveyParticipants->countAll($survey_id, [
                        'survey_completed_at IS NOT' => null,
                        'Users.email LIKE' => '%@email.invalid',
                    ]),
                    'survey_completed_nominal' => $this->Surveys->SurveyParticipants->countAll($survey_id, [
                        'survey_completed_at IS NOT' => null,
                        'Users.email NOT LIKE' => '%@email.invalid',
                    ]),
                    'invitation_sent_num' => $this->Surveys->SurveyParticipants->countAll($survey_id, [
                        'invitation_delivered_at IS NOT' => null,
                        'Users.email NOT LIKE' => '%@email.invalid',
                    ]),
                    'reminder_sent_num' => $this->Surveys->SurveyParticipants->countAll($survey_id, [
                        'first_reminder_delivered_at IS NOT' => null,
                    ]),
                    'sendable_invitation_num' => $this->Surveys->SurveyParticipants->countAllToBeNotifiedFor($survey_id, 'invitation'),
                    'sendable_reminder_num' => $this->Surveys->SurveyParticipants->countAllToBeNotifiedFor($survey_id, 'reminder'),
                    'sent' => $this->Surveys->SurveyParticipants->countSent($survey_id),
                    'errors' => $this->Surveys->SurveyParticipants->countErrors($survey_id),
                ],
            ];
            Cache::write($w, $res, 'hour');
        }

        $this->set('stats', $res);
        $this->viewBuilder()->setOption('serialize', ['stats']);
    }

    public function getStatsByOffice($survey_id, $office_question_id)
    {
        $this->allowRolesOnly(['admin', 'moma', 'moma_area']);
        $connection = ConnectionManager::get('default');

        $sql = 'select answer, count(answer) as c from answers where survey_id=:survey_id and question_id=:question_id group by answer order by count(answer) desc;';
        $statement = $connection->prepare($sql);
        $statement->bindValue('survey_id', $survey_id, 'integer');
        $statement->bindValue('question_id', $office_question_id, 'integer');
        $statement->execute();
        $res = $statement->fetchAll('assoc');

        $this->set('stats', $res);
        $this->viewBuilder()->setOption('serialize', ['stats']);
    }

    //Elimina i partecipanti anonimi che non hanno nessuna risposta

    public function deleteEmptyParticipants($survey_id)
    {
        $this->allowRolesOnly(['admin', 'moma']);
        $this->request->allowMethod(['post', 'delete']);
        // $this->autoRender = false;
        // initialize varibles
        $errorMsg = null;
        $participants = 0;
        $users = 0;
        try {
            $survey = $this->Surveys->get($survey_id);
            $company_id = $survey->company_id;

            // // Define the batch size
            // $batchSize = 10000;

            // Obtain user IDs in batches for users with empty answers
            $usersQuery = $this->Surveys->Users->find()
                ->matching('SurveyParticipants', function ($q) use ($survey_id) {
                    return $q->where(['SurveyParticipants.survey_id' => $survey_id]);
                })
                ->leftJoinWith('Answers', function ($q) use ($survey_id) {
                    return $q->where(['Answers.survey_id' => $survey_id]);
                })
                ->where(['Users.company_id' => $company_id])
                ->group(['Users.id'])
                ->having(['COUNT(Answers.id) = 0'])
                ->select(['Users.id', 'Users.email', 'Users.first_name', 'Users.last_name', 'Users.company_id']);

            // ->each(function ($user) {
            //     $user->answers = $this->Surveys->Answers->find()
            //         ->where(['user_id' => $user->id])
            //         ->count();
            // });
            // ->select(['user_id'])
            //     ->distinct();

            // $usersQuery = $this->Surveys->SurveyParticipants->find()
            //     ->innerJoinWith('Users', function ($q) {
            //         return $q->where(['Users.email LIKE' => '%invalid%']);
            //     })
            //     ->where(['survey_id' => $survey_id])
            //     ->select(['user_id'])
            //     ->distinct();

            $totalUsers = $usersQuery->count();
            // $batches = ceil($totalUsers / $batchSize);

            // for ($i = 0; $i < $batches; $i++) {
            //     $batchUsers = $usersQuery->limit($batchSize)->offset($i * $batchSize)->toArray();
            //     $userIds = array_map(function ($user) {
            //         return $user->id;
            //     }, $batchUsers);
            $userIds = array_map(function ($user) {
                return $user->id;
            }, $usersQuery->toArray());

            if (!empty($userIds)) {
                // Delete from survey_participants
                $participants = $this->Surveys->SurveyParticipants->deleteAll([
                    'user_id IN' => $userIds,
                    'survey_id' => $survey_id,
                    // 'user_id NOT IN' => $this->Surveys->Answers->find()
                    //     ->select(['user_id'])
                    //     ->where(['survey_id' => $survey_id])
                ]);

                // Delete from users
                $users = $this->Surveys->Users->deleteAll([
                    'company_id' => $company_id,
                    'last_name' => 'Anonimo',
                    'first_name' => 'Partecipante',
                    'id IN' => $userIds,
                ]);
            }
            // }


            $this->Surveys->SurveyParticipants->deleteCountAllCache($survey_id);
            $this->Surveys->SurveyParticipants->deleteCountAllCache($survey_id, [
                'invitation_delivered_at IS NOT' => null,
                'Users.email NOT LIKE' => '%@email.invalid',
            ]);
            $w = "getStats-$survey_id";
            // delete the cache
            Cache::delete($w, 'long');
        } catch (\Exception $e) {
            // Cache::write('participants', $participants);
            // Cache::write('users_deleted', $users);
            $errorMsg = $e->getMessage();
            $this->set('errorMsg', $errorMsg);
            $this->set('participants', $participants);
            $this->set('users_deleted', $users);
            $this->viewBuilder()->setOption('serialize', ['errorMsg', 'participants', 'users_deleted', 'answers', 'origins', 'employees']);
            if ($this->request->is('post')) {
                $this->Flash->error(__('The empty participants have not been deleted. Participants deleted: ' . $participants . ' Users deleted: ' . $users));

                return $this->redirect(['action' => 'index']);
            }
        }
        // $errorMsg = $e->getMessage();
        $this->set('errorMsg', $errorMsg);
        $this->set('participants', $participants);
        $this->set('users_deleted', $users);
        $this->viewBuilder()->setOption('serialize', ['errorMsg', 'participants', 'users_deleted', 'answers', 'origins', 'employees']);
        // if post
        if ($this->request->is('post')) {
            $this->Flash->success(__('The empty participants have been deleted. Participants deleted: ' . $participants . ' Users deleted: ' . $users));

            return $this->redirect(['action' => 'index']);
        }
    }

    public function sendNotifications($type, $survey_id)
    {
        $this->allowRolesOnly(['admin', 'moma']);

        $survey = $this->Surveys->get($survey_id, [
            'contain' => [
                'SurveyDeliveryConfigs',
            ],
        ]);
        $now = FrozenDate::now();

        if (!$survey) {
            throw new Exception("Survey $survey_id not found");
        }
        if ($survey['opening_date'] == null) {
            $survey['opening_date'] = $now;
        }
        if ($survey['closing_date'] == null) {
            $survey['closing_date'] = $now;
        }
        if (!($now >= $survey['opening_date'] && $survey['closing_date'] >= $now)) {
            throw new Exception("Survey $survey_id is closed");
        }

        if (!in_array($type, ['invitation', 'reminder'])) {
            throw new Exception("Unknown notification type $type");
        }

        foreach ($this->Surveys->SurveyParticipants->getAllToBeNotifiedFor($survey_id, $type) as $participant) {
            //il partecipante viene modificato con l'ora di invio
            $participant = $participant->sendToParticipant($survey, $type);
            //quindi devo salvare la modifica
            $SurveyParticipant = $this->Surveys->SurveyParticipants->newEntity($participant, ['associated' => ['Notifications']]);
            $this->Surveys->SurveyParticipants->save($SurveyParticipant);
        }
        // delete the cache
        $w = "getStats-$survey_id";
        Cache::delete($w, 'long');

        $this->set('res', null);
        $this->viewBuilder()->setOption('serialize', ['res']);
    }

    public function sendTestInvitation($survey_id)
    {
        $this->allowRolesOnly(['admin', 'moma']);

        $this->request->allowMethod(['post']);
        $survey = $this->Surveys->get($survey_id, [
            'contain' => [
                'SurveyDeliveryConfigs',
            ],
        ]);
        $test_rcpt = $this->request->getData();

        $pd = [
            'user' => [
                'email' => $test_rcpt['email'],
                'name' => $test_rcpt['name'],
            ],
            'id' => 'test',
        ];
        $participant = new SurveyParticipant($pd);
        $participant->sendToParticipant($survey, 'invitation');

        $res = 'invio avvenuto con successo';
        $this->set('res', $res);
        $this->viewBuilder()->setOption('serialize', ['res']);
    }

    public function importParticipant($survey_id)
    {
        $this->allowRolesOnly(['admin', 'moma']);

        if ($this->request->is(['post'])) {
            $participant = $this->request->getData();
            $errorMsg = false;
            try {
                $this->loadModel('SurveyParticipants');
                $p = [];
                $p['email'] = $participant[0];
                $p['first_name'] = $participant[1] ?? null;
                $p['last_name'] = $participant[2] ?? null;
                $this->SurveyParticipants->add($p, $survey_id);
            } catch (\Exception $e) {
                $errorMsg = $e->getMessage();
            }
            $this->set('errorMsg', $errorMsg);
            $this->viewBuilder()->setOption('serialize', ['errorMsg']);
        }
    }

    // genera un nuovo partecipante (e utente) per la compilazione di un questionario come utente anonimo
    // (da link anonimo)

    public function addAnonParticipant($survey_id)
    {
        $s = $this->Surveys->exists(['id' => $survey_id]);
        if (!$s) {
            throw new NotFoundException("Il questionario $survey_id non esiste");
        }

        // METODO PUBBLICO!
        $errorMsg = false;
        $participant_id = null;
        try {
            $this->loadModel('SurveyParticipants');
            $participant_id = $this->SurveyParticipants->add([
                'email' => \Cake\Utility\Text::uuid() . '@email.invalid', //http://www.faqs.org/rfcs/rfc2606.html
                'first_name' => 'Partecipante',
                'last_name' => 'Anonimo',
            ], $survey_id);
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
        }
        $this->set('errorMsg', $errorMsg);
        $this->set('participant_id', $participant_id);
        $this->viewBuilder()->setOption('serialize', ['errorMsg', 'participant_id']);
    }

    public function createTemplate()
    {
        $this->allowRolesOnly(['admin', 'moma']);

        if ($this->request->is(['post'])) {
            $errorMsg = false;
            $survey_id = $this->request->getData('survey_id');
            try {
                $source = $this->Surveys->get($survey_id);
                if (empty($source) || $source['version_tag'] == 'template') {
                    throw new \Exception("Questionario $survey_id non trovato");
                }
                $existingTemplateFromSameSurvey = $this->Surveys->find()->where([
                    'name' => $source['name'],
                    'version_tag' => 'template',
                ])->first();
                $id = empty($existingTemplateFromSameSurvey) ? null : $existingTemplateFromSameSurvey['id'];
                $template = $this->Surveys->newEntity([
                    'id' => $id,
                    'name' => $source['name'],
                    'version_tag' => 'template',
                    'description' => "template from {$source["name"]}",
                ]);
                $this->Surveys->save($template);
                $this->Surveys->cloneQuestions($survey_id, $template['id']);
            } catch (\Exception $e) {
                $errorMsg = $e->getMessage();
            }
            $this->set('errorMsg', $errorMsg);
            $this->set('template_id', $template['id']);
            $this->viewBuilder()->setOption('serialize', ['errorMsg', 'template_id']);
        }
    }

    public function templateList()
    {
        $this->allowRolesOnly(['admin', 'moma']);

        $templates = $this->Surveys->find()
            ->select(['id', 'name'])
            ->where([
                'version_tag' => 'template',
            ])
            ->order('name')
            ->toArray();

        $this->set('templates', $templates);
        $this->viewBuilder()->setOption('serialize', ['templates']);
    }

    public function getList()
    {
        // $this->Authorization->skipAuthorization();
        $this->allowRolesOnly(['admin', 'moma', 'moma_area']);
        $company_id = $this->request->getQuery('company_id');
        $office_id = $this->request->getQuery('office_id');

        $surveyList = $this->Surveys->find()
            ->select(['id', 'name', 'year'])
            ->where([
                'version_tag <>' => 'template',
            ])
            ->order('year DESC, name ASC');
        $this->Authorization->applyScope($surveyList);
        $surveyList->where(['company_id' => $company_id]);

        // $identity = $this->Authentication->getIdentity();
        // if ($identity->get('company_id')) {
        //     $company_id = $identity->get('company_id');
        // }
        // if (!empty($company_id)) {
        //     $surveyList->where(['company_id' => $company_id]);
        // }
        /* if ($identity->get('office_id')) {
            $office_id  =   $identity->get('office_id');
            $surveyList->where(['office_id' => $office_id]);
        } */

        $surveyList->toList();

        $this->set('surveyList', $surveyList);
        $this->viewBuilder()->setOption('serialize', ['surveyList']);
    }

    public function removeQuestion($survey_id)
    {
        $this->allowWhoCanSeeSurveyOnly($survey_id);
        if ($this->request->is(['post'])) {
            $errorMsg = false;
            $question_id = $this->request->getData('question_id');
            try {
                $connection = ConnectionManager::get('default');
                $connection->delete('questions_surveys', [
                    'survey_id' => $survey_id,
                    'question_id' => $question_id,
                ]);
                //Se la domanda è proprio origine spostamenti, elimino le domande che sono impostate in Questions_spos
                if ($question_id == Configure::read('Questions.origine_spostamenti')) {
                    $q_maps = Configure::read('Questions_spos');
                    foreach ($q_maps as $q_map_id) {
                        $connection->delete('questions_surveys', [
                            'survey_id' => $survey_id,
                            'question_id' => $q_map_id,
                        ]);
                    }
                }
            } catch (\Exception $e) {
                $errorMsg = $e->getMessage();
            }
            $this->set('errorMsg', $errorMsg);
            $this->viewBuilder()->setOption('serialize', ['errorMsg']);
        }
    }

    // setta a completed il questionario di un dato partecipante (metodo pubblico!)

    public function completed($survey_id)
    {
        if ($this->request->is(['post'])) {
            $errorMsg = false;
            $participant_id = $this->request->getData('participant_id');
            try {
                $connection = ConnectionManager::get('default');
                $connection->update('survey_participants', [
                    'survey_completed_at' => date('Y-m-d H:i:s'),
                ], [
                    'id' => $participant_id,
                    'survey_id' => $survey_id,
                ]);
            } catch (\Exception $e) {
                $errorMsg = $e->getMessage();
            }
            $this->set('errorMsg', $errorMsg);
            $this->viewBuilder()->setOption('serialize', ['errorMsg']);
        }
    }

    public function exportSurveyData($survey_id)
    {

        $ans = $this->Surveys->find()
            ->contain(['Answers']);

        // $query = $this->Offices->find()->select()
        // ->contain(['Companies']);

        sqld($ans);

        // $spreadsheet = new Spreadsheet();
        // $sheet = $spreadsheet->getActiveSheet();

        // $row = '1';
        // $col = 'A';
        // $columns = ['province', 'city', 'company.name', 'name', 'address', 'office_code_external', 'company.company_code',];

        // foreach ($companies as $p) {
        //     //We convert every row to a flat array
        //     $p = Hash::flatten($p);
        //     if ($row == 1) {
        //         //https://api.cakephp.org/4.0/trait-Cake.Datasource.EntityTrait.html#getVisible
        //         //Restituisce l'elenco dei campi visibili della query

        //         foreach ($columns as $c) {
        //             if ($c == "company.company_code") {
        //                 $sheet->setCellValue("$col$row", "Formazione Professionale");
        //             } else {
        //                 $sheet->setCellValue("$col$row", $c);
        //             }
        //             $col++;
        //         }
        //     }

        //     $row++;
        //     $col = 'A';
        //     foreach ($columns as $c) {
        //         $value = $p[$c];
        //         if ($c == 'office_code_external') {
        //             if ($value == null) {
        //                 $sheet->setCellValue("$col$row", "PRIVATO");
        //             } else {
        //                 $sheet->setCellValue("$col$row", "PUBBLICO");
        //             }
        //         } elseif ($c == "company.company_code") {
        //             if ($value == null) {
        //                 $sheet->setCellValue("$col$row", "SI");
        //             } else {
        //                 $sheet->setCellValue("$col$row", "NO");
        //             }
        //         } else {
        //             $sheet->setCellValue("$col$row", $value);
        //         }
        //         $col++;
        //     }
        // }

        // $writer = new Xlsx($spreadsheet);
        // $writer->save('php://output');

        // // Return response object to prevent controller from trying to render
        // // a view.
        // return;
    }

    //Elimina i partecipanti anonimi che non hanno nessuna risposta

    public function deleteAllSurveyParticipants($id)
    {
        $this->allowRolesOnly(['admin', 'moma']);
        $this->allowWhoCanSeeSurveyOnly($id);
        $errorMsg = false;

        $this->request->allowMethod(['post', 'delete']);
        try {
            $participants = $this->Surveys->SurveyParticipants->deleteAll(['survey_id' => $id, 'survey_completed_at IS' => null]);
            $w = "getStats-$id";
            Cache::delete($w, 'long');
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
        }
        $this->set('errorMsg', $errorMsg);
        $this->set('participants', $participants);
        $this->viewBuilder()->setOption('serialize', ['errorMsg', 'participants']);
    }

    /**
     * Pass a configuration from backend to frontend.
     * Usecase: the chartcolors are defined only in the backend so that the frontend can get it
     *
     * @param mixed $what //The settings.php parameter you want to read from the configuration
     * @return \App\Controller\json value of the configure settings
     * @throws \Cake\Http\Exception\MethodNotAllowedException  (the settings you want to allow should be in the whitelist)
     * @throws \App\Controller\InvalidArgumentException
     */
    public function getConfig($what)
    {
        $whiteList = ['chartColors', 'Questions_spos'];

        if (!in_array($what, $whiteList)) {
            //throw not allowed exception
            throw new MethodNotAllowedException("The requested config $what is not allowed");
        }

        $res = Configure::read($what);
        $this->set('data', $res);
        $this->viewBuilder()->setOption('serialize', ['data']);
    }

    public function updateOfficeQuestion($id)
    {
        $offices = $this->request->getData();
        if (empty($offices)) {
            $this->set('msg', '');
            $this->viewBuilder()->setOption('serialize', ['msg']);

            return;
        }
        //TODO: Verificare se serve verificare il ruolo moma e admin

        $this->QuestionSurveys = TableRegistry::getTableLocator()->get('QuestionsSurveys');
        $q = $this->QuestionSurveys->find()
            ->where(['survey_id' => $id])
            ->where(['question_id' => Configure::read('Questions_spos.sede_mappa')])
            ->first();

        //Se non trovo nessuna domanda sede per questo questionario
        if (empty($q)) {
            $this->set('msg', '');
            $this->viewBuilder()->setOption('serialize', ['msg']);

            return;
        }

        $onlyNames = [];
        foreach ($offices as $o) {
            $onlyNames[] = $o['name'];
        }
        $q->options = $onlyNames;
        if (!$this->QuestionSurveys->save($q)) {
            $msg = 'Impossibile salvare le sedi per questa azienda';
        } else {
            $msg = 'Salvataggio sedi per questa azienda: ok';
        }
        $this->set('msg', $msg);
        $this->viewBuilder()->setOption('serialize', ['msg']);
    }

    // Elimina utenti che non hanno completato il questionario

    public function deleteUsersNotCompleted($survey_id)
    {
        try {
            $this->allowRolesOnly(['admin', 'moma']);
            $this->allowWhoCanSeeSurveyOnly($survey_id);
            $errorMsg = false;

            $this->request->allowMethod(['post', 'delete']);
            // initialize variables
            $participants = 0;
            $users_deleted = 0;
            $answers = 0;
            $origins = 0;
            $employees = 0;

            // Obtain users that have not completed the survey
            $usersQuery = $this->Surveys->SurveyParticipants->find()
                ->where(['survey_id' => $survey_id, 'survey_completed_at IS' => null])
                ->select(['user_id']);

            // Process users in batches
            $batchSize = 100; // Define the batch size
            $users = $usersQuery->toArray();
            $totalUsers = count($users);
            $batches = ceil($totalUsers / $batchSize);

            for ($i = 0; $i < $batches; $i++) {
                $batchUsers = array_slice($users, $i * $batchSize, $batchSize);
                $userIds = array_map(function ($user) {
                    return $user->user_id;
                }, $batchUsers);

                if (!empty($userIds)) {
                    // Delete their answers
                    $answers = $this->Surveys->Answers->deleteAll(['survey_id' => $survey_id, 'user_id IN' => $userIds]);
                    // Delete origins
                    $origins = $this->Surveys->Users->Origins->deleteAll(['user_id IN' => $userIds]);
                    // Delete employees
                    $employees = $this->Surveys->Users->Employees->deleteAll(['user_id IN' => $userIds]);
                    // Delete the users
                    $users_deleted = $this->Surveys->Users->deleteAll(['id IN' => $userIds]);
                    // Delete the users in survey_participants
                    $participants = $this->Surveys->SurveyParticipants->deleteAll(['survey_id' => $survey_id, 'user_id IN' => $userIds]);
                }
            }
            // Delete the cache
            $w = "getStats-$survey_id";
            Cache::delete($w, 'long');
            $this->Surveys->SurveyParticipants->deleteCountAllCache($survey_id, [
                'survey_completed_at IS' => null,
            ]);

            $this->set('errorMsg', $errorMsg);
            $this->set('participants', $participants);
            $this->set('users_deleted', $users_deleted);
            $this->set('answers', $answers);
            $this->set('origins', $origins);
            $this->set('employees', $employees);

            $this->viewBuilder()->setOption('serialize', ['errorMsg', 'participants', 'users_deleted', 'answers', 'origins', 'employees']);
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
            // Optionally log the error
            Log::error('Error in deleteUsersNotCompleted: ' . $e->getMessage());
        }
    }

    // delete surveys that are not answers, not closing date and created first of 31/12/2023
    public function deleteSurveysEmpty()
    {
        try {
            $this->allowRolesOnly(['admin', 'moma']);
            $errorMsg = false;

            // $this->request->allowMethod(['post', 'delete']);
            $surveys = $this->Surveys->find()
                ->leftJoinWith('Answers')
                ->where(['closing_date IS' => null, 'Surveys.created <' => '2023-12-31'])
                ->group(['Surveys.id'])
                ->having(['COUNT(Answers.id) =' => 0])
                ->select(['Surveys.id'])
                ->toArray();

            // Extract survey IDs
            $surveyIds = array_map(function ($survey) {
                return $survey->id;
            }, $surveys);

            $participants = $this->Surveys->SurveyParticipants->deleteAll(['survey_id IN' => $surveyIds]);
            $surveys_deleted = $this->Surveys->deleteAll(['id IN' => $surveyIds]);

            $this->Flash->success("Sono eliminati questi questionari: $surveys_deleted");
            $this->Flash->success("Sono eliminati questi partecipanti: $participants");

            $this->viewBuilder()->setOption('serialize', ['errorMsg', 'participants', 'surveys_deleted', 'answers']);
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
            // Optionally log the error
            Log::error('Error in deleteSurveysNotCompleted: ' . $e->getMessage());
            $this->Flash->error('Messaggio di errore:' . $e->getMessage());
        }

        return $this->redirect(['action' => 'index']);
    }
    public function generateMissingUsers($survey_id)
    {
        $this->allowRolesOnly(['admin']);

        //Prima pulisco il questionario
        try {
            $errorMsg = $this->Surveys->SurveyParticipants->generateMissingUsers($survey_id);
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
        }
        $this->set('errorMsg', $errorMsg);
        $this->viewBuilder()->setOption('serialize', ['errorMsg']);
    }
}
