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
 * Measures Controller
 *
 * @property \App\Model\Table\MeasuresTable $Measures
 * @method \App\Model\Entity\Measure[]|\Cake\Datasource\ResultSetInterface paginate($object = null, array $settings = [])
 */
class MeasuresController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->Authorization->skipAuthorization();
    }

    /**
     * Index method
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        if (!$this->request->is('json')) {
            $this->paginate = [
                'contain' => ['Pillars'],
            ];
            $measures = $this->paginate($this->Measures);
        } else {
            $measures = $this->Measures->find();
        }
        $this->set(compact('measures'));
        $this->viewBuilder()->setOption('serialize', ['measures']);
    }

    /**
     * View method
     *
     * @param string|null $id Measure id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $measure = $this->Measures->get($id, [
            'contain' => ['Pillars'],
        ]);

        $this->set(compact('measure'));
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $pillar_id = $this->request->getQuery('pillar_id');

        $measure = $this->Measures->newEmptyEntity();
        if ($pillar_id) {
            $measure->pillar_id = $pillar_id;
        }
        if ($this->request->is('post')) {
            $measure = $this->Measures->patchEntity($measure, $this->request->getData());
            if ($this->Measures->save($measure)) {
                $this->Flash->success(__('The measure has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The measure could not be saved. Please, try again.'));
        }
        $pillars = $this->Measures->Pillars->find('list', ['limit' => 200]);
        $this->set(compact('measure', 'pillars'));
    }

    /**
     * Edit method
     *
     * @param string|null $id Measure id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $measure = $this->Measures->get($id, [
            'contain' => [],
        ]);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $dt = $this->request->getData();

            $attachment = $this->request->getData('img');
            if ($attachment['error'] == 0) {
                $name = $attachment['name'];
                $fname = $attachment['tmp_name'];
                $dt['img'] = "measures/$name";
                $measure = $this->Measures->patchEntity($measure, $dt);
                move_uploaded_file($fname, WWW_ROOT . '/img/measures/' . $name);
            } else {
                unset($dt['img']);
            }
            $measure = $this->Measures->patchEntity($measure, $dt);

            if ($this->Measures->save($measure)) {
                $this->Flash->success(__('The measure has been saved.'));

                return $this->redirect(['controller' => 'Pillars', 'action' => 'view', $measure->pillar_id]);
            }
            $this->Flash->error(__('The measure could not be saved. Please, try again.'));
        }
        $pillars = $this->Measures->Pillars->find('list', ['limit' => 200]);
        $this->set(compact('measure', 'pillars'));
    }

    /**
     * Delete method
     *
     * @param string|null $id Measure id.
     * @return \Cake\Http\Response|null|void Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $measure = $this->Measures->get($id);
        if ($this->Measures->delete($measure)) {
            $this->Flash->success(__('The measure has been deleted.'));
        } else {
            $this->Flash->error(__('The measure could not be deleted. Please, try again.'));
        }

        return $this->redirect(['action' => 'index']);
    }

    public function getPsclMeasureLabels($id)
    {
        $labels = $this->Measures->get($id)->getPsclLabels($id);
        $this->set(compact('labels'));
        $this->viewBuilder()->setOption('serialize', ['labels']);
    }

    public function getPsclMeasureImpacts($company_id, $office_id, $survey_id, $year)
    {
        $impacts = [];

        $this->loadModel('Offices');

        $this->loadModel('Monitorings');

        $monitorings_query = $this->Monitorings->find();
        // ->where(['Monitorings.office_id' => $office_id]);
        if ($year != 'TUTTI') {
            // $monitorings_query->where(['year' => $year]);
            $monitorings_query->contain(['Pscl'])->matching('Pscl', function ($q) use ($year, $office_id, $company_id) {
                if ($office_id == 'null') {
                    return $q->where(['Pscl.year' => $year, 'Pscl.office_id is null', 'Pscl.company_id' => $company_id]);
                } else {
                    return $q->where(['Pscl.year' => $year, 'Pscl.office_id' => $office_id]);
                }
            });
            // $pscl = $this->reducePSCLToActive($monitorings_query->pscl->toArray());
        } else {
            // where survey_id

            $monitorings_query->where(['Monitorings.office_id' => $office_id, 'pscl_id IS NULL']);
            if ($office_id != null) {
                $office = $this->Offices->get($office_id, [
                    'contain' => ['Companies' => ['CompanyTypes']],
                ]);
                $pscl = $this->reducePSCLToActive($office->PSCL);
            }
        }

        $monitorings = $monitorings_query->toArray();
        $all_series = $monitorings;
        if ($year != 'TUTTI' && !empty($monitorings)) {
            $pscl = $this->reducePSCLToActive($monitorings[0]->pscl->plan);
        }
        $this->loadModel('Pillars');
        $pillars  = $this->Pillars->find()
            ->contain(['Measures']);

        foreach ($pillars as $pillar) {
            foreach ($pillar->measures as $measure) {
                $measure_monitorings = array_filter($all_series, function ($series) use ($measure) {
                    return $series->measure_id === $measure->id;
                });
                if (isset($pscl[$measure->id])) {
                    $tmp = [];
                    foreach ($measure_monitorings as $measure_monitoring) {
                        $values = $measure_monitoring->jvalues;
                        // if ($measure->id==21) {
                        //     print_r($values);
                        //     print_r($measure_monitoring);
                        // }
                        $emissions = $this->Measures->get($measure->id)->calculateImpactPscl($measure->id, $values);

                        if ($emissions == null) {
                            break;
                        }
                        array_unshift($emissions, $measure_monitoring->name);

                        $emissions['name'] = $emissions[0];
                        unset($emissions[0]);

                        array_push($tmp, $emissions);
                    }
                    if (!empty($tmp)) {
                        $impacts[$measure->id] = $tmp;
                    }
                }
            }
        }

        $this->set(compact('impacts'));
        $this->viewBuilder()->setOption('serialize', ['impacts']);
    }

    private function reducePSCLToActive($pscl)
    {
        $result = [];
        array_shift($pscl);
        foreach ($pscl as $ms) {
            foreach ($ms as $m) {
                if (isset($m['pillar_id']) && isset($m['measure_id'])) {
                    if ($m['value']) {
                        $result[$m['measure_id']] = $m;
                    }
                }
            }
        }

        return $result;
    }
}
