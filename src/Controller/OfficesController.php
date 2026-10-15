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

use Cake\Cache\Cache;
use Cake\Database\Query;
use Cake\Http\Exception\HttpException;
use Cake\Http\Exception\NotFoundException;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;
use Cake\Utility\Hash;
use Exception;

/**
 * Offices Controller
 *
 * @property \App\Model\Table\OfficesTable $Offices
 * @method \App\Model\Entity\Office[]|\Cake\Datasource\ResultSetInterface paginate($object = null, array $settings = [])
 */
class OfficesController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->Authentication->allowUnauthenticated([
            'forSurvey',
        ]);
        if ($this->components()->has('Security')) {
            $this->Security->setConfig(
                'unlockedActions',
                [
                    'forSurvey',
                    'copySurveyYear',
                ]
            );
        }
    }

    /**
     * Index method
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        $this->allowRolesOnly(['admin', 'moma', 'superiori', 'user', 'moma_area']);

        $identity = $this->Authentication->getIdentity();
        $query = $this->Offices->find()
            ->order(['company_id', 'Offices.name'])
            ->where(['Offices.name <>' => '']);

        if ($this->request->getQuery('short')) {
            $query->select(['id', 'name']);
        } else {
            $query->contain(['Companies']);
        }

        if ($this->request->getQuery('company_id')) {
            $query->where(['Offices.company_id' => $this->request->getQuery('company_id')]);
        }
        if ($this->request->getQuery('company_type')) {
            $query->where(['Companies.type' => $this->request->getQuery('company_type')]);
        }
        if ($this->request->getQuery('provincia')) {
            $query->where(['Offices.province' => $this->request->getQuery('provincia')]);
        }
        //Attenzione in questo caso la condition è sul contain
        if ($this->request->getQuery('from')) {
            $f = $this->request->getQuery('from');
            $query->contain('Timetables', function (Query $q) use ($f) {
                return $q->where(['Timetables.valid_from >=' => $f]);
            });
        } else {
            $query->contain('Timetables');
        }
        if ($this->request->getQuery('q')) {
            $q =  $this->request->getQuery('q');
            $query->where(
                [
                    'OR' => [
                        ['Offices.city LIKE' => "%$q%"],
                        ['Companies.name LIKE' => "%$q%"],
                    ],
                ]
            );
        }
        // sqld($query);
        if ($this->request->getQuery('limit')) {
            $select = explode(',', $this->request->getQuery('limit'));
            foreach ($select as $k => $s) {
                $select[$k] = trim($s);
            }
            $query->select($select);
        }

        // //If the user is moma-area can only see if province and its area
        // if($identity->role=="moma_area"){
        //     $query->where([
        //         'Offices.province' => 'TO',
        //     ]);

        // }else{
        //      //L'utente moma può vedere solo la sua azienda
        //     $company_id = $identity->get('company_id');
        //     if (!empty($company_id)) {
        //         $query->where(['OR' => [
        //             'company_id' => $company_id,
        //             [
        //                 'coworking' => 1,
        //                 'private_coworking' => 0,
        //             ],
        //         ]]);
        //     }


        //     //L'utente moma può vedere solo la sua azienda
        //     $office_id = $identity->get('office_id');
        //     if (!empty($office_id)) {
        //         $query
        //         // ->where(['Offices.id' =>  $office_id]);
        //         ->where(['OR' => [
        //             'Offices.id' =>  $office_id,
        //             [
        //                 'coworking' => 1,
        //                 'private_coworking' => 0,
        //             ],
        //         ]]);
        //     }

        // }
        $this->Authorization->applyScope($query);

        $cacheKey = $this->getOfficesIndexCacheKey($identity, $this->request->getQueryParams());
        $cachedResponse = Cache::read($cacheKey . '-json', 'offices');

        if (is_string($cachedResponse) && $cachedResponse !== '') {
            return $this->buildCachedJsonResponse($cachedResponse, 'HIT');
        }

        // debug($query2);
        // Pagination is opt-in: only applied when `per_page` is present, so existing
        // callers (which never send it) keep getting the full unbounded result set
        // with the exact same {offices: [...]} response shape as before. `page`
        // (1-indexed) defaults to 1 when `per_page` is given without it; `page`
        // alone, without `per_page`, is a no-op. `total` (the count of the full
        // filtered result set, before the limit) is only added to the response when
        // pagination was actually applied.
        $total = null;
        if ($this->request->getQuery('per_page')) {
            $perPage = max(1, (int)$this->request->getQuery('per_page'));
            $page = max(1, (int)($this->request->getQuery('page') ?: 1));

            $total = $query->count();
            $query->page($page, $perPage);
        }

        $responseData = ['offices' => $query->all()->toList()];
        if ($total !== null) {
            $responseData['total'] = $total;
        }
        $jsonResponse = $this->encodeJsonResponse($responseData);
        Cache::write($cacheKey . '-json', $jsonResponse, 'offices');

        return $this->buildCachedJsonResponse($jsonResponse, 'MISS');
    }

    // metodo (pubblico!) per ottenere la lista uffici per la domanda di tipo mappa

    public function forSurvey($survey_id)
    {
        $cacheKey = "offices-$survey_id";
        $cachedResponse = Cache::read($cacheKey . '-json', 'offices');

        if (is_string($cachedResponse) && $cachedResponse !== '') {
            return $this->buildCachedJsonResponse($cachedResponse, 'HIT');
        }

        $this->Surveys = TableRegistry::getTableLocator()->get('Surveys');
        $survey = $this->Surveys->find()
            ->where([
                'Surveys.id' => $survey_id,
            ])
            ->select(['Surveys.id', 'company_id', 'year'])
            ->first();

        if (!empty($survey)) {
            $company_id = $survey->company_id;
            if (!empty($company_id)) {
                $offices = $this->Offices->find()->where([
                    'company_id' => $company_id,
                ])->order('name')->toArray();

                // Filtro le sedi in base all'anno della survey:
                // years == null/[] => sede attiva in tutti gli anni;
                // years = [<anni>] => attiva solo negli anni elencati.
                $year = $survey->year;
                if (!empty($year)) {
                    $year = (string)$year;
                    $offices = array_values(array_filter($offices, function ($office) use ($year) {
                        $years = $office->years;
                        // years non è castato in _initializeSchema: decodifico se arriva come stringa
                        if (is_string($years)) {
                            $years = json_decode($years, true);
                        }
                        if (empty($years) || !is_array($years)) {
                            return true; // null / [] => attiva in tutti gli anni
                        }

                        return in_array($year, array_map('strval', $years), true);
                    }));
                }
            } else {
                // create default office
                $offices = [
                    [
                        'id' => 0,
                        'name' => 'Sede principale template',
                        'lat' => 45.070312,
                        'lon' => 7.686856,
                        'address' => 'Via XX Settembre 123',
                        'city' => 'Torino',
                    ],
                ];
            }
        } else {
            $offices = [];
        }

        $jsonResponse = $this->encodeJsonResponse(['offices' => $offices]);
        Cache::write($cacheKey . '-json', $jsonResponse, 'offices');

        return $this->buildCachedJsonResponse($jsonResponse, 'MISS');
    }

    /**
     * View method
     *
     * @param string|null $id Office id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $this->allowRolesOnly(['admin', 'moma', 'superiori', 'moma_area']);
        //leggo l'anno utilizzato
        $usedYear = $this->request->getQuery('year', date('Y'));
        // historyOnly: se attivo, in assenza di storia per l'anno NON si fa fallback ai
        // dati live (survey/aggregated_survey restano vuoti). Usato dalla TasksCard per
        // allineare il dettaglio "campi mancanti" al badge strict per anno; le pagine di
        // edit non passano il flag e mantengono il fallback (pre-fill dell'ultimo anno).
        $historyOnly = filter_var($this->request->getQuery('historyOnly', false), FILTER_VALIDATE_BOOLEAN);

        $office = $this->Offices->get($id, [
            'contain' => ['Companies'],
        ]);

        $conditions = [
            'company_id' => $office->company_id,
            'office_id' => $office->id,
            'type' => 'sede',
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
            $office->survey = $historyEntry->answer;
        } elseif ($historyOnly) {
            // strict per anno: nessun fallback ai dati live
            $office->survey = [];
        }

        // Load aggregated_survey from history for the selected year
        $aggregatedHistoryEntry = $csh->find()
            ->where([
                'company_id' => $office->company_id,
                'office_id'  => $office->id,
                'year'       => $usedYear,
                'type'       => 'aggregated',
            ])
            ->order(['created' => 'DESC'])
            ->first();

        if (!empty($aggregatedHistoryEntry)) {
            $office->aggregated_survey = $aggregatedHistoryEntry->answer;
        } elseif ($historyOnly) {
            // strict per anno: nessun fallback ai dati live
            $office->aggregated_survey = [];
        }

        // Flag per il frontend: esiste già una conferma (riga di storia) per l'anno selezionato?
        // Il componente AnnoCompilazione si nasconde quando la conferma esiste (ask-once per anno).
        $office->survey_history_found = !empty($historyEntry);
        $office->aggregated_survey_history_found = !empty($aggregatedHistoryEntry);

        $this->set(compact('office'));
        $this->viewBuilder()->setOption('serialize', ['office']);
    }

    /**
     * Add method
     *
     * NON USATO, lo setto solo accessibile da admin
     */
    public function add()
    {
        $this->allowRolesOnly(['admin']);

        $office = $this->Offices->newEmptyEntity();
        if ($this->request->is('post')) {
            $office = $this->Offices->patchEntity($office, $this->request->getData());
            if ($this->Offices->save($office)) {
                $this->Flash->success(__('The office has been saved.'));
                $this->clearOfficesCache();

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The office could not be saved. Please, try again.'));
        }
        $companies = $this->Offices->Companies->find('list', ['limit' => 200]);
        $this->set(compact('office', 'companies'));
    }

    /**
     * Valida il tipo di foglio sede ('sede' default, o 'aggregated').
     *
     * @param mixed $type Valore grezzo.
     * @return string
     */
    private function surveySheetType($type): string
    {
        $type = $type === null || $type === '' ? 'sede' : $type;
        if (!in_array($type, ['sede', 'aggregated'], true)) {
            throw new HttpException(__('Tipo non valido'), 422);
        }

        return $type;
    }

    /**
     * Anni con una riga di storia del foglio della sede (?type=sede|aggregated), decrescenti.
     *
     * @param string|null $id Office id.
     * @return void
     */
    public function surveyYears($id = null)
    {
        $this->request->allowMethod(['get']);
        $this->allowRolesOnly(['admin']);
        $type = $this->surveySheetType($this->request->getQuery('type'));
        $office = $this->Offices->find()->select(['Offices.id', 'Offices.company_id'])
            ->where(['Offices.id' => (int)$id])->first();
        if (empty($office)) {
            throw new NotFoundException(__('Sede non trovata'));
        }
        $years = $this->fetchTable('CompanySurveyHistory')
            ->sheetYears((int)$office->company_id, (int)$office->id, $type);
        $this->set(compact('years'));
        $this->viewBuilder()->setOption('serialize', ['years']);
    }

    /**
     * Admin only: copia il foglio sede/aggregato da from_year a to_year (solo storia, mai offices.survey).
     * Aggiorna la riga dell'anno di destinazione se esiste, altrimenti la crea.
     *
     * @param string|null $id Office id.
     * @return void
     */
    public function copySurveyYear($id = null)
    {
        $this->request->allowMethod(['post']);
        $this->allowRolesOnly(['admin']);

        $csh = $this->fetchTable('CompanySurveyHistory');
        $data = (array)$this->request->getData();
        $type = $this->surveySheetType($data['type'] ?? null);
        $fromYear = $csh::parseYear($data['from_year'] ?? null);
        $toYear = $csh::parseYear($data['to_year'] ?? null);
        if ($fromYear === null || $toYear === null) {
            throw new HttpException(__('Anni non validi'), 422);
        }
        if ($fromYear === $toYear) {
            throw new HttpException(__('L\'anno di origine e quello di destinazione devono essere diversi'), 422);
        }
        $office = $this->Offices->find()->select(['Offices.id', 'Offices.company_id'])
            ->where(['Offices.id' => (int)$id])->first();
        if (empty($office) || empty($office->company_id)) {
            throw new NotFoundException(__('Sede non trovata'));
        }
        // La sede appartiene sempre all'azienda del suo record: la riga storica usa quel company_id.
        if (isset($data['company_id']) && (int)$data['company_id'] !== (int)$office->company_id) {
            throw new NotFoundException(__('La sede non appartiene all\'azienda indicata'));
        }

        $target = $csh->copySheetYear((int)$office->company_id, (int)$office->id, $type, $fromYear, $toYear);
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
     * @param string|null $id Office id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $this->allowRolesOnly(['admin', 'moma', 'superiori', 'moma_area']);

        $office = null;
        if (!empty($id)) {
            $office = $this->Offices->get($id, [
                'contain' => [],
            ]);
            if (empty($office)) {
                throw new Exception('Not found');
            }
        }

        if ($this->request->is(['patch', 'post', 'put'])) {
            $dt = $this->request->getData();
            if (isset($dt['survey']) && is_array($dt['survey'])) {
                unset($dt['survey']['anno_compilazione']);
            }
            // Segnale esplicito di conferma anno (campo top-level, mai persistito né servito):
            // solo OfficeSurvey.vue lo invia; OfficeEdit.vue (anagrafica) non può inviarlo.
            $annoConfirm = $dt['anno_compilazione'] ?? null;
            unset($dt['anno_compilazione']);
            $usedYear  = $dt['year'] ?? date('Y');

            $company_id = empty($dt['company_id']) ? $office['company_id'] : $dt['company_id'];
            $this->allowWhoCanSeeCompanyOnly($company_id);

            $officeSurveySubmitted = false;
            if (
                !empty($office) && isset($dt['survey'])
                && (
                    json_encode($office->survey) != json_encode($dt['survey'])
                    // conferma esplicita dell'anno: registra la storia anche a dati invariati
                    || (string)$annoConfirm === (string)$usedYear
                )
            ) {
                $officeSurveySubmitted = true;
            }

            $office = !empty($id) ? $this->Offices->patchEntity($office, $dt) : $this->Offices->newEntity($dt);
            $isNewOffice = empty($id);

            if ($this->Offices->save($office)) {
                // Auto-geocode new offices (only fill empty coordinates, never overwrite)
                if ($isNewOffice && $this->Offices->geocodeOffice($office, false)) {
                    $this->Offices->save($office);
                }

                if ($officeSurveySubmitted) {
                    //Devo anche caricare la surveyhistorey per leggere la versione corrente se esiste, se no prendo quella della survey
                    $csh = $this->fetchTable('CompanySurveyHistory');
                    $historyEntry = $csh->find()
                        ->where([
                            'company_id' => $office->company_id,
                            'office_id' => $office->id,
                            'year' => $usedYear,
                            'type' => 'sede',
                        ])
                        ->order(['created' => 'DESC'])
                        ->first();

                    if (empty($historyEntry)) {
                        $historyEntry = $csh->newEmptyEntity();
                        $historyEntry->company_id = $office->company_id;
                        $historyEntry->office_id = $office->id;
                        $historyEntry->year = $usedYear;
                        $historyEntry->type = 'sede';
                    }
                    $historyEntry->answer = $office->survey;
                    if (!$csh->save($historyEntry)) {
                        Log::error('Impossibile salvare la storia della survey dell\'ufficio ' . $office->id);
                    }
                }

                $this->clearOfficesCache();
                if (!$this->request->is('json')) {
                    $this->Flash->success(__('The office has been saved.'));

                    return $this->redirect(['action' => 'index']);
                } else {
                    $msg  = 'salvataggio avvenuto correttamente';
                    $this->set('msg', $msg);
                    $this->viewBuilder()->setOption('serialize', ['msg']);
                }
            } else {
                if (!$this->request->is('json')) {
                    $this->Flash->error(__('The office could not be saved. Please, try again.'));
                } else {
                    $officeErrors = $office->getErrors();
                    Log::error(
                        'OfficesController::edit save failed for office id ' .
                        ($office->id ?? 'new') . ': ' . json_encode($officeErrors)
                    );
                    throw new Exception('Impossibile salvare. Validation errors: ' . json_encode($officeErrors));
                }
            }
        }
        $companies = $this->Offices->Companies->find('list', ['limit' => 200]);
        $this->set(compact('office', 'companies'));
    }

    /**
     * Delete method
     *
     * @param string|null $id Office id.
     * @return \Cake\Http\Response|null|void Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->allowRolesOnly(['admin', 'moma', 'superiori', 'moma_area']);
        $this->request->allowMethod(['delete']);
        $office = $this->Offices->get($id);

        $this->allowWhoCanSeeCompanyOnly($office['company_id']);

        if ($this->Offices->delete($office)) {
            $this->clearOfficesCache();
            $this->Flash->success(__('The office has been deleted.'));
        } else {
            $this->Flash->error(__('The office could not be deleted. Please, try again.'));
        }

        // return $this->redirect(['action' => 'index']);
    }

    private function getOfficesIndexCacheKey($identity, array $queryParams): string
    {
        $identityData = [
            'role' => $identity ? $identity->get('role') : null,
            'company_id' => $identity ? $identity->get('company_id') : null,
            'office_id' => $identity ? $identity->get('office_id') : null,
            'area' => $identity ? $this->normalizeCacheArray(Hash::flatten((array)$identity->get('area'))) : [],
            'query' => $this->normalizeCacheArray($queryParams),
        ];

        return 'offices-index-' . md5((string)json_encode($identityData));
    }

    private function normalizeCacheArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->normalizeCacheArray($value);
            } elseif (is_bool($value)) {
                $data[$key] = (int)$value;
            } elseif ($value === null) {
                $data[$key] = '';
            }
        }

        ksort($data);

        return $data;
    }

    private function clearOfficesCache(): void
    {
        Cache::clearGroup('offices', 'offices');
    }

    private function encodeJsonResponse(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw new Exception('Impossibile serializzare la risposta JSON degli uffici');
        }

        return $json;
    }

    private function buildCachedJsonResponse(string $jsonResponse, string $cacheStatus)
    {
        return $this->response
            ->withType('application/json')
            ->withHeader('X-Offices-Cache', $cacheStatus)
            ->withStringBody($jsonResponse);
    }

    private function advance($msg)
    {
        $s['msg'] = $msg;
        Log::write('debug', $msg);
    }

    /**
     * Flatten nested JSON/array into dot notation
     *
     * @param mixed $data The data to flatten
     * @param string $prefix The prefix for keys
     * @return array Flattened array with dot notation keys
     */
    private function flattenJson($data, $prefix = '')
    {
        $result = [];
        if (is_array($data) || is_object($data)) {
            foreach ($data as $key => $value) {
                $newKey = $prefix === '' ? $key : $prefix . '.' . $key;
                if (is_array($value) || is_object($value)) {
                    // Check if it's an indexed array with all primitive values
                    if (is_array($value) && array_keys($value) === range(0, count($value) - 1)) {
                        // Check if all values are primitives (not arrays or objects)
                        $allPrimitives = true;
                        foreach ($value as $item) {
                            if (is_array($item) || is_object($item)) {
                                $allPrimitives = false;
                                break;
                            }
                        }
                        if ($allPrimitives) {
                            // It's a simple list, encode as JSON
                            $result[$newKey] = json_encode($value, JSON_UNESCAPED_UNICODE);
                        } else {
                            // It's an array of objects/arrays, flatten with numeric indices
                            $result = array_merge($result, $this->flattenJson($value, $newKey));
                        }
                    } else {
                        // It's an object/associative array, flatten it
                        $result = array_merge($result, $this->flattenJson($value, $newKey));
                    }
                } else {
                    $result[$newKey] = $value;
                }
            }
        }

        return $result;
    }

    /**
     * Bulk-imports offices for a company from an uploaded Excel file (xls/xlsx).
     *
     * Expected columns (no header row is required in the data, the first row is
     * always discarded as the header): office_code, name, address, city, cap,
     * province, num_employees. Rows matching an existing office (same name +
     * address within the company) are updated in place instead of duplicated.
     *
     * @param string|int|null $company_id Company the imported offices belong to.
     * @return void
     */
    public function import($company_id = null)
    {
        $this->allowRolesOnly(['admin', 'moma', 'superiori', 'moma_area']);
        if (!empty($company_id)) {
            $this->allowWhoCanSeeCompanyOnly($company_id);
        }

        $errorMsg = false;
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $geocode_skipped = 0;

        if ($this->request->is('post')) {
            $filename = null;
            try {
                if (empty($company_id)) {
                    throw new Exception(__('Azienda non specificata.'));
                }

                $file = $this->request->getUploadedFile('excelfile');
                if (empty($file) || $file->getError() !== UPLOAD_ERR_OK) {
                    Log::info('Offices import - error while uploading the file');

                    throw new Exception(__('Errore nell\'apertura del file.'));
                }

                $filename = TMP . uniqid('offices_import_') . '_' . basename($file->getClientFilename());
                $file->moveTo($filename);
                Log::info('Offices import - file moved in correctly');

                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filename);
                $sheetData = $spreadsheet->getActiveSheet()->toArray();
                //La prima riga contiene i titoli e la scarto
                array_shift($sheetData);

                if (empty($sheetData)) {
                    throw new Exception(__('Il file importato è vuoto.'));
                }

                // Geocoding time budget: ~15 seconds total to avoid timeout
                $geocodeStartTime = hrtime(true);
                $geocodeBudgetNs = 15 * 1e9; // 15 seconds in nanoseconds
                $consecutiveGeocodingFailures = 0;
                $maxConsecutiveFailures = 3;

                foreach ($sheetData as $row) {
                    $name = trim((string)($row[1] ?? ''));
                    $address = trim((string)($row[2] ?? ''));

                    if ($name === '' || $address === '') {
                        $skipped++;
                        continue;
                    }

                    $office = $this->Offices->find()
                        ->where([
                            'name' => $name,
                            'address' => $address,
                            'company_id' => $company_id,
                        ])
                        ->first();
                    $isNew = empty($office);
                    if ($isNew) {
                        $office = $this->Offices->newEmptyEntity();
                    }

                    $office->office_code = $row[0] ?? null;
                    $office->name = $name;
                    $office->address = $address;
                    $office->city = $row[3] ?? null;
                    $office->cap = $row[4] ?? null;
                    $office->province = $row[5] ?? null;
                    $office->num_employees = is_numeric($row[6] ?? null) ? (int)$row[6] : null;
                    $office->company_id = $company_id;

                    if ($this->Offices->save($office)) {
                        $isNew ? $created++ : $updated++;
                        $this->advance("office save successfull {$office->name}");

                        // Try to geocode after save: skip if row already has coordinates
                        if (empty($office->lat) && empty($office->lon)) {
                            // Check if we still have time budget
                            $elapsedNs = hrtime(true) - $geocodeStartTime;
                            if ($elapsedNs < $geocodeBudgetNs && $consecutiveGeocodingFailures < $maxConsecutiveFailures) {
                                if ($this->Offices->geocodeOffice($office, false)) {
                                    $this->Offices->save($office);
                                    $consecutiveGeocodingFailures = 0;
                                } else {
                                    $consecutiveGeocodingFailures++;
                                    $geocode_skipped++;
                                }
                            } else {
                                // Out of time or too many failures; mark for manual geocoding
                                $geocode_skipped++;
                            }
                        }
                    } else {
                        $skipped++;
                        $this->advance("office save unsuccessfull {$office->name}");
                    }
                }

                $this->clearOfficesCache();
            } catch (Exception $e) {
                $errorMsg = $e->getMessage();
                Log::error('Offices import error: ' . $e->getMessage());
            } finally {
                if (!empty($filename) && file_exists($filename)) {
                    unlink($filename);
                }
            }
        }

        $this->set('errorMsg', $errorMsg);
        $this->set('created', $created);
        $this->set('updated', $updated);
        $this->set('skipped', $skipped);
        $this->set('geocode_skipped', $geocode_skipped);
        $this->viewBuilder()->setOption('serialize', ['errorMsg', 'created', 'updated', 'skipped', 'geocode_skipped']);
    }

    public function geocode($office_id)
    {
        $this->allowRolesOnly(['moma', 'admin']);
        $force = ($this->request->getQuery('force') == 'true');

        $office = $this->Offices->find()
            ->where(['id' => $office_id])
            ->first();
        $msg = '';

        if (!$office) {
            $msg = "Impossibile trovare l'ufficio $office_id";
        } else {
            // Sede già geocodificata e nessun `force`: niente da fare, NON è un errore.
            // Il modale "Geocodifica sedi" (Companies/edit.vue) scorre tutte le sedi e
            // considera msg === '' un successo: un messaggio qui riempirebbe il log di
            // falsi errori per ogni sede già a posto.
            if (!$force && !empty($office->lat) && !empty($office->lon)) {
                // no-op
            } elseif ($this->Offices->geocodeOffice($office, $force)) {
                if (!$this->Offices->save($office)) {
                    Log::error("Impossibile salvare la geocodifica della sede {$office->id}");
                    $msg = "Impossibile salvare la geocodifica della sede {$office->id}";
                }
            } else {
                Log::error("Impossibile geocodificare la sede {$office->id}");
                $msg = "Impossibile geocodificare la sede {$office->id}";
            }
        }
        $this->set('msg', $msg);
        $this->set('office', $office);
        $this->viewBuilder()->setOption('serialize', ['msg', 'office']);
    }

    public function getLabel($office_id)
    {
        $labelSurvey = null;
        $officeName = $this->Offices->get($office_id, [
            'contain' => ['Companies'],
        ])->name;

        if (!empty($office_id)) {
            $labelSurvey = $this->Offices->find()
                ->where(['id' => $office_id])
                ->select('label_survey')
                ->first();
            if (empty($labelSurvey)) {
                throw new Exception('label_survey of this Office Not found');
            }
        }
        //add to label_survey array the office_name
        $labelSurvey->label_survey['office_name'] = $officeName;
        $this->set('labelSurvey', $labelSurvey);
        $this->viewBuilder()->setOption('serialize', ['labelSurvey']);
    }

    /**
     * Salvataggio dati aggregati
     *
     * @param mixed $office_id
     * @return void
     */
    public function saveAggregated($office_id)
    {
        $this->allowRolesOnly(['admin', 'moma', 'superiori', 'moma_area']);

        $office = $this->Offices->get($office_id, [
            'contain' => [],
        ]);

        if ($this->request->is(['patch', 'post', 'put'])) {
            $dt = $this->request->getData();
            if (isset($dt['aggregated_survey']) && is_array($dt['aggregated_survey'])) {
                unset($dt['aggregated_survey']['anno_compilazione']);
            }
            if (isset($dt['survey']) && is_array($dt['survey'])) {
                unset($dt['survey']['anno_compilazione']);
            }
            unset($dt['anno_compilazione']);
            // Precedenza: 'year' (anno selezionato lato frontend) ha priorità su 'anno'
            // (anno di somministrazione della survey) cosi la storia segue l'anno di lavoro.
            $usedYearRaw = $dt['year'] ?? $dt['anno'] ?? date('Y');
            $usedYear = is_numeric($usedYearRaw) ? (int)$usedYearRaw : (int)date('Y');
            unset($dt['year']);

            $company_id = empty($dt['company_id']) ? $office['company_id'] : $dt['company_id'];
            $this->allowWhoCanSeeCompanyOnly($company_id);

            $office->aggregated_survey = $dt;
            $office = $this->Offices->patchEntity($office, $dt);
            if ($this->Offices->save($office)) {
                $csh = $this->fetchTable('CompanySurveyHistory');
                $historyEntry = $csh->find()
                    ->where([
                        'company_id' => $office->company_id,
                        'office_id'  => $office->id,
                        'year'       => $usedYear,
                        'type'       => 'aggregated',
                    ])
                    ->order(['created' => 'DESC'])
                    ->first();

                if (empty($historyEntry)) {
                    $historyEntry = $csh->newEmptyEntity();
                    $historyEntry->company_id = $office->company_id;
                    $historyEntry->office_id  = $office->id;
                    $historyEntry->year       = $usedYear;
                    $historyEntry->type       = 'aggregated';
                }
                $historyEntry->answer = $office->aggregated_survey;
                if (!$csh->save($historyEntry)) {
                    Log::error('Impossibile salvare la storia della survey aggregata dell\'ufficio ' . $office->id);
                }

                Cache::delete("offices-$company_id");
                // La scheda aggregata alimenta il fallback dello split modale (getIndicator,
                // cache 'dashboard' ~5 min): svuoto il gruppo cosi' il grafico non resta datato.
                Cache::clearGroup('dashboard');
                if (!$this->request->is('json')) {
                    $this->Flash->success(__('The office has been saved.'));

                    return $this->redirect(['action' => 'index']);
                } else {
                    $msg  = 'salvataggio avvenuto correttamente';
                    $this->set('msg', $msg);
                    $this->viewBuilder()->setOption('serialize', ['msg']);
                }
            } else {
                if (!$this->request->is('json')) {
                    $this->Flash->error(__('The office could not be saved. Please, try again.'));
                } else {
                    throw new Exception('Impossibile salvare');
                }
            }
        }
        $this->set(compact('office'));
    }

    private function mappa_stato_attuazione($stato_attuazione)
    {
        $mappa = [
            0 => '--',
            1 => 'Già presente',
            2 => 'Già presente, da potenziare',
            3 => 'Non presente, da sviluppare',
        ];

        return $mappa[$stato_attuazione] ?? $stato_attuazione;
    }

    /**
     * Export all offices data to XLS
     *
     * Exports all office columns, related company columns, and JSON fields:
     * - office.survey
     * - office.aggregated_survey
     * - company.survey
     *
     * @param string|null $year Optional year filter
     * @return \Cake\Http\Response|null
     */
    public function export($year = null)
    {
        $this->Authorization->authorize($this->Offices, 'export');
        // Definisci i campi da esportare
        // Rimuovi i campi che non vuoi esportare da questi array
        // TODO: spostare da qui e mettere in una variabile di configurazione
        $exportFields = [
            'company.name',
            'company.survey.codice_ateco',
            'company.survey.indirizzo',
            'company.survey.comune',
            'company.survey.district',
            'company.survey.nr_sedi',
            'company.survey.nr_dipendenti',
            'company.survey.nome_mobility_manager',
            'company.survey.mm_nome_cognome',
            'company.survey.mm_email',
            'company.survey.mm_telefono',
            'company.survey.pec_azienda',
            'company.survey.rapporto_lavorativo',
            'company.survey.data_nomina_mm',
            'office.pscl.0.plan.0.data_adozione', //Colonna 14
            'company.company_type.name',

            //Campi di office
            'name',
            'address',
            'cap',
            'city',
            'district',
            'province',
            'lat',
            'lon',
            'survey.ul_nr_dipendenti',
            'survey.ul_dipendenti_flessibili',
            'survey.ul_dipendenti_turnazione',
            //Con la sintassi .0. prendo il primo elemento di un gruppo
            'survey.azi_sosta_bici_areas.0.posti_totali',
            'survey.azi_sosta_bici_areas.0.posti_ricarica',

            //Trasporto Aziendale
            'survey.trasporto_aziendale',
            'survey.nr_trasporto_aziendale',
            'survey.tragitto',

            //Autovetture
            'survey.az_sosta',
            'survey.az_sosta_auto_totale',
            'survey.az_sosta_auto_el_nr',
            'survey.az_sosta_carpooling_nr',

            //Scooter
            'survey.sosta_scooter.0',
            'survey.az_sosta_moto_totale',
            'survey.az_sosta_moto_el_nr',

            //Flotta Auto Promiscue
            'survey.az_veicoli',
            'survey.az_auto',
            'survey.az_auto_flotta.0.numero',
            'survey.az_auto_flotta.0.alimentazione',
            'survey.az_auto_flotta.1.numero',
            'survey.az_auto_flotta.1.alimentazione',
            'survey.az_auto_flotta.2.numero',
            'survey.az_auto_flotta.2.alimentazione',
            'survey.az_auto_flotta.3.numero',
            'survey.az_auto_flotta.3.alimentazione',

            //Flotta Auto Servizio
            'survey.az_auto_servizio',
            'survey.az_auto_servizio_flotta.0.numero_servizio',
            'survey.az_auto_servizio_flotta.0.alimentazione_servizio',
            'survey.az_auto_servizio_flotta.1.numero_servizio',
            'survey.az_auto_servizio_flotta.1.alimentazione_servizio',
            'survey.az_auto_servizio_flotta.2.numero_servizio',
            'survey.az_auto_servizio_flotta.2.alimentazione_servizio',
            'survey.az_auto_servizio_flotta.3.numero_servizio',
            'survey.az_auto_servizio_flotta.3.alimentazione_servizio',

            //Moto
            'survey.az_moto',
            'survey.az_moto_flotta.0.numero',
            'survey.az_moto_flotta.0.alimentazione',
            'survey.az_moto_flotta.1.numero',
            'survey.az_moto_flotta.1.alimentazione',
            'survey.az_moto_flotta.2.numero',
            'survey.az_moto_flotta.2.alimentazione',
            'survey.az_moto_flotta.3.numero',
            'survey.az_moto_flotta.3.alimentazione',

            //Moto Servizio
            'survey.az_moto_servizio',
            'survey.az_moto_servizio_flotta.0.numero_servizio',
            'survey.az_moto_servizio_flotta.0.alimentazione_servizio',
            'survey.az_moto_servizio_flotta.1.numero_servizio',
            'survey.az_moto_servizio_flotta.1.alimentazione_servizio',
            'survey.az_moto_servizio_flotta.2.numero_servizio',
            'survey.az_moto_servizio_flotta.2.alimentazione_servizio',
            'survey.az_moto_servizio_flotta.3.numero_servizio',
            'survey.az_moto_servizio_flotta.3.alimentazione_servizio',

            //Bici
            'survey.az_bici',
            'survey.az_bici_flotta.0.numero',
            'survey.az_bici_flotta.0.tipologia',
            'survey.az_bici_flotta.1.numero',
            'survey.az_bici_flotta.1.tipologia',

            'survey.az_bici_servizio',
            'survey.az_bici_servizio_flotta.0.numero_servizio',
            'survey.az_bici_servizio_flotta.0.tipologia_servizio',
            'survey.az_bici_servizio_flotta.1.numero_servizio',
            'survey.az_bici_servizio_flotta.1.tipologia_servizio',

            //Monopattini
            'survey.az_monopattini',
            'survey.az_monopattini_flotta.0.numero',
            'survey.az_monopattini_flotta.0.tipologia',
            'survey.az_monopattini_flotta.1.numero',
            'survey.az_monopattini_flotta.1.tipologia',

            'survey.az_monopattini_servizio',
            'survey.az_monopattini_servizio_flotta.0.numero',
            'survey.az_monopattini_servizio_flotta.0.tipologia',
            'survey.az_monopattini_servizio_flotta.1.numero',
            'survey.az_monopattini_servizio_flotta.1.tipologia',

            //Dati Aggregati
            /**
             * {"anno":"2024","rispondenti":"71",
             * "splitModale":{"aPiedi":"2","bici":"3","autoConducente":"87","autoPasseggero":"0","navetta":"0","trasportoPubblico":"8","autoTpl":"0","altro":"0"},
             * "propensioneCambiamento":{"aPiedi":"5.60","bici":"8.80","carpooling":"2.8","navetta":"4.9","trasportoPubblico":"9.90","intermodalita":"5.7"}}
             **/
            'aggregated_survey.anno',
            'aggregated_survey.rispondenti',
            'aggregated_survey.splitModale.aPiedi',
            'aggregated_survey.splitModale.bici',
            'aggregated_survey.splitModale.autoConducente',
            'aggregated_survey.splitModale.autoPasseggero',
            'aggregated_survey.splitModale.navetta',
            'aggregated_survey.splitModale.trasportoPubblico',
            'aggregated_survey.splitModale.autoTpl',
            'aggregated_survey.splitModale.altro',
            'aggregated_survey.propensioneCambiamento.aPiedi',
            'aggregated_survey.propensioneCambiamento.bici',
            'aggregated_survey.propensioneCambiamento.carpooling',
            'aggregated_survey.propensioneCambiamento.navetta',
            'aggregated_survey.propensioneCambiamento.trasportoPubblico',
            'aggregated_survey.propensioneCambiamento.intermodalita',
        ];

        // Query all offices with their companies
        // TODO: Manca la gestione dell'anno in offices
        $query = $this->Offices->find()
            ->contain(['Companies' => ['CompanyTypes'], 'Pscl' => [
                'conditions' => ['Pscl.year' => $year],
            ]])
            ->order(['Offices.company_id', 'Offices.name']);

        // Apply authorization scope
        $this->Authorization->applyScope($query);
        // Mi serve per restituire i risultati come array e non oggetti (per poter ciclare)
        // https://book.cakephp.org/5.x/orm/retrieving-data-and-resultsets.html#using-finders-to-load-data
        $query->disableHydration();
        $offices = $query->all();

        $rows = [];
        $pscl_headers = [];
        $pscl_rows = [];
        foreach ($offices as $office) {
            $row = [];
            $pscl_row = [];

            foreach ($exportFields as $f) {
                //Uso la funzione HASH per avere una sintassi flat dei campi
                //https://book.cakephp.org/5.x/core-libraries/hash.html#hash-get
                $row[] = Hash::get($office, $f) ?? '';
            }

            //Inserisco le misure del pscl, che hanno una struttura diversa
            //Solo la prima volta inserisco negli header le misure del pscl
            //Attenzione a non confondere il campo della tabella pscl con il
            //campo legacy PSCL (Maiuscolo) che non usiamo più
            if (isset($office['pscl']) && $office['pscl'] && is_array($office['pscl']) && isset($office['pscl'][0]['plan']) && is_array($office['pscl'][0]['plan'])) {
                //Inserisco la data di adozione del piano se presente (fa schifo @massimoi)
                if ($office['pscl'][0]['plan'][0]['data_adozione'] ?? null) {
                    $row[14] = $office['pscl'][0]['plan'][0]['data_adozione'];
                }
                foreach ($office['pscl'][0]['plan'] as $pillar) {
                    if (is_array($pillar)) {
                        foreach ($pillar as $measure) {
                            if (isset($measure['pillar_id'])) {
                                $pscl_row[$measure['measure_id']] = $this->mappa_stato_attuazione((int)($measure['value'] ?? 0));
                                $pscl_headers[$measure['measure_id']] = $measure['measure_slug'];
                            }
                        }
                    }
                }
            }

            $rows[] = $row;
            $pscl_rows[] = $pscl_row;
        }

        $headers = array_merge($exportFields, array_values($pscl_headers));

        //Ora devo aggiungere ad ogni row i valori di $pscl_row corrispondendi a $pscl_headers
        for ($i = 0; $i < count($rows); $i++) {
            if (count($pscl_rows[$i]) > 0) {
                foreach ($pscl_headers as $measure_id => $measure_slug) {
                    $rows[$i][] = $pscl_rows[$i][$measure_id] ?? '';
                }
            }
        }

        $this->set(compact('headers', 'rows'));
        $this->viewBuilder()->setOption('serialize', ['headers', 'rows']);
    }
}
