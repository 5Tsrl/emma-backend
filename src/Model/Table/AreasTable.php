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
     * WKT polygon values stashed by beforeSave(), keyed by spl_object_id($entity),
     * and written by afterSave() via a raw ST_GeomFromText() UPDATE. See beforeSave()
     * for why the normal ORM bind can't be used for this column.
     *
     * @var array<int, string|null>
     */
    protected array $_pendingPolygonWkt = [];

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
            // clause('select') keys come back backtick-quoted (e.g. "`Areas__polygon`"),
            // not the bare "Areas__polygon" - an unquoted in_array() check here never
            // matched, so this conversion silently never applied and every read
            // returned polygon as raw binary WKB (which then fails UTF-8 JSON
            // encoding and serializes as null - the exact symptom this was masking).
            if (in_array(trim($field, '`'), ['Areas__polygon', 'polygon'])) {
                $select[$field] = 'ST_AsText(polygon)';
            }
        }
        // disableAutoFields(): belongsToMany contains (e.g. Users contain('Areas') in
        // findForAuthentication) run with autoFields on, and _addDefaultFields() would
        // then re-add raw `Areas.polygon` under the same alias at compile time,
        // overwriting ST_AsText() - $user->area[*]->polygon came back as binary WKB.
        // $select already holds every column (->sql() above populated it).
        $query->select($select, $overwrite = true)->disableAutoFields();
    }

    public function beforeSave(\Cake\Event\EventInterface $event, $entity, $options)
    {
        // `polygon` is a native MySQL GEOMETRY column. It can't be written through
        // the normal ORM bind - MySQL requires ST_GeomFromText() wrapped directly
        // in the INSERT/UPDATE statement's SQL text, not a value bound after being
        // fetched back out via a separate SELECT (the previous approach here did
        // exactly that and silently produced a value MySQL wouldn't persist,
        // leaving `polygon` null on every save). So: stash the submitted WKT (or
        // null) here, null the field so the base save doesn't try to bind a raw
        // WKT string into the GEOMETRY column, and do the real write in
        // afterSave() via a raw UPDATE with ST_GeomFromText() in the SQL itself.
        // Only touch this when `polygon` was actually part of the incoming patch -
        // otherwise an edit that doesn't resend `polygon` would wipe an existing
        // saved polygon.
        if ($entity->isDirty('polygon')) {
            $this->_pendingPolygonWkt[spl_object_id($entity)] = $entity->polygon ?: null;
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
    }

    public function afterSave(\Cake\Event\EventInterface $event, $entity, $options)
    {
        $key = spl_object_id($entity);
        if (!array_key_exists($key, $this->_pendingPolygonWkt)) {
            return;
        }
        $wkt = $this->_pendingPolygonWkt[$key];
        unset($this->_pendingPolygonWkt[$key]);

        $connection = ConnectionManager::get('default');
        if ($wkt) {
            $connection->execute(
                'UPDATE areas SET polygon = ST_GeomFromText(:wkt) WHERE id = :id',
                ['wkt' => $wkt, 'id' => $entity->id]
            );
        } else {
            $connection->execute(
                'UPDATE areas SET polygon = NULL WHERE id = :id',
                ['id' => $entity->id]
            );
        }

        // Reflect the real value back on the in-memory entity so a caller reading
        // $area->polygon right after save() (e.g. the controller's JSON response)
        // sees what was actually submitted, not the null beforeSave() wrote.
        $entity->polygon = $wkt;
        $entity->setDirty('polygon', false);
    }
}
