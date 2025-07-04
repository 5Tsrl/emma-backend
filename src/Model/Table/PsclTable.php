<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Filesystem\Folder;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Utility\Inflector;
use Cake\Validation\Validator;

/**
 * Pscl Model
 *
 * @property \App\Model\Table\CompaniesTable&\Cake\ORM\Association\BelongsTo $Companies
 * @property \App\Model\Table\OfficesTable&\Cake\ORM\Association\BelongsTo $Offices
 * @property \App\Model\Table\SurveysTable&\Cake\ORM\Association\BelongsTo $Surveys
 * @method \App\Model\Entity\Pscl newEmptyEntity()
 * @method \App\Model\Entity\Pscl newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\Pscl[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Pscl get($primaryKey, $options = [])
 * @method \App\Model\Entity\Pscl findOrCreate($search, ?callable $callback = null, $options = [])
 * @method \App\Model\Entity\Pscl patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\Pscl[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\Pscl|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\Pscl saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\Pscl[]|\Cake\Datasource\ResultSetInterface|false saveMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\Pscl[]|\Cake\Datasource\ResultSetInterface saveManyOrFail(iterable $entities, $options = [])
 * @method \App\Model\Entity\Pscl[]|\Cake\Datasource\ResultSetInterface|false deleteMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\Pscl[]|\Cake\Datasource\ResultSetInterface deleteManyOrFail(iterable $entities, $options = [])
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class PsclTable extends Table
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

        $this->setTable('pscl');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('Companies', [
            'foreignKey' => 'company_id',
        ]);
        $this->belongsTo('Offices', [
            'foreignKey' => 'office_id',
        ]);
        $this->belongsTo('Surveys', [
            'foreignKey' => 'survey_id',
        ]);
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
            ->scalar('version_tag')
            ->maxLength('version_tag', 45)
            ->allowEmptyString('version_tag');

        $validator
            ->integer('company_id')
            ->allowEmptyString('company_id');

        $validator
            ->integer('office_id')
            ->allowEmptyString('office_id');

        $validator
            ->integer('survey_id')
            ->allowEmptyString('survey_id');

        $validator
            ->allowEmptyString('plan');

        return $validator;
    }

    /**
     * Returns a rules checker object that will be used for validating
     * application integrity.
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn('company_id', 'Companies'), ['errorField' => 'company_id']);
        $rules->add($rules->existsIn('office_id', 'Offices'), ['errorField' => 'office_id']);
        $rules->add($rules->existsIn('survey_id', 'Surveys'), ['errorField' => 'survey_id']);

        return $rules;
    }

    public function getFiles($company_id, $office_id, $year = 'TUTTI')
    {
        if ($year == 'TUTTI') {
            $files = Cache::read("pscl-$company_id-$office_id", 'long');
        } else {
            if ($office_id == 'null') {
                $files = Cache::read("pscl-$year-$company_id", 'long');
            } else {
                $files = Cache::read("pscl-$year-$company_id-$office_id", 'long');
            }
        }

        $folderName = $this->getFolderName($company_id, $office_id, $year);
        if (empty($files)) {
            $folder_path = WWW_ROOT . Configure::read('sitedir') . '/PSCL/' . $folderName;
            $dir = new Folder($folder_path);
            $files = $dir->find('.*', true);
            if ($year == 'TUTTI') {
                Cache::write("pscl-$company_id-$office_id", $files, 'long');
            } else {
                if ($office_id == 'null') {
                    Cache::write("pscl-$year-$company_id", $files, 'long');
                } else {
                    Cache::write("pscl-$year-$company_id-$office_id", $files, 'long');
                }
            }
        }

        return $files;
    }

    /**
     * Estrae un file dal filesystem per il PSCL
     *
     * @param mixed $folder_path
     * @param mixed $file
     * @param mixed $year
     * @param mixed $file
     * @return string|false
     * @throws \InvalidArgumentException
     */
    public function getFile($folder_path, $file)
    {
        $file_path = $folder_path . '/' . $file;
        $file = file_get_contents($file_path);

        return $file;
    }

    /**
     * Salva un file nel filesystem per il PSCL
     *
     * @param mixed $folder_path
     * @param mixed $file
     * @param mixed $data
     * @param mixed $file
     * @param mixed $data
     * @return int|false
     * @throws \InvalidArgumentException
     */
    public function saveFile($folder_path, $file, $data)
    {
        $file_path = $folder_path . '/' . $file;
        $file = file_put_contents($file_path, $data);

        return $file;
    }

    /**
     * Ottiene il nome del folder per un PSCL
     *
     * @param mixed $company_id
     * @param mixed $office_id
     * @param mixed $year
     * @return string
     */
    public function getFolderName($company_id, $office_id, $year)
    {
        if ($year == 'TUTTI') {
            $folderName = $company_id . '/' . $office_id;
        } else {
            if ($office_id == 'null') {
                $folderName = $year . '/' . $company_id;
            } else {
                $folderName = $year . '/' . $company_id . '/' . $office_id;
            }
        }

        return $folderName;
    }

    public function getExporterClass($format)
    {
        //Verifico che il formato sia corretto, altrimenti prendo Html
        //TODO: leggere i formati da un file di configurazione
        if (!in_array($format, Configure::read('Exporter.extensions', ['html', 'docx']))) {
            $format = 'html';
        }
        $format = Inflector::camelize($format);
        //$format = ucfirst(strtolower($format));

        //Uso il formattatore corretto
        $class = '\\App\\Exporter\\' . $format . 'Exporter';
        if (!class_exists($class)) {
            throw new \Exception('Formato non supportato');
        }

        return $class;
    }
}
