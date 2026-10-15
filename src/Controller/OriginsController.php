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

use App\Model\Entity\Origin;
use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Database\Expression\QueryExpression;
use Cake\Database\Query;
use Cake\Http\Exception\NotAcceptableException;
use Cake\Http\Exception\NotFoundException;
use Cake\ORM\TableRegistry;
use Exception;
use Sk\Geohash\Geohash;

class OriginsController extends AppController
{
    public function beforeFilter($event)
    {
        $this->response->setTypeMap('kml', ['application/vnd.google-earth.kml+xml']);
        parent::beforeFilter($event);

        if ($this->request->getParam('_ext') == 'kml') {
            $this->response = $this->response->withType('kml');
        }
    }

    public function index()
    {
        $this->allowRolesOnly(['admin', 'moma', 'moma_area']);
        $identity = $this->Authentication->getIdentity();

        $source = $this->request->getQuery('source');
        if (empty($source)) {
            $source = 'employees';
        }

        $conditions = [
            'geohash IS NOT' => null,
        ];

        $origins = $this->Origins->find()
            ->select([
                'id', // mi serve come chiave per v-for
                // 'lon', //tolgo lat, lon per essere privacy compliant
                // 'lat',
                'user_id',
                'year',
                'geohash',
            ])
            ->where($conditions);

        //Prima fisso la querystring (o vuoto)
        $company_id = $this->request->getQuery('company_id');
        //Se l'utente ha un'azienda può vedere solo la sua
        if (!empty($identity->get('company_id'))) {
            $company_id = $identity->get('company_id');
        }
        //Se c'è un valore filtro per quello
        if ($company_id) {
            $origins->where(['Origins.company_id' =>  $company_id]);
        }

        //Ora verifico office_id e subcompany
        // $office_id  =  $this->request->getQuery('office_id');
        $office_id  =  $identity->get('office_id');
        $subcompany  =   $identity->get('subcompany');
        if (!empty($office_id)) {
            $origins->matching('Users', function ($q) use ($office_id) {
                return $q->where(['Users.office_id' => $office_id]);
            });
        }
        if (!empty($subcompany)) {
            $origins->matching('Users', function ($q) use ($subcompany) {
                return $q->where(['Users.subcompany' => $subcompany]);
            });
        }

        $y = $this->request->getQuery('year');
        // filter origins by year
        if (!(empty($y) || $y == 'TUTTI')) {
            $origins->where(['Origins.year' => $y]);
        }
        // filter users.years JSON field
        if (!(empty($y) || $y == 'TUTTI')) {
            $origins->matching('Users', function ($q) use ($y) {
                return $q->where(function ($exp) {
                    return $exp->add('JSON_CONTAINS(Users.years, :year)');
                });
            });
            $origins->bind(':year', json_encode($y), 'string');
        } else {
            $origins->matching('Users');
        }
        //print_r($origins->toArray()); die;
        // echo $origins;

        if ($source == 'answers') {
            $origins = $this->originsFromAnswers($origins);
            $survey_id = $this->request->getQuery('survey_id');
            if (!empty($survey_id)) {
                $origins->where(['survey_id' => $survey_id]);
            }
        } elseif ($source == 'employees') { //esplicito per evitare injection
            $origins = $this->originsFromEmployees($origins);
        } else {
            throw new NotAcceptableException(h("Il tipo di sorgente richiesta non esiste: $source"));
        }

        //Se mi hanno chiesto excel aggiungo dei campi
        if ($this->request->getParam('_ext') == 'xls') {
            // Configure contains with specific fields to limit data
            $containConfig = [
                'Companies' => [
                    'fields' => ['Companies.name', 'Companies.id', 'Companies.company_code', 'Companies.city', 'Companies.province', 'Companies.type'],
                ],
                'Users' => [
                    'fields' => ['Users.email', 'Users.id', 'Users.office_id'],
                    'Offices' => [
                        'fields' => [
                            'id',
                            'name',
                            'address',
                            'province',
                            'district',
                            'lat',
                            'lon',
                        ],
                    ],
                ],
            ];

            // Add Employee association for employees source
            if ($source == 'employees') {
                $containConfig['Users']['Employees'] = [
                    'fields' => [
                        'Employees.user_id', // Required foreign key field for association
                        'Employees.gender',
                        'Employees.dob',
                        'Employees.shift',
                        'Employees.office_id',
                        'Employees.role_description',
                    ],
                ];
            }

            // Override any previous contain() calls to ensure we get all required data for Excel
            $origins->contain($containConfig, true); // true parameter overwrites existing contains

            // Include both Origins fields AND associated table fields in select
            $selectFields = [
                'Origins.id',
                //'Origins.address', Tolgo address per essere privacy compliant
                'Origins.postal_code',
                'Origins.city',
                'Origins.province',
                'Origins.district',
                //'Origins.lat',
                //'Origins.lon',
                'Origins.geohash',
                'Origins.modified',
                'Origins.user_id', // Required foreign key field for Users association
                'Origins.company_id', // Required foreign key field for Companies association
                // Company fields
                'Companies.name',
                'Companies.id',
                'Companies.company_code',
                'Companies.city',
                'Companies.province',
                'Companies.type',
                // User fields
                'Users.email',
                'Users.id',
                'Users.office_id',
            ];

            // Note: Don't add Employee fields to select() because they're accessed through Users association
            // CakePHP will handle the nested relationship: Origins -> Users -> Employees

            $origins->select($selectFields);

            $origins_res = $origins->toArray();

            // Convert entities to arrays with proper nested data extraction
            $originsArray = [];
            foreach ($origins as $origin) {
                $originData = $origin->toArray();

                // Extract nested entity data properly
                if (isset($origin->user) && is_object($origin->user)) {
                    $userData = $origin->user->toArray();
                    foreach ($userData as $key => $value) {
                        // Skip nested arrays/objects - they'll be handled separately
                        if (!is_array($value) && !is_object($value)) {
                            $originData['user.' . $key] = $value;
                        }
                    }
                }

                if (isset($origin->company) && is_object($origin->company)) {
                    $companyData = $origin->company->toArray();
                    foreach ($companyData as $key => $value) {
                        // Skip nested arrays/objects
                        if (!is_array($value) && !is_object($value)) {
                            $originData['company.' . $key] = $value;
                        }
                    }
                }

                if (isset($origin->user->office) && is_object($origin->user->office)) {
                    $officeData = $origin->user->office->toArray();
                    foreach ($officeData as $key => $value) {
                        if (!is_array($value) && !is_object($value)) {
                            $originData['office.' . $key] = $value;
                        }
                    }
                }

                // Extract employee data if available
                if (isset($origin->user->employee) && is_object($origin->user->employee)) {
                    $employeeData = $origin->user->employee->toArray();
                    foreach ($employeeData as $key => $value) {
                        $originData['employee.' . $key] = $value;
                    }
                }

                //Add Lat Lon from geohash for backwards compatibility with old excel template, if geohash is present
                if (isset($originData['geohash']) && !empty($originData['geohash'])) {
                    $h = new Geohash();

                    $latlon = $h->decode($originData['geohash']);
                    $originData['lat'] = $latlon[0];
                    $originData['lon'] = $latlon[1];
                } else {
                    $originData['lat'] = null;
                    $originData['lon'] = null;
                }

                $originsArray[] = $originData;
            }

            // Process arrays for PhpSpreadsheet compatibility - process each record individually
            foreach ($originsArray as &$origin) {
                // Only flatten nested arrays and objects within each record, not the record itself
                foreach ($origin as $key => &$value) {
                    if (is_array($value)) {
                        // Convert nested arrays to JSON strings for Excel compatibility
                        $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                    } elseif (is_object($value)) {
                        // Convert objects to strings
                        if (method_exists($value, '__toString')) {
                            $value = (string)$value;
                        } else {
                            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                        }
                    } elseif (is_resource($value)) {
                        $value = 'Resource';
                    } elseif (is_null($value)) {
                        $value = '';
                    }
                }
            }

            $this->set('origins', $originsArray);
            // if VUE_APP_THEME_BRESCIA is true, use Brescia template for excel
            if (Configure::read('VUE_APP_THEME_BRESCIA')) {
                $this->viewBuilder()->setPlugin('Brescia');
            }
            $this->viewBuilder()->setOption('serialize', ['origins']);
        } else {
            // print_r($origins->sql()); die;
            // sql($origins);

            $count = $this->request->getQuery('count');
            if (!empty($count)) {
                $count = $origins->count();
                $this->set('count', $count);
                $this->viewBuilder()->setOption('serialize', 'count');
            } else {
                //sqld($origins);
                $q = md5($_SERVER['QUERY_STRING']);
                $short = "origins-$company_id-$subcompany-$office_id-$source-$y-" . md5(serialize($origins->clause('where')) . "$q");
                $origins_res = Cache::read($short, 'origins');
                $origins_res = null;
                if ($origins_res === null) {
                    // Memory-safety cap: an unfiltered (or accidentally-broad)
                    // origins query on this table can hold 100k+ rows, which
                    // exhausts PHP's memory limit before it can even respond
                    // with an error. Never applies to the count=1 branch
                    // above, which must always report the real total. See
                    // CLAUDE.md "Origins index memory-safety cap".
                    $origins->limit((int)Configure::read('Origins.maxResults'));
                    $origins_res = $origins->toArray();
                    Cache::write($short, $origins_res, 'origins');
                }
                $origins = $origins_res;
                foreach ($origins as $key => $value) {
                    $origs[$key]['color'] = 'red';
                    $origs[$key]['radius'] = 6;
                }
                // debug sql($origins);
                // debug($origins); die;
                $this->set('origins', $origins);

                $this->viewBuilder()->setOption('serialize', ['origins']);
            }
        }
    }

