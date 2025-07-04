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

use App\Model\Entity\Origin;
use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\Http\Exception\NotFoundException;
use Cake\Log\Log;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Validation\Validator;
use Exception;
use Psy\Readline\Hoa\Console;

/**
 * Employees Model
 *
 * @property \Moma\Model\Table\UsersTable&\Cake\ORM\Association\BelongsTo $Users
 * @property \Moma\Model\Table\OfficesTable&\Cake\ORM\Association\BelongsTo $Offices
 * @method \Moma\Model\Entity\Employee newEmptyEntity()
 * @method \Moma\Model\Entity\Employee newEntity(array $data, array $options = [])
 * @method \Moma\Model\Entity\Employee[] newEntities(array $data, array $options = [])
 * @method \Moma\Model\Entity\Employee get($primaryKey, $options = [])
 * @method \Moma\Model\Entity\Employee findOrCreate($search, ?callable $callback = null, $options = [])
 * @method \Moma\Model\Entity\Employee patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \Moma\Model\Entity\Employee[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \Moma\Model\Entity\Employee|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \Moma\Model\Entity\Employee saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \Moma\Model\Entity\Employee[]|\Cake\Datasource\ResultSetInterface|false saveMany(iterable $entities, $options = [])
 * @method \Moma\Model\Entity\Employee[]|\Cake\Datasource\ResultSetInterface saveManyOrFail(iterable $entities, $options = [])
 * @method \Moma\Model\Entity\Employee[]|\Cake\Datasource\ResultSetInterface|false deleteMany(iterable $entities, $options = [])
 * @method \Moma\Model\Entity\Employee[]|\Cake\Datasource\ResultSetInterface deleteManyOrFail(iterable $entities, $options = [])
 */
class EmployeesTable extends Table
{
    /**
     * @var \Cake\ORM\Table
     */
    private $Origins;

