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

use Exception;

/**
 * Companies Controller
 */
class CompanyTypesController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->Authorization->skipAuthorization();
        $this->loadComponent('Security');

        $this->Security->setConfig('unlockedActions', ['edit', 'add']);
    }

    public function beforeFilter($event)
    {
        parent::beforeFilter($event);
    }

    /**
     * Index method
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        $this->allowRolesOnly(['admin', 'moma', 'moma_area']);

        $company_types = $this->CompanyTypes->find()->toArray();
        $this->set(compact('company_types'));
        $this->viewBuilder()->setOption('serialize', ['company_types']);
    }

    public function add()
    {
        $this->allowRolesOnly(['admin', 'moma' , 'moma_area']);

        $identity = $this->Authentication->getIdentity();
        $role = $identity->get('role');
        $userId = $identity->get('id');
        $company_id = $identity->get('company_id');
        $isAdmin = $role == 'admin';
        $isMomaArea = $role == 'moma' && empty($company_id);

        if (!($isAdmin || $isMomaArea)) {
            throw new \Exception('You are not allowed to access this location');
        }

        if ($this->request->is(['patch', 'post', 'put'])) {
            $data = $this->request->getData();
            $companyType = $this->CompanyTypes->newEntity($data);
            if (!$this->CompanyTypes->save($companyType)) {
                throw new Exception('The company type could not be saved. Please, try again.');
            }
            $this->set(compact('companyType'));
            $this->viewBuilder()->setOption('serialize', ['companyType']);
        }
    }
}