    private function originsFromAnswers($origins)
    {
        $survey_id = $this->request->getQuery('survey_id');
        //$company_id = $this->request->getQuery('company_id');
        $office_ids = $this->request->getQuery('office_id');

        if (!empty($office_ids)) {
            $office_ar = explode(',', $office_ids);
            $origins->matching('Users', function ($q) use ($office_ar) {
                return $q->where(['Users.office_id IN' => $office_ar]);
            });
        }

        if ($survey_id) {
            $origins->where(['survey_id' => $survey_id]);
        } else {
            $origins->where(['survey_id IS NOT' => null]);
        }

        $filters = array_filter(
            $this->request->getQueryParams(),
            fn($flt) => str_starts_with($flt, 'filter'),
            ARRAY_FILTER_USE_KEY
        );
        // filter origins by filter_quartiere and filter_comune from filters
        $quartiere = $filters['filter_quartiere'] ?? null;
        $comune = $filters['filter_comune'] ?? null;
        if ($quartiere) {
            $origins->where(['Origins.district' => $quartiere]);
        }
        if ($comune) {
            $origins->where(['Origins.city' => $comune]);
        }
        // remove filter_quartiere and filter_comune from filters
        unset($filters['filter_quartiere']);
        unset($filters['filter_comune']);
        $answ_usr_ids = [];

        if (!empty($filters)) {
            $this->Questions = TableRegistry::getTableLocator()->get('Questions');
            $this->Answers = TableRegistry::getTableLocator()->get('Answers');
            foreach ($filters as $flt => $answ) {
                $filter_name = str_replace('_', '-', str_replace('filter_', '', $flt));
                $question = $this->Questions->find()
                    ->where(['Questions.name' => $filter_name])
                    ->select(['Questions.id'])
                    ->firstOrFail();
                $answers = $this->Answers->find()
                    ->select(['Answers.user_id', 'Answers.answer'])
                    ->where(['Answers.question_id' => $question->id]);
                if ($survey_id != null) {
                    $answers->where(['Answers.survey_id' => $survey_id]);
                }
                if (count($answ_usr_ids) > 1) {
                    $answers->where(['Answers.user_id IN' => $answ_usr_ids]);
                    $answ_usr_ids = [];
                }

                if (
                    $filter_name == 'costo-spostamento' or $filter_name == 'quale-distanza' or $filter_name == 'quale-distanza-auto' or $filter_name == 'spesa-spostamento'
                    or $filter_name == 'distanza-totale'
                ) {
                    $answ = $this->rangeAnswers($filter_name, $answ);
                    foreach ($answers as $a) {
                        //Altrimenti cerco il valore esatto
                        if ($answ[0] <= $a->answer && $answ[1] >= $a->answer) {
                            $answ_usr_ids[] = $a->user_id;
                        }
                    }
                } else {
                    foreach ($answers as $a) {
                        //Se la risposta è un array cerco un valore dentro l'array
                        if (is_array($a->answer)) {
                            if (in_array($answ, $a->answer)) {
                                $answ_usr_ids[] = $a->user_id;
                            }
                        } else {
                            //Altrimenti cerco il valore esatto
                            if (trim((string)$answ) == trim((string)$a->answer)) {
                                $answ_usr_ids[] = $a->user_id;
                            }
                        }
                    }
                }
            }
            //Se la risposta è vuota inserisco un valore falso per non ritornare nulla
            if (empty($answ_usr_ids)) {
                $answ_usr_ids = [-1];
            }
            $origins->where(['Origins.user_id IN' => $answ_usr_ids]);
        }

        return $origins;
    }

