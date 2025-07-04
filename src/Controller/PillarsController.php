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
 * Pillars Controller
 *
 * @property \App\Model\Table\PillarsTable $Pillars
 * @method \App\Model\Entity\Pillar[]|\Cake\Datasource\ResultSetInterface paginate($object = null, array $settings = [])
 */
class PillarsController extends AppController
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
            $pillars = $this->paginate($this->Pillars);
        } else {
            $pillars = $this->Pillars->find();
        }
        $this->set(compact('pillars'));
        $this->viewBuilder()->setOption('serialize', ['pillars']);
    }

    /**
     * View method
     *
     * @param string|null $id Pillar id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $pillar = $this->Pillars->get($id, [
            'contain' => ['Measures'],
        ]);

        $this->set(compact('pillar'));
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $pillar = $this->Pillars->newEmptyEntity();
        if ($this->request->is('post')) {
            $pillar = $this->Pillars->patchEntity($pillar, $this->request->getData());
            if ($this->Pillars->save($pillar)) {
                $this->Flash->success(__('The pillar has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The pillar could not be saved. Please, try again.'));
        }
        $this->set(compact('pillar'));
    }

    /**
     * Edit method
     *
     * @param string|null $id Pillar id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $pillar = $this->Pillars->get($id, [
            'contain' => [],
        ]);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $pillar = $this->Pillars->patchEntity($pillar, $this->request->getData());
            if ($this->Pillars->save($pillar)) {
                $this->Flash->success(__('The pillar has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The pillar could not be saved. Please, try again.'));
        }
        $this->set(compact('pillar'));
    }

    /**
     * Delete method
     *
     * @param string|null $id Pillar id.
     * @return \Cake\Http\Response|null|void Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $pillar = $this->Pillars->get($id);
        if ($this->Pillars->delete($pillar)) {
            $this->Flash->success(__('The pillar has been deleted.'));
        } else {
            $this->Flash->error(__('The pillar could not be deleted. Please, try again.'));
        }

        return $this->redirect(['action' => 'index']);
    }
}
