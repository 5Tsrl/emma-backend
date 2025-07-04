<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\Datasource\ConnectionManager;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Areas Model
 *
 * @property \App\Model\Table\UsersTable&\Cake\ORM\Association\BelongsToMany $Users
 * @method \App\Model\Entity\Area newEmptyEntity()
 * @method \App\Model\Entity\Area newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\Area[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Area get($primaryKey, $options = [])
 * @method \App\Model\Entity\Area findOrCreate($search, ?callable $callback = null, $options = [])
 * @method \App\Model\Entity\Area patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\Area[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\Area|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\Area saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\Area[]|\Cake\Datasource\ResultSetInterface|false saveMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\Area[]|\Cake\Datasource\ResultSetInterface saveManyOrFail(iterable $entities, $options = [])
 * @method \App\Model\Entity\Area[]|\Cake\Datasource\ResultSetInterface|false deleteMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\Area[]|\Cake\Datasource\ResultSetInterface deleteManyOrFail(iterable $entities, $options = [])
 */
class AreasTable extends Table
{
    /**
     * Initialize method
     *
     * @param array $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('areas');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->belongsToMany('Users', [
            'foreignKey' => 'area_id',
            'targetForeignKey' => 'user_id',
            'joinTable' => 'areas_users',
        ]);

        // $this->addBehavior('Spatial');
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar('name')
            ->maxLength('name', 255)
            ->allowEmptyString('name');
        $validator
            ->scalar('city')
            ->maxLength('city', 255)
            ->allowEmptyString('city');

        $validator
            ->scalar('province')
            ->maxLength('province', 2)
            ->allowEmptyString('province');

        $validator
            ->scalar('polygon')
            ->allowEmptyString('polygon');

        return $validator;
    }

    public function beforeFind(\Cake\Event\EventInterface $event, \Cake\ORM\Query $query, \ArrayObject $options, $primary): void
    {
        // ->sql() transforms the query, setting the select fields when it is not setted
        $query->sql();

        $select = $query->clause('select');
        $fields = array_keys($select);

        foreach ($fields as $field) {
            if (in_array($field, [ 'Areas__polygon'])) {
                $select[$field] = 'ST_AsText(polygon)';
            }
        }
        $query->select($select, $overwrite = true);
    }

    public function beforeSave(\Cake\Event\EventInterface $event, $entity, $options)
    {
        if ($entity->polygon) {
            $geometryString = $entity->polygon;
            // Get the default database connection
            $connection = ConnectionManager::get('default');

            // Use a raw SQL query to convert the string to a geometry object
            $query = 'SELECT ST_GeomFromText(:geometryString) AS geometry';
            $result = $connection->execute($query, ['geometryString' => $geometryString])->fetch('assoc');

            // Access the geometry object
            $geometryObject = $result['geometry'];
            // Your string representation of geometry

            // $json = '{ "type": "Polygon", "coordinates": [ [ [ 0.0, 0.0 ], [ 10.0, 0.0 ], [ 10.0, 10.0 ], [ 0.0, 10.0 ], [ 0.0, 0.0 ] ], [ [ 5.0, 5.0 ], [ 7.0, 5.0 ], [ 7.0, 7.0 ], [ 5.0, 7.0 ], [ 5.0, 5.0 ] ] ] }';

            // decode the JSON data into a PHP object
            // $geometryObject = json_decode($json);
            $entity->polygon = $geometryObject;
        } else {
            $entity->polygon = null;
        }
        if ($entity->name) {
            $entity->name = strtoupper($entity->name);
        } else {
            $entity->name = null;
        }
        if ($entity->city) {
            $entity->city = strtoupper($entity->city);
        } else {
            $entity->city = null;
        }
        if ($entity->province) {
            $entity->province = strtoupper($entity->province);
        } else {
            $entity->province = null;
        }

    // Other code
    }
}