    private function originsFromEmployees($origins)
    {
        $ruolo = $this->request->getQuery('filter_ruolo');
        $orario = $this->request->getQuery('filter_orario');
        $sesso = $this->request->getQuery('filter_sesso');
        $ageRange = $this->request->getQuery('filter_fascia-di-età');
        $office_ids = $this->request->getQuery('office_id');
        $cap = $this->request->getQuery('filter_cap');
        $comune = $this->request->getQuery('filter_comune');
        $quartiere = $this->request->getQuery('filter_quartiere');

        $origins->where(['survey_id IS' => null])
            ->contain(['Employees']);

        if ($ruolo) {
            $origins->where(['Employees.role_description' => $ruolo]);
        }
        if ($orario) {
            $origins->where(['Employees.orario' => $orario]);
        }
        if ($sesso) {
            $origins->where(['Employees.gender' => $sesso]);
        }
        if ($ageRange) {
            $minDate = 0;
            $maxDate = 100;
            switch ($ageRange) {
                case '18 - 25':
                    $minDate = 18;
                    $maxDate = 25;
                    break;
                case '25 - 35':
                    $minDate = 25;
                    $maxDate = 35;
                    break;
                case '35 - 45':
                    $minDate = 35;
                    $maxDate = 45;
                    break;
                case '45 - 55':
                    $minDate = 45;
                    $maxDate = 55;
                    break;
                case '55 - 65':
                    $minDate = 55;
                    $maxDate = 65;
                    break;
            }
            $origins->where(function (QueryExpression $exp, Query $q) use ($minDate, $maxDate) {
                return $exp->between('TIMESTAMPDIFF (YEAR, Employees.dob, CURDATE())', $minDate, $maxDate);
            });
        }

        if (!empty($office_ids)) {
            $office_ar = explode(',', $office_ids);
            $origins->where(['Users.office_id IN' => $office_ar]);
        }
        // filter cap
        if ($cap) {
            $origins->where(['Origins.postal_code' => $cap]);
        }
        // filter comune
        if ($comune) {
            $origins->where(['Origins.city' => $comune]);
        }
        // filter quartiere
        if ($quartiere) {
            $origins->where(['Origins.district' => $quartiere]);
        }

        //sqld($origins);
        // debug($origins); die;
        return $origins;
    }

