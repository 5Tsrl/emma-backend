<?php
declare(strict_types=1);

namespace App\Controller;

/**
 * Areas Controller
 *
 * @property \App\Model\Table\AreasTable $Areas
 * @method \App\Model\Entity\Area[]|\Cake\Datasource\ResultSetInterface paginate($object = null, array $settings = [])
 */
class AreasController extends AppController
{
    /**
     * Index method
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index($area_name = null, $username = null)
    {
        $area_name = $this->request->getQuery('area_name');
        $username = $this->request->getQuery('username');
        $filter = $this->Areas->find('all')->contain(['Users' => ['fields' => [
            'id',
            'username',
            'email',
        ]]]);
        if (!is_null($area_name)) {
            $filter->where(['Areas.name LIKE' => '%' . $area_name . '%']);
        }
        if (!is_null($username)) {
            $filter->matching('Users', function ($q) use ($username) {
                return $q->where(['Users.username LIKE' => '%' . $username . '%']);
            });
        }

        $areas = $this->paginate($filter);

        $this->set(compact('areas'));
        $this->viewBuilder()->setOption('serialize', ['areas']);
    }

    /**
     * View method
     *
     * @param string|null $id Area id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null)
    {
        $area = $this->Areas->get($id, [
            'contain' => ['Users'],
        ]);

        $this->set(compact('area'));
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|null|void Redirects on successful add, renders view otherwise.
     */
    public function add()
    {
        $area = $this->Areas->newEmptyEntity();
        if ($this->request->is('post')) {
            // $values=$this->request->getData();
            // $values['city']='';
            // $values['polygon']="ST_GeomFromText('POINT(1 1)')";
            // $entity['latitude']=10;
            // $entity['longitude']=10;
            // $values['polygon']=new QueryExpression('GeomFromText(\'POINT(' . $entity['latitude'] . " " . $entity['longitude'] . ')\')');
            // Your string representation of geometry
            // $geometryString = 'POLYGON((45.08 7.41, 45.08 7.42, 45.09 7.42, 45.09 7.41, 45.08 7.41))';
            // $geometryString = $values['polygon'];
            // // Get the default database connection
            // $connection = ConnectionManager::get('default');

            // // Use a raw SQL query to convert the string to a geometry object
            // $query = "SELECT ST_GeomFromText(:geometryString) AS geometry";
            // $result = $connection->execute($query, ['geometryString' => $geometryString])->fetch('assoc');

            // // Access the geometry object
            // $geometryObject = $result['geometry'];
            // // Your string representation of geometry

            // // $json = '{ "type": "Polygon", "coordinates": [ [ [ 0.0, 0.0 ], [ 10.0, 0.0 ], [ 10.0, 10.0 ], [ 0.0, 10.0 ], [ 0.0, 0.0 ] ], [ [ 5.0, 5.0 ], [ 7.0, 5.0 ], [ 7.0, 7.0 ], [ 5.0, 7.0 ], [ 5.0, 5.0 ] ] ] }';

            // // decode the JSON data into a PHP object
            // // $geometryObject = json_decode($json);
            // $values['polygon']=$geometryObject;
            $area = $this->Areas->patchEntity($area, $this->request->getData());
            if ($this->Areas->save($area)) {
                $this->Flash->success(__('The area has been saved.'));

                if (!$this->request->is('json')) {
                    return $this->redirect(['action' => 'index']);
                }
            }
            $this->Flash->error(__('The area could not be saved. Please, try again.'));
        }
        $users = $this->Areas->Users->find('list', ['limit' => 200])->where([
            'role = "moma_area"',
        ])->all();
        $this->set(compact('area', 'users'));
        $this->viewBuilder()->setOption('serialize', ['area', 'users']);
    }

    /**
     * Edit method
     *
     * @param string|null $id Area id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit($id = null)
    {
        $area = $this->Areas->get($id, [
            'contain' => ['Users' => ['fields' => [
                'id',
                'username',
                'email',
            ]]],
        ]);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $area = $this->Areas->patchEntity($area, $this->request->getData());
            if ($this->Areas->save($area)) {
                $this->Flash->success(__('The area has been saved.'));
                if (!$this->request->is('json')) {
                    return $this->redirect(['action' => 'index']);
                }
            }
            $this->Flash->error(__('The area could not be saved. Please, try again.'));
        }
        $users = $this->Areas->Users->find('list', ['limit' => 200])->where([
            'role = "moma_area"',
        ])->all();
        $this->set(compact('area', 'users'));
        $this->viewBuilder()->setOption('serialize', ['area', 'users']);
    }

    /**
     * Delete method
     *
     * @param string|null $id Area id.
     * @return \Cake\Http\Response|null|void Redirects to index.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function delete($id = null)
    {
        $this->request->allowMethod(['post', 'delete']);
        $area = $this->Areas->get($id);
        if ($this->Areas->delete($area)) {
            $this->Flash->success(__('The area has been deleted.'));
        } else {
            $this->Flash->error(__('The area could not be deleted. Please, try again.'));
        }

        return $this->redirect(['action' => 'index']);
    }

    public function getArea($id = null)
    {
        $area = $this->Areas->get($id);

        $this->set(compact('area'));
        $this->viewBuilder()->setOption('serialize', ['area']);
    }
}
