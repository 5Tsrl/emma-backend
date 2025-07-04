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

namespace App\Model\Table;

use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use Exception;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Surveys Model
 *
 * @property \App\Model\Table\CompaniesTable&\Cake\ORM\Association\BelongsTo $Companies
 * @property \App\Model\Table\AnswersTable&\Cake\ORM\Association\HasMany $Answers
 * @property \App\Model\Table\SurveyDispathConfigsTable&\Cake\ORM\Association\HasOne $SurveyDispatchConfigs
 * @property \App\Model\Table\SurveyParticipantsTable&\Cake\ORM\Association\HasMany $SurveyParticipants
 * @method \App\Model\Entity\Survey newEmptyEntity()
 * @method \App\Model\Entity\Survey newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\Survey[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Survey get($primaryKey, $options = [])
 * @method \App\Model\Entity\Survey findOrCreate($search, ?callable $callback = null, $options = [])
 * @method \App\Model\Entity\Survey patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\Survey[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\Survey|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\Survey saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\Survey[]|\Cake\Datasource\ResultSetInterface|false saveMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\Survey[]|\Cake\Datasource\ResultSetInterface saveManyOrFail(iterable $entities, $options = [])
 * @method \App\Model\Entity\Survey[]|\Cake\Datasource\ResultSetInterface|false deleteMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\Survey[]|\Cake\Datasource\ResultSetInterface deleteManyOrFail(iterable $entities, $options = [])
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class SurveysTable extends Table
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
        $this->addBehavior('Translate', ['fields' => ['welcome']]);

        $this->setTable('surveys');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');
        $this->belongsToMany('Questions');

        $this->belongsTo('Companies', [
        'foreignKey' => 'company_id',
        ]);
        $this->belongsTo('Users', [
        'foreignKey' => 'user_id',
        ]);
        $this->hasMany('Answers', [
        'foreignKey' => 'survey_id',
        'dependent' => true,  //In questo modo cancello le risposte quando cancello la survey
        ]);
        $this->hasOne('SurveyDeliveryConfigs', [
        'className' => 'SurveyDeliveryConfigs',
        'foreignKey' => 'survey_id',
        'dependent' => true,
        ]);
        $this->hasMany('SurveyParticipants', [
        'dependent' => true,
        ]);
        $this->belongsToMany('Questions', [
            'through' => 'QuestionsSurveys',
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
        ->integer('id')
        ->allowEmptyString('id', null, 'create');

        $validator
        ->scalar('name')
        ->maxLength('name', 255)
        ->allowEmptyString('name');

        $validator
        ->scalar('version_tag')
        ->maxLength('version_tag', 45)
        ->allowEmptyString('version_tag');

        $validator
        ->scalar('description')
        ->allowEmptyString('description');

        $validator
        ->date('date')
        ->allowEmptyDate('date');

        $validator
        ->date('start_date')
        ->allowEmptyDate('start_date');

        $validator
        ->date('end_date')
        ->allowEmptyDate('end_date');

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
        $rules->add($rules->existsIn(['company_id'], 'Companies'));

        return $rules;
    }

    public function importExcel(string $fname)
    {
        $inputFileName = WWW_ROOT . 'Moma/' . $fname;
        $reader = IOFactory::createReaderForFile($inputFileName);
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);
        $spreadsheet = $reader->load($inputFileName);
        $sheetData = $spreadsheet->getActiveSheet()->toArray(null, false, false, true);

        return $sheetData;
    }

  // ATTENZIONE! DA USARE SOLO IN FASE DI CREAZIONE DI toId (METODO DISTRUTTIVO!)

    public function cloneQuestions($fromId, $toId)
    {
      // questions_surveys non è facilmente manipolabile perchè non ha entities o tables collegate, usa
    //   // query diretta
    //     $connection = ConnectionManager::get('default');
    //   // 1. cancella le questions di toId (se esistono già)
    //     $connection->delete('questions_surveys', ['survey_id' => $toId]);
    //   // 2. carica le questions di fromId
    //     $results = $connection->execute('SELECT * FROM questions_surveys WHERE survey_id = :survey_id', [
    //     'survey_id' => $fromId,
    //     ])->fetchAll('assoc');
    //   // 3. crea le nuove questions per toId
    //     foreach ($results as $row) {
    //         $row['id'] = null;
    //         $row['survey_id'] = $toId;
    //         $connection->insert('questions_surveys', $row);
    //     }
        $this->Questions->QuestionsSurveys->cloneQuestions($fromId, $toId);
    }

    public function createNewEmptySurvey($company)
    {
        $survey = $this->newEntity([
        'name' => $company['name'],
        'company_id' => $company['id'],
        'version_tag' => '',
        'date' => date('Y-m-d'),
        'year' => date('Y'),
        ]);
        if ($this->save($survey)) {
            return $survey['id'];
        } else {
            throw new Exception('Errore durante la generazione della survey');
        }
    }

    public function createNewSurveyFromTemplate($company, $template_id)
    {
        $survey = $this->newEntity([
        'name' => $company['name'],
        'company_id' => $company['id'],
        'version_tag' => '',
        'date' => date('Y-m-d'),
        'year' => date('Y'),
        ]);
        if ($this->save($survey)) {
            $this->cloneQuestions($template_id, $survey['id']);

            return $survey['id'];
        } else {
            throw new Exception('Errore durante la generazione della survey');
        }
    }

    public function fromCompanyId($company_id)
    {
        $survey = $this->find()
        ->where(['company_id' => $company_id])
        ->first();
        $survey_id = null;
        if (!empty($survey)) {
            $survey_id = $survey->id;
        }

        return $survey_id;
    }

    public function countParticipants($survey_id)
    {
        $survey_participants_num = $this->SurveyParticipants->find()->where([
        'survey_id' => $survey_id,
        ])->count();

        return $survey_participants_num;
    }
}