    public function resetGeocoding($company_id)
    {
        // // delete origins that user_id not exist in table users
        try {
            $this->Origins->deleteAll([
                'company_id' => $company_id,
                'NOT EXISTS (SELECT 1 FROM users WHERE users.id = user_id)',
            ]);

            //Elimino tutte le origin che hanno null address, postal_code, city, province e geohash, in quanto non geocodificabili
            $this->Origins->deleteAll([
                'company_id' => $company_id,
                [
                    ['address IS' => null],
                    ['postal_code IS' => null],
                    ['city IS' => null],
                    ['province IS' => null],
                    ['geohash IS' => null],
                ],
            ]);

            $this->Origins->updateAll(
                //Non voglio più lat/lon ma solo geohash, per essere più privacy compliant, quindi resetto solo quello
                //['lat' => null, 'lon' => null],
                ['geohash' => null],
                ['company_id' => $company_id, 'address IS NOT' => null]
            );
        } catch (Exception $e) {
            $this->set('success', false);
            $this->set('msg', $e->getMessage());
            $this->viewBuilder()->setOption('serialize', ['success', 'msg']);

            return;
        }
        $this->set('success', true);
        $this->viewBuilder()->setOption('serialize', ['success']);
    }

    public function notGeocoded($company_id)
    {
        $this->allowRolesOnly(['admin', 'moma', 'moma_area']);

        $ids = $this->Origins->getAllNotGeocoded($company_id);
        $this->set('ids', $ids);
        $this->viewBuilder()->setOption('serialize', ['ids']);
    }

