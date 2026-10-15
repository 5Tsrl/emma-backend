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

use App\Exception\EmployeeImportRowException;
use Cake\Cache\Cache;
use Cake\ORM\TableRegistry;

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
            $this->Offices = TableRegistry::getTableLocator()->get('Offices');
            $conditions = ['office_id IN' => $this->Offices->getIdsByCompanyId($company_id)];
        }

        $this->paginate = [
            'contain' => [
                'Users' => ['fields' => ['id', 'email']],
                'Offices' => [
                    'fields' => ['id', 'name'],
                    'Companies' =>  ['fields' => ['id', 'name']],
                ],
                'Origins' => ['fields' => ['id', 'address', 'postal_code', 'city', 'province', 'lat', 'lon']],
            ],
            'conditions' => $conditions,
        ];
        $employees = $this->paginate($this->Employees);
        $pagination = $this->Paginator->getPagingParams();

        $this->set(compact('employees', 'pagination'));
        $this->viewBuilder()->setOption('serialize', ['employees', 'pagination']);
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
            $this->Offices = TableRegistry::getTableLocator()->get('Offices');
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
            $asAdmin = $this->request->getData('asAdmin') ?? false;
            $office_id = $this->request->getData('office_id');
            $batchOnly = (bool)($this->request->getData('batchOnly') ?? false);
            $this->allowWhoCanSeeCompanyOnly($company_id);
            $success = true;
            $errorMsg = false;

            if (!empty($office_id)) {
                $this->Offices = TableRegistry::getTableLocator()->get('Offices');
                $office = $this->Offices->find()
                    ->where(['id' => $office_id])
                    ->first();
                if (empty($office) || (int)$office->company_id !== (int)$company_id) {
                    $errorMsg = 'The selected office does not belong to this company';
                    $success = false;
                    $count = 0;
                    $totalDeletedEmployees = 0;
                    $totalDeletedOrigins = 0;
                    $totalDeletedUsers = 0;
                    $totalOldUsers = 0;
                    $this->set(compact('errorMsg', 'count', 'totalDeletedEmployees', 'totalDeletedOrigins', 'totalDeletedUsers', 'totalOldUsers', 'success'));
                    $this->viewBuilder()->setOption('serialize', ['errorMsg', 'count', 'totalDeletedEmployees', 'totalDeletedOrigins', 'totalDeletedUsers', 'totalOldUsers', 'success']);

                    return;
                }
            }
            try {
                $this->Offices = TableRegistry::getTableLocator()->get('Offices');
                $this->Origins = TableRegistry::getTableLocator()->get('Origins');
                $this->Users = TableRegistry::getTableLocator()->get('Users');
                // $this->loadModel('SurveyParticipants');
                // $subquery = $this->SurveyParticipants->find()->select('user_id');
                // 1. Find the latest batch
                $identity = $this->Authentication->getIdentity();
                $isAdmin = $identity->get('role') === 'admin';
                if ($asAdmin) {
                    $isAdmin = true;
                }

                // Admin: collect all batches associated to the company; otherwise use caller's latest batch
                if ($isAdmin) {
                    $companyBatchesQuery = $this->Users->Batches->find()
                        // ->select(['Batches.user_id'])
                        ->matching('Users', function ($q) use ($company_id) {
                            return $q->where(['Users.company_id' => $company_id]);
                        })
                        ->order(['Batches.created' => 'DESC']);
                    if ($batchOnly) {
                        $companyBatchesQuery = $companyBatchesQuery->limit(1);
                    }
                    $companyBatches = $companyBatchesQuery->all();

                    if ($companyBatches->isEmpty()) {
                        throw new \Exception('Nessun dipendenti e caricato per te, né per la tua azienda. Rimuovi il flag "Elimina tutti gli elenchi di Lavoratori importati in precedenza per questa azienda"');
                    }
                    // Use the newest batch as reference for backward compatibility (not used for filtering users when admin)
                    $latestBatch = $companyBatches;
                } else {
                    $latestBatchQuery = $this->Users->Batches->find()
                        // ->select(['Batches.created'])
                        ->where(['user_id' => $identity->get('id')])
                        ->order(['created' => 'DESC']);
                    if ($batchOnly) {
                        $latestBatchQuery = $latestBatchQuery->limit(1);
                    }
                    $latestBatch = $latestBatchQuery->all();
                    if ($latestBatch->isEmpty()) {
                        // throw new \Exception('Nessun batch trovato per l\'utente corrente.');
                        $companyBatches = $this->Users->Batches->find()
                        // ->select(['Batches.user_id'])
                        ->matching('Users', function ($q) use ($company_id) {
                            return $q->where(['Users.company_id' => $company_id]);
                        })
                        ->order(['Batches.created' => 'DESC'])
                        ->all();
                        $success = false;
                        $countBatches = $companyBatches->count();
                        if ($countBatches == 0) {
                            $errorMsg = 'Nessun dipendenti e caricato per te, né per la tua azienda. Rmuovi il flag "Elimina tutti gli elenchi di Lavoratori importati in precedenza per questa azienda"';
                        } else {
                            $errorMsg = 'Nessun dipendenti e caricato per te, ma ci sono ' . $countBatches . ' dipendenti caricati per la tua azienda.';
                        }
                        $this->set(compact('errorMsg', 'countBatches', 'success'));
                        $this->viewBuilder()->setOption('serialize', ['errorMsg', 'countBatches', 'success']);

                        return;
                    }
                }
                // 2. Use batches to filter users

                $usersConditions = [
                    'role' => 'user',
                    'company_id' => $company_id,
                    // 'years IS NOT' => null,
                ];
                if (!empty($office_id)) {
                    $usersConditions['office_id'] = $office_id;
                }
                $users = $this->Users->find()
                    ->notMatching(
                        'SurveyParticipants',
                        function ($q) {
                            return $q;
                        },
                    )
                    ->where($usersConditions);

                // Non-admin: restrict to the latest batches of the caller
                    $batchUserIds = $latestBatch->extract('user_id')->toArray();
                    $users = $users->Matching('Batches', function ($q) use ($batchUserIds) {
                        return $q->where([
                            'Batches.user_id IN' => $batchUserIds,
                        ]);
                    });

                // Only admin can delete users across all years; non-admin must specify a year
                if ($year == 'TUTTI' && !$isAdmin) {
                    throw new \Exception('Solo gli amministratori possono eliminare utenti di tutti gli anni.');
                }
                if (!(empty($year) || $year == 'TUTTI' || $year == 'VUOTI')) {
                    $users = $users->where(function ($exp) {
                        return $exp->add('JSON_CONTAINS(years, :year)');
                    });
                    $users = $users->bind(':year', '"' . $year . '"');
                } elseif ($year == 'VUOTI') {
                    // users with empty years array or null
                    $users = $users->where(function ($exp) {
                        return $exp->add('(JSON_LENGTH(years) = 0 OR years IS NULL OR JSON_CONTAINS(years, \'"null"\'))');
                    });
                }
                $count = $users->count();
                // if ($count == 0) {
                //     throw new \Exception('Nessun utente da cancellare');
                // }
                $users->limit(1000); // Limit to 1000 users to avoid memory issues
                $users = $users->select(['id', 'years'])
                    ->toArray();
                if (empty($users)) {
                    $count = 0;
                    $totalDeletedEmployees = 0;
                    $totalDeletedOrigins = 0;
                    $totalDeletedUsers = 0;
                    $totalOldUsers = 0;
                    $errorMsg = false;
                    $success = false;
                    $this->set(compact('errorMsg', 'count', 'totalDeletedEmployees', 'totalDeletedOrigins', 'totalDeletedUsers', 'totalOldUsers', 'success'));
                    $this->viewBuilder()->setOption('serialize', ['errorMsg', 'count', 'totalDeletedEmployees', 'totalDeletedOrigins', 'totalDeletedUsers', 'totalOldUsers', 'success']);

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
                if (!empty($office_id)) {
                    $officeIds = [(int)$office_id];
                } else {
                    $offices = $this->Offices->find()->select('id')->where([
                        'company_id' => $company_id,
                    ])->toArray();
                    // Convert offices to an array of IDs
                    $officeIds = array_map(function ($office) {
                        return $office->id;
                    }, $offices);
                }

                if (!empty($officeIds)) {
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
                $originsQuery = $this->Origins->find()
                    ->where([
                        'company_id' => $company_id,
                        'survey_id IS' => null, // this identifies if the origin is related to employee or to an answer to a survey
                        'user_id IN' => $userIds,
                    ]);

                // Only filter by year if not 'TUTTI'
                if (!(empty($year) || $year == 'TUTTI' || $year == 'VUOTI')) {
                    $originsQuery = $originsQuery->where(['year' => $year]);
                } elseif ($year == 'VUOTI') {
                    // origins with empty year
                    $originsQuery = $originsQuery->where(function ($exp) {
                        return $exp->add('year IS NULL OR year = \'\'');
                    });
                }

                $origins = $originsQuery->limit($batchSize)->toArray();

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
                $usersConditions2 = [
                    'Users.role' => 'user',
                    'Users.company_id' => $company_id,
                    // 'Users.year' => date('Y'),
                ];
                if (!empty($office_id)) {
                    $usersConditions2['Users.office_id'] = $office_id;
                }
                $users = $this->Users->find()
                    ->notMatching(
                        'SurveyParticipants',
                        function ($q) {
                            return $q;
                        },
                    )
                    ->where($usersConditions2);
                $batchUserIds = $latestBatch->extract('user_id')->toArray();
                $users = $users->Matching('Batches', function ($q) use ($batchUserIds) {
                    return $q->where([
                        'Batches.user_id IN' => $batchUserIds,
                    ]);
                });

                // Only admin can delete users across all years; non-admin must specify a year
                if ($year == 'TUTTI' && !$isAdmin) {
                    throw new \Exception('Solo gli amministratori possono eliminare utenti di tutti gli anni.');
                }
                if (!(empty($year) || $year == 'TUTTI' || $year == 'VUOTI')) {
                    $users = $users->where(function ($exp) {
                        return $exp->add('JSON_CONTAINS(years, :year)');
                    });
                    $users = $users->bind(':year', '"' . $year . '"');
                } elseif ($year == 'VUOTI') {
                    // users with empty years array or null
                    $users = $users->where(function ($exp) {
                        return $exp->add('(JSON_LENGTH(years) = 0 OR years IS NULL OR JSON_CONTAINS(years, \'"null"\'))');
                    });
                }
                // // check sql and die
                // dd($users);
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
                        // delete batch rows
                        if (!empty($user->batches)) {
                            foreach ($user->batches as $batch) {
                                $this->Users->Batches->delete($batch);
                            }
                        }
                        // Check if the user exists before attempting to delete
                        if ($this->Users->delete($user)) {
                            $res_users++;
                        }
                    }

                    $totalDeletedUsers += $res_users;
                }
                // remove year from old users
                $totalOldUsers = 0;
                $batchUserIds = $latestBatch->extract('user_id')->toArray();
                $oldUsers = $this->Users->find()
                    ->notMatching(
                        'SurveyParticipants',
                        function ($q) {
                            return $q;
                        },
                    )
                    ->notMatching('Batches', function ($q) use ($batchUserIds) {
                        return $q->where([
                            'Batches.user_id IN' => $batchUserIds,
                        ]);
                    })
                    ->where($usersConditions2);
                // Only admin can update users across all years; non-admin must specify a year
                if ($year == 'TUTTI' && !$isAdmin) {
                    throw new \Exception('Solo gli amministratori possono eliminare utenti di tutti gli anni.');
                }
                if (!(empty($year) || $year == 'TUTTI' || $year == 'VUOTI')) {
                    $oldUsers = $oldUsers->where(function ($exp) {
                        return $exp->add('JSON_CONTAINS(years, :year)');
                    });
                    $oldUsers = $oldUsers->bind(':year', '"' . $year . '"');
                } elseif ($year == 'VUOTI') {
                    // users with empty years array or null
                    $oldUsers = $oldUsers->where(function ($exp) {
                        return $exp->add('(JSON_LENGTH(years) = 0 OR years IS NULL OR JSON_CONTAINS(years, \'"null"\'))');
                    });
                }
                // // query and die
                // debug($oldUsers); die;
                $oldUsers = $oldUsers->select(['id', 'years'])->limit($batchSize)
                    ->toArray();
                foreach ($oldUsers as $key => $value) {
                    $user = $this->Users->get($value['id']);
                    // remove year from array
                    $user->years = array_values(array_diff($user->years, [$year]));
                    $this->Users->save($user);
                    $totalOldUsers++;
                }
                if ($totalDeletedEmployees < 1000 && $totalDeletedOrigins < 1000 && $totalDeletedUsers < 1000) {
                    $count = 0;
                }
                //     else {
                //     $count = 1000;
                // }
            } catch (\Exception $e) {
                $success = false;
                $errorMsg = $e->getMessage();
                $count = 0;
                $totalDeletedEmployees = 0;
                $totalDeletedOrigins = 0;
                $totalDeletedUsers = 0;
                $totalOldUsers = 0;
            }

            $this->set(compact('errorMsg', 'count', 'totalDeletedEmployees', 'totalDeletedOrigins', 'totalDeletedUsers', 'totalOldUsers', 'success'));
            $this->viewBuilder()->setOption('serialize', ['errorMsg', 'count', 'totalDeletedEmployees', 'totalDeletedOrigins', 'totalDeletedUsers', 'totalOldUsers', 'success']);
            // $this->set('errorMsg', $errorMsg);
            // $this->viewBuilder()->setOption('serialize', ['errorMsg']);
        }
    }

    // invocato in loop dal frontend.
    // Prevede OBBLIGATORIAMENTE l'invocazione di deleteAll() prima che il loop venga eseguito!

    public function import($company_id, $year)
    {
        $this->allowRolesOnly(['admin', 'moma', 'moma_area']);
        $this->allowWhoCanSeeCompanyOnly($company_id);
        $identity = $this->Authentication->getIdentity();

        if ($this->request->is(['post'])) {
            $employee = $this->request->getData();
            $errorMsg = false;
            // One entry per failed field on the rejected row: [{field, message}, ...].
            // Empty on success. `field` is the dot-path field name from
            // EmployeeImportFields (e.g. "employees.dob", "origins.province"),
            // `message` is the Italian, human-readable validation message.
            $errors = [];
            try {
                $this->Employees->import($employee, $company_id, $identity, $year);
            } catch (EmployeeImportRowException $e) {
                $errors = $e->getErrors();
                $errorMsg = $e->getMessage();
            } catch (\Exception $e) {
                $errorMsg = $e->getMessage();
                $errors = [['field' => null, 'message' => $e->getMessage()]];
            }
            $this->set(compact('errorMsg', 'errors'));
            $this->viewBuilder()->setOption('serialize', ['errorMsg', 'errors']);
        }
    }

    public function originFilters($company_id)
    {
        $this->allowRolesOnly(['admin', 'moma', 'moma_area']);

        // TODO alcuni filtri dovrebbero dipendere dall'azienda selezionata ma per il momento è troppo complicato
        // read cache
        $cached = Cache::read('employee_origin_filters_' . $company_id, 'hour');
        if (!$cached) {
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
                ->select(
                    'postal_code'
                )->distinct('postal_code')
                ->orderAsc('postal_code')
                ->where(['postal_code IS NOT' => null, 'postal_code IS NOT' => '', 'company_id' => $company_id])
                ->toArray());
            // comune
            $comune = array_map(function ($r) {
                return $r['city'];
            }, $this->Employees->Origins->find()
                ->select(
                    'city'
                )->distinct('city')
                ->orderAsc('city')
                ->where(['city IS NOT' => null, 'city IS NOT' => '', 'company_id' => $company_id, 'survey_id IS' => null])
                ->toArray());
            // district
            $district = array_map(function ($r) {
                return $r['district'];
            }, $this->Employees->Origins->find()
                ->select(
                    'district'
                )->distinct('district')
                ->orderAsc('district')
                ->where(['district IS NOT' => null, 'district IS NOT' => '', 'company_id' => $company_id, 'survey_id IS' => null])
                ->toArray());
            // cache results
            Cache::write('employee_origin_filters_' . $company_id, [
                'roles' => $roles,
                'orari' => $orari,
                'gender' => $gender,
                'cap' => $cap,
                'comune' => $comune,
                'district' => $district,
            ], 'hour');
        } else {
            $roles = $cached['roles'];
            $orari = $cached['orari'];
            $gender = $cached['gender'];
            $cap = $cached['cap'];
            $comune = $cached['comune'];
            $district = $cached['district'];
        }
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
            ->contain([
                'Origins',
                'Offices' => [
                    'Companies' => ['fields' => ['id', 'name']],
                    'fields' => ['id', 'name'],
                ]])
            ->select([  'orario',
                        'shift',
                        'Origins.postal_code',
                        'Origins.city',
                        'Origins.province',
                        'Origins.district',
                        'Offices.name',
                        'Companies.name',
                    ])
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
        //$q = $q->toArray();
        $q = $q->all();

        $this->set('columns', [
            'orario',
            'shift',
            'origin.postal_code',
            'origin.city',
            'origin.province',
            'origin.district',
            'office.name',
            'office.company.name',
        ]);
        $this->set('result', $q);
        $this->viewBuilder()->setOption('serialize', ['result']);
    }

    public function stats($company_id)
    {
        $this->allowRolesOnly(['admin', 'moma']);
        $year = $this->request->getQuery('year');

        $cacheKey = "stats_{$company_id}_" . ($year !== null && $year !== '' ? $year : 'all');
        $res = Cache::remember($cacheKey, function () use ($company_id, $year) {
            $offices = $this->Employees->Offices->find()
                ->select(['id', 'name', 'office_code'])
                ->where(['company_id' => $company_id])
                ->toArray();

            $officeIds = array_map(fn($o) => (int)$o->id, $offices);
            $stats = $this->Employees->getStatsBatch($officeIds, $year);

            $out = [];
            foreach ($offices as $office) {
                $oid = (int)$office->id;
                $out[] =
                    ['name' => $office->name] +
                    ['code' => $office->office_code] +
                    ['id' => $office->id] +
                    ($stats[$oid] ?? $this->Employees->emptyStats());
            }

            return $out;
        }, 'employee_stats');

        $this->set('result', $res);
        $this->viewBuilder()->setOption('serialize', ['result']);
    }
}
