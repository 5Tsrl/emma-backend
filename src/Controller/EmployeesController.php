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

/**
 * Employees Controller
 *
 * @property \Moma\Model\Table\EmployeesTable $Employees
 * @method \Moma\Model\Entity\Employee[]|\Cake\Datasource\ResultSetInterface paginate($object = null, array $settings = [])
 */
class EmployeesController extends AppController
{
    /**
     * Index method
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        $this->allowRolesOnly(['admin', 'moma']);

        $identity = $this->Authentication->getIdentity();
        $role = $identity->get('role');
        $company_id = $identity->get('company_id');
        $isMomaAzienda = $role == 'moma' && !empty($company_id);

        $conditions = [];
        if ($isMomaAzienda) {
            // faccio vedere solo gli impiegati della sua azienda
            $this->loadModel('Offices');
            $conditions = ['office_id IN' => $this->Offices->getIdsByCompanyId($company_id)];
        }

        $this->paginate = [
            'contain' => [
                'Users' => ['fields' => ['id','email']],
                'Offices' => ['fields' => ['id','name'],
                                'Companies' =>  ['fields' => ['id','name']] ],
                'Origins' => ['fields' => ['id','address', 'postal_code','city','province','lat','lon']],
            ],
            'conditions' => $conditions,
        ];
        $employees = $this->paginate($this->Employees);
        $pagination = $this->Paginator->getPagingParams();

        $this->set(compact('employees', 'pagination'));
        $this->viewBuilder()->setOption('serialize', ['employees','pagination']);
    }

    /**
     * View method
     *
     * @param string|null $id Employee id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $this->allowRolesOnly(['admin', 'moma']);

        $identity = $this->Authentication->getIdentity();
        $role = $identity->get('role');
        $company_id = $identity->get('company_id');
        $isMomaAzienda = $role == 'moma' && !empty($company_id);

        $conditions = [];
        if ($isMomaAzienda) {
            // faccio vedere solo gli impiegati della sua azienda
            $this->loadModel('Offices');
            $conditions = ['office_id IN' => $this->Offices->getIdsByCompanyId($company_id)];
        }

        $employee = $this->Employees->get($id, [
            'contain' => ['Users', 'Offices'],
            'conditions' => $conditions,
        ]);

        $this->set(compact('employee'));
    }

    /**
     * Add method
     *
     * NON USATO DA NESSUN PARTE
     */
    public function add()
    {
        $this->allowRolesOnly(['admin']);

        $employee = $this->Employees->newEmptyEntity();
        if ($this->request->is('post')) {
            $employee = $this->Employees->patchEntity($employee, $this->request->getData());
            if ($this->Employees->save($employee)) {
                $this->Flash->success(__('The employee has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The employee could not be saved. Please, try again.'));
        }
        $users = $this->Employees->Users->find('list', ['limit' => 200]);
        $offices = $this->Employees->Offices->find('list', ['limit' => 200]);
        $this->set(compact('employee', 'users', 'offices'));
    }

    /**
     * Edit method
     *
     *  NON USATO DA NESSUN PARTE
     */
    public function edit($id = null)
    {
        $this->allowRolesOnly(['admin']);

        $employee = $this->Employees->get($id, [
            'contain' => ['Users'],
        ]);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $employee = $this->Employees->patchEntity($employee, $this->request->getData());
            if ($this->Employees->save($employee)) {
                $this->Flash->success(__('The employee has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The employee could not be saved. Please, try again.'));
        }
        $users = $this->Employees->Users->find('list', ['limit' => 200]);
        $offices = $this->Employees->Offices->find('list', ['limit' => 200]);
        $this->set(compact('employee', 'users', 'offices'));
        $this->viewBuilder()->setOption('serialize', ['employee']);
    }

    /**
     * Delete method
     *
     *  NON USATO DA NESSUN PARTE
     */
    public function delete($id = null)
    {
        $this->allowRolesOnly(['admin']);

        $this->request->allowMethod(['post', 'delete']);
        $employee = $this->Employees->get($id);
        if ($this->Employees->delete($employee)) {
            $this->Flash->success(__('The employee has been deleted.'));
        } else {
            $this->Flash->error(__('The employee could not be deleted. Please, try again.'));
        }

        return $this->redirect(['action' => 'index']);
    }

    /*public function importFromFile()
    {
      if ($this->request->is(['post'])) {
        $companyId = $this->request->getData('companyId');
        $file = $this->request->getData('file');
        $fname = $file['tmp_name'];
        $error = $file['error'];
        if ($error != 0) {
          throw new Error(__('Errore nell\'apertura del file.'));
        }

        set_time_limit(240);
        ini_set('memory_limit', '3G');
        $this->Employees->importExcel($fname, $companyId);
        $this->set('success', true);
        $this->viewBuilder()->setOption('serialize', ['success']);
      }
    }*/

    // entry point per la procedura di importazione impiegati
    // attenzione: verificare che non vengano cancellati gli utenti con ruolo moma o admin

    public function deleteAll()
    {
        if ($this->request->is(['post'])) {
            $company_id = $this->request->getData('company_id');
            $year = $this->request->getData('year');
            $this->allowWhoCanSeeCompanyOnly($company_id);

            $errorMsg = false;
            try {
                $this->loadModel('Offices');
                $this->loadModel('Origins');
                $this->loadModel('Users');
                // $this->loadModel('SurveyParticipants');
                // $subquery = $this->SurveyParticipants->find()->select('user_id');
                // 1. Find the latest batch
                $latestBatch = $this->Users->Batches->find()
                ->select(['created'])
                ->where(['user_id' => $this->Authentication->getIdentity()->get('id')])
                ->order(['created' => 'DESC'])
                ->first();
                if (!$latestBatch) {
                    throw new \Exception('Nessun batch trovato per l\'utente corrente.');
                }
                // 2. Use the latest batch to filter users

                $users = $this->Users->find()
                        ->notMatching(
                            'SurveyParticipants', function ($q) {
                                return $q;
                            },
                        )
                        ->Matching('Batches', function ($q) use ($latestBatch) {
                            // Filter users by the latest batch created date
                            return $q->where([
                                'Batches.created' => $latestBatch->created,
                            ]);
                        })
                        ->where([
                            'role' => 'user',
                            'company_id' => $company_id,
                            // 'years IS NOT' => null,
                        ]);
                if (!(empty($year) || $year == 'TUTTI')) {
                    $users = $users->where(function ($exp) {
                        return $exp->add('JSON_CONTAINS(years, :year)');
                    });
                    $users = $users->bind(':year', '"'.$year.'"');
                }
                $count = $users->count();
                // if ($count == 0) {
                //     throw new \Exception('Nessun utente da cancellare');
                // }
                $users->limit(1000); // Limit to 1000 users to avoid memory issues
                $users = $users->select(['id','years'])
                ->toArray();
                if (empty($users)) {
                    $count = 0;
                    $totalDeletedEmployees = 0;
                    $totalDeletedOrigins = 0;
                    $totalDeletedUsers = 0;
                    $totalOldUsers = 0;
                    $errorMsg = false;
                    $this->set(compact('errorMsg', 'count', 'totalDeletedEmployees', 'totalDeletedOrigins', 'totalDeletedUsers', 'totalOldUsers'));
                    $this->viewBuilder()->setOption('serialize', ['errorMsg', 'count', 'totalDeletedEmployees', 'totalDeletedOrigins', 'totalDeletedUsers', 'totalOldUsers']);
                    return;

                }
                // is an array of more than one element
                $usersManyearsid = array_map(function ($user) {
                    // if array has more years
                    if (is_array($user->years) && count($user->years) > 1) {
                        return $user->id;
                    }
                }, $users);
                // remove null values from array
                $usersManyearsid = array_filter($usersManyearsid);
                // remove year from usersManyears
                foreach ($usersManyearsid as $key => $value) {
                    $user = $this->Users->get($value);
                    // remove year from array
                    $user->years = array_values(array_diff($user->years, [$year]));
                    // print_r($user->years);
                    // die;
                    $this->Users->save($user);
                }

                // $offices = array_map(function ($office) {
                //     return $office['id'];
                // }, $this->Offices->find()->where([
                //     'company_id' => $company_id,
                // ])->toArray());
                $offices = $this->Offices->find()->select('id')->where([
                    'company_id' => $company_id,
                ])->toArray();

                if (!empty($offices)) {
                    // Convert offices to an array of IDs
                    $officeIds = array_map(function ($office) {
                        return $office->id;
                    }, $offices);
                    $userIds = array_map(function ($user) {
                        return $user->id;
                    }, $users);
                    // $employees = $this->Employees->find()
                    //     ->where([
                    //         'office_id IN' => $offices,
                    //     ])->toArray();
                    // $employees = $this->Employees->find()
                    //     ->where([
                    //         'user_id IN' => $users,
                    //     ])->toArray();

                    // 1. cancella gli impiegati
                    // $res_em=$this->Employees->deleteAll([
                    //     'office_id IN' => $offices,
                    //     'user_id IN' => $users,
                    // ]);
                    // Break down the delete operation into smaller batches
                    $batchSize = 1000; // Adjust batch size as needed
                    $totalDeletedEmployees = 0;

                    // Fetch employees in batches
                    // do {
                    $employees = $this->Employees->find()
                        ->where([
                            'office_id IN' => $officeIds,
                            'user_id IN' => $userIds,
                        ])
                        ->limit($batchSize)
                        ->toArray();

                    if (!empty($employees)) {
                        $employeeIds = array_map(function ($employee) {
                            return $employee->id;
                        }, $employees);

                        // Delete employees in the current batch
                        $res_em = $this->Employees->deleteAll([
                            'id IN' => $employeeIds,
                        ]);

                        $totalDeletedEmployees += $res_em;
                    }
                    // } while (!empty($employees));

                    // echo "Total employees deleted: " . $totalDeleted;
                }
                // $employeesCheck = $this->Employees->find()
                //     ->where([
                //         'office_id IN' => $offices,
                //     ])->toArray();


                // 2. cancella le origin ad essi collegate
                $totalDeletedOrigins = 0;
                 // Fetch ORIGINS in batches
                // do {
                $origins = $this->Origins->find()
                    ->where([
                        'company_id' => $company_id,
                        'survey_id IS' => null, // this identifies if the origin is related to employee or to an answer to a survey
                        'user_id IN' => $userIds,
                    ])
                    ->limit($batchSize)
                    ->toArray();

                if (!empty($origins)) {
                    $originIds = array_map(function ($origin) {
                        return $origin->id;
                    }, $origins);

                    // Delete origins in the current batch
                    $res_origins = $this->Origins->deleteAll([
                        'id IN' => $originIds,
                    ]);

                    $totalDeletedOrigins += $res_origins;
                }
                // } while (!empty($origins));
                // echo "Total origins deleted: " . $totalDeleted;
                // $this->Origins->deleteAll([
                //   'company_id' => $company_id,
                //   'survey_id IS' => null, // this identifies if the origin is related to employee or to an answer to a survey
                //   'user_id IN' => $users,
                // ]);

                // 3. cancella gli utenti ad essi collegati: NON posso cancellare indistintamente tutti gli
                // utenti collegati all'azienda perchè alcuni potrebbero essere stati generati con procedura ad hoc
                // dal questionario (scollegati dagli impiegati)
                // Cerco gli users che non hanno associato un questionario compilato
                //Elenco di utenti che hanno un invito per partecipare ad una survey o hanno già partecipare,
                //Questi non vanno mai cancellati!

                $totalDeletedUsers = 0;
                $batchSize = 1000;
                 // Fetch ORIGINS in batches
                // do {
                $users = $this->Users->find()
                        ->notMatching(
                            'SurveyParticipants', function ($q) {
                                return $q;
                            },
                        )
                        ->Matching('Batches', function ($q) use ($latestBatch) {
                            return $q->where([
                                'Batches.created' => $latestBatch->created,
                            ]);
                        })
                        ->where([
                            'Users.role' => 'user',
                            'Users.company_id' => $company_id,
                            // 'Users.year' => date('Y'),
                        ]);
                if (!(empty($year) || $year == 'TUTTI')) {
                    $users = $users->where(function ($exp) {
                        return $exp->add('JSON_CONTAINS(years, :year)');
                    });
                    $users = $users->bind(':year', '"'.$year.'"');
                }
                $users = $users->limit($batchSize)
                    ->toArray();

                if (!empty($users)) {
                    $userIds = array_map(function ($user) {
                        return $user->id;
                    }, $users);

                    // Delete users in the current batch using delete (to trigger callbacks)
                    $res_users = 0;
                    foreach ($userIds as $userId) {
                        $user = $this->Users->find('all', [
                            'conditions' => ['id' => $userId],
                        ])->contain('Batches')->first();
                        // delete batch row
                        $this->Users->Batches->delete($user->batches[0]);
                        // Check if the user exists before attempting to delete
                        if ($this->Users->delete($user)) {
                            $res_users++;
                        }
                    }

                    $totalDeletedUsers += $res_users;
                }
                // remove year from old users
                $totalOldUsers = 0;
                $oldUsers = $this->Users->find()
                        ->notMatching(
                            'SurveyParticipants', function ($q) {
                                return $q;
                            },
                        )
                        ->where([
                            'Users.role' => 'user',
                            'Users.company_id' => $company_id,
                            // 'Users.year' => date('Y'),
                        ]);
                if (!(empty($year) || $year == 'TUTTI')) {
                    $oldUsers = $oldUsers->where(function ($exp) {
                        return $exp->add('JSON_CONTAINS(years, :year)');
                    });
                    $oldUsers = $oldUsers->bind(':year', '"'.$year.'"');
                }
                $oldUsers = $oldUsers->select(['id','years'])->limit($batchSize)
                    ->toArray();
                foreach ($oldUsers as $key => $value) {
                    $user = $this->Users->get($value['id']);
                    // remove year from array
                    $user->years = array_values(array_diff($user->years, [$year]));
                    $this->Users->save($user);
                    $totalOldUsers++;
                }
                if($totalDeletedEmployees < 1000 && $totalDeletedOrigins < 1000 && $totalDeletedUsers < 1000) {
                    $count = 0;
                    }
                //     else {
                //     $count = 1000;
                // }
            } catch (\Exception $e) {
                $errorMsg = $e->getMessage();
            }
            
            $this->set(compact('errorMsg', 'count', 'totalDeletedEmployees', 'totalDeletedOrigins', 'totalDeletedUsers', 'totalOldUsers'));
            $this->viewBuilder()->setOption('serialize', ['errorMsg', 'count', 'totalDeletedEmployees', 'totalDeletedOrigins', 'totalDeletedUsers', 'totalOldUsers']);
            // $this->set('errorMsg', $errorMsg);
            // $this->viewBuilder()->setOption('serialize', ['errorMsg']);
        }
    }

    // invocato in loop dal frontend.
    // Prevede OBBLIGATORIAMENTE l'invocazione di deleteAll() prima che il loop venga eseguito!

    public function import($company_id, $year)
    {
        $this->allowRolesOnly(['admin']);
        $this->allowWhoCanSeeCompanyOnly($company_id);
        $identity = $this->Authentication->getIdentity();


        if ($this->request->is(['post'])) {
            $employee = $this->request->getData();
            $errorMsg = false;
            try {
                $this->Employees->import($employee, $company_id, $identity, $year);
            } catch (\Exception $e) {
                $errorMsg = $e->getMessage();
            }
            $this->set('errorMsg', $errorMsg);
            $this->viewBuilder()->setOption('serialize', ['errorMsg']);
        }
    }

    public function originFilters($company_id)
    {
        $this->allowRolesOnly(['admin', 'moma','moma_area']);

        // TODO alcuni filtri dovrebbero dipendere dall'azienda selezionata ma per il momento è troppo complicato
        $roles = array_map(function ($r) {
            return $r['role_description'];
        }, $this->Employees->find()
            ->matching('Users', function ($q) use ($company_id) {
                    return $q->where(['Users.company_id' => $company_id]);
                    })
            ->select('role_description')
            ->distinct('role_description')
            ->where(['role_description IS NOT' => null])
            ->orderAsc('role_description')
            ->toArray());
        $orari = array_map(function ($r) {
            return $r['orario'];
        }, $this->Employees->find()
            ->matching('Users', function ($q) use ($company_id) {
                return $q->where(['Users.company_id' => $company_id]);
                })
            ->select('orario')
            ->distinct('orario')
            ->orderAsc('orario')
            ->where(['orario IS NOT' => null])
            ->toArray());
        $gender = array_map(function ($r) {
            return $r['gender'];
        }, $this->Employees->find()
            ->matching('Users', function ($q) use ($company_id) {
                return $q->where(['Users.company_id' => $company_id]);
                })
            ->select('gender')
            ->distinct('gender')
            ->orderAsc('gender')
            ->where(['gender IS NOT' => null])
            ->toArray());
        $cap = array_map(function ($r) {
            return $r['postal_code'];
        }, $this->Employees->Origins->find()
            ->select('postal_code'
            )->distinct('postal_code')
            ->orderAsc('postal_code')
            ->where(['postal_code IS NOT' => null,'postal_code IS NOT' => '', 'company_id' => $company_id])
            ->toArray());

        $filters = [
            [
                'name' => 'ruolo',
                'options' => $roles,
            ],
            [
                'name' => 'cap',
                'options' => $cap,
            ],
            [
                'name' => 'orario',
                'options' => $orari,
            ],
            [
                'name' => 'sesso',
                'options' => $gender,
            ],
            [
                'name' => 'fascia-di-età',
                'options' => [
                    '18 - 25',
                    '25 - 35',
                    '35 - 45',
                    '45 - 55',
                    '55 - 65',
                ],
            ],
        ];
        $this->set('filters', $filters);
        $this->viewBuilder()->setOption('serialize', ['filters']);
    }

    public function count()
    {
        $this->allowRolesOnly(['admin', 'moma', 'user', 'moma_area']);

        $count =  $this->Employees->find()->count();
        $this->set(compact('count'));
        $this->viewBuilder()->setOption('serialize', ['count']);
    }

    //https://api.5t.drupalvm.test/employees/export-cap-turni/968.json

    public function exportCapTurni($office_id, $year = '2024')
    {
        $this->allowRolesOnly(['admin', 'moma']);

        $q = $this->Employees->find()
                ->contain(['Origins'])
                ->select(['orario','shift','Origins.postal_code'])
                ->where(['Employees.office_id' => $office_id]);
        if ($year != 'TUTTI') {
                $q = $q->matching('Users', function ($q) use ($year) {
                    return $q->where(
                        function ($exp) {
                            return $exp->add('JSON_CONTAINS(Users.years, :year)');
                        // return $exp->add('Users.years IS NOT NULL');
                        // $conditions[] = $exp->add('JSON_CONTAINS(Users.years, :year)');
                        // return $exp->or_([
                        //     $exp->or_($conditions),
                        //     $exp->isNull('Users.years')
                        // ]);
                        }
                    );
                    // return $q->bind(':year', json_encode($year), 'string');
                });
                $q->bind(':year', json_encode($year), 'string');
        }
        // print_r($q->sql());
        // debug($q);
        // die();
        $q = $q->all();

        $this->set('columns', ['orario','shift','origin.postal_code']);
        $this->set('result', $q);
        $this->viewBuilder()->setOption('serialize', ['result']);
    }

    public function stats($company_id)
    {
        $this->allowRolesOnly(['admin', 'moma']);
        $year = $this->request->getQuery('year');

        //Get all offices for a company
        $offices = $this->Employees->Offices->find()->where(['company_id' => $company_id])->toArray();

        $res = [];
        foreach ($offices as $office) {
            $res[] =
            ['name' => $office->name] +
            ['code' => $office->office_code] +
            ['id' => $office->id] +
             $this->Employees->getStats($office->id, $year);
        }

        $this->set('result', $res);
        $this->viewBuilder()->setOption('serialize', ['result']);
    }
}