    /**
     * Initialize method
     *
     * @param array $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('employees');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->belongsTo('Users', [
            'foreignKey' => 'user_id',
            'className' => 'Users',
        ]);
        // $this->hasOne('Users',[
        //     'dependent' => true,
        // ]);
        $this->belongsTo('Offices', [
            'foreignKey' => 'office_id',
            'className' => 'Offices',
        ]);
        $this->belongsTo('Origins', [
            'foreignKey' => 'origin_id',
            'className' => 'Origins',
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
            ->scalar('role_description')
            ->maxLength('role_description', 45)
            ->allowEmptyString('role_description');

        $validator
            ->scalar('orario')
            ->maxLength('orario', 45)
            ->allowEmptyString('orario');

        $validator
            ->scalar('gender')
            ->maxLength('gender', 1)
            ->allowEmptyString('gender');

        $validator
            ->date('dob')
            ->allowEmptyDate('dob');

        $validator
            ->boolean('shift')
            ->allowEmptyString('shift');

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
        // NON POSSO FARE IL CONTROLLO SULL'UTENTE PERCHÈ NEL CASO DI IMPIEGATI ANONIMI SETTO UN UUID CHE
        // NON HA CORRISPONDENZA NELLA TABELLA UTENTI
        //$rules->add($rules->existsIn(['user_id'], 'Users'), ['errorField' => 'user_id']);
        $rules->add($rules->existsIn(['office_id'], 'Offices'), ['errorField' => 'office_id']);

        return $rules;
    }

    public function import($employee, $companyId, $identity, $year = '2024')
    {
        if (!is_array($employee)) {
            throw new \Exception("L'impiegato non è un array");
        }

        $this->Offices = TableRegistry::getTableLocator()->get('Offices');
        $this->Origins = TableRegistry::getTableLocator()->get('Origins');
        $this->Users = TableRegistry::getTableLocator()->get('Users');

        $office_list = $this->Offices->find('list', ['valueField' => 'office_code'])
            ->where(['company_id' => $companyId, 'office_code IS NOT NULL'])
            ->toArray();
        $offices = array_flip($office_list);
        $sitedir = Configure::read('sitedir');

        $fieldMap = [
            1 => [
                'field' => 'employees.office_id',
                'formatter' => function ($val) use ($offices) {
                    return $offices[trim(strval($val))] ?? null;
                },
            ],
            2 => [
                'field' => 'employees.role_description',
                'formatter' => function ($val) {
                    return strtoupper(trim($val));
                },
            ],
            3 => [
                'field' => 'employees.orario',
                'formatter' => function ($val) {
                    return strtoupper(trim($val));
                },
            ],
            4 => [
                'field' => 'employees.gender',
                'formatter' => function ($val) {
                    if ($val == 'Female' || $val == 'F' || $val == 'f') {
                        return 'F';
                    }
                    if ($val == 'Male' || $val == 'M' || $val == 'm') {
                        return 'M';
                    }
                },
            ],
            5 => [
                'field' => 'employees.dob',
                'formatter' => function ($val) {
                    if (is_string($val)) {
                        $msg = 'Errore nella colonna Data di nascita  : ' . json_encode($val) . ' il formato deve essere una data';
                        Log::write('error', $msg);
                        throw new NotFoundException('Errore nella colonna Data di nascita  : ' . json_encode($val) . ' il formato deve essere una data');

                        return null;
                    } else {
                        return date('Y-m-d', \PhpOffice\PhpSpreadsheet\Shared\Date::excelToTimestamp($val));
                    }
                },
            ],
            6 => [
                'field' => 'origins.address',
                'formatter' => function ($val) {
                    return strtoupper(trim(strval($val)));
                },
            ],
            7 => [
                'field' => 'origins.city',
                'formatter' => function ($val) {
                    return strtoupper(trim($val));
                },
            ],
            8 => [
                'field' => 'origins.province',
                'formatter' => function ($val) {
                    try {
                        return substr(strval(trim($val)), 0, 2);
                    } catch (Exception $e) {
                        Log::debug("Errore nella decodifica della provincia $val");

                        return $val;
                    }
                },
            ],
            9 => [
                'field' => 'origins.postal_code',
            ],
            10 => [
                'field' => 'employees.shift',
                'formatter' => function ($val) {
                    return $val == 'Turni' || $val == 'turni' ? 1 : 0;
                },
            ],
        ];
        if ($sitedir == '5T') {
            $fieldMap[11] = [
                'field' => 'users.subcompany',
                'formatter' => function ($val) {
                    return strtoupper(trim($val));
                },
            ];
            $fieldMap[15] = [
                'field' => 'users.cf',
                'formatter' => function ($val) {
                    return strtoupper(trim($val));
                },
            ];
        } else {
            $fieldMap[11] = [
                'field' => 'users.email',
                'formatter' => function ($val) {
                    return strtolower(trim($val));
                },
            ];
            $fieldMap[12] = [
                'field' => 'users.first_name',
                'formatter' => function ($val) {
                    return strtoupper(trim($val));
                },
            ];
            $fieldMap[13] = [
                'field' => 'users.last_name',
                'formatter' => function ($val) {
                    return strtoupper(trim($val));
                },
            ];
            $fieldMap[14] = [
                'field' => 'users.subcompany',
                'formatter' => function ($val) {
                    return strtoupper(trim($val));
                },
            ];
            $fieldMap[15] = [
                'field' => 'users.cf',
                'formatter' => function ($val) {
                    return strtoupper(trim($val));
                },
            ];
        }

        $formatted = [];
        foreach ($fieldMap as $col => $def) {
            $field = explode('.', $def['field']);
            $formatter = $def['formatter'] ?? function ($val) {
                return $val;
            };
            $formatted[$field[0]][$field[1]] = isset($employee[$col]) ? $formatter($employee[$col]) : null;
        }

        if (!isset($formatted['employees']['office_id']) || $formatted['employees']['office_id'] == null) {
            throw new \Exception('Impossibile Trovare la sede ' . $employee[1]);
        }

        //Uid
        $uid = \Cake\Utility\Text::uuid();

        //Creo/cerco l'utente e l'employee
        if (!empty($formatted['users']['email'])) {
            $user = $this->Users->find()
                ->where(['email' => $formatted['users']['email']])
                ->first();
        } else {
            $formatted['users']['email'] = $uid . '@email.invalid';  //http://www.faqs.org/rfcs/rfc2606.html
        }

        if (empty($formatted['users']['first_name']) && empty($formatted['users']['last_name'])) {
            $formatted['users']['first_name'] = 'Anonimo importato da HR';
            $formatted['users']['last_name'] = substr($uid, -10);
        }

        //Se non c'è l'utente devo crearlo
        if (empty($user)) {
            $yearArray = [];
            array_push($yearArray, $year);
            // $identity = $this->Authentication->getIdentity();
            $batch =[
                'username' => $identity->get('username'),
                'user_id' => $identity->getIdentifier(),
                'created' => date('Y-m-d H:i:s'),
                'modified' => date('Y-m-d H:i:s'),
            ];
            $batchEntity = $this->Users->Batches->newEntity($batch);
            if(!$this->Users->Batches->save($batchEntity)) {
                $info = $batchEntity->getErrors();
                $msg = 'Error in Users Batches new entity : ' . json_encode($info);
                Log::write('error', $msg);
                throw new NotFoundException('Users Batches error : ' . json_encode($info));
            }
            $user = $this->Users->newEntity([
                'username' => $formatted['users']['email'],
                'email' => $formatted['users']['email'],
                'password' => 'fake unguessable password (no string could have this as hashed value)',
                'active' => false,
                'role' => 'user',
                'company_id' => $companyId,
                'office_id' => $formatted['employees']['office_id'],
                'first_name' => $formatted['users']['first_name'],
                'last_name' => $formatted['users']['last_name'],
                'subcompany' => $formatted['users']['subcompany'],
                'cf' => $formatted['users']['cf'],
                'id' => $uid,    //Forzo l'id lato php così viene uguale alla mail
                'years' => $yearArray,
                'batches' => [
                ['id' => $batchEntity->id]
                ],
            ]);

            $user_id = $user->id;
            // create batches
            
        } else { //C'è già un utente con questa mail: associo l'utente a questo employee
            $user->office_id =  $formatted['employees']['office_id'];
            $user->company_id = $companyId;
            $user->office_id = $formatted['employees']['office_id'];
            $user->first_name = $formatted['users']['first_name'];
            $user->last_name = $formatted['users']['last_name'];
            $user->subcompany = $formatted['users']['subcompany'];
            $user->cf = $formatted['users']['cf'];
            $user_id = $user->id;
            // $user->years = date('Y');
            // error if $user->years is not an array
            if (!is_array($user->years)) {
                if (!empty($user->years)) {
                    if ($user->years == $year) {
                        $user->years = [];
                        array_push($user->years, $year);
                        $user->years = $user->years;
                    } else {
                        $info = 'Error in Users new entity : ' . $user->email . ' years is not an array';
                        $msg = 'Error in Users new entity : ' . json_encode($info);
                        Log::write('error', $msg);
                        throw new NotFoundException('Users error : ' . json_encode($info));
                    }
                } else {
                    $user->years = [];
                    array_push($user->years, $year);
                    $user->years = $user->years;
                }
            } else {
                // check year in array
                if (!in_array($year, $user->years)) {
                    array_push($user->years, $year);
                    $user->years = $user->years;
                }
            }
            // array_push($user->years,$year);
            // $user->years=$user->years;
        }

        if (!$this->Users->save($user)) {
            $info = $user->getErrors();
            $msg = 'Error in Users new entity : ' . json_encode($info);
            Log::write('error', $msg);
            throw new NotFoundException('Users error : ' . json_encode($info));
        }

        // se c'è già un'origine per questo utente la riciclo, se no la creo
        $origin = $this->Origins->findByUserId($user_id)->where(['survey_id IS' => null])->first();
        if ($origin) {
            $formatted['origins']['id'] = $origin->id;
        }

        // salva l'origine
        $formatted['origins']['user_id'] = $user_id;
        $formatted['origins']['company_id'] = (int)$companyId;
        // $formatted['origins']['company_id'] = (int)$companyId;
        if (empty($origin)) {
            $origin = $this->Origins->newEntity($formatted['origins']);
        }

        if (!$this->Origins->save($origin)) {
            $info = $origin->getErrors();
            $msg = 'Error in Origins new entity : ' . json_encode($info);
            Log::write('error', $msg);
            throw new NotFoundException('Origins error : ' . json_encode($info));
        }

        // salva l'impiegato
        // se c'è già un impiegato per questo utente la riciclo, se no la creo
        $employee = $this->findByUserId($user_id)->contain(['Origins'])->where(['survey_id IS' => null])->first();
        // $employee = $this->findByUserId($user_id)->where(['survey_id IS' => null])->first();
        if ($employee) {
            $formatted['employees']['id'] = $employee->id;
        }

        $formatted['employees']['user_id'] = $user_id;
        // $formatted['employees']['office_id'] = (int)$companyId;
        //2020-12-09 massimo e marco al telefono - togliamo il loop?
        //No, perchè se non c'è lo user (nel caso di employee anonimo)
        //bisogna poter collegare l'origin con l'employee
        $formatted['employees']['origin_id'] = $origin->id;
        if (empty($employee)) {
            $employee = $this->newEntity($formatted['employees']);
        }
        // if($employee['employees']['origin_id']!= $origin->id){
        //   $employee['employees']['origin_id']= $origin->id;
        // }

        if (!$this->save($employee)) {
            $info = $employee->getErrors();
            $msg = 'Error in Employee new entity : ' . json_encode($info) . ' Value: ' . json_encode($formatted['employees']);
            Log::write('error', $msg);
            throw new NotFoundException('Employees error : ' . json_encode($info) . ' Value: ' . json_encode($formatted['employees']));
        }
    }

    //Aggiunge il filtro per l'anno alla query degli impiegati che gli passi
    private function filterByYear($employees, $year)
    {
        if (!empty($year) && $year != 'TUTTI') {
            $employees = $employees->matching('Users', function ($employees) use ($year) {
                return $employees->where(
                    function ($exp) {
                        return $exp->add('JSON_CONTAINS(Users.years, :year)');
                    }
                );
            });
            $employees->bind(':year', json_encode($year), 'string');
        }

        return $employees;
    }

    //Restituisce le statistiche di una azienda in base ai suoi impiegati
    public function getStats($office_id, $year)
    {
        $connection = ConnectionManager::get('default');

        //Carico la posizione dell'ufficio
        $this->Offices = TableRegistry::getTableLocator()->get('Offices');
        $office = $this->Offices->find()
            ->select(['lat', 'lon'])
            ->where(['id' => $office_id])
            ->first();

        //Estraggo gli impiegati e le origini collegate
        $employees = $this->find()
            ->contain(['Origins', 'Users'])
            ->where(['Employees.office_id' => $office_id]);
        $employees = $this->filterByYear($employees, $year);

        //Calcolo il numero di impiegati complessivo
        $num_impiegati = $employees->count();
        Log::debug("$num_impiegati ");

        //Calcolo il numero di impiegati per sesso
        $employees = $this->find()
            ->contain(['Origins', 'Users'])
            ->select(['Employees.user_id'])
            ->where(['Employees.office_id' => $office_id]);
        $employees = $this->filterByYear($employees, $year);
        $num_impiegati_sesso = $employees->group(['gender'])
            ->select(['count' => $employees->func()->count('*'), 'gender']);

        Log::debug(json_encode($num_impiegati_sesso->toArray()));

        //Calcolo il numero di impiegati per città (ordine decrescente)
        $employees = $this->find()
            ->contain(['Origins', 'Users'])
            ->where(['Employees.office_id' => $office_id]);
        $employees = $this->filterByYear($employees, $year);

        $num_impiegati_citta = $employees->group(['Origins.city'])
            ->select([
                'count' => $employees->func()->count('*'),
                'Origins.city',
                'Origins.lat',
                'Origins.lon',
            ])
            ->order(['count' => 'DESC']);

        $num_impiegati_citta = $connection
            ->execute(
                'SELECT 
                        count( * ) as count,
                        origins.city,
                        max(origins.lat) as lat,
                        max(origins.lon) as lon,
                        ROUND(2 * 6371 * ASIN(SQRT(POWER(SIN((lat - abs(:olat)) * pi()/180 / 2), 2) + COS(:olat * pi()/180 ) * COS(lat * pi()/180) * POWER(SIN((lon - :olon) * pi()/180 / 2), 2))),0) 
                         as distance
                        FROM 
                            employees left join origins on employees.origin_id = origins.id
                            left join users on (employees.user_id = users.id and JSON_CONTAINS(users.years, :year))
                        WHERE employees.office_id = :id
                        GROUP BY origins.city
                        ORDER BY count DESC
                        ',
                ['id' => $office_id, 'olat' => $office->lat, 'olon' => $office->lon, 'year' => json_encode($year)]
            )
            ->fetchAll('assoc');

        Log::debug(json_encode($num_impiegati_citta));

        //Calcola la distanza media degli impiegati, escludendo i percentili più alti e più bassi
        $distanza_media_pesata = round($this->calcDistanzaMedia($num_impiegati_citta));

        //Calcolo il numero di impiegati per turno
        $employees = $this->find()
            ->contain(['Users'])
            ->select(['Employees.user_id'])
            ->where(['Employees.office_id' => $office_id]);
        $employees = $this->filterByYear($employees, $year);

        $num_impiegati_orario = $employees->group(['Employees.orario'])
            ->select(['count' => $employees->func()->count('*'), 'Employees.orario'])
            ->order(['count' => 'DESC']);

        Log::debug(json_encode($num_impiegati_orario->toArray()));

        //Calcolo il numero di impiegati per turno e città
        $employees = $this->find()
            ->contain(['Origins', 'Users'])
            ->where(['Employees.office_id' => $office_id]);
        $employees = $this->filterByYear($employees, $year);

        $num_impiegati_orario_citta = $employees
            ->select([
                'count' => $employees->func()->count('*'),
                'Employees.orario',
                'Origins.city',
                'Employees.user_id',
                'Employees.origin_id',
                'Users.id'
            ])
            ->group(['Employees.orario', 'Origins.city'])
            ->order(['count' => 'DESC'])
            ->toArray();

        foreach ($num_impiegati_orario_citta as &$n) {
            if ($n['origin'] == null) {
                $n['origin'] = new Origin(['city' => '--']);
            }
        }
        Log::debug(json_encode($num_impiegati_orario_citta));

        //Calcolo il numero di impiegati per gruppi ordinati per anno di nascita
        $num_impiegati_anno = $connection
            ->execute(
                'SELECT     CONCAT( 
                            (YEAR(CURDATE()) - YEAR(employees.dob) - (RIGHT(CURDATE(), 5) < RIGHT(employees.dob, 5))) DIV 10 * 10,
                            "-",
                            (YEAR(CURDATE()) - YEAR(employees.dob) - (RIGHT(CURDATE(), 5) < RIGHT(employees.dob, 5))) DIV 10 * 10 + 9
                        )
                         as decade,
                        count( (YEAR(CURDATE()) - YEAR(employees.dob) - (RIGHT(CURDATE(), 5) < RIGHT(employees.dob, 5))) DIV 10 * 10 ) as count
                        FROM 
                            employees 
                            join users on (employees.user_id = users.id and JSON_CONTAINS(users.years, :year))
                        WHERE employees.office_id = :id
                        GROUP BY (YEAR(CURDATE()) - YEAR(employees.dob) - (RIGHT(CURDATE(), 5) < RIGHT(employees.dob, 5))) DIV 10 * 10
                        ORDER BY (YEAR(CURDATE()) - YEAR(employees.dob) - (RIGHT(CURDATE(), 5) < RIGHT(employees.dob, 5))) DIV 10 * 10 DESC
                        ',
                ['id' => $office_id, 'year' => json_encode($year)]
            )
            ->fetchAll('assoc');

        Log::debug(json_encode($num_impiegati_anno));

        //Calcolo il numero di impiegati per ruolo
        $employees = $this->find()
            ->contain(['Users'])
            ->select(['Employees.user_id', 'Employees.role_description'])
            ->where(['Employees.office_id' => $office_id]);
        $employees = $this->filterByYear($employees, $year);

        $num_impiegati_ruolo = $employees->group(['Employees.role_description'])
            ->select(['count' => $employees->func()->count('*'), 'Employees.role_description'])
            ->order(['count' => 'DESC']);
        Log::debug(json_encode($num_impiegati_ruolo->toArray()));

        //Calcolo il numero di impiegati per orario
        $employees = $this->find()
            ->contain(['Users'])
            ->select(['Employees.user_id'])
            ->where(['Employees.office_id' => $office_id]);
        $employees = $this->filterByYear($employees, $year);

        $num_impiegati_orario = $employees->group(['Employees.orario'])
            ->select([
                'count' => $employees->func()->count('*'),
                'Employees.orario',
            ])
            ->order(['count' => 'DESC']);
        Log::debug(json_encode($num_impiegati_orario->toArray()));

        return [
            'num_impiegati' =>  $num_impiegati,
            'num_impiegati_sesso' => $num_impiegati_sesso->toArray(),
            'num_impiegati_citta' => $num_impiegati_citta,
            'num_impiegati_orario' => $num_impiegati_orario->toArray(),
            'num_impiegati_orario_citta' => $num_impiegati_orario_citta,
            'num_impiegati_anno' => $num_impiegati_anno,
            'num_impiegati_ruolo' => $num_impiegati_ruolo->toArray(),
            'num_impiegati_orario' => $num_impiegati_orario->toArray(),
            'distanza_media_pesata' => $distanza_media_pesata,
        ];
    }

    /**
     * Calcola la distanza media degli impiegati, escludendo i percentili più alti e più bassi
     */
    private function calcDistanzaMedia($impiegati)
    {
        $lowerPercentile = 0; // escludiamo il 5% dei valori più bassi
        $upperPercentile = 90; // escludiamo il 5% dei valori più alti

        // ordiniamo l'array in base alla distanza in ordine crescente
        usort($impiegati, function ($a, $b) {
            return $a['distance'] - $b['distance'];
        });

        // calcoliamo il numero di valori da escludere
        $lowerCount = round(count($impiegati) * $lowerPercentile / 100);
        $upperCount = round(count($impiegati) * $upperPercentile / 100);

        // calcoliamo la somma dei valori rimanenti
        $sum = 0;
        $count = 0;
        for ($i = $lowerCount; $i < $upperCount; $i++) {
            $sum += $impiegati[$i]['count'] * $impiegati[$i]['distance'];
            $count += $impiegati[$i]['count'];
        }
        // calcoliamo la media pesata
        if ($count == 0) {
            $weightedAverage = 0;
        } else {
            $weightedAverage = $sum / $count;
        }

        return $weightedAverage;
    }
}
