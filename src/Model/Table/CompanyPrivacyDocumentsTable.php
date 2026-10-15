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

/**
 * CompanyPrivacyDocuments Model
 *
 * Requisito 3 della compliance privacy RER "EMMA RE-R": UNA riga per azienda (vincolo
 * di unicità su `company_id`), riusata per tutte le survey/anni successivi. Vedi
 * AdhesionRequestsController::employeePrivacyDocument() (scrittura, token-authenticated)
 * e SurveysController::employeePrivacyDocument() (lettura pubblica, per la compilazione
 * anonima e per il gate di invio autenticato).
 *
 * Ciclo di vita a due fasi: il documento può essere caricato già durante `in_review`
 * (prima che esista un `company_id`), agganciato temporaneamente alla richiesta di
 * adesione tramite `adhesion_request_id`; all'approvazione viene ri-agganciato
 * all'azienda (`company_id`) e `adhesion_request_id` viene nullata
 * (AdhesionRequestsController::enable()). Invariante: **esattamente una tra
 * `company_id` e `adhesion_request_id` è valorizzata** — mai entrambe, mai nessuna
 * delle due (vedi la regola `exactlyOneOwner` in buildRules()).
 *
 * @property \App\Model\Table\CompaniesTable&\Cake\ORM\Association\BelongsTo $Companies
 * @property \App\Model\Table\AdhesionRequestsTable&\Cake\ORM\Association\BelongsTo $AdhesionRequests
 * @method \App\Model\Entity\CompanyPrivacyDocument newEmptyEntity()
 * @method \App\Model\Entity\CompanyPrivacyDocument newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\CompanyPrivacyDocument[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\CompanyPrivacyDocument get($primaryKey, $options = [])
 * @method \App\Model\Entity\CompanyPrivacyDocument patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\CompanyPrivacyDocument|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\CompanyPrivacyDocument saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 */
class CompanyPrivacyDocumentsTable extends Table
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

        $this->setTable('company_privacy_documents');
        $this->setDisplayField('filename');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('Companies', [
            'foreignKey' => 'company_id',
        ]);
        $this->belongsTo('AdhesionRequests', [
            'foreignKey' => 'adhesion_request_id',
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
            ->integer('company_id')
            ->allowEmptyString('company_id');

        $validator
            ->integer('adhesion_request_id')
            ->allowEmptyString('adhesion_request_id');

        $validator
            ->scalar('filename')
            ->maxLength('filename', 255)
            ->notEmptyString('filename');

        $validator
            ->scalar('path')
            ->maxLength('path', 500)
            ->notEmptyString('path');

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
        $rules->add($rules->existsIn(['adhesion_request_id'], 'AdhesionRequests'));
        $rules->add($rules->isUnique(['company_id'], ['allowMultipleNulls' => true]));
        $rules->add($rules->isUnique(['adhesion_request_id'], ['allowMultipleNulls' => true]));
        $rules->add(
            function ($entity) {
                return !empty($entity->company_id) !== !empty($entity->adhesion_request_id);
            },
            'exactlyOneOwner',
            [
                'errorField' => 'company_id',
                'message' => 'Il documento deve essere collegato a un\'azienda OPPURE a una richiesta di '
                    . 'adesione, mai a entrambe né a nessuna delle due',
            ]
        );

        return $rules;
    }
}