    public function geocode($originId = null)
    {
        $this->allowRolesOnly(['admin', 'moma', 'moma_area']);

        if ($this->request->is(['post'])) {
            $originId = $this->request->getData('originId');
        }
        if (!empty($originId)) {
            $res = $this->Origins->geocode($originId);
            if ($res) {
                $this->set('success', true);
            } else {
                $this->set('success', "Impossibile geocodificare origin: $originId");
            }

            $this->viewBuilder()->setOption('serialize', ['success']);
        }
    }

    public function missingDistrict($company_id, $city)
    {
        $this->allowRolesOnly(['admin', 'moma', 'moma_area']);

        $ids = $this->Origins->getAllMissingDistrict($company_id, $city);
        $this->set('ids', $ids);
        $this->viewBuilder()->setOption('serialize', ['ids']);
    }

    public function addDistrict($originId = null): void
    {
        $this->allowRolesOnly(['admin', 'moma', 'moma_area']);

        if ($this->request->is(['post'])) {
            $originId = $this->request->getData('originId');
        }
        if (!empty($originId)) {
            $origin = $this->Origins->get($originId);
            $origin->addDistrict();

            if ($origin->district === null) {
                // get user email
                $user = $this->Origins->Users->get($origin->user_id);
                $userEmail = $user ? $user->email : 'unknown';
                $this->set('success', false);
                $msg = "Impossibile trovare il quartiere per l'origin: $originId, {$origin->address}, user email: $userEmail";
                $this->set('msg', $msg);
                // set user email
                $this->set('user_email', $userEmail);
                $this->viewBuilder()->setOption('serialize', ['success', 'msg', 'user_email']);

                return;
                // throw new Exception("Impossibile trovare il quartiere per l'origin: $originId, {$origin->address}, user email: $userEmail");
            }

            if ($this->Origins->save($origin)) {
                $this->set('success', true);
            } else {
                throw new Exception("Impossibile salvare l'origin dopo aver aggiunto il quartiere: $originId, {$origin->address}");
            }

            $this->viewBuilder()->setOption('serialize', ['success']);
        }
    }

    public function importFromAnswers()
    {
        $this->allowRolesOnly(['admin', 'moma', 'moma_area']);

        if ($this->request->is(['post'])) {
            $survey_id = $this->request->getData('survey_id');
            $this->importFromAnswersForSurvey($survey_id);
            $this->set('success', true);
            $this->viewBuilder()->setOption('serialize', ['success']);
        }
    }

