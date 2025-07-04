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

use App\Indicator\emissionA;
use App\Indicator\emissionB;
use App\Indicator\emissionC;
use App\Indicator\emissionD;
use Cake\Cache\Cache;
use Exception;

/**
 * Companies Controller
 *
 * @property \App\Model\Table\CompaniesTable $Companies
 * @method \App\Model\Entity\Company[]|\Cake\Datasource\ResultSetInterface paginate($object = null, array $settings = [])
 */
class CompaniesController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->loadComponent('Security');
        $this->loadComponent('Paginator');
    }

    public function beforeFilter($event)
    {
        parent::beforeFilter($event);

        $this->Security->setConfig('unlockedActions', ['edit', 'add', 'emissions','delete']);
    }

    public function emissionImpacts($company_id = null)
    {
        $q = $this->Companies->find()
            ->select(['emissions', 'id'])
            ->where(['id' => $company_id])
            ->firstOrFail();

        $emissioni = $q->emissions;

        $emissions_result = [
            'a' => $this->getEmissionA($emissioni),
            'b' => $this->getEmissionB($emissioni),
            'c' => $this->getEmissionC($emissioni),
            'd' => $this->getEmissionD($emissioni),
        ];

        $this->set(compact(['emissions_result']));
        $this->viewBuilder()->setOption('serialize', ['emissions_result']);
    }

    public function emissions($company_id = null)
    {
        $this->allowRolesOnly(['admin', 'moma', 'moma_area']);
        $d = $this->request->getData();

        $q = $this->Companies->find()
            ->select(['emissions', 'id'])
            ->where(['id' => $company_id])
            ->firstOrFail();

        $emissioni = $q->emissions;

        if ($this->request->is(['get'])) {
            $this->set(compact(['emissioni']));
            $this->viewBuilder()->setOption('serialize', ['emissioni']);

            return;
        }
        $this->autoRender = false;
        if ($this->request->is(['post'])) {
            $q->emissions = $d;
            if (!$this->Companies->save($q)) {
                throw new Exception('Impossibile salvare le emissioni');
            }

            return;
        }
    }

    private function emptyEmissionData()
    {
        $emission_types = ['a', 'b', 'c', 'd'];
    }

    private function getEmissionA($emissioni)
    {
        $emissionA = new emissionA(
            floatval($emissioni['a']['gg']),
            floatval($emissioni['a']['riduzione_utenti_auto']),
            floatval($emissioni['a']['riduzione_km_auto'])
        );

        return $emissionA->getOutput();
    }

    private function getEmissionB($emissioni)
    {
        $emissionB = new emissionB(
            floatval($emissioni['b']['gg']),
            floatval($emissioni['b']['riduzione_km_auto']),
            floatval($emissioni['b']['ut']),
            0,
            floatval($emissioni['b']['noleggi_gg'])
        );

        return $emissionB->getOutput();
    }

    private function getEmissionC($emissioni)
    {
        $emissionC = new emissionC(
            floatval($emissioni['c']['gg']),
            floatval($emissioni['c']['km_da_sostituire']),
            floatval($emissioni['c']['km_nuovi']),
            floatval($emissioni['c']['fc_auto_nuovi'])
        );

        return $emissionC->getOutput();
    }

    private function getEmissionD($emissioni)
    {
        $emissionD = new emissionD(
            floatval($emissioni['d']['gg']),
            floatval($emissioni['d']['az_nr_dipendenti']),
            floatval($emissioni['d']['az_distanza_spostamenti']),
            floatval($emissioni['d']['az_spostamenti_auto'])
        );

        return $emissionD->getOutput();
    }

    /**
     * Index method
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        $query = $this->Companies->find();

        $identity = $this->Authentication->getIdentity();
        //$myRole = $identity->get('role');
        //L'utente moma può vedere solo la sua azienda
        $company_id = $identity->get('company_id');
        if (!empty($company_id)) {
            $query->where(['id' =>  $company_id]);
        }

        $companies = $this->paginate($query);

        $this->set(compact('companies'));
        $this->viewBuilder()->setOption('serialize', ['companies']);
    }

    public function list()
    {
        $query = $this->Companies->find()
            ->join([
                'table' => 'offices',
                'alias' => 'Offices',
                'type' => 'LEFT',
                'conditions' => 'Offices.company_id = Companies.id',
            ])
            ->contain(['CompanyTypes']);
        $query->where(['Companies.name IS NOT' => null]);
        $query->select([
            'Companies.id', 'Companies.name', 'Companies.city', 'Companies.province',
            'Companies.type', 'CompanyTypes.name', 'CompanyTypes.icon', 'Companies.num_employees',
            'Companies.years',
            'offices_num' => $query->func()->sum('Offices.num_employees'),
        ])
            ->group([
                'Offices.company_id', 'Companies.id', 'Companies.name', 'Companies.city', 'Companies.province',
                'Companies.type', 'CompanyTypes.name', 'CompanyTypes.icon', 'Companies.num_employees',
            ])
            ->order(['Companies.name']);
        // sqld($query);
        $q = $this->request->getQuery('q');
        $t = $this->request->getQuery('type');
        $year = $this->request->getQuery('year');
        if (!empty($q)) {
            $query->where(['Companies.name LIKE' => "%$q%"]);
        }
        if (!empty($t)) {
            $query->where(['Companies.type' => $t]);
        }
        if (!(empty($year) || $year == 'TUTTI')) {
            $query->where(function ($exp) {
                return $exp->add('(JSON_CONTAINS(Companies.years, :year) OR Companies.years IS NULL OR JSON_LENGTH(Companies.years) = 0)');
            });
            $query->bind(':year', json_encode($year), 'string');
        }

        $identity = $this->Authentication->getIdentity();
        if ($identity == null) {
            throw new \Cake\Http\Exception\UnauthorizedException('Utente non autorizzato');
        }

        // //If the user is moma-area can only see if province and its area
        // if($identity->role=="moma_area"){
        //     $query->where([
        //         'Offices.province' => 'TO',
        //     ]);

        // }else{
        //     //$myRole = $identity->get('role');
        //     //L'utente moma può vedere solo la sua azienda
        //     $company_id = $identity->get('company_id');
        //     if (!empty($company_id)) {
        //         $query->where(['Companies.id' =>  $company_id]);
        //     }
        // }
        // debug($query->select(['Offices.name','Companies.id'])->where(['Companies.id'=>1892])->toarray());
        $this->Authorization->applyScope($query);

        // debug($query);
        $companies = $query->all();
        $this->set('companies', $companies);
        $this->viewBuilder()->setOption('serialize', ['companies']);
    }

    /**
     * View method
     *
     * @param string|null $id Company id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $identity = $this->Authentication->getIdentity();
        $company_id = $identity->get('company_id');
        $office_id  =   $identity->get('office_id');

        //Se l'utente ha company_id settato deve vedere solo la sua company
        //Se l'utente ha office_id settato devo vedere solo office_id
        $company = $this->Companies->get($id, [
            //'contain' => ['Surveys', 'Offices'],
            'contain' => ['Surveys'],
        ]);
        $this->Authorization->authorize($company);
        // $this->Authorization->applyScope($company);
        // //L'utente moma può vedere solo la sua azienda
        // if (!empty($company_id) && $company_id != $company['id']) {
        //     throw new NotFoundException();
        // }
        //TODO: ripulire questa chiamata che è ridondante, ma bisogna vedere
        //tutti i punti in cui siamo invocati.
        $this->loadModel('Offices');
        $offices = $this->Offices
            ->find()->order(['name']);
        $this->Authorization->applyScope($offices);
        $offices->where(['company_id' => $id]);

        // if ($office_id) {
        //     $offices->where(['id' => $office_id]);
        // }

        $offices->toArray();
        $this->set(compact('company', 'offices'));
        $this->viewBuilder()->setOption('serialize', ['company', 'offices']);
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    /*public function add()
    {
      $company = $this->Companies->newEmptyEntity();
      if ($this->request->is('post')) {
        $company = $this->Companies->patchEntity($company, $this->request->getData());
        if ($this->Companies->save($company)) {
          $this->Flash->success(__('The company has been saved.'));

          $this->set(compact('company'));
          $this->viewBuilder()->setOption('serialize', ['company']);
          if (!$this->getRequest()->is('json')) {
            return $this->redirect(['action' => 'index']);
          }
        }
        $this->Flash->error(__('The company could not be saved. Please, try again.'));
      }

      $momas = $this->Companies->Users->find('list', ['keyField' => 'id', 'valueField' => 'username', 'limit' => 200]);
      $companyTypes = Company::companyTypes();
      $this->set(compact('company', 'companyTypes', 'momas'));
    }*/

    /**
     * Edit method
     *
     * @param string|null $id Company id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $this->allowRolesOnly(['admin', 'moma', 'moma_area']);

        $identity = $this->Authentication->getIdentity();
        $role = $identity->get('role');
        $userId = $identity->get('id');
        $company_id = $identity->get('company_id');
        $isAdmin = $role == 'admin';
        $isMomaArea = $role == 'moma' && empty($company_id);
        $isMomaAzienda = $role == 'moma' && !empty($company_id);

        $company = null;
        if (!empty($id)) {
            $company = $this->Companies->get($id, [
                'contain' => ['Offices'],
            ]);
            if (!empty($company) && $isMomaAzienda) {
                if ($company['id'] != $company_id) {
                    throw new \Exception('Non sei autorizzato a procedere');
                }
            }
        } else { // crea azienda, possono farlo solo admin e moma manager generale
            if (!($isAdmin || $isMomaArea)) {
                throw new \Exception('Non sei autorizzato a creare aziende');
            }
        }

        if ($this->request->is(['patch', 'post', 'put'])) {
            $data = $this->request->getData();

            // solo per creazione nuova azienda creo anche sede principale
            if (empty($id)) {
                $data['offices'][] = [];
                $data['offices'][0]['name'] = 'Sede Principale';
                $data['offices'][0]['office_code'] = 'SEDE';
                $company = $this->Companies->newEntity($data);
            } else {
                $company = $this->Companies->patchEntity($company, $data);
            }

            //Non voglio salvare ogni volta le survey, rischio duplicati
            unset($company->surveys);

            if (!$this->Companies->save($company)) {
                throw new Exception('The company could not be saved. Please, try again.');
            }

            if (empty($id)) { // solo per creazione nuova azienda
                $this->loadModel('Surveys');
                // gestisci il template di questionario scelto
                if (empty($data['public_survey_template']) || $data['public_survey_template'] == -1) {
                    $this->Surveys->createNewEmptySurvey($company);
                } else {
                    $this->Surveys->createNewSurveyFromTemplate($company, $data['public_survey_template']);
                }
            }

            //TODO: Gestire in maniera esplicita la storia delle versioni, non ad ogni salvataggio
           /*  // save an entry in history
            $this->loadModel('CompanySurveyHistory');
            $historyEntry = $this->CompanySurveyHistory->newEntity([
                'company_id' => $company->id,
                'type' => 'generale',
                'answer' => $data['survey'],
            ]);
            $this->CompanySurveyHistory->save($historyEntry); */
        }
        $this->loadModel('Offices');
        $offices = $this->Offices
            ->find()
            ->where(['company_id' => $company->id])
            ->toArray();
        $this->set(compact('company', 'offices'));
        $this->viewBuilder()->setOption('serialize', ['company', 'offices']);
    }

    /**
     * Delete method
     *
     * @param string|null $id Company id.
     * @return \Cake\Http\Response|null|void Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $identity = $this->Authentication->getIdentity();
        $role = $identity->get('role');
        $company_id = $identity->get('company_id');
        $isAdmin = $role == 'admin';
        $isMomaArea = $role == 'moma_area';

        if (!($isAdmin || $isMomaArea)) {
            throw new \Exception('Non sei autorizzato a procedere');
        }

        $this->request->allowMethod(['post', 'delete']);
        $company = $this->Companies->get($id);
        $surveys = $this->Companies->Surveys->find()->select(['id'])->where(['company_id' => $id])->extract('id')->toList();
        try {
            $this->Companies->getConnection()->begin();
            if ($this->Companies->delete($company)) {
                $this->Flash->success(__('The company has been deleted.'));
                //devo eliminare anche i questionari, le risposte, le domande el questionario, gli utenti, i partecipanti
                $this->Companies->Surveys->deleteAll(['company_id' => $id]);
                $this->loadModel('QuestionsSurveys');
                if (count($surveys)) {
                    $this->QuestionsSurveys->deleteAll(['survey_id IN' => $surveys]);
                    $this->Companies->Surveys->SurveyParticipants->deleteAll(['survey_id IN' => $surveys]);
                }
                $this->Companies->Users->deleteAll(['company_id' => $id]);
            } else {
                $this->Flash->error(__('The company could not be deleted. Please, try again.'));
            }
            $this->Companies->getConnection()->commit();
        } catch (\Cake\ORM\Exception\PersistenceFailedException $e) {
            $this->Companies->getConnection()->rollback();
        }

        $this->autoRender = false;
        //return $this->redirect(['action' => 'index']);
    }

    public function count()
    {
        $this->Authorization->skipAuthorization();
        $count =  $this->Companies->find()->count();
        $this->set(compact('count'));
        $this->viewBuilder()->setOption('serialize', ['count']);
    }

    public function getType($company_id)
    {
        $c = $this->Companies->get($company_id, ['contain' => 'CompanyTypes']);
        if (!empty($c)) {
            $data = $c->company_type;
        }

        $this->set(compact('data'));
        $this->viewBuilder()->setOption('serialize', ['data']);
    }

    public function exportList()
    {
        $identity = $this->Authentication->getIdentity();
        if ($identity == null) {
            throw new \Cake\Http\Exception\UnauthorizedException('Utente non autorizzato');
        }
        $user_id = $identity->get('id');
        $q = $this->request->getQuery('q');
        $t = $this->request->getQuery('type');
        $year = $this->request->getQuery('year');
        if (empty($q) && empty($t)) {
            $companies = Cache::read("companies-$user_id", 'long');
        }
        if (!empty($companies)) {
            $this->set('companies', $companies);
            $this->viewBuilder()->setOption('serialize', ['companies']);

            return;
        }

        //Non ho una risposta in cache, devo generala
        $query = $this->Companies->find()
            ->contain([
                'CompanyTypes',
                'Offices' => ['fields' => [
                        'id',
                        'name',
                        'lat',
                        'lon',
                        'company_id',
                        'num_employees',
                    ]],
                'Surveys' => ['fields' => [
                    'id',
                    'name',
                    'company_id',
                    'year',
                ],
                'conditions' => function ($exp, $query) use ($year) {
                    return $exp->eq('Surveys.year', $year);
                }
                ],
                ]);
        // contain users where user is moma
        $query->contain('Users', function ($q) {
            return $q
            ->select(['id','first_name','last_name','username','company_id','role'])
            ->where(['Users.role in' => ['moma', 'moma_bloccato']]);
        });

        $query->select([
            'Companies.id', 'Companies.name', 'Companies.city', 'Companies.province',
            'Companies.type', 'CompanyTypes.name', 'CompanyTypes.icon', 'Companies.num_employees',
        ]);
        //     ->group([
        //         'Offices.company_id', 'Companies.id', 'Companies.name', 'Companies.city', 'Companies.province',
        //         'Companies.type', 'CompanyTypes.name', 'CompanyTypes.icon', 'Companies.num_employees',
        //     ])
        //
        // sqld($query);

        //TODO: vedere se le prestazioni migliorano o no, eventualmente mettere un indice
        $query->order(['Companies.name']);
        //$query->where(['Companies.name IS NOT' => null]);

        if (!empty($q)) {
            $query->where(['Companies.name LIKE' => "%$q%"]);
        }
        if (!empty($t)) {
            $query->where(['Companies.type' => $t]);
        }

        $this->Authorization->applyScope($query);
        $query->group(['Companies.name']);
        $companies = $query->all();

        //TODO: corregere il nuovo modo di caricare il model
        $this->loadModel('Pscl');
        foreach ($companies as $keyc => $c) {
            foreach ($c->offices as $keyo => $o) {
                $o->pscl = $this->Pscl->getFiles($c->id, $o->id, $year);
            }
        }
        if (empty($q) && empty($t)) {
            Cache::write("companies-$user_id", $companies, 'long');
        }
        $this->set('companies', $companies);
        $this->viewBuilder()->setOption('serialize', ['companies']);
    }
}
