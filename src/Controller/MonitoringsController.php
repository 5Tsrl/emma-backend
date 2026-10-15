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

use Cake\Http\Exception\InternalErrorException;
use Cake\Utility\Text;

/**
 * Monitorings Controller
 *
 * @property \App\Model\Table\MonitoringsTable $Monitorings
 * @method \App\Model\Entity\Monitoring[]|\Cake\Datasource\ResultSetInterface paginate($object = null, array $settings = [])
 */
class MonitoringsController extends AppController
{
    /**
     * Index method
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        $monitorings = [];
        if ($this->request->is('json')) {
            $monitorings = $this->Monitorings->find();
            $conditions = [];

            $measure_id = $this->request->getQuery('measure_id');
            if (!empty($measure_id)) {
                $conditions['measure_id'] = $measure_id;
            }
            $pscl_id = $this->request->getQuery('pscl_id');
            if (!empty($pscl_id)) {
                $conditions['pscl_id'] = $pscl_id;
            }
            if (!empty($conditions)) {
                $monitorings = $monitorings->where($conditions);
            }
        }

        $this->set(compact('monitorings'));
        $this->viewBuilder()->setOption('serialize', ['monitorings']);
    }

    /**
     * View method
     *
     * @param string|null $id Monitoring id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $monitoring = $this->Monitorings->get($id, [
            'contain' => ['Measures', 'Offices'],
        ]);

        $this->set(compact('monitoring'));
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */

    // Type Comment
    // id   int Auto Increment
    // name varchar(45)
    // monitoring_date  datetime NULL
    // created  datetime NULL
    // modified datetime NULL
    // measure_id   int NULL
    // office_id    int NULL
    // values

    public function add($year = null)
    {
        $monitoring = $this->Monitorings->newEmptyEntity();
        if ($this->request->is('post')) {
            $dt = $this->request->getData();
            $monitoring->measure_id = $dt['measure_id'];
            if ($year == 'TUTTI') {
                $monitoring->office_id = $dt['office_id'];
                //$monitoring->survey_id = $dt['survey_id'];
            }
            $monitoring->pscl_id = $dt['pscl_id'];
            $monitoring->objective = $dt['objective'];
            // if($year != null){
            //     $monitoring->year = (int)$year;
            // }

            $monitoring->name = $dt['name'];
            $monitoring->dt = $dt['dt'];
            unset($dt['name']);
            unset($dt['dt']);
            unset($dt['office_id']);
            unset($dt['measure_id']);
            unset($dt['survey_id']);
            unset($dt['objective']);

            $monitoring->jvalues = $dt;

            if ($this->Monitorings->save($monitoring)) {
                if (!$this->request->is('json')) {
                    $this->Flash->success(__('The monitoring has been saved.'));

                    return $this->redirect(['action' => 'index']);
                } else {
                    $msg = 'Monitoraggio salvate coorrettamente';
                    $this->set(compact('msg'));
                    $this->viewBuilder()->setOption('serialize', ['msg']);
                }
            } else {
                if (!$this->request->is('json')) {
                    $this->Flash->error(__('The monitoring could not be saved. Please, try again.'));
                } else {
                    $msg = 'Impossibile salvare';
                    throw new InternalErrorException($msg);
                }
            }
        }
        if (!$this->request->is('json')) {
            $measures = $this->Monitorings->Measures->find('list', ['limit' => 200]);
            $offices = $this->Monitorings->Offices->find('list', [
                //'limit' => 200,
                'keyField' => 'id',
                'valueField' => function ($office) {
                    if (isset($office->company)) {
                        $r = "{$office->company->name}-  {$office->name}";
                    } else {
                        $r = $office->name;
                    }

                    return Text::truncate($r, 50);
                },
            ])->contain(['Companies' => ['fields' => ['id', 'name']]]);
            $this->set(compact('measures', 'offices'));
            $this->viewBuilder()->setOption('serialize', ['measures', 'offices']);
        }

        $this->set(compact('monitoring'));
        $this->viewBuilder()->setOption('serialize', ['monitoring']);
    }

    /**
     * Edit method
     *
     * @param string|null $id Monitoring id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit($id = null, $year = null)
    {
        if ($year != null && $year != 'TUTTI') {
            $monitoring = $this->Monitorings->find('all')->where(['id' => $id])->first();
            if ($monitoring == null) {
                $monitoring = $this->Monitorings->get($id, [
                    'contain' => [],
                ]);
                // $monitoring = $this->Monitorings->find('all')->where(['id' => $id])->first();
            }
        } else {
            $monitoring = $this->Monitorings->get($id, [
                'contain' => [],
            ]);
        }

        if ($this->request->is(['patch', 'post', 'put'])) {
            $dt = $this->request->getData();
            $monitoring->name = $dt['name'];
            $monitoring->dt = $dt['dt'];
            // $monitoring->year = (int)$year;
            if ($monitoring->pscl_id == null) {
                $monitoring->pscl_id = $dt['pscl_id'];
            }

            unset($dt['name']);
            unset($dt['dt']);
            unset($dt['id']);
            // adding remaining field to json field
            $monitoring->jvalues = $dt;
            // $monitoring_data =$monitoring->toArray();
            // $monitoring_new= $this->Monitorings->newEmptyEntity();
            // $monitoring = $this->Monitorings->patchEntity($monitoring_new,$monitoring_data );


            if ($this->Monitorings->save($monitoring)) {
                if (!$this->request->is('json')) {
                    $this->Flash->success(__('The monitoring has been saved.'));

                    return $this->redirect(['action' => 'index']);
                } else {
                    $msg = 'Monitoraggio salvate coorrettamente';
                    $this->set(Compact('msg'));
                    $this->viewBuilder()->setOption('serialize', ['msg']);
                }
            } else {
                if (!$this->request->is('json')) {
                    $this->Flash->error(__('The monitoring could not be saved. Please, try again.'));
                } else {
                    $msg = 'Impossibile salvare';
                    throw new InternalErrorException($msg);
                }
            }
        }
        if (!$this->request->is('json')) {
            $measures = $this->Monitorings->Measures->find('list', ['limit' => 200]);
            $offices = $this->Monitorings->Offices->find('list', ['limit' => 200]);
            $this->set(compact('measures', 'offices'));
            $this->viewBuilder()->setOption('serialize', ['measures', 'offices']);
        }

        $this->set(compact('monitoring'));
        $this->viewBuilder()->setOption('serialize', ['monitoring']);
    }

    /**
     * Delete method
     *
     * @param string|null $id Monitoring id.
     * @return \Cake\Http\Response|null|void Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $monitoring = $this->Monitorings->get($id);

        if ($this->Monitorings->delete($monitoring)) {
            if (!$this->request->is('json')) {
                $this->Flash->success(__('The monitoring has been deleted.'));

                return $this->redirect(['action' => 'index']);
            } else {
                $msg = 'Monitoring deleted';
                $this->set(compact('msg'));
                $this->viewBuilder()->setOption('serialize', ['msg']);
            }
        } else {
            if (!$this->request->is('json')) {
                $this->Flash->error(__('The monitoring could not be deleted. Please, try again.'));
            } else {
                $msg = 'Monitoring not deleted';
                throw new InternalErrorException($msg);
            }
        }
        if (!$this->request->is('json')) {
            return $this->redirect(['action' => 'index']);
        }
    }
}
