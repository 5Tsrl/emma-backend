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
use Cake\Core\Configure;
use Cake\Database\Query;
use Cake\Log\Log;
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
        $this->allowRolesOnly(['admin', 'moma', 'superiori','user', 'moma_area']);

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

        $identity = $this->Authentication->getIdentity();

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

        // debug($query2);
        //TODO: Gestire nuovamente la paginazione
        //$offices = $this->paginate($query);
        // sqld($query);
        $offices = $query->all();
        $this->set(compact('offices'));
        $this->viewBuilder()->setOption('serialize', ['offices']);
    }

    // metodo (pubblico!) per ottenere la lista uffici per la domanda di tipo mappa

    public function forSurvey($survey_id)
    {
        $offices = null;
        $offices = Cache::read("offices-$survey_id");
        if (empty($offices)) {
            $this->loadModel('Surveys');
            $survey = $this->Surveys->find()
                ->where([
                    'id' => $survey_id,
                ])
                ->select(['id', 'company_id'])
                ->first();

            if (!empty($survey)) {
                $company_id = $survey->company_id;
                if (!empty($company_id)) {
                    $offices = $this->Offices->find()->where([
                        'company_id' => $company_id,
                    ])->order('name')->toArray();
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
            }
            Cache::write("offices-$company_id", $offices);
        }
        $this->set(compact('offices'));
        $this->viewBuilder()->setOption('serialize', ['offices']);
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
        $this->allowRolesOnly(['admin', 'moma', 'superiori','moma_area']);

        $office = $this->Offices->get($id, [
            'contain' => ['Companies'],
        ]);

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
                Cache::delete("offices-{$office->company_id}");

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The office could not be saved. Please, try again.'));
        }
        $companies = $this->Offices->Companies->find('list', ['limit' => 200]);
        $this->set(compact('office', 'companies'));
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

            $company_id = empty($dt['company_id']) ? $office['company_id'] : $dt['company_id'];
            $this->allowWhoCanSeeCompanyOnly($company_id);

            $officeSurveySubmitted = false;
            if (!empty($office) && isset($dt['survey']) && json_encode($office->survey) != json_encode($dt['survey'])) {
                $officeSurveySubmitted = true;
            }

            $office = !empty($id) ? $this->Offices->patchEntity($office, $dt) : $this->Offices->newEntity($dt);
            if ($this->Offices->save($office)) {
                if ($officeSurveySubmitted) {
                    // save an entry in history
                    /* $this->loadModel('CompanySurveyHistory');
                    if ($officeSurveySubmitted) {
                        $historyEntry = $this->CompanySurveyHistory->newEntity([
                            'company_id' => $office->company_id,
                            'type' => 'sede',
                            'answer' => $dt['survey'],
                        ]);
                        $this->CompanySurveyHistory->save($historyEntry);
                    }
                    */
                }
                Cache::delete("offices-$company_id");
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
            Cache::delete("offices-{$office->company_id}");
            $this->Flash->success(__('The office has been deleted.'));
        } else {
            $this->Flash->error(__('The office could not be deleted. Please, try again.'));
        }

        // return $this->redirect(['action' => 'index']);
    }

    private function advance($msg)
    {
        $s['msg'] = $msg;
        Log::write('debug', $msg);
    }

    public function import($company_id = null)
    {
        if ($this->request->is('post')) {
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xls();
            $attachment = $this->request->getData('excelfile');
            Log::info('Offices import - received the xls file');
            $name = $attachment['name'];
            $fname = $attachment['tmp_name'];
            $error = $attachment['error'];

            if ($error != 0) {
                Log::info('Offices import - error while uploading the file' . var_export($attachment, true));

                return $this->Flash->error(__('Errore nell\'apertura del file.'));
            }

            $filename = TMP . $name;

            move_uploaded_file($fname, $filename);
            Log::info('Offices import - file moved in correctly');

            $spreadsheet = $reader->load($filename);
            $sheetData = $spreadsheet->getActiveSheet()->toArray();
            //La prima riga contiene i titoli e la scarto
            array_shift($sheetData);

            if (empty($spreadsheet)) {
                return $this->Flash->error(__('Il file importato e vuoto.'));
                Log::info('Offices import - the spreadshit is empty');
            }

            foreach ($sheetData as $row) {
                $office = $this->Offices->newEmptyEntity();
                //Importo le colonne
                $office->office_code = $row[0];
                $office->name = $row[1];
                $office->address = $row[2];
                $office->city = $row[3];
                $office->cap = $row[4];
                $office->province = $row[5];
                $office->num_employees = $row[6];
                $office->company_id = $company_id;

                if (!empty($office->name) && !empty($office->address)) {
                    // dd($officeName);
                    $oldOffice = $this->Offices->find('all', [
                        'conditions' => [
                            'name' => $office->name,
                            'address' => $office->address,
                        ],
                    ])->first();

                    if ($this->Offices->save($office)) {
                        $this->advance("office num_employees save successfull {$office->name}");
                    } else {
                        $this->advance("office num_employees save unsuccessfull {$office->name}");
                    }
                }
            }
        }
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
            if ($force || (!$office->lat || !$office->lon)) {
                $origin = [
                    'address' =>  $office->address,
                    'cap' =>  $office->cap,
                    'city' =>  $office->city,
                    'province' =>  $office->province,
                ];
                try {
                    $result = $office->geocode($origin);
                } catch (Exception $e) {
                    Log::error("Impossibile geocodificare la sede {$office->id}");
                    $msg .= "Impossibile geocodificare la sede {$office->id} \n";
                }

                $office->lat = $result['lat'];
                $office->lon = $result['lon'];

                if (!$office->office_code) {
                    $office->office_code = "SEDE-{$office->id}";
                }

                if (!$this->Offices->save($office)) {
                    Log::error("Impossibile salvare la geocodifica della sede {$office->id}");
                    $msg .= "Impossibile salvare la geocodifica della sede {$office->id} \n";
                }
            }
        }
        $this->set('msg', $msg);
        $this->set('office', $office);
        $this->viewBuilder()->setOption('serialize', ['msg', 'office']);
    }

    public function getCoworkingTypes()
    {
        $this->allowRolesOnly(['admin', 'moma', 'moma_area']);
        $this->set('types', Configure::read('Coworking.types'));
        $this->viewBuilder()->setOption('serialize', ['types']);
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
}
