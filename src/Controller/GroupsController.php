<?php
declare(strict_types=1);

namespace App\Controller;

class GroupsController extends AppController
{
    public function beforeFilter(\Cake\Event\EventInterface $event)
    {
        parent::beforeFilter($event);
        // Allow public access to list/groups options
        $this->Authentication->allowUnauthenticated(['index', 'options']);
    }

    public function index()
    {
        try {
            $groups = $this->Groups->find()->order(['Groups.id' => 'ASC'])->toArray();
            $this->returnSuccess(true, $groups);

            return;
        } catch (\Exception $e) {
            $this->returnSuccess(false, $e->getMessage());

            return;
        }
    }

    public function options()
    {
        // Return a lightweight list for select/options
        try {
            $groups = $this->Groups->find('list', ['keyField' => 'id', 'valueField' => 'name'])->order(['id' => 'ASC'])->toArray();
            $this->returnSuccess(true, $groups);

            return;
        } catch (\Exception $e) {
            $this->returnSuccess(false, $e->getMessage());

            return;
        }
    }
}
