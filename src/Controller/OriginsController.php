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
use Exception;

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
            'lat IS NOT' => null,
            'lon IS NOT' => null,
        ];

        $origins = $this->Origins->find()
            ->select([
                'id', // mi serve come chiave per v-for
                'lon',
                'lat',
                'user_id',
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
        if (!(empty($y) || $y == 'TUTTI')) {
            $origins->matching('Users', function ($q) use ($y) {
                return $q->where(function ($exp) {
                    return $exp->add('JSON_CONTAINS(Users.years, :year)');
                });
            });
            $origins->bind(':year', json_encode($y), 'string');
        }else{
            $origins->matching('Users');
        }
        //print_r($origins->toArray()); die;
        // echo $origins;
        //Se mi hanno chiesto excel aggiungo dei campi
        if ($this->request->getParam('_ext') == 'xls') {
            $origins->select([
                'Origins.id', 'Origins.address', 'Origins.postal_code', 'Origins.city', 'Origins.province',
                'Origins.modified',
                'Companies.name', 'Companies.id', 'Companies.company_code', 'Companies.city', 'Companies.province',
                'Companies.type',
                'Employees.gender', 'Employees.dob', 'Employees.shift', 'Employees.office_id', 'Employees.role_description',
                'Users.email', 'Users.id',
            ])
                ->contain(['Companies', 'Users' => 'Employees']);
        }
        // print_r($origins->sql()); die;
        // sql($origins);
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
        $answ_usr_ids = [];

        if (!empty($filters)) {
            $this->loadModel('Questions');
            $this->loadModel('Answers');
            foreach ($filters as $flt => $answ) {
                $filter_name = str_replace('_', '-', str_replace('filter_', '', $flt));
                $question = $this->Questions->find()
                                ->where(['name' => $filter_name])
                                ->select(['id'])
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
                            if ($answ == $a->answer) {
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

        //sqld($origins);
        // debug($origins); die;
        return $origins;
    }

    public function resetGeocoding($company_id)
    {
        // // // delete origins that user_id not exist in table users
        // $this->Origins->deleteAll([
        //     'company_id' => $company_id,
        //     'NOT EXISTS (SELECT 1 FROM users WHERE users.id = user_id)',
        // ]);
        // // delete employess that user_id not exist in table users
        // $employees=$this->Origins->Employees->find()->matching('Users', function ($q) use ($company_id) {
        //     return $q->where(['Users.company_id' => $company_id]);
        // });
        // find origins that user_id not exist in table users
        // $origins = $this->Origins->find()->where([
        //     'company_id' => $company_id,
        //     'NOT EXISTS (SELECT 1 FROM users WHERE users.id = user_id)',
        // ])->toArray();
        $this->Origins->updateAll(
            ['lat' => null, 'lon' => null],
            ['company_id' => $company_id]
        );

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

        $this->loadModel('Surveys');
        $this->loadModel('Answers');
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

        if (empty($answers)) {
            $origin_question_ids = Configure::read('Origins.origin_question_ids_map');
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
            if (!isset($originsData[$user_id])) {
                if ($map_question) {
                    $a_map = json_decode($a['answer'], true);
                    $originsData[$user_id] = [
                    'user_id' => $user_id, // devo obbligatoriamente valorizzarlo, diversamente non potrei filtrare le origini in base alle risposte al questionario
                    'survey_id' => $survey_id,
                    'company_id' => $survey['company_id'],
                    'address' => $a_map['origin']['address'],
                    'postal_code' => $a_map['origin']['postal_code'],
                    'province' => $a_map['origin']['province'],
                    'city' => $a_map['origin']['city'],
                    'lat' => $a_map['origin']['lat'],
                    'lon' => $a_map['origin']['lon'],
                    ];
                } else {
                    $originsData[$user_id] = [
                        'user_id' => $user_id, // devo obbligatoriamente valorizzarlo, diversamente non potrei filtrare le origini in base alle risposte al questionario
                        'survey_id' => $survey_id,
                        'company_id' => $survey['company_id'],
                        'address' => '',
                        'postal_code' => '',
                        'city' => '',
                    ];
                }
            }
            $field = $origin_question_ids[$a['question_id']]['field'];
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
            $originsData[$user_id][$field] = $formatter($a['answer']);
        }

        $originsData = array_values($originsData);
        $origins = $this->Origins->newEntities($originsData);

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
}