    private function importFromAnswersForSurvey($survey_id)
    {
        $this->allowWhoCanSeeSurveyOnly($survey_id);

        $this->Surveys = TableRegistry::getTableLocator()->get('Surveys');
        $this->Answers = TableRegistry::getTableLocator()->get('Answers');
        $survey = $this->Surveys->get($survey_id);
        if (!$survey) {
            throw new Exception("Survey $survey_id not found");
        }

        $origin_map_question = Configure::read('Questions.origine_spostamenti');
        $answers = $this->Answers->find()->select(['question_id', 'answer', 'user_id'])->where([
                'survey_id' => $survey_id,
                'question_id' => $origin_map_question,
            ])->order(['user_id', 'question_id'])->toArray();
        $map_question = true;
        $origin_question_ids = Configure::read('Origins.origin_question_ids_map');
        if (empty($answers)) {
            // la risposta alla domanda legata alla città restituisce un risultato che sembra essere un array json stringified e non
            // una stringa pulita, il che richiede ulteriori elaborazioni prima di poter comporre l'indirizzo completo
            // Per tali motivi elaboro gli indirizzi via php a partire dalle entità Answer

            $answers = $this->Answers->find()->select(['question_id', 'answer', 'user_id'])->where([
                'survey_id' => $survey_id,
                'question_id IN' => array_keys($origin_question_ids),
            ])->order(['user_id', 'question_id'])->toArray();
            $map_question = false;
        }
        // nota: non è detto che ci siano le risposte per tutte le domande!
        $originsData = [];

        foreach ($answers as $a) {
            $user_id = $a['user_id'];
            if ($map_question) {
                // Map branch: a single answer carries the whole origin payload
                // already structured under origin.{address,city,...,lat,lon}.
                // Persist only the non-PII fields plus an encoded geohash; the
                // address column is intentionally not populated on new rows
                // (privacy-by-design — see OriginsTable::beforeSave()).
                if (!isset($originsData[$user_id])) {
                    $a_map = $a['answer'];
                    $row = \App\Geocoder\OriginPayloadHelper::extractOriginRowData($a_map['origin'] ?? null);
                    $row['user_id'] = $user_id; // devo obbligatoriamente valorizzarlo, diversamente non potrei filtrare le origini in base alle risposte al questionario
                    $row['survey_id'] = $survey_id;
                    $row['company_id'] = $survey['company_id'];
                    $originsData[$user_id] = $row;
                }
            } else {
                // Non-map branch: one Answer row per field. Skip legacy
                // `address` field — it is no longer persisted.
                $field = $origin_question_ids[$a['question_id']]['field'];
                if ($field === 'address') {
                    continue;
                }

                if (!isset($originsData[$user_id])) {
                    $originsData[$user_id] = [
                        'user_id' => $user_id, // devo obbligatoriamente valorizzarlo, diversamente non potrei filtrare le origini in base alle risposte al questionario
                        'survey_id' => $survey_id,
                        'company_id' => $survey['company_id'],
                        'postal_code' => '',
                        'city' => '',
                    ];
                }

                $maxlen = 0;
                if (isset($origin_question_ids[$a['question_id']]['maxlen'])) {
                    $maxlen = $origin_question_ids[$a['question_id']]['maxlen'];
                }

                $formatter = function ($s) use ($maxlen) {
                    if (!is_null($maxlen)) {
                        return mb_convert_encoding(substr($s == null ? '' : $s, 0, $maxlen), 'ASCII');
                    }

                    return $s;
                }; // default ('self') formatter

                if ($origin_question_ids[$a['question_id']]['formatter'] == 'json_array') {
                    $formatter = function ($s) use ($maxlen) {
                        //$s = json_decode($s, true); //Massimoi: importante passare true, se no non restituisce Array, ma Obj
                        if (!is_array($s)) {
                            $s = [$s];
                        }

                        $s1 =  implode(' ', $s);

                        return mb_convert_encoding(substr($s1 == null ? '' : $s1, 0, $maxlen), 'ASCII');
                    };
                }
                // Run on every iteration so users with multiple answers get
                // all their fields populated (previously this lived inside
                // the !isset() guard and only the first field was saved).
                $originsData[$user_id][$field] = $formatter($a['answer']);
            }
        }

        // Empty-answers path: don't wipe existing rows for the survey when
        // the questionnaire has no usable answers (e.g. mis-configured
        // origin_question_ids_map). Otherwise a single bad request would
        // silently delete every origin previously imported for the survey.
        if (empty($originsData)) {
            return;
        }

        // Flatten the user-keyed map into a sequential list once, after the
        // loop. Calling array_values() inside the loop (the previous bug)
        // re-indexed by integer mid-iteration and broke the !isset() guard.
        $originsData = array_values($originsData);
        $origins = $this->Origins->newEntities($originsData);

        // Wrap the destructive replace in a transaction so a save failure
        // rolls back the deleteAll() too.
        $this->Origins->getConnection()->transactional(function () use ($origins, $survey_id) {
            // rimpiazza le precedenti
            $this->Origins->deleteAll([
                'survey_id' => $survey_id,
            ]);

            //Faccio il ciclo individuale anche se è più lengto,
            // così riesco a beccare la riga che dà l'errore (nel caso ci fosse qualche valore inaccetabile)
            foreach ($origins as $o) {
                if (!$this->Origins->save($o)) {
                    throw new NotFoundException('Origin: ' . $o->id);
                }
            }
        });
    }

