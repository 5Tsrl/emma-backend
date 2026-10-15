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

use ArrayObject;
use Cake\Core\Configure;
use Cake\Datasource\EntityInterface;
use Cake\Event\EventInterface;
use Cake\Log\Log;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Companies Model
 *
 * @property \App\Model\Table\SurveysTable&\Cake\ORM\Association\BelongsTo $Surveys
 * @method \App\Model\Entity\Company newEmptyEntity()
 * @method \App\Model\Entity\Company newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\Company[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Company get($primaryKey, $options = [])
 * @method \App\Model\Entity\Company findOrCreate($search, ?callable $callback = null, $options = [])
 * @method \App\Model\Entity\Company patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\Company[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\Company|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\Company saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\Company[]|\Cake\Datasource\ResultSetInterface|false saveMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\Company[]|\Cake\Datasource\ResultSetInterface saveManyOrFail(iterable $entities, $options = [])
 * @method \App\Model\Entity\Company[]|\Cake\Datasource\ResultSetInterface|false deleteMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\Company[]|\Cake\Datasource\ResultSetInterface deleteManyOrFail(iterable $entities, $options = [])
 */
class SurveyDeliveryConfigsTable extends Table
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

        $this->setTable('survey_delivery_configs');
        $this->setPrimaryKey('id');
        $this->addBehavior('Translate', ['fields' => ['invitation_template','reminder_template','invitation_subject','reminder_subject']]);

        $this->belongsTo('Surveys', [
            'className' => 'Surveys',
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
            ->integer('id')
            ->allowEmptyString('id', null, 'create');

        $validator
            ->integer('days_before_first_reminder');

        $validator
            ->integer('days_before_second_reminder');

        $validator
            ->allowEmptyString('invitation_template');

        $validator
            ->allowEmptyString('reminder_template');

        $validator
            ->boolean('is_active');

        return $validator;
    }

    /**
     * Mittente effettivo delle email di indagine. Se `Surveys.forcedSenderEmail`
     * (env SURVEY_FORCED_SENDER_EMAIL) è un indirizzo valido, sovrascrive sempre
     * il valore salvato o inviato dal client. Vuoto/non valido = nessun forzamento.
     *
     * @param string|null $stored Mittente salvato nella configurazione.
     * @return string|null
     */
    public static function effectiveSenderEmail(?string $stored): ?string
    {
        $forced = trim((string)Configure::read('Surveys.forcedSenderEmail'));
        if ($forced === '') {
            return $stored;
        }
        if (!filter_var($forced, FILTER_VALIDATE_EMAIL)) {
            Log::write('warning', "SURVEY_FORCED_SENDER_EMAIL non valido: '$forced', mittente non forzato");

            return $stored;
        }

        return $forced;
    }

    /**
     * Unico punto di scrittura: copre salvataggi diretti e annidati (es. da SurveysController::edit).
     *
     * @param \Cake\Event\EventInterface $event Evento beforeSave.
     * @param \Cake\Datasource\EntityInterface $entity Configurazione di invio.
     * @param \ArrayObject $options Opzioni di salvataggio.
     * @return void
     */
    public function beforeSave(EventInterface $event, EntityInterface $entity, ArrayObject $options): void
    {
        $entity->sender_email = self::effectiveSenderEmail($entity->sender_email);
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
        $rules->add($rules->existsIn(['survey_id'], 'Surveys'));

        return $rules;
    }
}
