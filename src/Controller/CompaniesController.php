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

use App\Indicator\emissionA;
use App\Indicator\emissionB;
use App\Indicator\emissionC;
use App\Indicator\emissionD;
use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Http\Exception\HttpException;
use Cake\Http\Exception\NotFoundException;
use Cake\Log\Log;
use Exception;

/**
 * Companies Controller
 *
 * @property \App\Model\Table\CompaniesTable $Companies
 * @method \App\Model\Entity\Company[]|\Cake\Datasource\ResultSetInterface paginate($object = null, array $settings = [])
 */
class CompaniesController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->loadComponent('Security');
        $this->loadComponent('Paginator');
    }

    public function beforeFilter($event)
    {
        parent::beforeFilter($event);

        $this->Security->setConfig('unlockedActions', ['edit', 'add', 'emissions', 'delete', 'copySurveyYear']);
    }

    public function emissionImpacts($company_id = null)
    {
        $q = $this->Companies->find()
            ->select(['emissions', 'id'])
            ->where(['id' => $company_id])
            ->firstOrFail();

        $emissioni = $q->emissions;

        $emissions_result = [
            'a' => $this->getEmissionA($emissioni),
            'b' => $this->getEmissionB($emissioni),
            'c' => $this->getEmissionC($emissioni),
            'd' => $this->getEmissionD($emissioni),
        ];

        $this->set(compact(['emissions_result']));
        $this->viewBuilder()->setOption('serialize', ['emissions_result']);
    }

    public function emissions($company_id = null)
    {
        $this->allowRolesOnly(['admin', 'moma', 'moma_area']);
        $d = $this->request->getData();

        $q = $this->Companies->find()
            ->select(['emissions', 'id'])
            ->where(['id' => $company_id])
            ->firstOrFail();

        $emissioni = $q->emissions;

        if ($this->request->is(['get'])) {
            $this->set(compact(['emissioni']));
            $this->viewBuilder()->setOption('serialize', ['emissioni']);

            return;
        }
        $this->autoRender = false;
        if ($this->request->is(['post'])) {
            $q->emissions = $d;
            if (!$this->Companies->save($q)) {
                throw new Exception('Impossibile salvare le emissioni');
            }

            return;
        }
    }

    private function emptyEmissionData()
    {
        $emission_types = ['a', 'b', 'c', 'd'];
    }

    private function getEmissionA($emissioni)
    {
        $emissionA = new emissionA(
            floatval($emissioni['a']['gg']),
            floatval($emissioni['a']['riduzione_utenti_auto']),
            floatval($emissioni['a']['riduzione_km_auto'])
        );

        return $emissionA->getOutput();
    }

    private function getEmissionB($emissioni)
    {
        $emissionB = new emissionB(
            floatval($emissioni['b']['gg']),
            floatval($emissioni['b']['riduzione_km_auto']),
            floatval($emissioni['b']['ut']),
            0,
            floatval($emissioni['b']['noleggi_gg'])
        );

        return $emissionB->getOutput();
    }

    private function getEmissionC($emissioni)
    {
        $emissionC = new emissionC(
            floatval($emissioni['c']['gg']),
            floatval($emissioni['c']['km_da_sostituire']),
            floatval($emissioni['c']['km_nuovi']),
            floatval($emissioni['c']['fc_auto_nuovi'])
        );

        return $emissionC->getOutput();
    }

    private function getEmissionD($emissioni)
    {
        $emissionD = new emissionD(
            floatval($emissioni['d']['gg']),
            floatval($emissioni['d']['az_nr_dipendenti']),
            floatval($emissioni['d']['az_distanza_spostamenti']),
            floatval($emissioni['d']['az_spostamenti_auto'])
        );

        return $emissionD->getOutput();
    }

    /**
     * Index method
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        $query = $this->Companies->find();

        $identity = $this->Authentication->getIdentity();
        //$myRole = $identity->get('role');
        //L'utente moma può vedere solo la sua azienda
        $company_id = $identity->get('company_id');
        if (!empty($company_id)) {
            $query->where(['id' =>  $company_id]);
        }

        $companies = $this->paginate($query);

        $this->set(compact('companies'));
        $this->viewBuilder()->setOption('serialize', ['companies']);
    }

    public function list()
    {
        $query = $this->Companies->find()
            ->join([
                'table' => 'offices',
                'alias' => 'Offices',
                'type' => 'LEFT',
                'conditions' => 'Offices.company_id = Companies.id',
            ])
            ->contain(['CompanyTypes' => ['strategy' => 'select']]);
        $query->where(['Companies.name IS NOT' => null]);
        $query->select([
            'Companies.id',
            'Companies.name',
            'Companies.city',
            'Companies.province',
            'Companies.type',
            'Companies.num_employees',
            'Companies.years',
            'offices_num' => $query->func()->sum('Offices.num_employees'),
        ])
            ->group([
                'Offices.company_id',
                'Companies.id',
                'Companies.name',
                'Companies.city',
                'Companies.province',
                'Companies.type',
                'Companies.num_employees',
            ])
            ->order(['Companies.name']);
        // sqld($query);
        $q = $this->request->getQuery('q');
        $t = $this->request->getQuery('type');
        $year = $this->request->getQuery('year');
        if (!empty($q)) {
            $query->where(['Companies.name LIKE' => "%$q%"]);
        }
        if (!empty($t)) {
            $query->where(['Companies.type' => $t]);
        }
        if (!(empty($year) || $year == 'TUTTI')) {
            $query->where(function ($exp) {
                return $exp->add('(JSON_CONTAINS(Companies.years, :year) OR Companies.years IS NULL OR JSON_LENGTH(Companies.years) = 0)');
            });
            $query->bind(':year', json_encode($year), 'string');
        }

        $identity = $this->Authentication->getIdentity();
        if ($identity == null) {
            throw new \Cake\Http\Exception\UnauthorizedException('Utente non autorizzato');
        }

        // //If the user is moma-area can only see if province and its area
        // if($identity->role=="moma_area"){
        //     $query->where([
        //         'Offices.province' => 'TO',
        //     ]);

        // }else{
        //     //$myRole = $identity->get('role');
        //     //L'utente moma può vedere solo la sua azienda
        //     $company_id = $identity->get('company_id');
        //     if (!empty($company_id)) {
        //         $query->where(['Companies.id' =>  $company_id]);
        //     }
        // }
        // debug($query->select(['Offices.name','Companies.id'])->where(['Companies.id'=>1892])->toarray());
        $this->Authorization->applyScope($query);

        // debug($query);
        $companies = $query->all();
        $this->set('companies', $companies);
        $this->viewBuilder()->setOption('serialize', ['companies']);
    }

    /**
     * View method
     *
     * @param string|null $id Company id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $identity = $this->Authentication->getIdentity();
        $company_id = $identity->get('company_id');
        $office_id  =   $identity->get('office_id');

        //leggo l'anno utilizzato
        $usedYear = $this->request->getQuery('year', date('Y'));
        // historyOnly: se attivo, in assenza di storia per l'anno NON si fa fallback
        // ai dati live (la survey resta vuota). Usato dalla TasksCard per far combaciare
        // il dettaglio "campi mancanti" col badge strict per anno. Le pagine di edit NON
        // passano il flag, quindi mantengono il fallback (pre-fill dell'ultimo anno).
        $historyOnly = filter_var($this->request->getQuery('historyOnly', false), FILTER_VALIDATE_BOOLEAN);
        //Se l'utente ha company_id settato deve vedere solo la sua company
        //Se l'utente ha office_id settato devo vedere solo office_id
        $company = $this->Companies->get($id, [
            //'contain' => ['Surveys', 'Offices'],
            'contain' => ['Surveys'],
        ]);
        $conditions = [
            'company_id' => $id,
            'office_id IS' => null,
            'type' => 'generale',
        ];
        if (is_numeric($usedYear)) {
            $conditions['year'] = (int)$usedYear;
        }

        //Devo anche caricare la surveyhistorey per leggere la versione corrente se esiste, se no prendo quella della survey
        $csh = $this->fetchTable('CompanySurveyHistory');
        $historyEntry = $csh->find()
            ->where($conditions)
            ->order(['created' => 'DESC'])
            ->first();

        //Se trovo la storia
        if (!empty($historyEntry)) {
            $company->survey = $historyEntry->answer;
        } elseif ($historyOnly) {
            // strict per anno: nessun fallback ai dati live
            $company->survey = [];
        }

        // Flag per il frontend: esiste già una conferma (riga di storia) per l'anno selezionato?
        // Il componente AnnoCompilazione si nasconde quando la conferma esiste (ask-once per anno).
        $company->survey_history_found = !empty($historyEntry);

        $this->Authorization->authorize($company);
        // $this->Authorization->applyScope($company);
        // //L'utente moma può vedere solo la sua azienda
        // if (!empty($company_id) && $company_id != $company['id']) {
        //     throw new NotFoundException();
        // }
        //TODO: ripulire questa chiamata che è ridondante, ma bisogna vedere
        //tutti i punti in cui siamo invocati.
        $officesTable = $this->fetchTable('Offices');
        $offices = $officesTable
            ->find()->order(['name']);
        $this->Authorization->applyScope($offices);
        $offices->where(['company_id' => $id]);

        // if ($office_id) {
        //     $offices->where(['id' => $office_id]);
        // }

        $offices->toArray();
        $this->set(compact('company', 'offices'));
        $this->viewBuilder()->setOption('serialize', ['company', 'offices']);
    }

    /**
     * Anni con una riga di storia del foglio azienda (type 'generale', office_id NULL), decrescenti.
     *
     * @param string|null $id Company id.
     * @return void
     */
    public function surveyYears($id = null)
    {
        $this->request->allowMethod(['get']);
        $this->allowRolesOnly(['admin']);
        if (!$this->Companies->exists(['id' => (int)$id])) {
            throw new NotFoundException(__('Azienda non trovata'));
        }
        $years = $this->fetchTable('CompanySurveyHistory')->sheetYears((int)$id, null, 'generale');
        $this->set(compact('years'));
        $this->viewBuilder()->setOption('serialize', ['years']);
    }

    /**
     * Admin only: copia il foglio azienda da from_year a to_year (solo storia, mai companies.survey).
     * Aggiorna la riga dell'anno di destinazione se esiste, altrimenti la crea.
     *
     * @param string|null $id Company id.
     * @return void
     */
    public function copySurveyYear($id = null)
    {
        $this->request->allowMethod(['post']);
        $this->allowRolesOnly(['admin']);

        $csh = $this->fetchTable('CompanySurveyHistory');
        $data = (array)$this->request->getData();
        $fromYear = $csh::parseYear($data['from_year'] ?? null);
        $toYear = $csh::parseYear($data['to_year'] ?? null);
        if ($fromYear === null || $toYear === null) {
            throw new HttpException(__('Anni non validi'), 422);
        }
        if ($fromYear === $toYear) {
            throw new HttpException(__('L\'anno di origine e quello di destinazione devono essere diversi'), 422);
        }
        if (!$this->Companies->exists(['id' => (int)$id])) {
            throw new NotFoundException(__('Azienda non trovata'));
        }

        $target = $csh->copySheetYear((int)$id, null, 'generale', $fromYear, $toYear);
        if (empty($target)) {
            throw new HttpException(__('Nessun dato disponibile per l\'anno di origine'), 422);
        }

        $answer = $target->answer;
        $to_year = $toYear;
        $from_year = $fromYear;
        $this->set(compact('answer', 'to_year', 'from_year'));
        $this->viewBuilder()->setOption('serialize', ['answer', 'to_year', 'from_year']);
    }

    /**
     * Edit method
     *
     * @param string|null $id Company id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $this->allowRolesOnly(['admin', 'moma', 'moma_area']);

        $identity = $this->Authentication->getIdentity();
        $role = $identity->get('role');
        $userId = $identity->get('id');
        $company_id = $identity->get('company_id');
        $isAdmin = $role == 'admin';
        $isMomaArea = $role == 'moma' && empty($company_id);
        $isMomaAzienda = $role == 'moma' && !empty($company_id);

        $company = null;
        if (!empty($id)) {
            $company = $this->Companies->get($id, [
                'contain' => ['Offices'],
            ]);
            if (!empty($company) && $isMomaAzienda) {
                if ($company['id'] != $company_id) {
                    throw new \Exception('Non sei autorizzato a procedere');
                }
            }
        } else { // crea azienda, possono farlo solo admin e moma manager generale
            if (!($isAdmin || $isMomaArea)) {
                throw new \Exception('Non sei autorizzato a creare aziende');
            }
        }

        if ($this->request->is(['patch', 'post', 'put'])) {
            $data = $this->request->getData();
            if (isset($data['survey']) && is_array($data['survey'])) {
                unset($data['survey']['anno_compilazione']);
            }
            unset($data['anno_compilazione']);

            // pscl_template_id: modificabile solo da admin (il frontend mostra
            // il campo disabled per gli altri ruoli, ma la protezione reale è
            // qui — Company::$_accessible è '*' => true, quindi senza questo
            // unset chiunque potrebbe forzarlo via payload). Vedi CLAUDE.md/
            // dev-notes PSCL per il contratto completo.
            if (!$isAdmin) {
                unset($data['pscl_template_id']);
            }

            $usedYearRaw = $data['year'] ?? date('Y');
            $usedYear = is_numeric($usedYearRaw) ? (int)$usedYearRaw : (int)date('Y');

            // solo per creazione nuova azienda creo anche sede principale
            if (empty($id)) {
                $data['offices'][] = [];
                // Nome localizzato nella lingua della richiesta (Accept-Language);
                // viene persistito così com'è e resta poi modificabile dall'utente.
                $data['offices'][0]['name'] = __('Sede Principale');
                // office_code è un codice macchina: NON localizzato.
                $data['offices'][0]['office_code'] = 'SEDE';
                // Template PSCL di default per le aziende appena create: un
                // admin può indicarne uno esplicito nel form di creazione
                // (vedi unset() sopra per gli altri ruoli); se non l'ha fatto,
                // ricado sul default di sito (Configure::read('PsclDefaultTemplateId'),
                // vedi config/app.php).
                if (empty($data['pscl_template_id'])) {
                    $data['pscl_template_id'] = Configure::read('PsclDefaultTemplateId');
                }
                $company = $this->Companies->newEntity($data);
            } else {
                $company = $this->Companies->patchEntity($company, $data);
            }

            //Non voglio salvare ogni volta le survey, rischio duplicati
            unset($company->surveys);

            if (!$this->Companies->save($company)) {
                $companyErrors = $company->getErrors();
                Log::error(
                    'CompaniesController::edit save failed for company id ' .
                    ($company->id ?? 'new') . ': ' . json_encode($companyErrors)
                );
                throw new Exception(
                    'The company could not be saved. Please, try again. Validation errors: ' .
                    json_encode($companyErrors)
                );
            }

            //Devo salvare in CompanySurveyHistory
            $csh = $this->fetchTable('CompanySurveyHistory');
            $historyEntry = $csh->find()
                ->where([
                    'company_id' => $company->id,
                    'office_id IS' => null,
                    'year' => $usedYear,
                    'type' => 'generale',
                ])
                ->order(['created' => 'DESC'])
                ->first();

            if (empty($historyEntry)) {
                $historyEntry = $csh->newEmptyEntity();
                $historyEntry->company_id = $company->id;
                $historyEntry->office_id = null;
                $historyEntry->year = $usedYear;
                $historyEntry->type = 'generale';
            }

            //Aggiorno la survey corrente nella storia
            $historyEntry->answer = $company->survey;
            if (!$csh->save($historyEntry)) {
                throw new Exception('The company survey history could not be saved. Please, try again.');
            }

            if (empty($id)) { // solo per creazione nuova azienda
                $surveysTable = $this->fetchTable('Surveys');
                // gestisci il template di questionario scelto
                if (empty($data['public_survey_template']) || $data['public_survey_template'] == -1) {
                    $surveysTable->createNewEmptySurvey($company);
                } else {
                    $surveysTable->createNewSurveyFromTemplate($company, $data['public_survey_template']);
                }
            }
        }

        $officesTable = $this->fetchTable('Offices');
        $offices = $officesTable
            ->find()
            ->where(['company_id' => $company->id])
            ->toArray();
        $this->set(compact('company', 'offices'));
        $this->viewBuilder()->setOption('serialize', ['company', 'offices']);
    }

    /**
     * Delete method
     *
     * @param string|null $id Company id.
     * @return \Cake\Http\Response|null|void Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $identity = $this->Authentication->getIdentity();
        $role = $identity->get('role');
        $company_id = $identity->get('company_id');
        $isAdmin = $role == 'admin';
        $isMomaArea = $role == 'moma_area';

        if (!($isAdmin || $isMomaArea)) {
            throw new \Exception('Non sei autorizzato a procedere');
        }

        $this->request->allowMethod(['post', 'delete']);
        $company = $this->Companies->get($id);
        $surveys = $this->Companies->Surveys->find()->select(['Surveys.id'])->where(['company_id' => $id])->extract('id')->toList();
        try {
            $this->Companies->getConnection()->begin();
            if ($this->Companies->delete($company)) {
                $this->Flash->success(__('The company has been deleted.'));
                //devo eliminare anche i questionari, le risposte, le domande el questionario, gli utenti, i partecipanti
                $this->Companies->Surveys->deleteAll(['company_id' => $id]);
                $questionsSurveysTable = $this->fetchTable('QuestionsSurveys');
                if (count($surveys)) {
                    $questionsSurveysTable->deleteAll(['survey_id IN' => $surveys]);
                    $this->Companies->Surveys->SurveyParticipants->deleteAll(['survey_id IN' => $surveys]);
                }
                $this->Companies->Users->deleteAll(['company_id' => $id]);
            } else {
                $this->Flash->error(__('The company could not be deleted. Please, try again.'));
            }
            $this->Companies->getConnection()->commit();
        } catch (\Cake\ORM\Exception\PersistenceFailedException $e) {
            $this->Companies->getConnection()->rollback();
        }

        $this->autoRender = false;
        //return $this->redirect(['action' => 'index']);
    }

    public function count()
    {
        $this->Authorization->skipAuthorization();
        $count =  $this->Companies->find()->count();
        $this->set(compact('count'));
        $this->viewBuilder()->setOption('serialize', ['count']);
    }

    public function getType($company_id)
    {
        $c = $this->Companies->get($company_id, ['contain' => 'CompanyTypes']);
        if (!empty($c)) {
            $data = $c->company_type;
        }

        $this->set(compact('data'));
        $this->viewBuilder()->setOption('serialize', ['data']);
    }

    public function exportList()
    {
        $identity = $this->Authentication->getIdentity();
        if ($identity == null) {
            throw new \Cake\Http\Exception\UnauthorizedException('Utente non autorizzato');
        }

        $user_id = $identity->get('id');
        $q = trim((string)$this->request->getQuery('q', ''));
        $t = $this->request->getQuery('type');
        $year = $this->request->getQuery('year');
        $page = max(1, (int)$this->request->getQuery('page', 1));
        $limit = max(1, min(50, (int)$this->request->getQuery('limit', 10)));

        $cacheKey = 'companies-' . md5((string)json_encode([
            'user_id' => $user_id,
            'q' => $q,
            'type' => $t,
            'year' => $year,
            'page' => $page,
            'limit' => $limit,
        ]));

        $cached = Cache::read($cacheKey, 'long');
        if (is_array($cached) && isset($cached['companies'], $cached['pagination'])) {
            $this->set($cached);
            $this->viewBuilder()->setOption('serialize', ['companies', 'pagination']);

            return;
        }

        $query = $this->Companies->find()
            ->contain([
                'CompanyTypes',
                'Offices' => ['fields' => [
                    'id',
                    'name',
                    'lat',
                    'lon',
                    'company_id',
                    'num_employees',
                ]],
                'Surveys' => [
                    'fields' => [
                        'id',
                        'name',
                        'company_id',
                        'year',
                    ],
                    'conditions' => function ($exp, $query) use ($year) {
                        // 'TUTTI' (or empty) means "all years": skip the filter.
                        // Surveys.year is an integer column, so binding the string
                        // 'TUTTI' here throws "Cannot convert value of type `string`
                        // to integer" during eager loading.
                        if (empty($year) || $year === 'TUTTI') {
                            return $exp;
                        }

                        return $exp->eq('Surveys.year', (int)$year);
                    },
                ],
            ]);

        $query->contain('Users', function ($q) {
            return $q
                ->select(['id', 'first_name', 'last_name', 'username', 'company_id', 'role'])
                ->where(['Users.role in' => ['moma', 'moma_bloccato']]);
        });

        $query->select([
            'Companies.id',
            'Companies.name',
            'Companies.city',
            'Companies.province',
            'Companies.type',
            'CompanyTypes.name',
            'CompanyTypes.icon',
            'Companies.num_employees',
        ]);

        $query->order(['Companies.name']);

        if ($q !== '') {
            $query->where(['Companies.name LIKE' => "%$q%"]);
        }
        if (!empty($t)) {
            $query->where(['Companies.type' => $t]);
        }

        $this->Authorization->applyScope($query);
        $query->distinct(['Companies.id']);

        $total = (clone $query)->count();
        $companies = $query
            ->limit($limit)
            ->page($page)
            ->all()
            ->toList();

        $psclTable = $this->fetchTable('Pscl');
        foreach ($companies as $company) {
            foreach ($company->offices as $office) {
                $office->pscl = $psclTable->getFiles($company->id, $office->id, $year);
            }
        }

        $pagination = [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pageCount' => max(1, (int)ceil($total / $limit)),
        ];

        $payload = compact('companies', 'pagination');
        Cache::write($cacheKey, $payload, 'long');

        $this->set($payload);
        $this->viewBuilder()->setOption('serialize', ['companies', 'pagination']);
    }

    /**
     * Controlla se un'azienda ha completato i task richiesti, in modo STRICT per anno.
     *
     * Query params opzionali:
     *  - `year`: anno di riferimento (default anno corrente); la completezza usa
     *    `company_survey_history` per quell'anno, senza fallback ai dati live.
     *  - `office_id`: se valorizzato (>0) i controlli a livello sede (sedi,
     *    upload_origini) riguardano solo quella sede, altrimenti tutte le sedi.
     *
     * La risposta include `pscl_status` (vedi psclStatus()): stato del PSCL per
     * azienda+anno (+ sede se `office_id`>0).
     *
     * @param mixed $company_id Id dell'azienda da controllare.
     * @return void
     */
    public function checkTasks($company_id)
    {
        $this->Authorization->skipAuthorization();
        // pscl_status espone dati del piano: solo admin, moma_area, moma senza azienda
        // e moma della propria azienda (stesso helper delle altre azioni company-scoped).
        $this->allowWhoCanSeeCompanyOnly($company_id);

        // Anno selezionato (default: anno corrente). La completezza è valutata in
        // modo STRICT per anno: si usa esclusivamente company_survey_history per
        // l'anno indicato, SENZA fallback ai dati live. Se non esiste storia per
        // quell'anno il relativo task risulta incompleto — corretto per una to-do
        // list: un nuovo anno parte "da fare" finché non si inseriscono i dati.
        $selectedYear = $this->request->getQuery('year', date('Y'));

        $tasks = [
            'company' => false,
            'sedi' => false,
            'upload_origini' => false,
            'eroga_questionario' => false,
            'dati_aggregati' => false,
            'analizza_risultati' => false,
            'pianifica_azioni' => false,
            'genera_pscl' => false,
            'carica_pscl' => false,
        ];

        $c = $this->Companies->get($company_id, ['contain' => ['Offices']]);
        $csh = $this->fetchTable('CompanySurveyHistory');

        // Eventuale restrizione a una singola sede: se ?office_id= è valorizzato (>0)
        // i controlli a livello sede (sedi, upload_origini; dati_aggregati lato frontend)
        // riguardano SOLO quella sede; altrimenti tutte le sedi dell'azienda.
        $officeId = $this->request->getQuery('office_id');
        $offices = $c->offices;
        if (is_numeric($officeId) && (int)$officeId > 0) {
            $offices = array_values(array_filter($offices, fn($o) => (int)$o->id === (int)$officeId));
        }

        // Verifico che la company sia completa per l'anno selezionato (strict):
        // sovrascrivo $c->survey con la storia 'generale' dell'anno; se assente la
        // azzero così checkComplete() restituisce false (niente fallback ai dati live).
        $companyEntry = $csh->latestCompanyEntry((int)$company_id, $selectedYear, 'generale');
        $c->survey = $companyEntry ? $companyEntry->answer : [];
        $tasks['company'] = $c->checkComplete();

        // Verifico che le sedi in scope siano complete per l'anno selezionato (strict).
        $tasks['sedi'] = true;
        foreach ($offices as $o) {
            $officeEntry = $csh->latestOfficeEntry((int)$company_id, (int)$o->id, $selectedYear, 'sede');
            $o->survey = $officeEntry ? $officeEntry->answer : [];
            if (!$o->checkComplete()) {
                $tasks['sedi'] = false;
                break;
            }
        }

        // upload_origini: basta che ALMENO una sede in scope abbia dipendenti per l'anno
        // selezionato; le sedi senza dipendenti caricati vengono comunque segnalate come
        // warning ($empty_employee_offices). L'anno NON è una colonna di employees: è
        // risolto su users.years (vedi Office::numEmployees / EmployeesTable::countForOffice).
        $empty_employee_offices = [];
        $officesWithEmployees = 0;
        foreach ($offices as $o) {
            if (empty($o->numEmployees($selectedYear))) {
                $empty_employee_offices[] = ['id' => $o->id, 'name' => $o->name];
            } else {
                $officesWithEmployees++;
            }
        }
        $tasks['upload_origini'] = $officesWithEmployees > 0;

        // Verifico le risposte associate al questionario dell'anno selezionato
        $surveys = \Cake\ORM\TableRegistry::getTableLocator()->get('Surveys');
        $surveyQuery = $surveys->find()
            ->where(['company_id' => $company_id])
            // Esclude i questionari dinamici di profilo Azienda/Scuola/Uni
            // (SurveysTable::findOrCreateCompanyQuestionnaire()): sono altre
            // righe `surveys` della stessa azienda/anno, ma "eroga_questionario"
            // deve restare il questionario di mobilità dei dipendenti, non uno
            // di questi. Senza questo filtro, il primo Azienda/Scuola/Uni creato
            // per l'anno potrebbe essere selezionato al posto suo (order by
            // created DESC, nessun altro discriminante). `version_tag IS NULL`
            // resta incluso esplicitamente: `NOT IN` da solo escluderebbe anche
            // le righe con version_tag NULL (confronto SQL che dà NULL, non
            // true), regredendo le survey "normali" mai taggate.
            ->where(['OR' => [
                ['Surveys.version_tag IS' => null],
                ['Surveys.version_tag NOT IN' => \App\Model\Table\SurveysTable::COMPANY_QUESTIONNAIRE_TYPES],
            ]])
            ->order(['created' => 'DESC']);
        if (is_numeric($selectedYear)) {
            $surveyQuery->where(['year' => (int)$selectedYear]);
        }
        $s = $surveyQuery->first();
        if (!empty($s)) {
            $tasks['eroga_questionario'] = $s->checkComplete();
        }

        // verifico che abbiano pianificato il pscl per l'anno selezionato
        $pscl = \Cake\ORM\TableRegistry::getTableLocator()->get('Pscl');
        $psclQuery = $pscl->find()->where(['company_id' => $company_id]);
        if (is_numeric($selectedYear)) {
            $psclQuery->where(['year' => (int)$selectedYear]);
        }
        $tasks['pianifica_azioni'] = $psclQuery->count() > 0;
        $pscl_status = $this->psclStatus((int)$company_id, $selectedYear, $officeId, $offices);

        // verifico che abbiano caricato il pscl

        $selected_year = is_numeric($selectedYear) ? (int)$selectedYear : $selectedYear;
        $current_year = (int)date('Y');
        $this->set(compact('tasks', 'selected_year', 'current_year', 'empty_employee_offices', 'pscl_status'));
        $this->viewBuilder()->setOption('serialize', [
            'tasks',
            'selected_year',
            'current_year',
            'empty_employee_offices',
            'pscl_status',
        ]);
    }

    /**
     * Stato del PSCL per il TasksCard: `not_started` (nessuna riga pscl), `incomplete`
     * (anni mancanti/non confermati) o `ok`. Con `office_id`>0 si considera la riga di
     * quella sede OR quella di azienda (office_id NULL); senza filtro, tutte le righe
     * dell'azienda per l'anno, con le liste unite. Anno non numerico (TUTTI) => 'ok'.
     * Regole in PsclTable::planStatus().
     *
     * Ritorna anche un campo `offices` con lo stato PSCL per ogni sede in scope:
     * un'entrata per ogni sede nel primo parametro, più una per il piano aziendale
     * (office_id null, office_name null) se esiste. Quando esiste il piano aziendale,
     * le sedi senza propria riga non sono riportate come not_started.
     *
     * @param int $companyId Id azienda.
     * @param mixed $year Anno selezionato (query param).
     * @param mixed $officeId Query param office_id.
     * @param array $offices Sedi in scope (Entity[], dalla Company con contain Offices).
     * @return array<string,mixed>
     */
    private function psclStatus(int $companyId, $year, $officeId, array $offices): array
    {
        $officeFilter = is_numeric($officeId) && (int)$officeId > 0 ? (int)$officeId : null;
        $status = [
            'state' => 'ok',
            'missing_year' => [],
            'unconfirmed' => [],
            'previous_year_available' => false,
            'office_id' => $officeFilter,
            'offices' => [],
        ];
        if (!is_numeric($year)) {
            return $status;
        }
        $year = (int)$year;

        /** @var \App\Model\Table\PsclTable $pscl */
        $pscl = $this->fetchTable('Pscl');

        // Query helper per le righe scoped
        $scoped = function (int $y, ?int $specificOfficeId = null) use ($pscl, $companyId, $officeFilter) {
            $q = $pscl->find()->where(['Pscl.company_id' => $companyId, 'Pscl.year' => $y]);
            if ($officeFilter !== null) {
                // Con filtro office_id: solo quella sede o azienda (NULL)
                $q->where(['OR' => ['Pscl.office_id' => $officeFilter, 'Pscl.office_id IS' => null]]);
            } elseif ($specificOfficeId !== null) {
                // Cerco la riga di una sede specifica (per l'aggregazione per-ufficio)
                $q->where(['Pscl.office_id' => $specificOfficeId]);
            }

            return $q;
        };

        $rows = $scoped($year)->all();
        $status['previous_year_available'] = $scoped($year - 1)->count() > 0;

        // Raccolgo le informazioni per il riepilgo aggregato (come prima)
        $missing = [];
        $unconfirmed = [];
        $slugs = [];
        foreach ($rows as $row) {
            $plan = is_array($row->plan) ? $row->plan : [];
            foreach ((array)($plan[1] ?? []) as $e) {
                if (is_array($e) && isset($e['measure_id'], $e['measure_slug'])) {
                    $slugs[(int)$e['measure_id']] = $e['measure_slug'];
                }
            }
            $res = $pscl->planStatus($plan, $year);
            $missing = array_merge($missing, $res['missing_year']);
            $unconfirmed = array_merge($unconfirmed, $res['unconfirmed']);
        }

        if ($rows->isEmpty()) {
            $status['state'] = 'not_started';
            // Anche senza dati PSCL, calcolo le liste per ufficio (vedi sotto)
        } else {
            $ids = array_merge(array_column($missing, 'measure_id'), array_column($unconfirmed, 'measure_id'));
            $names = $pscl->measureNames($ids, $slugs);
            $status['missing_year'] = array_map(
                fn($i) => ['measure_id' => $i['measure_id'], 'name' => $names[$i['measure_id']], 'type' => $i['type']],
                $missing
            );
            $status['unconfirmed'] = array_map(
                fn($i) => [
                    'measure_id' => $i['measure_id'],
                    'name' => $names[$i['measure_id']],
                    'type' => $i['type'],
                    'year' => $i['year'],
                ],
                $unconfirmed
            );
            $status['state'] = empty($missing) && empty($unconfirmed) ? 'ok' : 'incomplete';
        }

        // Calcolo il dettaglio per ogni ufficio in scope
        $officesList = [];

        // Verifico se esiste un piano di azienda (office_id NULL)
        $companyPlanRow = null;
        if ($officeFilter === null) {
            // Solo se non sono già filtrato su un'ufficio specifico
            $companyPlanRow = $pscl->find()
                ->where(['Pscl.company_id' => $companyId, 'Pscl.year' => $year, 'Pscl.office_id IS' => null])
                ->first();
        }

        // Per ogni ufficio in scope, calcolo il suo stato
        foreach ($offices as $office) {
            $officeStatusDetail = $this->computeOfficeStatus($pscl, $companyId, $office->id, $year);
            // Se il piano di azienda esiste e questa sede non ha sua riga, non la includo
            if ($companyPlanRow !== null) {
                $officeHasOwnRow = $pscl->find()
                    ->where(['Pscl.company_id' => $companyId, 'Pscl.year' => $year, 'Pscl.office_id' => $office->id])
                    ->first() !== null;
                if (!$officeHasOwnRow) {
                    continue; // Skip this office as it's covered by the company plan
                }
            }
            $officeStatusDetail['office_name'] = $office->name;
            $officesList[] = $officeStatusDetail;
        }

        // Aggiungo il piano di azienda se esiste e non sono filtrato su una sede specifica
        if ($officeFilter === null && $companyPlanRow !== null) {
            $companyStatus = $this->computeOfficeStatus($pscl, $companyId, null, $year);
            $companyStatus['office_name'] = null;
            $officesList[] = $companyStatus;
        }

        $status['offices'] = $officesList;

        return $status;
    }

    /**
     * Computa lo stato PSCL per una singola sede (office_id).
     * Se $skipNotStartedForCoveredByCompanyPlan è true e l'ufficio non ha una sua riga,
     * non viene riportato come not_started (il piano aziendale lo copre).
     *
     * @param \App\Model\Table\PsclTable $pscl
     * @param int $companyId
     * @param int|null $officeId
     * @param int $year
     * @param bool $skipNotStartedForCoveredByCompanyPlan
     * @return array<string,mixed>
     */
    private function computeOfficeStatus(\App\Model\Table\PsclTable $pscl, int $companyId, ?int $officeId, int $year): array
    {
        $conditions = ['Pscl.company_id' => $companyId, 'Pscl.year' => $year];
        if ($officeId === null) {
            $conditions['Pscl.office_id IS'] = null;
        } else {
            $conditions['Pscl.office_id'] = $officeId;
        }
        $row = $pscl->find()->where($conditions)->first();

        $officeStatus = [
            'office_id' => $officeId,
            'state' => 'not_started',
            'missing_year' => [],
            'unconfirmed' => [],
            'previous_year_available' => false,
        ];

        if ($row === null) {
            // Nessuna riga per questa sede/anno
            $officeStatus['state'] = 'not_started';
            // Verifico se esiste una riga per l'anno precedente
            $prevYearConds = ['Pscl.company_id' => $companyId, 'Pscl.year' => $year - 1];
            if ($officeId === null) {
                $prevYearConds['Pscl.office_id IS'] = null;
            } else {
                $prevYearConds['Pscl.office_id'] = $officeId;
            }
            $prevYearRow = $pscl->find()
                ->where($prevYearConds)
                ->first();
            $officeStatus['previous_year_available'] = $prevYearRow !== null;

            return $officeStatus;
        }

        // Esiste una riga per questa sede/anno
        $plan = is_array($row->plan) ? $row->plan : [];
        $res = $pscl->planStatus($plan, $year);

        $missing = $res['missing_year'];
        $unconfirmed = $res['unconfirmed'];

        // Raccolgo i nomi delle misure
        $slugs = [];
        foreach ((array)($plan[1] ?? []) as $e) {
            if (is_array($e) && isset($e['measure_id'], $e['measure_slug'])) {
                $slugs[(int)$e['measure_id']] = $e['measure_slug'];
            }
        }

        $ids = array_merge(array_column($missing, 'measure_id'), array_column($unconfirmed, 'measure_id'));
        $names = $pscl->measureNames($ids, $slugs);

        $officeStatus['missing_year'] = array_map(
            fn($i) => ['measure_id' => $i['measure_id'], 'name' => $names[$i['measure_id']], 'type' => $i['type']],
            $missing
        );
        $officeStatus['unconfirmed'] = array_map(
            fn($i) => [
                'measure_id' => $i['measure_id'],
                'name' => $names[$i['measure_id']],
                'type' => $i['type'],
                'year' => $i['year'],
            ],
            $unconfirmed
        );
        $officeStatus['state'] = empty($missing) && empty($unconfirmed) ? 'ok' : 'incomplete';

        // Verifico anno precedente
        $prevYearConds = ['Pscl.company_id' => $companyId, 'Pscl.year' => $year - 1];
        if ($officeId === null) {
            $prevYearConds['Pscl.office_id IS'] = null;
        } else {
            $prevYearConds['Pscl.office_id'] = $officeId;
        }
        $prevYearRow = $pscl->find()
            ->where($prevYearConds)
            ->first();
        $officeStatus['previous_year_available'] = $prevYearRow !== null;

        return $officeStatus;
    }
}