    public function getAnswer($origin_id): void
    {
        $origin = $this->Origins->get($origin_id);
        if (empty($origin)) {
            throw new NotFoundException('Origin non trovata');
        }

        $this->set(compact('origin'));
        $this->viewBuilder()->setOption('serialize', ['origin']);
    }

    public function edit($id = null)
    {
        $this->allowRolesOnly(['admin', 'moma']);

        if (!empty($id)) {
            $origin = $this->Origins->findById($id)->first();
        } else {
            $origin = new Origin();
            $origin->company_id = null;
        }

        if ($this->request->is(['patch', 'post', 'put'])) {
            // $dt = $this->request->getData();
            $origin = $this->Origins->patchEntity($origin, $this->request->getData());

            if ($this->Origins->save($origin)) {
                Cache::clearGroup('origins');
                if (!$this->request->is('json')) {
                    $this->Flash->success(__('The origin has been saved.'));

                    return $this->redirect(['action' => 'index']);
                } else {
                    $msg = 'origin info saved';
                    $this->set(Compact('msg'));
                    $this->viewBuilder()->setOption('serialize', ['msg']);
                }
            } else {
                if (!$this->request->is('json')) {
                    $this->Flash->error(__('origin info cannot be saved. Please, try again.'));
                } else {
                    throw new \Exception("Impossibile salvare l'origine: " . implode($this->validationErrors));
                }
            }
        }

        if (!$this->request->is('json')) {
            $origins = $this->Origins->find('list', ['limit' => 200]);
            $this->set(compact('origins'));
            $this->viewBuilder()->setOption('serialize', ['origins']);
        }

        $this->set(compact('origin'));
        $this->viewBuilder()->setOption('serialize', ['origin']);
    }

