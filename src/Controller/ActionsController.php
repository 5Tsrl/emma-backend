<?php
declare(strict_types=1);

namespace App\Controller;

/**
 * Actions Controller
 *
 * @property \App\Model\Table\ActionsTable $Actions
 * @method \App\Model\Entity\Action[]|\Cake\Datasource\ResultSetInterface paginate($object = null, array $settings = [])
 */
class ActionsController extends AppController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->loadComponent('RequestHandler');
        $this->viewBuilder()->setClassName('Json');
        $this->viewBuilder()->setOption('serialize', 'data');
    }

    /**
     * Index method
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index()
    {
        // Pillars are no longer a separate model; Actions only associate Measures.
        $this->paginate = [
            'contain' => ['Measures'],
        ];
        $actions = $this->paginate($this->Actions);

        $this->set('data', $actions);
    }

    /**
     * View method
     *
     * @param string|null find action by measure_id and pscl_id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($measure_id, $pscl_id)
    {
        $action = $this->Actions->find()
            ->where(['measure_id' => $measure_id, 'pscl_id' => $pscl_id])
            ->contain(['Pscl' =>
                [   'fields' => ['id'],
                    'Companies' => [
                        'fields' => ['id', 'name'],
                    ],
                    'Offices' => [
                        'fields' => ['id', 'name'],
                    ] ,
                    'Surveys' => [
                        'fields' => ['id', 'name'],
                    ] ,
                ],
            ])
            ->firstOrFail();

        $this->set('data', $action);
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $action = $this->Actions->newEmptyEntity();
        if ($this->request->is('post')) {
            $dt = $this->request->getData();
            $action = $this->Actions->patchEntity($action, $dt);
            if ($this->Actions->save($action)) {
                $this->set('data', $action);
                $this->response = $this->response->withStatus(201);
            } else {
                $this->set('data', ['errors' => $action->getErrors()]);
                $this->response = $this->response->withStatus(400);
            }
            //salva anche i monitoring
            $this->saveMonitorings($dt);
        } else {
            $this->set('data', ['message' => 'Use POST to add an action']);
            $this->response = $this->response->withStatus(405);
        }
    }

    /**
     * Edit method
     *
     * @param string|null $id Action id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $action = $this->Actions->get($id, [
            'contain' => [],
        ]);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $dt = $this->request->getData();
            $action = $this->Actions->patchEntity($action, $dt);
            if (!$this->Actions->save($action)) {
                $this->set('data', ['errors' => $action->getErrors()]);
                $this->response = $this->response->withStatus(400);
            }
            //salva anche i monitoring
            $this->saveMonitorings($dt);
            $this->set('data', $action);
            $this->response = $this->response->withStatus(200);
        } else {
            $this->set('data', $action);
        }
    }

    /**
     * Delete method
     *
     * @param string|null $id Action id.
     * @return \Cake\Http\Response|null|void Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $action = $this->Actions->get($id);
        if ($this->Actions->delete($action)) {
            $this->response = $this->response->withStatus(204);

            return $this->response;
        } else {
            $this->set('data', ['message' => 'Could not delete action']);
            $this->response = $this->response->withStatus(400);
        }
    }

    private function saveMonitorings($dt)
    {
        //salva anche i monitoring
        $monitoringsTable = $this->getTableLocator()->get('Monitorings');
        if (isset($dt['monitorings']) && is_array($dt['monitorings'])) {
            //Prima di salvare devo convertire il valore dt di ogni record in un formato valido per cakephp
            foreach ($dt['monitorings'] as &$monitoring) {
                try {
                    if (is_string($monitoring['dt'])) {
                        $monitoring['dt'] = \Cake\I18n\FrozenTime::createFromFormat('Y-m-d', $monitoring['dt']);
                    }
                } catch (\Exception $e) {
                    $monitoring['dt'] = null;
                }
            }
            $m = $monitoringsTable->newEntities($dt['monitorings']);
            if (!$monitoringsTable->saveMany($m)) {
                //errore salvataggio monitorings
                //Genera una stringa ciclando su ogni $m  e concatenando gli errori
                $errors = [];
                foreach ($m as $entity) {
                    $errors[] = $entity->getErrors();
                }
                $this->set('data', ['message' => 'Could not save monitorings: ' . json_encode($errors)]);
                $this->log('Could not save monitorings: ' . json_encode($errors));
            }
        }
        if (isset($dt['objective'])) {
            try {
                if (is_string($dt['objective']['dt'])) {
                    $dt['objective']['dt'] = \Cake\I18n\FrozenTime::createFromFormat('Y-m-d', $dt['objective']['dt']);
                }
            } catch (\Exception $e) {
                $dt['objective']['dt'] = null;
            }
            if (!empty($dt['objective']['id'])) {
                $m = $monitoringsTable->get($dt['objective']['id']);
                $m = $monitoringsTable->patchEntity($m, $dt['objective']);
            } else {
                $m = $monitoringsTable->newEntity($dt['objective']);
            }

            if (!$monitoringsTable->save($m)) {
                //errore salvataggio monitoring objective
                $this->set('data', ['message' => 'Could not save objective objective']);
                $this->log('Could not save objective monitoring: ' . json_encode($m));
            }
        }
    }
}
