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

use Cake\ORM\Query;
use Cake\ORM\Table;

/**
 * Application specific Users Table with non plugin conform field(s)
 *
 * sono costretto ad usare 'Users' come nome perchè setTable non sembra essere applicato ...
 */
class UsersTable extends Table
{
    // TODO: aggiungere beforeFind per chi può vedere chi

    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setDisplayField('email');
        $this->hasMany('SurveyParticipants');
        $this->hasMany('Origins', [
            'foreignKey' => 'user_id',
            'dependent' => true,
        ]);
        $this->belongsTo('Companies');
        $this->belongsTo('Offices');
        $this->hasOne('Employees');
        $this->addBehavior('Timestamp');
        $this->belongsToMany('Areas', [
            'joinTable' => 'areas_users',
            'dependent' => true,
        ])->setProperty('area');

        $this->hasMany('Answers');
        $this->belongsToMany('Batches', [
            'joinTable' => 'batches_users',
            'foreignKey' => 'user_id',
            'targetForeignKey' => 'batch_id',
            'dependent' => true,
            'cascadeCallbacks' => true,
        ]);
    }

    // carica gli utenti con il dettaglio per la survey specificata (l'ultima a cui ha partecipato se non viene specificata)

    public function index($conditions, $year = null)
    {
        $survey_id = $conditions['SurveyParticipants.survey_id'] ?? null;
        $search = $conditions['search'] ?? null;
        $company_id = $conditions['Users.company_id'] ?? null;
        $role = $conditions['Users.role'] ?? null;
        $office_id = $conditions['Users.office_id'] ?? null;
        if (isset($conditions['geocoded'])) {
            $geocoded = $conditions['geocoded'];
            unset($conditions['geocoded']);
        } else {
            $geocoded = null;
        }

        if ($survey_id) { // tira su i soli utenti che hanno partecipato alla survey specificata (inner join)
            $query = $this->find();
            $query
                ->select($this)
                ->select($this->SurveyParticipants)
                ->select(['Surveys.name'])
                ->matching('SurveyParticipants', function ($q) use ($survey_id) {
                    return $q->where(['SurveyParticipants.survey_id' => $survey_id]);
                })
                ->matching('SurveyParticipants.Surveys');

            if ($geocoded == 'y') {
                $query->matching(
                    'Origins',
                    function (Query $q) use ($survey_id) {
                        return $q->where(['Origins.lat IS NOT' => null, 'Origins.survey_id' => $survey_id])->select(['id', 'user_id', 'lat', 'lon']);
                    }
                );
            } elseif ($geocoded == 'n') {
                $query->matching(
                    'Origins',
                    function (Query $q) use ($survey_id) {
                        return $q->where(['Origins.lat IS ' => null, 'Origins.survey_id IS' => $survey_id])->select(['id', 'user_id', 'lat', 'lon']);
                    }
                );
            }
        } else { // tira su tutti gli utenti con l'ultima survey (se esiste) a cui hanno partecipato
            $subquery = $this->SurveyParticipants->find();
            $subquery
                ->select(['last_survey_id' => $subquery->func()->max('SurveyParticipants.survey_id')])
                ->where(function ($exp, $q) {
                    return $exp->equalFields('Users.id', 'SurveyParticipants.user_id');
                });

            $query = $this->find();
            $query
                ->select($this)
                ->select($this->SurveyParticipants)
                ->select(['Surveys.name', 'Surveys.sending_mode'])
                ->leftJoinWith('SurveyParticipants')
                ->leftJoinWith('SurveyParticipants.Surveys');

            if ($geocoded == 'y') {
                $query->matching(
                    'Origins',
                    function (Query $q) {
                        return $q->where(['Origins.lat IS NOT' => null, 'Origins.survey_id IS' => null])->select(['id', 'user_id', 'lat', 'lon']);
                    }
                );
            } elseif ($geocoded == 'n') {
                $query->matching(
                    'Origins',
                    function (Query $q) {
                        return $q->where(['Origins.lat IS ' => null, 'Origins.survey_id IS' => null])->select(['id', 'user_id', 'lat', 'lon']);
                    }
                );
            }
        }

        if ($role) {
            $query->where(['Users.role' => $role]);
        }
        if ($company_id) {
            $query->where(['Users.company_id' => $company_id]);
        }
        if ($office_id) {
            $query->where(['Users.office_id' => $office_id]);
        }
        if ($search) { // questo if non fa niente
            $query->where(['or' => [
                ['CONCAT(first_name, " ", last_name) LIKE' => "%$search%"],
                ['Users.email LIKE' => "%$search%"],
            ]]);
        }
        if (!(empty($year) || $year == 'TUTTI')) {
            $query->where(
                function ($exp) {
                    return $exp->add('(JSON_CONTAINS(Users.years, :year) OR Users.years IS NULL)');
                // $conditions[] = $exp->add('JSON_CONTAINS(Users.years, :year)');
                // return $exp->or_([
                //     $exp->or_($conditions),
                //     $exp->isNull('Users.years')
                // ]);
                }
            );
            $query->bind(':year', json_encode($year), 'string');
        }

        $query
            ->select(['Companies.name', 'Offices.name'])
            ->contain([
                'Companies' => ['fields' => ['name']],
                'Offices' => ['fields' => ['name']],
            ])
            ->where($conditions)
            // ->bind(':year', json_encode($year), 'string')
            ->order(['last_name', 'first_name', 'Users.company_id']);

        //write the sql of the query for debug
        // print_r($query->sql()); die;
        return $query;
    }

    public function findForAuthentication(Query $query, array $options)
    {
        return $query->contain('Areas');
        // return $query->contain('Areas', function (Query $q) {
        //     $func = new FunctionsBuilder();
        //     $stAsText = $func->ST_AsText(['polygon'=>'literal']);
        //     return $q
        //         ->select(['id', 'polygon'=>$stAsText, 'city', 'province']);
        // });
    }

    public function anonymize($company_id = null)
    {
        $query = $this->updateQuery()
            ->set([
                "username =  CONCAT(id, '@email.invalid') ", //http://www.faqs.org/rfcs/rfc2606.html
                "email =  CONCAT(id, '@email.invalid') ", //http://www.faqs.org/rfcs/rfc2606.html
                'first_name' => 'Anonimo importato da HR',
                'last_name = SUBSTR(id, -10,10)',
            ])
            ->where([
                'role' => 'user',
                'first_name NOT IN' => ['Anonimo importato da HR', 'Partecipante'],
            ]);

        //Se mi hai specificato l'azienda anonimizzo solo quella
        if (!empty($company_id)) {
            $query->where(['company_id' => $company_id]);
        }

        //Eseguo la query di aggiornamento
        $c = $query->count();
        //debug($query->sql());
        $query->execute();

        return $c;
    }
}
