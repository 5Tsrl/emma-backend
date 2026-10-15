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
use App\Service\CompanyPrivacyDocumentService;
use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\Filesystem\Folder;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Exception\InternalErrorException;
use Cake\Http\Exception\MethodNotAllowedException;
use Cake\Http\Exception\NotFoundException;
use Cake\I18n\FrozenDate;
use Cake\I18n\I18n;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;
use Cake\Routing\Router;
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
            'employeePrivacyDocument',
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
            'Users',
        ])
            ->select(['Surveys.id', 'name', 'opening_date', 'closing_date', 'version_tag', 'company_id', 'year'])
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
        $version_tag = $this->request->getQuery('version_tag');
        if (!empty($version_tag)) {
            $query->where(['Surveys.version_tag' => $version_tag]);
        } else {
            $year = $this->request->getQuery('year');
            if (!(empty($year) || $year == 'TUTTI')) {
                $query->where([
                    'OR' => [
                        ['Surveys.year' => $year],
                        ['Surveys.year IS' => null],
                    ],
                ]);
            }
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

        // Regressione i18n (Part 16, terza occorrenza della stessa classe di
        // bug del Part 15/editFromSurvey — vedi CLAUDE.md): a differenza di
        // edit(), questo metodo non forzava la locale di default sulle
        // tabelle Translate-enabled coinvolte. Sotto una locale diversa dalla
        // default (es. Accept-Language: fr, usata da Analytics.vue), il
        // Translate behavior sostituisce le colonne base delle Questions
        // contenute (`description`/`options`) con la riga i18n tradotta —
        // per `options` questo restituisce una stringa pipe-delimited invece
        // dell'array canonico. Stesso pattern di guardia di edit() (righe
        // ~354-358 qui sopra), senza SurveyDeliveryConfigs perché view() non
        // la contiene.
        $defaultLocale = I18n::getDefaultLocale();
        $this->Surveys->setLocale($defaultLocale);
        $this->Surveys->Questions->setLocale($defaultLocale);
        $this->Surveys->Questions->QuestionsSurveys->setLocale($defaultLocale);

        // Apply scope to the Questions association
        $survey = $this->Surveys->get($id, [
            'contain' => [
                'Companies',
                'Users',
                'Questions' => function ($q) {
                    return $this->Authorization->applyScope($q);
                },
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

    /**
     * Add Bulk method — creates one survey per selected company.
     *
     * Accepts POST with JSON body:
     *   { "company_ids": [1, 2, 3], "survey_template_id": -1, "year": 2026 }
     *
     * Returns:
     *   { "surveys": [{"company_id": 1, "company_name": "...", "survey_id": 5}],
     *     "errors":  [{"company_id": 2, "error": "..."}] }
     *
     * @return \Cake\Http\Response|null|void
     */
    public function addBulk()
    {
        $this->allowRolesOnly(['admin', 'moma']);
        $this->request->allowMethod(['post']);

        $companyIds = array_values(array_unique(array_map('intval', (array)$this->request->getData('company_ids'))));
        $companyIds = array_values(array_filter($companyIds, function ($companyId) {
            return $companyId > 0;
        }));
        $applyToAllRaw = $this->request->getData('apply_to_all');
        $applyToAll = filter_var($applyToAllRaw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $applyToAll = $applyToAll ?? false;
        $surveyTemplateId = $this->request->getData('survey_template_id');
        $yearRaw = $this->request->getData('year');
        $year = !empty($yearRaw) ? (int)$yearRaw : null;

        $surveys = [];
        $errors = [];

        $authorizedCompaniesQuery = $this->Surveys->Companies->find()
            ->select(['Companies.id', 'Companies.name']);
        $authorizedCompaniesQuery = $this->Authorization->applyScope($authorizedCompaniesQuery, 'list');

        if (!$applyToAll) {
            $authorizedCompaniesQuery->where(['Companies.id IN' => $companyIds]);
        }

        $authorizedCompanies = $authorizedCompaniesQuery->all()->toList();

        if (!$applyToAll && !empty($companyIds)) {
            $authorizedCompanyIds = array_map(function ($company) {
                return (int)$company->id;
            }, $authorizedCompanies);
            $unauthorizedCompanyIds = array_diff($companyIds, $authorizedCompanyIds);
            foreach ($unauthorizedCompanyIds as $unauthorizedCompanyId) {
                $errors[] = [
                    'company_id' => (int)$unauthorizedCompanyId,
                    'error' => 'Non sei autorizzato ad accedere alla risorsa richiesta',
                ];
            }
        }

        foreach ($authorizedCompanies as $company) {
            try {
                if (empty($surveyTemplateId) || (int)$surveyTemplateId === -1) {
                    $sid = $this->Surveys->createNewEmptySurvey($company, $year);
                } else {
                    $sid = $this->Surveys->createNewSurveyFromTemplate($company, (int)$surveyTemplateId, $year);
                }
                $surveys[] = [
                    'company_id' => $company->id,
                    'company_name' => $company->name,
                    'survey_id' => $sid,
                ];
            } catch (\Exception $e) {
                $errors[] = [
                    'company_id' => $company->id,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $requestedCount = $applyToAll ? count($authorizedCompanies) : count($companyIds);

        $summary = [
            'apply_to_all' => $applyToAll,
            'requested_count' => $requestedCount,
            'authorized_count' => count($authorizedCompanies),
            'created_count' => count($surveys),
            'error_count' => count($errors),
        ];

        $this->set(compact('surveys', 'errors', 'summary'));
        $this->viewBuilder()->setOption('serialize', ['surveys', 'errors', 'summary']);
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
                $fullDirTemplate = ':sitedir/companies/logo/';
                $save_dir = Text::insert($fullDirTemplate, [
                    'sitedir' => Configure::read('sitedir'),
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

        // Editor di traduzioni: la pagina deve mostrare i valori BASE canonici
        // (italiani) + TUTTE le lingue in `_translations`. Sotto una lingua ≠ default
        // il Translate behavior tradurrebbe i campi base — es. `options` (JSON array)
        // torna come stringa pipe-delimited tradotta — rompendo il front-end che si
        // aspetta un array. `find('translations')` carica comunque `_translations`
        // per ogni lingua indipendentemente dalla locale, quindi forziamo la lingua
        // di default su tutte le tabelle Translate coinvolte: niente join i18n sui
        // campi base (EavStrategy::beforeFind esce subito), `_translations` intatto.
        // Vale anche per il ramo di SAVE (scrive il valore canonico nella colonna base
        // e le traduzioni esplicite via $survey->translation($lang)). Vedi CLAUDE.md i18n.
        $defaultLocale = I18n::getDefaultLocale();
        $this->Surveys->setLocale($defaultLocale);
        $this->Surveys->Questions->setLocale($defaultLocale);
        $this->Surveys->Questions->QuestionsSurveys->setLocale($defaultLocale);
        $this->Surveys->SurveyDeliveryConfigs->setLocale($defaultLocale);

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
            // Part 11: skip-empty deve avvenire PRIMA di patchEntity(). Il
            // Translate behavior marshalla automaticamente `_translations`
            // dentro patchEntity() (TranslateStrategyTrait::buildMarshalMap):
            // crea comunque una sotto-entity di traduzione per ogni lingua
            // presente nel payload, indipendentemente dal contenuto dei campi —
            // un controllo skip-empty fatto DOPO patchEntity() (un semplice
            // ciclo manuale) arriva troppo tardi per evitare quella sotto-entity
            // (e quindi la riga i18n) di essere creata. Filtrare i dati grezzi
            // qui è l'unico punto che previene davvero una riga i18n "vuota".
            // Di conseguenza una traduzione non può oggi essere CANCELLATA
            // dall'editor (limite noto, vedi CLAUDE.md).
            $data['_translations'] = $this->stripEmptyTranslations($data['_translations'] ?? null);
            $survey = $this->Surveys->patchEntity($survey, $data);
            // Gestione della traduzione: mi aspetto di ricevere un array [en|fr|de] => ['campo' => traduzione, 'campo2'=>traduzione]
            // Part 11: esteso da solo 'en' a 'en'+'fr'. Ridondante con il
            // marshalling automatico di cui sopra (stessa sotto-entity, stesso
            // valore) ma innocuo — mantenuto per coerenza con la struttura
            // preesistente del metodo.
            foreach (['en', 'fr', 'de'] as $lang) {
                if (!isset($data['_translations'][$lang])) {
                    continue;
                }
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
     * Part 11 (i18n en+fr): strips empty/whitespace field VALUES out of a raw
     * `_translations` payload (`[lang => [field => value]]`) before it reaches
     * `patchEntity()`. Necessary because the Translate behavior's
     * `buildMarshalMap()` auto-marshals `_translations` INSIDE `patchEntity()`,
     * creating a translation sub-entity for every language key present
     * regardless of field content — a skip-empty check applied only AFTER
     * `patchEntity()` is too late to stop that sub-entity (and its i18n row)
     * from being persisted on save. `QuestionsController::editFromSurvey()` has
     * its own private copy of this same logic (no shared base class for it).
     *
     * @param mixed $translations The raw `_translations` value from request data.
     * @return array The filtered translations, with any language left empty
     *   after filtering removed entirely.
     */
    private function stripEmptyTranslations($translations): array
    {
        if (!is_array($translations)) {
            return [];
        }
        foreach ($translations as $lang => $fields) {
            if (!is_array($fields)) {
                unset($translations[$lang]);
                continue;
            }
            foreach ($fields as $field => $value) {
                if (trim((string)$value) === '') {
                    unset($translations[$lang][$field]);
                }
            }
            if (empty($translations[$lang])) {
                unset($translations[$lang]);
            }
        }

        return $translations;
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
            Cache::clearGroup('dashboard');
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

            $this->Questions = TableRegistry::getTableLocator()->get('Questions');
            $this->Answers = TableRegistry::getTableLocator()->get('Answers');

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
            // Sanitized (CWE-22): $name is the raw client-supplied filename, only
            // ever used here (write + immediate read-back within this same
            // request) - see AppController::sanitizeUploadFilename(). Kept
            // separate from $name, which is still used above as-is for the
            // new-company `name` field (not a path, no traversal risk there).
            $safeName = $this->sanitizeUploadFilename($name);
            move_uploaded_file($fname, WWW_ROOT . 'Moma/' . $safeName);
            $result = $this->Surveys->importExcel($safeName);
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
        } elseif ($lang == 'fr') {
            I18n::setLocale('fr');
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
                } elseif (!is_array($q['options']) && $lang == 'fr') {
                    $q['fr']['options'] = json_decode($q['options']);
                } elseif ($lang == 'fr') {
                    $q['fr']['options'] = $q['options'];
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
            $normalizedConditions = $this->normalizeSurveyQuestionCondition($q->_joinData['conditions']);
            if ($normalizedConditions !== null) {
                $q->conditions = $normalizedConditions;
            } else {
                $q->conditions = null;
                Log::warning(sprintf(
                    'Malformed conditions in questions_surveys (survey_id=%s, question_id=%s)',
                    (string)$q->_joinData['survey_id'],
                    (string)$q->id
                ));
            }
        }

        return $q;
    }

    /**
     * Normalize conditions payload from questions_surveys row.
     *
     * @param mixed $conditions
     * @return array<string,mixed>|null
     */
    private function normalizeSurveyQuestionCondition($conditions): ?array
    {
        if (!is_array($conditions) || $conditions === []) {
            return null;
        }

        if (($conditions['display'] ?? null) !== 'conditionally') {
            return null;
        }

        if (!array_key_exists('question', $conditions) || !is_numeric($conditions['question'])) {
            return null;
        }

        if (!array_key_exists('value', $conditions)) {
            return null;
        }

        if (!is_array($conditions['value']) && !is_numeric($conditions['value'])) {
            return null;
        }

        return $conditions;
    }

    public function getTranslations($id)
    {
        // Editor di traduzioni: base canonica + tutte le lingue in `_translations`.
        // Forzo la lingua di default (vedi nota in edit()).
        $this->Surveys->setLocale(I18n::getDefaultLocale());

        $survey = $this->Surveys->find('translations')
            ->where(['Surveys.id' => $id])
            ->first();

        $t = $survey->_translations;
        $this->set(compact('t'));
        $this->viewBuilder()->setOption('serialize', ['t']);
    }

    // TODO: A CHE SERVE?

    public function create_options_from_answers($id)
    {
        // Riscrive le `options` canoniche dalle risposte: forzo la lingua di default
        // sulla tabella Questions così il save aggiorna la colonna base e non crea
        // una traduzione i18n sotto locale ≠ default. Vedi nota i18n in CLAUDE.md.
        $this->Surveys->Questions->setLocale(I18n::getDefaultLocale());

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

        $this->Questions = TableRegistry::getTableLocator()->get('Questions');
        // Brescia #523 (reverts the old "tutti devono poterla modificare" decision):
        // only an admin may hide a question that is compulsory in this survey.
        // Showing it again (hidden=0) is always allowed.
        if ((int)$hidden === 1 && !$this->isAdminIdentity()) {
            try {
                $isCompulsory = $this->Questions->isCompulsory($question_id, $survey_id);
            } catch (\Exception $e) {
                // isCompulsory() throws a generic \Exception for a missing row
                throw new NotFoundException('Associazione domanda/questionario non trovata');
            }
            if ($isCompulsory) {
                throw new ForbiddenException(__('Non puoi nascondere una domanda obbligatoria'));
            }
        }

        $connection = ConnectionManager::get('default');
        $connection->update(
            'questions_surveys',
            ['hidden' => (int)$hidden],
            ['question_id' => $question_id, 'survey_id' => $survey_id]
        );

        $this->viewBuilder()->setOption('serialize', []);
    }

    /**
     * True when the authenticated user has the `admin` role.
     */
    private function isAdminIdentity(): bool
    {
        $identity = $this->Authentication->getIdentity();

        return $identity !== null && $identity->get('role') === 'admin';
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

        $this->Questions = TableRegistry::getTableLocator()->get('Questions');
        // Brescia #523: changing the compulsory flag is admin-only.
        if (!$this->isAdminIdentity()) {
            throw new ForbiddenException(__('Solo un amministratore può modificare l\'obbligatorietà di una domanda'));
        }

        $connection = ConnectionManager::get('default');
        $connection->update(
            'questions_surveys',
            ['compulsory' => (int)$compulsory],
            ['question_id' => $question_id, 'survey_id' => $survey_id]
        );

        $this->viewBuilder()->setOption('serialize', []);
    }

    /**
     * toggleQuestionOptionsPermission - Admin-only grant/revoke of the
     * "moma can hide answer options" permission for a question in a survey.
     * Refused on any question type other than single/multiple/array.
     * `likert` is DELIBERATELY excluded: `LikertScale.vue` (frontend) stores
     * the answer as the option INDEX itself, so hiding a scale point would
     * corrupt stored answers — not an oversight, see the frontend note.
     * Body: `{question_id, survey_id, allowed}`.
     *
     * @return void
     */
    public function toggleQuestionOptionsPermission(): void
    {
        $question_id = $this->request->getData('question_id');
        $survey_id = $this->request->getData('survey_id');
        $allowed = $this->request->getData('allowed');

        if (empty($survey_id)) {
            throw new NotFoundException('Impossibile trovare un questionario vuoto');
        }

        $this->allowRolesOnly(['admin']);
        $this->allowWhoCanSeeSurveyOnly($survey_id);

        $this->Questions = TableRegistry::getTableLocator()->get('Questions');
        // Forzo la lingua di default: queste query leggono `compulsory_answer`/
        // `type` (non tradotti) ma toccano tabelle con Translate behavior — sotto
        // una locale non default un find() qui aprirebbe comunque il join i18n
        // (id ambiguo, vedi CLAUDE.md "Part 14 Stage A/C"). Stessa cautela usata
        // in detachSection()/deleteSectionGlobal().
        $defaultLocale = I18n::getDefaultLocale();
        $this->Questions->setLocale($defaultLocale);
        $this->Questions->QuestionsSurveys->setLocale($defaultLocale);

        $questionsSurveys = $this->Questions->QuestionsSurveys->find()
            ->where(['question_id' => $question_id, 'survey_id' => $survey_id])
            ->first();
        if (empty($questionsSurveys)) {
            throw new NotFoundException('Associazione domanda/questionario non trovata');
        }

        $question = $this->Questions->get($question_id);
        if (!in_array($question['type'], ['single', 'multiple', 'array'], true)) {
            throw new ForbiddenException(
                __('Questo tipo di domanda non supporta la disabilitazione delle opzioni')
            );
        }

        $update = ['moma_can_edit_options' => (int)$allowed];
        if (!$allowed) {
            // Revoca: azzero anche le opzioni già disabilitate, così una
            // futura ri-concessione riparte pulita.
            $update['disabled_options'] = null;
        }

        $connection = ConnectionManager::get('default');
        $connection->update(
            'questions_surveys',
            $update,
            ['question_id' => $question_id, 'survey_id' => $survey_id]
        );

        $this->viewBuilder()->setOption('serialize', []);
    }

    /**
     * setQuestionDisabledOptions - lets a moma user (or admin) hide individual
     * canonical answer options of a question in a survey at RENDER TIME only
     * — the canonical option list is never deleted/reordered/rewritten.
     * Requires the admin-granted `moma_can_edit_options` permission (see
     * toggleQuestionOptionsPermission()), re-checked server-side even though the UI also gates this
     * (it does NOT refuse on `compulsory`/`compulsory_answer`: a granted
     * compulsory question may have options hidden)
     * (client-side gating is never a security boundary, see the pentest note
     * in 5t.vue's CLAUDE.md — same principle applies here).
     * Body: `{question_id, survey_id, disabled_options}` — a flat array of
     * canonical option indexes for `single`/`multiple`, or an object keyed by
     * group index (e.g. `{"0":[2],"1":[0,4]}`) for `array`; null/empty means
     * "nothing disabled".
     *
     * @return void
     */
    public function setQuestionDisabledOptions(): void
    {
        $question_id = $this->request->getData('question_id');
        $survey_id = $this->request->getData('survey_id');
        $disabledOptions = $this->request->getData('disabled_options');

        if (empty($survey_id)) {
            throw new NotFoundException('Impossibile trovare un questionario vuoto');
        }

        $this->allowWhoCanSeeSurveyOnly($survey_id);

        $this->Questions = TableRegistry::getTableLocator()->get('Questions');
        // Forzo la lingua di default: qui leggo `options` (Translate field su
        // entrambe le tabelle) e mi serve il valore CANONICO per la
        // validazione range — sotto una locale non default tornerebbe una
        // stringa tradotta invece dell'array/JSON canonico (vedi CLAUDE.md,
        // landmine "options" + ambiguità colonna id).
        $defaultLocale = I18n::getDefaultLocale();
        $this->Questions->setLocale($defaultLocale);
        $this->Questions->QuestionsSurveys->setLocale($defaultLocale);

        $questionsSurveys = $this->Questions->QuestionsSurveys->find()
            ->where(['question_id' => $question_id, 'survey_id' => $survey_id])
            ->first();
        if (empty($questionsSurveys)) {
            throw new NotFoundException('Associazione domanda/questionario non trovata');
        }
        if (!$questionsSurveys['moma_can_edit_options']) {
            throw new ForbiddenException(__('Non sei autorizzato a modificare le opzioni di questa domanda'));
        }

        $question = $this->Questions->get($question_id);

        // Elenco opzioni "effettivo" per la validazione del range: l'override
        // per-survey (questions_surveys.options) vince sul canonico
        // questions.options — stesso pattern "closer question" di
        // getCloserQuestion().
        $effectiveOptions = !empty($questionsSurveys['options']) ? $questionsSurveys['options'] : $question['options'];
        if (is_string($effectiveOptions)) {
            $effectiveOptions = json_decode($effectiveOptions, true);
        }

        if (is_string($disabledOptions)) {
            $disabledOptions = json_decode($disabledOptions, true);
        }

        if ($question['type'] === 'array') {
            $normalized = $this->validateArrayDisabledOptions($disabledOptions, $effectiveOptions);
        } elseif (in_array($question['type'], ['single', 'multiple'], true)) {
            $normalized = $this->validateFlatDisabledOptions($disabledOptions, $effectiveOptions);
        } else {
            throw new ForbiddenException(
                __('Questo tipo di domanda non supporta la disabilitazione delle opzioni')
            );
        }

        $connection = ConnectionManager::get('default');
        $connection->update(
            'questions_surveys',
            ['disabled_options' => empty($normalized) ? null : json_encode($normalized)],
            ['question_id' => $question_id, 'survey_id' => $survey_id]
        );

        $this->viewBuilder()->setOption('serialize', []);
    }

    /**
     * Validates a flat disabled_options payload (single/multiple questions):
     * every index must be a non-negative integer within range of $options,
     * and at least one option must remain enabled.
     *
     * @param mixed $disabledOptions Raw payload (expected: flat array of indexes).
     * @param mixed $options Effective canonical option list.
     * @return array<int, int> The normalized (int-cast, de-duplicated) index list.
     */
    private function validateFlatDisabledOptions($disabledOptions, $options): array
    {
        if (empty($disabledOptions)) {
            return [];
        }
        if (!is_array($disabledOptions)) {
            throw new ForbiddenException(__('Formato disabled_options non valido'));
        }

        $optionsList = is_array($options) ? array_values($options) : [];
        $optionsCount = count($optionsList);

        $seen = [];
        foreach ($disabledOptions as $idx) {
            if (!is_numeric($idx) || (int)$idx != $idx || (int)$idx < 0) {
                throw new ForbiddenException(__('Indice opzione non valido'));
            }
            $idx = (int)$idx;
            if ($idx >= $optionsCount) {
                throw new ForbiddenException(__('Indice opzione fuori intervallo'));
            }
            $seen[$idx] = true;
        }

        if ($optionsCount > 0 && count($seen) >= $optionsCount) {
            throw new ForbiddenException(__('Non è possibile disabilitare tutte le opzioni'));
        }

        return array_values(array_keys($seen));
    }

    /**
     * Validates a per-group disabled_options payload (array-type questions,
     * `{group_index: [option_index, ...]}`): every group index and option
     * index must be valid, and every group must keep at least one enabled
     * option.
     *
     * @param mixed $disabledOptions Raw payload (expected: object keyed by group index).
     * @param mixed $options Effective canonical option list (`{groups:[{label,options:[]}]}`).
     * @return array<int, array<int, int>> The normalized (int-cast, de-duplicated) structure.
     */
    private function validateArrayDisabledOptions($disabledOptions, $options): array
    {
        if (empty($disabledOptions)) {
            return [];
        }
        if (!is_array($disabledOptions)) {
            throw new ForbiddenException(__('Formato disabled_options non valido'));
        }

        $groups = is_array($options) ? ($options['groups'] ?? []) : [];
        $groupsCount = count($groups);

        $normalized = [];
        foreach ($disabledOptions as $groupIndex => $indexes) {
            if (!is_numeric($groupIndex) || (int)$groupIndex != $groupIndex || (int)$groupIndex < 0) {
                throw new ForbiddenException(__('Indice gruppo non valido'));
            }
            $groupIndex = (int)$groupIndex;
            if ($groupIndex >= $groupsCount) {
                throw new ForbiddenException(__('Indice gruppo fuori intervallo'));
            }
            if (!is_array($indexes)) {
                throw new ForbiddenException(__('Formato disabled_options non valido'));
            }

            $groupOptions = $groups[$groupIndex]['options'] ?? [];
            $groupOptionsCount = is_array($groupOptions) ? count($groupOptions) : 0;

            $seen = [];
            foreach ($indexes as $idx) {
                if (!is_numeric($idx) || (int)$idx != $idx || (int)$idx < 0) {
                    throw new ForbiddenException(__('Indice opzione non valido'));
                }
                $idx = (int)$idx;
                if ($idx >= $groupOptionsCount) {
                    throw new ForbiddenException(__('Indice opzione fuori intervallo'));
                }
                $seen[$idx] = true;
            }

            if ($groupOptionsCount > 0 && count($seen) >= $groupOptionsCount) {
                throw new ForbiddenException(__('Non è possibile disabilitare tutte le opzioni di un gruppo'));
            }

            $normalized[$groupIndex] = array_values(array_keys($seen));
        }

        return $normalized;
    }

    /**
     * detachSection - removes the questions_surveys link between a section-header
     * question (questions.type = 'section') and a survey, but only when the
     * section's group is empty in that survey.
     *
     * Admin-only. Body: `{"survey_id":N,"question_id":M}` where question_id is the
     * section-header question. "Empty" = no NON-section question sits in this
     * header's weight band: from this header's questions_surveys.weight up to the
     * next section-header's weight in the same survey (or +infinity). If any
     * non-section question falls strictly between → the group is non-empty →
     * refuse. The global section-question row itself is NOT deleted (shared across
     * surveys); only the per-survey questions_surveys link is removed.
     *
     * @return \Cake\Http\Response|null|void
     */
    public function detachSection()
    {
        $this->allowRolesOnly(['admin']);
        $this->request->allowMethod(['post']);

        $errorMsg = false;
        try {
            $surveyId = $this->request->getData('survey_id');
            $questionId = $this->request->getData('question_id');

            if (empty($surveyId) || empty($questionId)) {
                throw new \Exception('survey_id e question_id sono obbligatori');
            }

            // Force default locale so the Translate behavior on the joined
            // `Questions` table does not left-join `i18n` (which makes `id`
            // ambiguous in sectionGroupIsEmpty's innerJoinWith count queries
            // under a non-default request locale such as Accept-Language: fr).
            $defaultLocale = I18n::getDefaultLocale();
            $questionsSurveys = TableRegistry::getTableLocator()->get('QuestionsSurveys');
            $questionsSurveys->setLocale($defaultLocale);
            $questionsSurveys->Questions->setLocale($defaultLocale);

            // The header row linking this section-question to the survey.
            $headerRow = $questionsSurveys->find()
                ->where(['survey_id' => $surveyId, 'question_id' => $questionId])
                ->first();
            if ($headerRow === null) {
                throw new \Exception('La sezione non è collegata a questo questionario');
            }

            if (!$this->sectionGroupIsEmpty($questionsSurveys, $surveyId, $questionId)) {
                throw new \Exception('Impossibile rimuovere la sezione: la sezione contiene domande. Sposta o elimina prima le domande della sezione.');
            }

            // Remove only the per-survey link, not the shared section-question.
            if (!$questionsSurveys->delete($headerRow)) {
                throw new \Exception('Si è verificato un errore durante la rimozione della sezione');
            }
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
        }

        $this->set('errorMsg', $errorMsg);
        $this->viewBuilder()->setOption('serialize', ['errorMsg']);
    }

    /**
     * deleteSectionGlobal - permanently deletes a section-header question
     * (questions.type = 'section') across ALL surveys it is linked to.
     *
     * Admin-only. Body: `{"question_id":M}`. Refuses when the section's group is
     * non-empty (see `sectionGroupIsEmpty()`) in ANY linked survey — deletes
     * nothing in that case. Otherwise, transactionally removes every
     * `questions_surveys` link, the question's `i18n` rows, and the question
     * itself. There are NO foreign keys on `questions.id` in this database, so
     * this app-level emptiness guard is the only protection against orphaning
     * answers/links — it MUST run before any deletion.
     *
     * @return \Cake\Http\Response|null|void
     */
    public function deleteSectionGlobal()
    {
        $this->allowRolesOnly(['admin']);
        $this->request->allowMethod(['post']);

        $errorMsg = false;
        try {
            $questionId = $this->request->getData('question_id');
            if (empty($questionId)) {
                throw new \Exception('question_id è obbligatorio');
            }

            // Force the default (canonical) locale on the Translate-enabled tables
            // so the behavior does NOT left-join the `i18n` table. Without this,
            // under a non-default request locale (e.g. Accept-Language: fr) the
            // bare `where(['id' => ...])` below and the `innerJoinWith('Questions')`
            // count queries in sectionGroupIsEmpty() reference an `id` column that
            // exists on both `questions` and `i18n` → SQL 1052 "Column 'id' in
            // where clause is ambiguous". Same guard pattern used elsewhere here.
            $defaultLocale = I18n::getDefaultLocale();
            $this->Questions = TableRegistry::getTableLocator()->get('Questions');
            $this->Questions->setLocale($defaultLocale);
            $question = $this->Questions->find()->where(['Questions.id' => $questionId])->first();
            if ($question === null || $question->type !== 'section') {
                throw new \Exception('La domanda non è una sezione valida');
            }

            $questionsSurveys = TableRegistry::getTableLocator()->get('QuestionsSurveys');
            $questionsSurveys->setLocale($defaultLocale);
            $questionsSurveys->Questions->setLocale($defaultLocale);
            $links = $questionsSurveys->find()->where(['question_id' => $questionId])->toArray();

            $nonEmptySurveyIds = [];
            foreach ($links as $link) {
                if (!$this->sectionGroupIsEmpty($questionsSurveys, $link->survey_id, $questionId)) {
                    $nonEmptySurveyIds[] = $link->survey_id;
                }
            }

            if (!empty($nonEmptySurveyIds)) {
                throw new \Exception(
                    'Impossibile eliminare: la sezione contiene domande nei questionari: '
                    . implode(', ', $nonEmptySurveyIds)
                );
            }

            $connection = $this->Questions->getConnection();
            $connection->transactional(function () use ($connection, $questionsSurveys, $questionId, $question) {
                $questionsSurveys->deleteAll(['question_id' => $questionId]);

                // Same raw-SQL i18n access pattern as ReorganizeSurveyMeasureModelCommand:
                // the `i18n` table backs the Translate behavior and has no ORM Table
                // class of its own in this codebase.
                $connection->execute(
                    'DELETE FROM i18n WHERE model = :model AND foreign_key = :fk',
                    ['model' => 'Questions', 'fk' => $questionId]
                );

                if (!$this->Questions->delete($question)) {
                    throw new \Exception('Impossibile eliminare la domanda sezione ' . $questionId);
                }
            });
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
            Log::write('error', 'Error in Surveys::deleteSectionGlobal: ' . $e->getMessage());
        }

        $this->set('errorMsg', $errorMsg);
        $this->viewBuilder()->setOption('serialize', ['errorMsg']);
    }

    /**
     * sectionGroupIsEmpty - checks whether a section-header question's group is
     * empty in a given survey.
     *
     * "Empty" = no NON-section question sits in this header's weight band: from
     * this header's `questions_surveys.weight` up to the next section-header's
     * weight in the same survey (or +infinity). If the header is not linked to
     * the survey at all, it is considered empty (nothing to protect there).
     * Shared by `detachSection()` and `deleteSectionGlobal()` so the emptiness
     * rule cannot drift between the two actions.
     *
     * @param \App\Model\Table\QuestionsSurveysTable $questionsSurveys The QuestionsSurveys table.
     * @param int|string $surveyId Survey id.
     * @param int|string $questionId Section-header question id.
     * @return bool True when the section's group is empty (or unlinked).
     */
    private function sectionGroupIsEmpty($questionsSurveys, $surveyId, $questionId): bool
    {
        $headerRow = $questionsSurveys->find()
            ->where(['survey_id' => $surveyId, 'question_id' => $questionId])
            ->first();
        if ($headerRow === null) {
            return true;
        }
        $headerWeight = (int)($headerRow->weight ?? 0);

        // Next section-header weight in the same survey (smallest section-header
        // weight strictly greater than this header's), else +infinity.
        $nextHeaderRow = $questionsSurveys->find()
            ->innerJoinWith('Questions', function ($q) {
                return $q->where(['Questions.type' => 'section']);
            })
            ->where([
                'QuestionsSurveys.survey_id' => $surveyId,
                'QuestionsSurveys.weight >' => $headerWeight,
            ])
            ->orderAsc('QuestionsSurveys.weight')
            ->orderAsc('QuestionsSurveys.id')
            ->first();
        $nextHeaderWeight = $nextHeaderRow !== null ? (int)($nextHeaderRow->weight ?? 0) : PHP_INT_MAX;

        // Any NON-section question strictly between the two header weights?
        $bandConditions = [
            'QuestionsSurveys.survey_id' => $surveyId,
            'QuestionsSurveys.weight >' => $headerWeight,
            'Questions.type !=' => 'section',
        ];
        if ($nextHeaderWeight !== PHP_INT_MAX) {
            $bandConditions['QuestionsSurveys.weight <'] = $nextHeaderWeight;
        }
        $groupCount = $questionsSurveys->find()
            ->innerJoinWith('Questions')
            ->where($bandConditions)
            ->count();

        return $groupCount === 0;
    }

    /**
     * reorderSections - flat re-weight of the questions_surveys rows for a survey.
     *
     * Admin-only. Body: `{"survey_id":N,"order":[<questionId>, ...]}` = the full
     * flat order of question ids for that survey (section headers and their
     * questions interleaved). Transactionally sets
     * `questions_surveys.weight = index*10` for each row matched by
     * (survey_id, question_id). Ids without a matching row are ignored.
     *
     * @return \Cake\Http\Response|null|void
     */
    public function reorderSections()
    {
        $this->allowRolesOnly(['admin']);
        $this->request->allowMethod(['post']);

        $errorMsg = false;
        try {
            $surveyId = $this->request->getData('survey_id');
            $order = (array)$this->request->getData('order');

            if (empty($surveyId)) {
                throw new \Exception('survey_id è obbligatorio');
            }

            $questionsSurveys = TableRegistry::getTableLocator()->get('QuestionsSurveys');

            $questionsSurveys->getConnection()->transactional(function () use ($questionsSurveys, $surveyId, $order) {
                foreach (array_values($order) as $index => $questionId) {
                    $row = $questionsSurveys->find()
                        ->where(['survey_id' => $surveyId, 'question_id' => $questionId])
                        ->first();
                    if ($row === null) {
                        continue;
                    }
                    $row->weight = $index * 10;
                    if (!$questionsSurveys->save($row)) {
                        throw new \Exception('Impossibile aggiornare il peso della domanda ' . $questionId);
                    }
                }
            });
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
            Log::write('error', 'Error in Surveys::reorderSections: ' . $e->getMessage());
        }

        $this->set('errorMsg', $errorMsg);
        $this->viewBuilder()->setOption('serialize', ['errorMsg']);
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
        // Release the PHP session lock so concurrent requests aren't blocked
        // while we run the (read-only) stats query.
        $this->request->getSession()->close();
        $cache = $this->request->getQuery('cache');
        if ($cache == 'clear') {
            Cache::clear('hour');
        }

        $res = $this->buildSurveyStats((int)$survey_id);

        $this->set('stats', $res);
        $this->viewBuilder()->setOption('serialize', ['stats']);
    }

    public function getStatsBatch()
    {
        $this->allowRolesOnly(['admin', 'moma', 'moma_area']);
        // Release the PHP session lock so concurrent requests aren't blocked
        // while we run the (read-only) stats query.
        $this->request->getSession()->close();

        $cache = $this->request->getQuery('cache');
        if ($cache == 'clear') {
            Cache::clear('hour');
        }

        $raw = (array)$this->request->getData('survey_ids');
        $ids = array_values(array_unique(array_filter(array_map('intval', $raw))));
        if (count($ids) > 100) {
            $ids = array_slice($ids, 0, 100);
        }

        $stats = [];
        if (!empty($ids)) {
            // Lightweight payload: this list view only needs total + completed.
            // Compute both for every requested survey in a single grouped query
            // instead of 11+ count queries per id.
            $participantsQuery = $this->Surveys->SurveyParticipants->find();
            $completedExpression = $participantsQuery->newExpr()
                ->case()
                ->when($participantsQuery->newExpr()->isNotNull('SurveyParticipants.survey_completed_at'))
                ->then(1)
                ->else(0);

            $rows = $participantsQuery
                ->select([
                    'survey_id' => 'SurveyParticipants.survey_id',
                    'total' => $participantsQuery->func()->count('*'),
                    'completed' => $participantsQuery->func()->sum($completedExpression),
                ])
                ->where(['SurveyParticipants.survey_id IN' => $ids])
                ->group(['SurveyParticipants.survey_id'])
                ->enableHydration(false)
                ->toArray();

            $countsBySurvey = [];
            foreach ($rows as $r) {
                $countsBySurvey[(int)$r['survey_id']] = [
                    'total' => (int)$r['total'],
                    'completed' => (int)$r['completed'],
                ];
            }

            // Anonymous surveys take total_num from companies.survey.nr_dipendenti.
            // SurveysTable ha il Translate behavior: sotto una lingua ≠ default
            // farebbe il join di i18n rendendo `id` ambiguo. Qui servono solo dati
            // canonici, quindi forzo la lingua di default (EavStrategy short-circuit)
            // e qualifico comunque `id` per sicurezza.
            $surveys = $this->Surveys->find('all', ['locale' => I18n::getDefaultLocale()])
                ->select(['Surveys.id', 'company_id', 'sending_mode'])
                ->where(['Surveys.id IN' => $ids])
                ->all()
                ->indexBy('id')
                ->toArray();

            $anonCompanyIds = [];
            foreach ($surveys as $s) {
                if ($s->sending_mode == 'a' && !empty($s->company_id)) {
                    $anonCompanyIds[] = $s->company_id;
                }
            }
            $companies = [];
            if (!empty($anonCompanyIds)) {
                $companies = $this->Surveys->Companies->find()
                    ->where(['id IN' => array_unique($anonCompanyIds)])
                    ->all()
                    ->indexBy('id')
                    ->toArray();
            }

            foreach ($ids as $id) {
                $totals = $countsBySurvey[$id] ?? ['total' => 0, 'completed' => 0];
                $totalNum = $totals['total'];

                $surveyRow = $surveys[$id] ?? null;
                if (!empty($surveyRow) && $surveyRow->sending_mode == 'a' && !empty($surveyRow->company_id)) {
                    $company = $companies[$surveyRow->company_id] ?? null;
                    if (!empty($company)) {
                        $totalNum = $company->survey['nr_dipendenti'] ?? 0;
                    }
                }

                $stats[$id] = [
                    'participants' => [
                        'total_num' => $totalNum,
                        'survey_completed_num' => $totals['completed'],
                    ],
                ];
            }
        }

        // Force JSON object output even for empty payloads.
        $this->set('stats', empty($stats) ? (object)[] : $stats);
        $this->viewBuilder()->setOption('serialize', ['stats']);
    }

    private function buildSurveyStats(int $surveyId, $survey = null): array
    {
        if ($survey === null) {
            // SurveysTable ha il Translate behavior: forzo la lingua di default per
            // evitare il join di i18n (id ambiguo) — qui servono solo dati canonici.
            $survey = $this->Surveys->find('all', ['locale' => I18n::getDefaultLocale()])
                ->select(['company_id', 'sending_mode'])
                ->where(['Surveys.id' => $surveyId])
                ->first();
        }

        $total_num = 0;
        if (!empty($survey) && $survey->sending_mode == 'a') {
            $company_id = $survey->company_id;
            if (!empty($company_id)) {
                $company = $this->Surveys->Companies->find()->where(['id' => $company_id])->first();
                $total_num = $company->survey['nr_dipendenti'] ?? 0;
            }
        } else {
            $total_num = $this->Surveys->SurveyParticipants->countAll($surveyId);
        }

        $w = "getStats-$surveyId";
        $res = Cache::read($w, 'hour');
        if (!empty($res) && $res['participants']['total_num'] != $total_num) {
            $res = [];
        }
        if (empty($res)) {
            $res = [
                'participants' => [
                    'total_num' => $total_num,
                    'survey_completed_num' => $this->Surveys->SurveyParticipants->countAll($surveyId, [
                        'survey_completed_at IS NOT' => null,
                    ]),
                    'survey_not_completed_num' => $this->Surveys->SurveyParticipants->countAll($surveyId, [
                        'survey_completed_at IS' => null,
                    ]),
                    'survey_completed_anonimous' => $this->Surveys->SurveyParticipants->countAll($surveyId, [
                        'survey_completed_at IS NOT' => null,
                        'Users.email LIKE' => '%@email.invalid',
                    ]),
                    'survey_completed_nominal' => $this->Surveys->SurveyParticipants->countAll($surveyId, [
                        'survey_completed_at IS NOT' => null,
                        'Users.email NOT LIKE' => '%@email.invalid',
                    ]),
                    'invitation_sent_num' => $this->Surveys->SurveyParticipants->countAll($surveyId, [
                        'invitation_delivered_at IS NOT' => null,
                        'Users.email NOT LIKE' => '%@email.invalid',
                    ]),
                    'reminder_sent_num' => $this->Surveys->SurveyParticipants->countAll($surveyId, [
                        'first_reminder_delivered_at IS NOT' => null,
                    ]),
                    'sendable_invitation_num' => $this->Surveys->SurveyParticipants->countAllToBeNotifiedFor($surveyId, 'invitation'),
                    'sendable_reminder_num' => $this->Surveys->SurveyParticipants->countAllToBeNotifiedFor($surveyId, 'reminder'),
                    'sent' => $this->Surveys->SurveyParticipants->countSent($surveyId),
                    'errors' => $this->Surveys->SurveyParticipants->countErrors($surveyId),
                ],
            ];
            Cache::write($w, $res, 'hour');
        }

        return $res;
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

        // Requisito 3 della compliance privacy RER "EMMA RE-R" (vedi
        // docs/dev-notes/rer-privacy-consent.md): hard-block sull'invio dei VERI inviti
        // finché l'azienda non ha caricato il modulo firmato sul trattamento dati dei
        // dipendenti. Solo 'invitation': 'reminder' e sendTestInvitation() restano
        // volutamente esenti (decisione confermata col cliente).
        if ($type === 'invitation' && Configure::read('EmployeePrivacyDocGate')) {
            $hasDocument = $this->fetchTable('CompanyPrivacyDocuments')->find()
                ->where(['company_id' => $survey->company_id])
                ->count() > 0;
            if (!$hasDocument) {
                $this->response = $this->response->withStatus(422);
                $this->set('msg', __(
                    'Devi prima caricare il modulo firmato sul trattamento dei dati personali dei ' .
                    'dipendenti tramite il link di conferma adesione della tua azienda.'
                ));
                $this->viewBuilder()->setOption('serialize', ['msg']);

                return;
            }
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
                $this->SurveyParticipants = TableRegistry::getTableLocator()->get('SurveyParticipants');
                $p = [];
                $p['email'] = $participant[0];
                $p['first_name'] = $participant[1] ?? null;
                $p['last_name'] = $participant[2] ?? null;
                $this->SurveyParticipants->add($p, $survey_id);
            } catch (\Exception $e) {
                $errorMsg = $e->getMessage();
                // $this->response = $this->response->withStatus(422);
                $this->set('errorMsg', $errorMsg);
                $this->viewBuilder()->setOption('serialize', ['errorMsg']);

                return;
            }
            $this->set('errorMsg', $errorMsg);
            $this->viewBuilder()->setOption('serialize', ['errorMsg']);
        }
    }

    // genera un nuovo partecipante (e utente) per la compilazione di un questionario come utente anonimo
    // (da link anonimo)

    public function addAnonParticipant($survey_id)
    {
        // Surveys ha il Translate behavior: sotto locale ≠ default il join di i18n
        // rende `id` ambiguo → qualifico sempre con l'alias della tabella.
        $s = $this->Surveys->exists(['Surveys.id' => $survey_id]);
        if (!$s) {
            throw new NotFoundException("Il questionario $survey_id non esiste");
        }

        // METODO PUBBLICO!
        $errorMsg = false;
        $participant_id = null;
        try {
            $this->SurveyParticipants = TableRegistry::getTableLocator()->get('SurveyParticipants');
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
            ->select(['Surveys.id', 'name'])
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
            ->select(['Surveys.id', 'name', 'year'])
            ->where([
                'version_tag <>' => 'template',
            ])
            ->order('year DESC, name ASC');
        $this->Authorization->applyScope($surveyList);
        if (!empty($company_id)) {
            $surveyList->where(['company_id' => $company_id]);
        }

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
                $update = [
                    'survey_completed_at' => date('Y-m-d H:i:s'),
                ];
                // Requisito 3 della compliance privacy RER "EMMA RE-R": conferma di
                // lettura del documento aziendale sul trattamento dati dei dipendenti
                // (checkbox del gate combinato di fill.vue, mostrata a inizio
                // compilazione). Nessuna riga `answers`/`survey_participants` nuova
                // esiste a quel momento: questa è la prima scrittura disponibile per
                // partecipante+survey, quindi è qui che il flag va accolto e persistito
                // (vedi la migrazione AddPrivacyDocAckToSurveyParticipants).
                $privacyDocAck = filter_var(
                    $this->request->getData('privacy_doc_ack'),
                    FILTER_VALIDATE_BOOLEAN
                );
                if ($privacyDocAck) {
                    $update['privacy_doc_ack'] = 1;
                    $update['privacy_doc_ack_date'] = date('Y-m-d H:i:s');
                }
                $connection->update('survey_participants', $update, [
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

    /**
     * Stato ({exists, download_url}) e download del "Modulo trattamento dati personali
     * dei dipendenti" (requisito 3, compliance privacy RER "EMMA RE-R") per una survey,
     * risolvendo l'associazione Surveys belongsTo Companies. Pubblico (senza
     * autenticazione): serve sia la compilazione anonima (fill.vue) sia il controllo
     * del gate di invio autenticato in SurveyEditHeading.vue — un solo endpoint, due
     * chiamanti, nessun secondo endpoint autenticato necessario.
     *
     * Estensione `.json` (default) -> stato; qualunque altra estensione (es. `.pdf`)
     * sullo stesso URL -> streaming del file.
     *
     * @param string|null $survey_id Id del questionario.
     */
    public function employeePrivacyDocument($survey_id = null)
    {
        // Passa se è attivo il gate RER (EmployeePrivacyDocGate) OPPURE il PDF privacy
        // caricabile dal MOMA (SurveyPrivacyPdf, vedi docs/dev-notes/survey-privacy-pdf.md).
        if (!Configure::read('EmployeePrivacyDocGate') && !Configure::read('SurveyPrivacyPdf')) {
            throw new NotFoundException();
        }
        $this->request->allowMethod(['get']);

        // Surveys ha il Translate behavior: sotto locale diversa dal default il join
        // di i18n rende `id` ambiguo, quindi qualifico sempre con l'alias della tabella
        // (stessa cautela di addAnonParticipant()).
        $survey = $this->Surveys->find()
            ->where(['Surveys.id' => $survey_id])
            ->first();
        if ($survey === null || empty($survey->company_id)) {
            throw new NotFoundException();
        }

        $document = $this->fetchTable('CompanyPrivacyDocuments')->find()
            ->where(['company_id' => $survey->company_id])
            ->first();

        if ($this->request->getParam('_ext') && $this->request->getParam('_ext') !== 'json') {
            if ($document === null || !file_exists($document->path)) {
                throw new NotFoundException();
            }

            // no-cache + revalidate: dopo una sostituzione lo stesso URL deve servire
            // subito il nuovo file (il `?v=` di download_url cambia comunque).
            return $this->serveFileInline(
                $document->path,
                $document->filename,
                $document->filename
            )->withHeader('Cache-Control', 'no-cache, must-revalidate');
        }

        $this->setPrivacyDocumentPayload((int)$survey->id, $document);

        return null;
    }

    /**
     * Imposta il payload {exists, download_url, filename} (mai `path`) per la vista JSON.
     *
     * @param int $surveyId Id del questionario.
     * @param \App\Model\Entity\CompanyPrivacyDocument|null $document Riga documento, se esiste.
     * @return void
     */
    private function setPrivacyDocumentPayload(int $surveyId, $document): void
    {
        $downloadUrl = null;
        if ($document !== null) {
            $downloadUrl = Router::url([
                'controller' => 'Surveys',
                'action' => 'employeePrivacyDocument',
                $surveyId,
                '_ext' => 'pdf',
                '?' => ['v' => $document->modified ? $document->modified->getTimestamp() : 0],
            ], true);
        }
        $this->set([
            'exists' => $document !== null,
            'download_url' => $downloadUrl,
            'filename' => $document !== null ? $document->filename : null,
        ]);
        $this->viewBuilder()->setOption('serialize', ['exists', 'download_url', 'filename']);
    }

    /**
     * Risolve la survey per upload/delete del PDF privacy e applica flag + authz.
     *
     * Upload/delete richiedono SurveyPrivacyPdf attivo E EmployeePrivacyDocGate spento
     * (su RER la riga è il modulo firmato dal legale rappresentante che sblocca l'invio:
     * il MOMA non deve poterla sostituire). Autorizzati: admin, oppure moma con
     * company_id NON vuoto uguale a quello della survey (un moma con company_id NULL è
     * trattato come "moma area" da allowWhoCanSeeCompanyOnly: qui va rifiutato).
     * moma_area e tutti gli altri ruoli: 403.
     *
     * @param string|int|null $survey_id Id del questionario.
     * @return \App\Model\Entity\Survey|null
     */
    private function resolveSurveyForPrivacyPdfWrite($survey_id)
    {
        if (!Configure::read('SurveyPrivacyPdf') || Configure::read('EmployeePrivacyDocGate')) {
            throw new NotFoundException();
        }

        $survey = $this->Surveys->find()
            ->where(['Surveys.id' => $survey_id])
            ->first();
        if ($survey === null) {
            throw new NotFoundException();
        }

        $identity = $this->Authentication->getIdentity();
        $role = $identity ? $identity->get('role') : null;
        $identityCompanyId = $identity ? $identity->get('company_id') : null;
        $allowed = $role === 'admin'
            || (
                $role === 'moma'
                && !empty($identityCompanyId)
                && !empty($survey->company_id)
                && (int)$identityCompanyId === (int)$survey->company_id
            );
        if (!$allowed) {
            throw new ForbiddenException(__('Non sei autorizzato ad accedere alla risorsa richiesta'));
        }

        if (empty($survey->company_id)) {
            $this->respondPrivacyPdfError(422, __('Il questionario non è associato a un\'azienda'));

            return null;
        }

        return $survey;
    }

    /**
     * Risposta JSON di errore {msg} con lo status indicato.
     *
     * @param int $status Status HTTP.
     * @param string $msg Messaggio utente.
     * @return void
     */
    private function respondPrivacyPdfError(int $status, string $msg): void
    {
        $this->response = $this->response->withStatus($status);
        $this->set('msg', $msg);
        $this->viewBuilder()->setOption('serialize', ['msg']);
    }

    /**
     * Carica (o sostituisce) il PDF privacy dell'azienda della survey; vale per TUTTE le
     * survey della stessa azienda. Solo PDF (no .p7m). Vedi docs/dev-notes/survey-privacy-pdf.md.
     *
     * @param string|int|null $survey_id Id del questionario.
     * @return void
     */
    public function uploadPrivacyDocument($survey_id = null): void
    {
        $this->request->allowMethod(['post']);
        $survey = $this->resolveSurveyForPrivacyPdfWrite($survey_id);
        if ($survey === null) {
            return;
        }
        $companyId = (int)$survey->company_id;

        $file = $this->request->getUploadedFile('file');
        if ($file === null) {
            $this->respondPrivacyPdfError(
                422,
                __('Nessun file ricevuto (potrebbe superare il limite di dimensione del server)')
            );

            return;
        }
        if ($file->getError() === UPLOAD_ERR_INI_SIZE || $file->getError() === UPLOAD_ERR_FORM_SIZE) {
            $this->respondPrivacyPdfError(422, __('Il file supera la dimensione massima consentita dal server'));

            return;
        }
        // Rifiuto PRIMA del servizio: la validazione precede la purge, così il file
        // corrente resta se l'upload è sbagliato.
        $extension = strtolower(pathinfo((string)$file->getClientFilename(), PATHINFO_EXTENSION));
        if ($extension !== 'pdf') {
            $this->respondPrivacyPdfError(422, __('È ammesso solo un file PDF'));

            return;
        }

        $service = new CompanyPrivacyDocumentService();
        try {
            $stored = $service->storeFile($file, $companyId);
        } catch (\RuntimeException $e) {
            $this->respondPrivacyPdfError(422, $e->getMessage());

            return;
        }

        $documents = $this->fetchTable('CompanyPrivacyDocuments');
        $document = $documents->find()->where(['company_id' => $companyId])->first();
        if ($document === null) {
            // adhesion_request_id resta null (regola exactlyOneOwner).
            $document = $documents->newEntity(['company_id' => $companyId]);
        }
        $document->filename = $stored['filename'];
        $document->path = $stored['path'];
        if (!$documents->save($document)) {
            $this->respondPrivacyPdfError(422, __('Impossibile registrare il documento'));

            return;
        }

        $this->setPrivacyDocumentPayload((int)$survey->id, $document);
    }

    /**
     * Rimuove il PDF privacy dell'azienda della survey (file + riga).
     *
     * @param string|int|null $survey_id Id del questionario.
     * @return void
     */
    public function deletePrivacyDocument($survey_id = null): void
    {
        $this->request->allowMethod(['post', 'delete']);
        $survey = $this->resolveSurveyForPrivacyPdfWrite($survey_id);
        if ($survey === null) {
            return;
        }
        $companyId = (int)$survey->company_id;

        (new CompanyPrivacyDocumentService())->deleteExisting($companyId);
        $this->fetchTable('CompanyPrivacyDocuments')->deleteAll(['company_id' => $companyId]);

        $this->set('exists', false);
        $this->viewBuilder()->setOption('serialize', ['exists']);
    }

    /**
     * Risolve (o crea, clonando dal template configurato) il questionario
     * dinamico Azienda/Scuola/Uni di una azienda o di una sua sede per un
     * dato anno, e restituisce {survey_id, participant_id} pronti per il
     * generico `AnswersController::update($survey_id, $participant_id)` —
     * lo stesso endpoint già usato da qualunque survey "normale". Rende i
     * questionari Azienda/Scuola/Uni omogenei ai questionari normali: niente
     * di nuovo lato salvataggio risposte, solo un modo per ottenere
     * l'istanza survey giusta.
     *
     * Query string: company_id (obbligatorio), office_id (opzionale, scope
     * 'sede' se valorizzato), version_tag (uno tra Azienda/Scuola/Uni),
     * year (opzionale, default anno corrente).
     *
     * @return void
     */
    public function companyQuestionnaire(): void
    {
        $companyId = (int)$this->request->getQuery('company_id');
        $this->allowWhoCanSeeCompanyOnly($companyId);

        $officeIdRaw = $this->request->getQuery('office_id');
        $officeId = is_numeric($officeIdRaw) && (int)$officeIdRaw > 0 ? (int)$officeIdRaw : null;
        $versionTag = (string)$this->request->getQuery('version_tag');
        $yearRaw = $this->request->getQuery('year', date('Y'));
        $year = is_numeric($yearRaw) ? (int)$yearRaw : (int)date('Y');

        $errorMsg = false;
        $survey_id = null;
        $participant_id = null;
        try {
            $survey = $this->Surveys->findOrCreateCompanyQuestionnaire($companyId, $officeId, $versionTag, $year);
            $survey_id = $survey->id;
            $participant_id = $this->Surveys->resolveCompanyQuestionnaireParticipantId((int)$survey_id);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            $errorMsg = $e->getMessage();
        }

        $this->set(compact('errorMsg', 'survey_id', 'participant_id'));
        $this->viewBuilder()->setOption('serialize', ['errorMsg', 'survey_id', 'participant_id']);
    }

    /**
     * Sincronizza le risposte dinamiche (Answers) di un questionario
     * Azienda/Scuola/Uni verso il blob JSON "legacy" (companies.survey o
     * offices.survey, a seconda dello scope) e verso `company_survey_history`
     * — così checkComplete(), gli Exporter (Html/Docx/MdExporter) e
     * l'anno-per-anno di CompaniesController::checkTasks() continuano a
     * funzionare invariati, senza sapere che la compilazione ora avviene
     * tramite il motore dinamico invece dei vecchi componenti Vue statici.
     * Da chiamare (lato frontend, quando implementato) dopo ogni salvataggio
     * riuscito su AnswersController::update() per questa survey.
     *
     * @param int|string $survey_id Id della survey Azienda/Scuola/Uni.
     * @return void
     */
    public function syncCompanyQuestionnaire($survey_id): void
    {
        $this->allowWhoCanSeeSurveyOnly($survey_id);

        $errorMsg = false;
        try {
            $survey = $this->Surveys->get($survey_id);
            if (!in_array($survey->version_tag, \App\Model\Table\SurveysTable::COMPANY_QUESTIONNAIRE_TYPES, true)) {
                throw new Exception(__(
                    'Il questionario {0} non è un questionario dinamico Azienda/Scuola/Uni',
                    $survey_id
                ));
            }

            $blob = $this->Surveys->assembleLegacySurveyBlob((int)$survey_id);

            if ($survey->office_id === null) {
                $company = $this->Surveys->Companies->get($survey->company_id);
                $company->survey = $blob;
                if (!$this->Surveys->Companies->save($company)) {
                    throw new Exception(__('Impossibile salvare il questionario azienda'));
                }
                $this->recordCompanySurveyHistory((int)$survey->company_id, null, (int)$survey->year, 'generale', $blob);
            } else {
                $offices = TableRegistry::getTableLocator()->get('Offices');
                $office = $offices->get($survey->office_id);
                $office->survey = $blob;
                if (!$offices->save($office)) {
                    throw new Exception(__('Impossibile salvare il questionario sede'));
                }
                $this->recordCompanySurveyHistory(
                    (int)$survey->company_id,
                    (int)$survey->office_id,
                    (int)$survey->year,
                    'sede',
                    $blob
                );
            }
        } catch (Exception $e) {
            $errorMsg = $e->getMessage();
        }

        $this->set('errorMsg', $errorMsg);
        $this->viewBuilder()->setOption('serialize', ['errorMsg']);
    }

    /**
     * Upsert di una riga `company_survey_history` — stessa logica già inline
     * in CompaniesController::edit()/OfficesController::edit() (non
     * refactorizzata lì per non toccare codice esistente/testato), riproposta
     * qui per il nuovo percorso di sync dei questionari dinamici
     * Azienda/Scuola/Uni (syncCompanyQuestionnaire()).
     *
     * @param int $companyId Id azienda.
     * @param int|null $officeId Id sede (null = scope 'generale').
     * @param int $year Anno di riferimento.
     * @param string $type 'generale' o 'sede'.
     * @param array<string, mixed> $answer Blob da salvare come storico.
     * @return void
     */
    private function recordCompanySurveyHistory(
        int $companyId,
        ?int $officeId,
        int $year,
        string $type,
        array $answer
    ): void {
        $csh = $this->fetchTable('CompanySurveyHistory');
        $conditions = [
            'company_id' => $companyId,
            'year' => $year,
            'type' => $type,
        ];
        if ($officeId === null) {
            $conditions['office_id IS'] = null;
        } else {
            $conditions['office_id'] = $officeId;
        }

        $historyEntry = $csh->find()->where($conditions)->order(['created' => 'DESC'])->first();
        if (empty($historyEntry)) {
            $historyEntry = $csh->newEmptyEntity();
            $historyEntry->company_id = $companyId;
            $historyEntry->office_id = $officeId;
            $historyEntry->year = $year;
            $historyEntry->type = $type;
        }
        $historyEntry->answer = $answer;
        if (!$csh->save($historyEntry)) {
            throw new Exception(__('Impossibile salvare lo storico del questionario'));
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
        // `options` è dato canonico (nomi sede della domanda mappa, usati per il
        // match), NON testo traducibile. Sotto locale ≠ default il Translate
        // behavior tenterebbe di scrivere il valore (un array) come traduzione
        // nella colonna stringa i18n.content → "Cannot convert array to string".
        // Forzo la lingua di default così il save va sulla colonna base.
        $this->QuestionSurveys->setLocale(I18n::getDefaultLocale());
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
            Cache::clearGroup('dashboard');
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

    /**
     * importSchema - importa uno schema JSON (generato da exportSchema) in una nuova survey.
     * Per ogni domanda cerca per `name`: se esiste la riusa, altrimenti la crea.
     * Crea una nuova survey copiando i metadati della survey $id e collega le domande
     * con le stesse impostazioni (conditions, hidden, compulsory, ecc.) dello schema.
     *
     * @param int $id ID della survey sorgente da cui copiare i metadati
     * @return void
     */
    public function importSchema()
    {
        // Brescia #522/#496: schema import creates questions (incl. sections) and
        // moma option grants, so it is an admin-only operation.
        $this->allowRolesOnly(['admin']);
        $this->request->allowMethod(['post']);

        $errorMsg = null;
        $newSurveyId = null;
        $questionsCreated = 0;
        $questionsLinked = 0;

        try {
            // Leggo il file JSON caricato (PSR-7 UploadedFileInterface)
            $file = $this->request->getUploadedFile('schema');
            if (empty($file) || $file->getError() !== UPLOAD_ERR_OK) {
                throw new \Exception('Nessun file caricato o errore nel caricamento');
            }

            $json = $file->getStream()->getContents();
            $schema = json_decode($json, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('File JSON non valido: ' . json_last_error_msg());
            }

            if (empty($schema['questions']) || !isset($schema['survey'])) {
                throw new \Exception('Schema JSON non valido: mancano i campi questions o survey');
            }

            $this->Questions = TableRegistry::getTableLocator()->get('Questions');

            // Sections are no longer a separate model: section-questions round-trip
            // naturally as Questions of type='section' matched by name (sec-<oldId>).
            // No section_id handling is required here anymore.
            //
            // L'import crea/aggiorna dati canonici. Sotto locale ≠ default il
            // Translate behavior dirotterebbe i save sulla tabella i18n e, per il
            // campo `options` (array), tenterebbe di scrivere un array nella colonna
            // stringa i18n.content → "Cannot convert array to string". Forzo la
            // lingua di default su tutte le tabelle Translate coinvolte così i save
            // vanno sulle colonne base. Vedi nota i18n in CLAUDE.md.
            $defaultLocale = I18n::getDefaultLocale();
            $this->Questions->setLocale($defaultLocale);
            $this->Surveys->setLocale($defaultLocale);

            // Mappa old_id -> name (serve per risolvere i question_id nel campo survey)
            $oldIdToName = [];
            foreach ($schema['questions'] as $qData) {
                $oldIdToName[$qData['id']] = $qData['name'];
            }

            // Tutto quanto segue muta il DB (domande globali + nuova survey + link):
            // avvolto in una transazione così un fallimento a metà strada (es. una
            // domanda non salvabile) non lascia domande orfane nel catalogo globale
            // senza che la survey che le dovrebbe referenziare sia mai stata creata.
            $connection = $this->Surveys->getConnection();
            $connection->transactional(function () use (
                $schema,
                $oldIdToName,
                $defaultLocale,
                &$nameToNewId,
                &$questionsCreated,
                &$newSurveyId,
                &$questionsLinked
            ) {
                // Per ogni domanda: cerca per name, crea se non esiste
                // Mappa name -> id reale nel DB
                $nameToNewId = [];
                foreach ($schema['questions'] as $qData) {
                    $name = $qData['name'];
                    $existing = $this->Questions->findByName($name)->first();
                    if (!empty($existing)) {
                        $nameToNewId[$name] = $existing->id;
                    } else {
                        $newQ = $this->Questions->newEntity([
                            'name'              => $name,
                            'description'       => $qData['description'] ?? null,
                            'options'           => $qData['options'] ?? null,
                            'type'              => $qData['type'] ?? 'single',
                            'creator_id'        => $qData['creator_id'] ?? null,
                            'conditions'        => $qData['conditions'] ?? null,
                            'compulsory_answer' => $qData['compulsory_answer'] ?? 0,
                            'long_description'  => $qData['long_description'] ?? null,
                            'moma_area'         => $qData['moma_area'] ?? 0,
                        ]);
                        if ($this->Questions->save($newQ)) {
                            $nameToNewId[$name] = $newQ->id;
                            $questionsCreated++;
                        } else {
                            throw new \Exception("Impossibile creare la domanda '$name'");
                        }
                    }
                }

                // Crea un nuovo template copiando le domande dello schema
                $name = $this->request->getData('name');
                $newSurvey = $this->Surveys->newEntity([
                    'name'        => !empty($name) ? $name : 'Template importato',
                    'company_id'  => null,
                    'version_tag' => 'template',
                    'year'        => date('Y'),
                    'date'        => date('Y-m-d'),
                ]);
                if (!$this->Surveys->save($newSurvey)) {
                    throw new \Exception('Impossibile creare il nuovo questionario');
                }
                $newSurveyId = $newSurvey->id;

                // Collega le domande alla nuova survey con le impostazioni dello schema
                $QuestionsSurveys = TableRegistry::getTableLocator()->get('QuestionsSurveys');
                $QuestionsSurveys->setLocale($defaultLocale); // save canonici (vedi sopra)
                foreach ($schema['survey'] as $qsData) {
                    $oldQId = $qsData['question_id'];
                    $name = $oldIdToName[$oldQId] ?? null;
                    if (empty($name) || !isset($nameToNewId[$name])) {
                        Log::warning("importSchema: impossibile risolvere question_id=$oldQId, saltato");
                        continue;
                    }
                    $newQId = $nameToNewId[$name];

                    $qs = $QuestionsSurveys->newEmptyEntity();
                    $qs->survey_id    = $newSurveyId;
                    $qs->question_id  = $newQId;
                    $qs->weight       = $qsData['weight'] ?? 0;
                    $qs->compulsory   = (int)($qsData['compulsory'] ?? 0);
                    $qs->hidden       = (int)($qsData['hidden'] ?? 0);
                    $qs->description  = $qsData['description'] ?? null;
                    $qs->long_description = $qsData['long_description'] ?? null;
                    $qs->options      = $qsData['options'] ?? null;
                    $conditions = $qsData['conditions'] ?? null;
                    if (is_array($conditions) && isset($conditions['question']) && !empty($conditions['question'])) {
                        $conditionOldQId = (int)$conditions['question'];
                        $conditionQuestionName = $oldIdToName[$conditionOldQId] ?? null;
                        if (!empty($conditionQuestionName) && isset($nameToNewId[$conditionQuestionName])) {
                            $conditions['question'] = (int)$nameToNewId[$conditionQuestionName];
                        } else {
                            Log::warning("importSchema: impossibile risolvere condition.question=$conditionOldQId per question_id=$oldQId");
                        }
                    }
                    $qs->conditions = $conditions;
                    $qs->compulsory_answer = (int)($qsData['compulsory_answer'] ?? 0);
                    // Brescia #496: import is admin-only, so the grant fields in the file are honoured.
                    $disabled = $qsData['disabled_options'] ?? null;
                    if (is_string($disabled)) {
                        $disabled = json_decode($disabled, true);
                    }
                    $qs->moma_can_edit_options = (int)($qsData['moma_can_edit_options'] ?? 0);
                    $qs->disabled_options = empty($disabled) ? null : $disabled;

                    if ($QuestionsSurveys->save($qs)) {
                        $questionsLinked++;
                    } else {
                        Log::warning("importSchema: impossibile salvare questions_surveys per question_id=$newQId");
                    }
                }
            });
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
            // The transaction above rolled back any question/survey/link created
            // during this attempt: reflect that in the response counters too, so
            // callers never see a "created" count for rows that no longer exist.
            $newSurveyId = null;
            $questionsCreated = 0;
            $questionsLinked = 0;
            Log::error('Error in importSchema: ' . $e->getMessage());
        }

        $this->set('errorMsg', $errorMsg);
        $this->set('new_survey_id', $newSurveyId);
        $this->set('questions_created', $questionsCreated);
        $this->set('questions_linked', $questionsLinked);
        $this->viewBuilder()->setOption('serialize', ['errorMsg', 'new_survey_id', 'questions_created', 'questions_linked']);
    }

    public function exportSchema($id)
    {
        $this->allowRolesOnly(['admin', 'moma']);

        $survey = $this->Surveys->find()
            ->contain([
                'Questions' => function ($q) use ($id) {
                    return $q->contain([
                        'QuestionsSurveys' => function ($qs) use ($id) {
                            return $qs->where(['survey_id' => $id]);
                        },
                    ]);
                },
            ])
            ->where(['Surveys.id' => $id])
            ->first();

        if (empty($survey)) {
            throw new NotFoundException("Questionario $id non trovato");
        }

        $questions = [];
        $surveyQuestions = [];

        foreach ($survey->questions as $q) {
            $questions[] = [
                'id'               => $q->id,
                'name'             => $q->name,
                'description'      => $q->description,
                'options'          => $q->options,
                'type'             => $q->type,
                'creator_id'       => $q->creator_id,
                'conditions'       => $q->conditions,
                'compulsory_answer' => $q->compulsory_answer,
                'long_description' => $q->long_description,
                'moma_area'        => $q->moma_area,
            ];

            foreach ($q->questions_surveys as $qs) {
                $surveyQuestions[] = [
                    'question_id'      => $q->id,
                    'weight'           => $qs->weight,
                    'compulsory'       => $qs->compulsory,
                    'hidden'           => $qs->hidden,
                    'description'      => $qs->description,
                    'long_description' => $qs->long_description,
                    'options'          => $qs->options,
                    'conditions'       => $qs->conditions,
                    'compulsory_answer' => $qs->compulsory_answer,
                    // moma option-toggle grant + hidden options (Brescia #496)
                    'moma_can_edit_options' => (int)$qs->moma_can_edit_options,
                    'disabled_options' => $qs->disabled_options,
                ];
            }
        }

        $export = [
            'questions' => $questions,
            'survey'    => $surveyQuestions,
        ];

        $json = json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $this->response = $this->response
            ->withType('application/json')
            ->withHeader('Content-Disposition', "attachment; filename=\"survey-schema-{$id}.json\"")
            ->withStringBody($json);

        return $this->response;
    }

    /**
     * Helper per servire PDFs inline (vs. download). I file .p7m rimangono attachments.
     *
     * @param string $path Percorso assoluto del file.
     * @param string $filename Nome del file per Content-Disposition.
     * @param string|null $storedFilename Nome del file memorizzato nel DB, usato come fallback per rilevare estensione.
     * @return \Cake\Http\Response
     */
    private function serveFileInline(
        string $path,
        string $filename,
        ?string $storedFilename = null
    ): \Cake\Http\Response {
        // Rileva .p7m dall'estensione del nome memorizzato.
        $isP7m = (($storedFilename ?? $filename) !== null &&
            strtolower(pathinfo($storedFilename ?? $filename, PATHINFO_EXTENSION)) === 'p7m');

        if ($isP7m) {
            // .p7m rimane attachment.
            return $this->response->withFile($path, [
                'name' => $filename,
                'download' => true,
            ]);
        }

        // PDF inline: sanitizza il nome da quote/newline e imposta Content-Disposition.
        $safeName = str_replace(['"', "\n", "\r"], '', $filename);

        return $this->response
            ->withFile($path)
            ->withType('pdf')
            ->withHeader('Content-Disposition', 'inline; filename="' . $safeName . '"')
            ->withHeader('X-Content-Type-Options', 'nosniff');
    }
}
