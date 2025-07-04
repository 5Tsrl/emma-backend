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

use App\Model\Entity\SurveyParticipant;
use Cake\Cache\Cache;
use Cake\Http\Exception\NotFoundException;
use Cake\Log\Log;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Validation\Validator;
use Exception;

class SurveyParticipantsTable extends Table
{
    use LocatorAwareTrait;

    private $Surveys;
    // Declare the property
    public $Users;

    /**
     * Initialize method
     *
     * @param array $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('survey_participants');
        $this->setPrimaryKey('id');

        $this->belongsTo('Surveys');
        $this->belongsTo('Users', [
        'foreignKey' => 'user_id',
        ]);
        $this->hasMany('Notifications', [
        'className' => 'Notifications',
        'foreignKey' => 'participant_id',
        'dependent' => true,
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
        ->scalar('id');

        $validator
        ->integer('is_survey_completed');

        $validator
        ->integer('sent_invitation_num');

        $validator
        ->dateTime('last_invitation_date')
        ->allowEmptyDate('last_invitation_date');

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
        $rules->add($rules->existsIn(['survey_id'], 'Surveys'));
        $rules->add($rules->existsIn(['user_id'], 'Users'));

        return $rules;
    }

    public function countAll($survey_id, $where = [])
    {
        $w = hash('crc32', serialize($where));
        $w = "count-$survey_id-$w";
        $count = Cache::read($w, 'hour');
        if (empty($count)) {
            $count = $this->getAll($survey_id, $where)->count();
            Cache::write($w, $count, 'hour');
        }

        return $count;
    }

    public function getAll($survey_id, $where = [])
    {
        if ($where == null) {
            $where = [];
        }
        $where['survey_id'] = $survey_id;

        return $this->find()->where($where)->contain('Users');
    }

    public function countAllToBeNotifiedFor($survey_id, $purpose)
    {
        return $this->getAllToBeNotifiedFor($survey_id, $purpose)->count();
    }

    public function getAllToBeNotifiedFor($survey_id, $purpose)
    {
        if (!in_array($purpose, ['invitation', 'reminder'])) {
            throw new Exception("Unknown message purpose $purpose");
        }

        $where = [
        'survey_id' => $survey_id,
        'survey_completed_at IS' => null,
        'Users.email NOT LIKE' => '%@email.invalid',
        ];
        if ($purpose == 'invitation') {
            $where['invitation_delivered_at IS'] = null;
        }

        return $this->find()
        ->where($where)
        ->contain('Users');
    }

  // @param participant: array con campi "email", "first_name", "last_name"
  // return l'id del partecipante

    public function add($participant, $survey_id)
    {
      // verifica che non esista già un utente con la stessa mail
        $this->Users = $this->getTableLocator()->get('Users');
        if (empty($participant['email'])) {
            throw new NotFoundException('Il partecipante non ha la mail: ' . $participant['first_name'] . ' ' . $participant['last_name']);
        }
        $user = $this->Users->find()
        ->where([
        'OR' => [
          [
            'username' => $participant['email'],
          ],
          ['email' => $participant['email']],
        ],
        ])->first();

        $this->Surveys = TableRegistry::get('Surveys');
        $survey = $this->Surveys
        ->find()
        ->where(['id' => $survey_id])
        ->first();
        if (empty($survey)) {
            throw new NotFoundException("Survey $survey not found");
        }

      //Se non c'è l'utente lo creo
        if (empty($user)) {
            Log::write('debug', "Utente Mancante, tentativo di creazione {$participant["email"]}");

            $user_id = \Cake\Utility\Text::uuid();
            $user = $this->Users->newEntity([
            'id' => $user_id,
            'username' => $participant['email'],
            'email' => $participant['email'],
            'password' => 'fake unguessable password (no string could have this as hashed value)',
            'first_name' => $participant['first_name'],
            'last_name' => $participant['last_name'],
            'active' => 1,
            'role' => 'user',
            'company_id' => $survey['company_id'],
            // years è un campo json che contiene gli anni in cui l'utente ha partecipato alle survey
            'years' => [(string)$survey['year']],
            ]);

            if (!$this->Users->save($user)) {
                Log::write('debug', "Errore salvataggio utente {$participant["email"]}");
                throw new \Exception('Errore salvataggio utente');
            }
            Log::write('debug', "Utente Mancante, creato {$participant['email']} - {$user->id}");
        }

        $user_id = $user->id;
        $participant = $this->find()
        ->where([
        'user_id' => $user_id,
        'survey_id' => $survey_id,
        ])
        ->first();

        if (empty($participant)) {
            $participant_id = \Cake\Utility\Text::uuid();
            Log::write('debug', "Partecipante Mancante, tentativo di creazione $participant_id");
          // genera il partecipante alla survey
            $participant = $this->newEntity([
            'id' => $participant_id,
            'user_id' => $user_id,
            'survey_id' => $survey_id,
            ]);
            if (!$this->save($participant)) {
                  Log::write('debug', "Partecipante Mancante, errore salvataggio {$participant['email']}");
                  throw new \Exception('Errore salvataggio partecipante', $this->validationErrors);
            }
        } else {
            $participant_id = $participant->id;
            Log::write('debug', "Partecipante Esistente, $participant_id");
        }

        return $participant_id;
    }

    public function getLatestSurveyForUser($user_id): ?SurveyParticipant
    {
        $survey = $this->find()
        ->select(['survey_id', 'id'])
        ->where(['user_id' => $user_id])
        ->order(['survey_id DESC'])
        ->first();
        if (!empty($survey)) {
            return $survey;
        }

        return null;
    }

    public function countSent($survey_id)
    {
        $m = $this->fetchTable('EmailQueue');
        $res = $m->find()
            ->distinct(['email'])
            ->where([
              'campaign_id' => $survey_id,
              'sent' => 1,
            ])
            ->count();

        return $res;
    }

    public function countErrors($survey_id)
    {
        $m = $this->fetchTable('EmailQueue');
        $res = $m->find()
        ->where([
        'campaign_id' => $survey_id,
        'error is not' => null,
        'sent' => 0,
        ])
        ->count();

        return $res;
    }

  //Questa funzione serve per generare gli users che mancano, ma che hanno compilato la survey
  //A volte può succedere che vengano cancellati per varie ragioni

    public function generateMissingUsers($survey_id)
    {
        $this->Surveys = $this->getTableLocator()->get('Surveys');
        $survey = $this->Surveys
        ->find()
        ->where(['id' => $survey_id])
        ->first();
        if (empty($survey)) {
            throw new NotFoundException("Survey $survey not found");
        }

        $company_id = $survey->company_id;
        $this->Users = $this->getTableLocator()->get('Users');
        $missing_participants = $this->find()
            ->select(['SurveyParticipants.user_id'])
            ->where(['survey_id' => $survey_id])
            ->notMatching('Users', function ($q) {
                return $q;
            })->limit(1000);
        $count = $missing_participants->count();
        if (empty($missing_participants->toarray())) {
            return 'nessun partecipante mancante tra gli utenti';
        }

        $users = [];
      //Genero un utente anonimo per questa azienda per ogni partecipante mancante
        foreach ($missing_participants as $mp) {
            $user_id = $mp->user_id;
            $user = $this->Users->newEntity([
            'id' => $user_id,
            'username' => "$user_id@email.invalid",
            'email' => "$user_id@email.invalid",
            'password' => 'fake unguessable password (no string could have this as hashed value)',
            'first_name' => 'Partecipante',
            'last_name' => 'Anonimo',
            'active' => 0,
            'role' => 'user',
            'company_id' => $company_id,
            'years' => [(string)$survey['year']],
            ]);
            $users[] = $user;
        }

        if ($this->Users->saveMany($users)) {
            return "generati $count degli utenti mancanti";
        } else {
            return 'errore durante la generazione degli utenti mancanti';
        }
    }

    public function deleteCountAllCache($survey_id, $where = [])
    {
        $w = hash('crc32', serialize($where));
        $w = "count-$survey_id-$w";
        $count = Cache::delete($w, 'hour');
    }
}
