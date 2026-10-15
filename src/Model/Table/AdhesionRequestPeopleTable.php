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

use App\Validation\ItalianFiscalValidator;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * AdhesionRequestPeople Model — "Elenco persone da abilitare" (specifica §5).
 *
 * @property \App\Model\Table\AdhesionRequestsTable&\Cake\ORM\Association\BelongsTo $AdhesionRequests
 * @property \App\Model\Table\UsersTable&\Cake\ORM\Association\BelongsTo $Users
 * @method \App\Model\Entity\AdhesionRequestPerson newEmptyEntity()
 * @method \App\Model\Entity\AdhesionRequestPerson newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\AdhesionRequestPerson[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\AdhesionRequestPerson get($primaryKey, $options = [])
 * @method \App\Model\Entity\AdhesionRequestPerson patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\AdhesionRequestPerson|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 */
class AdhesionRequestPeopleTable extends Table
{
    /**
     * Initialize method.
     *
     * @param array $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('adhesion_request_people');
        $this->setDisplayField('last_name');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('AdhesionRequests', [
            'foreignKey' => 'adhesion_request_id',
            'joinType' => 'INNER',
        ]);
        // L'utente creato all'abilitazione (null fino alla doppia approvazione).
        $this->belongsTo('Users', [
            'foreignKey' => 'user_id',
            'joinType' => 'LEFT',
        ]);
    }

    /**
     * Regole di validazione.
     *
     * Il codice fiscale usa il validatore STRETTO (con carattere di controllo), non la
     * regex `^[A-Z0-9]{16}$` usata nel resto della piattaforma: qui i dati arrivano da un
     * form pubblico anonimo e diventano account reali. Vedi la nota d'ambito in
     * App\Validation\ItalianFiscalValidator.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar('first_name')
            ->maxLength('first_name', 255)
            ->notEmptyString('first_name', 'Il nome è obbligatorio');

        $validator
            ->scalar('last_name')
            ->maxLength('last_name', 255)
            ->notEmptyString('last_name', 'Il cognome è obbligatorio');

        $validator
            ->email('email', false, 'Indirizzo email non valido')
            ->maxLength('email', 255)
            ->notEmptyString('email', 'L\'email è obbligatoria');

        $validator
            ->notEmptyString('cf', 'Il Codice Fiscale è obbligatorio')
            ->add('cf', 'format', [
                'rule' => function ($value) {
                    return ItalianFiscalValidator::isValidPersonalCf($value);
                },
                'message' => 'Codice Fiscale non valido (verificare il carattere di controllo)',
            ]);

        return $validator;
    }
}