    public function rangeAnswers($filter_name, $answ)
    {
        if ($filter_name == 'quale-distanza' || $filter_name == 'quale-distanza-auto' || $filter_name == 'distanza-totale') {
            $rangeMap = [
                '<2 km' => [0, 2],
                '2,1-5 km' => [2, 5],
                '5,1-10 km' => [5, 10],
                '10,1-15 km' => [10, 15],
                '15,1-25 km' => [15, 25],
                '25,1-50 km' => [25, 50],
                '50,1-75 km' => [50, 75],
                '75,1-100 km' => [75, 100],
                '100,1-125 km' => [100, 125],
                '125,1-150 km' => [125, 150],
                '>150 km' => [150, 100000], // Note: 100000 is just a placeholder, you can adjust it accordingly
            ];

            if (isset($rangeMap[$answ])) {
                return $rangeMap[$answ];
            } else {
                return 'Dati non validi';
            }
        } elseif ($filter_name == 'costo-spostamento') {
            $rangeMap = [
                'meno di 30 €/mese' => [0, 30],
                'tra 30 e 50 €/mese' => [30, 50],
                'tra 50 e 100 €/mese' => [50, 100],
                'tra 100 e 150 €/mese' => [100, 150],
                'tra 150 e 200 €/mese' => [150, 200],
                'più di 200 €/mese' => [200, 100000],
            ];

            if (isset($rangeMap[$answ])) {
                return $rangeMap[$answ];
            } else {
                return 'Dati non validi';
            }
        } elseif ($filter_name == 'spesa-spostamento') {
            $rangeMap = [
                'Meno di 30 Euro' => [0, 30],
                'Tra 30 e 50 Euro' => [30, 50],
                'Tra 50 e 80 Euro' => [50, 80],
                'Più di 80 Euro' => [80, 100000],
            ];

            if (isset($rangeMap[$answ])) {
                return $rangeMap[$answ];
            } else {
                return 'Dati non validi';
            }
        }
    }

    // originFilters by survey_id
    public function originFilters($company_id, $survey_id)
    {
        $this->allowRolesOnly(['admin', 'moma', 'moma_area']);
        // read cache first
        $comune = Cache::read("origin_filters_comune_{$company_id}_{$survey_id}", 'hour');
        $district = Cache::read("origin_filters_district_{$company_id}_{$survey_id}", 'hour');
        if ($comune == false && $district == false) {
            // if survey_id is null change where to survey_id IS NULL
            if ($survey_id === 'null') {
                // comune
                $comune = array_map(function ($r) {
                    return $r['city'];
                }, $this->Origins->find()
                    ->select(
                        'city'
                    )->distinct('city')
                    ->orderAsc('city')
                    ->where(['city IS NOT' => null, 'city IS NOT' => '', 'company_id' => $company_id, 'survey_id is not' => null])
                    ->toArray());
                // district
                $district = array_map(function ($r) {
                    return $r['district'];
                }, $this->Origins->find()
                    ->select(
                        'district'
                    )->distinct('district')
                    ->orderAsc('district')
                    ->where(['district IS NOT' => null, 'district IS NOT' => '', 'company_id' => $company_id, 'survey_id is not' => null])
                    ->toArray());
            } else {
                // comune
                $comune = array_map(function ($r) {
                    return $r['city'];
                }, $this->Origins->find()
                    ->select(
                        'city'
                    )->distinct('city')
                    ->orderAsc('city')
                    ->where(['city IS NOT' => null, 'city IS NOT' => '', 'company_id' => $company_id, 'survey_id' => $survey_id])
                    ->toArray());
                // district
                $district = array_map(function ($r) {
                    return $r['district'];
                }, $this->Origins->find()
                    ->select(
                        'district'
                    )->distinct('district')
                    ->orderAsc('district')
                    ->where(['district IS NOT' => null, 'district IS NOT' => '', 'company_id' => $company_id, 'survey_id' => $survey_id])
                    ->toArray());
            }
            // write cache in long
            Cache::write("origin_filters_comune_{$company_id}_{$survey_id}", $comune, 'hour');
            Cache::write("origin_filters_district_{$company_id}_{$survey_id}", $district, 'hour');
        }
        $filters = [
            [
                'name' => 'comune',
                'options' => $comune,
            ],
            ...(empty($district) ? [] : [
                [
                    'name' => 'quartiere',
                    'options' => $district,
                ],
            ]),
        ];
        $this->set('filters', $filters);
        $this->viewBuilder()->setOption('serialize', ['filters']);
    }
}
