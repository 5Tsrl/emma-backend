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

use App\Exception\EmployeeImportRowException;
use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\Datasource\EntityInterface;
use Cake\Event\EventInterface;
use Cake\Http\Exception\NotFoundException;
use Cake\Log\Log;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\Validation\Validator;

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
    private $Offices;
    private $Users;

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

    public function afterSaveCommit(EventInterface $event, EntityInterface $entity, \ArrayObject $options): void
    {
        Cache::clearGroup('employee_stats');
    }

    public function afterDeleteCommit(EventInterface $event, EntityInterface $entity, \ArrayObject $options): void
    {
        Cache::clearGroup('employee_stats');
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

        // Per ogni nome di campo indico una funzione di formatting + validazione.
        // Attenzione: se vengono aggiunti dei campi nel settings (EmployeeImportFields)
        // bisogna aggiungere anche qui la funzione altrimenti si prende il campo così com'è
        // (nessuna validazione, comportamento di fallback pre-esistente).
        $fields = Configure::read('EmployeeImportFields');
        // Se non sono definiti i campi non posso importare ed esco
        if (empty($fields)) {
            Log::write('error', 'EmployeeImportFields non definito in settings.php');
            throw new NotFoundException('EmployeeImportFields non definito in settings.php');
        }

        // Elenco canonico delle province ISTAT (unica fonte di verità, riusata
        // anche da Geocoder::__construct() tramite bare require - vedi
        // src/Geocoder/province.php).
        $provinces = require __DIR__ . '/../../Geocoder/province.php';
        // Direzione nome -> sigla, costruita UNA sola volta a partire dallo stesso
        // array (nessuna struttura duplicata): permette a 'origins.province' di
        // accettare anche il nome completo della provincia (es. "Milano"), non solo
        // la sigla a 2 lettere. mb_strtoupper (non strtolower/strtoupper, che sono
        // byte-unsafe sugli accenti) normalizza sia le chiavi qui sia il valore
        // grezzo nell'handler, per un confronto case-insensitive corretto anche sui
        // nomi multi-parola accentati (es. "Forlì-Cesena", "Monza e della Brianza").
        $provinceNameToCode = [];
        // Direzione nome (senza parole di collegamento) -> sigla, per tollerare
        // varianti come "MONZA E BRIANZA" al posto del nome canonico "Monza e
        // della Brianza" - vedi normalizeProvinceNameForFuzzyMatch().
        $provinceNameToCodeFuzzy = [];
        foreach ($provinces as $code => $name) {
            $provinceNameToCode[mb_strtoupper($name, 'UTF-8')] = $code;
            $provinceNameToCodeFuzzy[self::normalizeProvinceNameForFuzzyMatch($name)] = $code;
        }
        $districts = (array)Configure::read('Districts');

        // Ogni handler riceve il valore GREZZO della cella e restituisce
        // ['value' => <valore formattato>, 'error' => null|<messaggio>].
        // A differenza del vecchio $formatter (che veniva chiamato dentro un
        // try/catch che azzerava silenziosamente il campo su qualunque
        // Throwable), qui la validazione è esplicita: un errore viene
        // raccolto e NON interrompe subito il giro, in modo da poter
        // segnalare TUTTI i campi non validi di una riga in un colpo solo
        // (vedi il loop più sotto).
        $fieldHandlers = [
            'users.badge_number' => function ($val) {
                $trimmed = is_scalar($val) ? trim((string)$val) : '';
                if ($trimmed === '') {
                    // Sempre stato facoltativo (vedi 'badge_number' => $formatted['users']['badge_number'] ?? null
                    // più sotto): molte aziende non usano un sistema di matricole.
                    return ['value' => null, 'error' => null];
                }
                if (self::looksLikeDateShape($trimmed) || filter_var($trimmed, FILTER_VALIDATE_EMAIL)) {
                    return [
                        'value' => $trimmed,
                        'error' => 'Numero di matricola non valido: sembra una data o un indirizzo email, ' .
                            'non "' . $trimmed . '"',
                    ];
                }

                return ['value' => $trimmed, 'error' => null];
            },
            'employees.office_id' => function ($val) use ($offices) {
                $normalize = function ($s) {
                    return strtolower(str_replace(' ', '', trim((string)$s)));
                };
                $map = [];
                foreach ($offices as $k => $v) {
                    $map[$normalize($k)] = $v;
                }
                $resolved = $map[$normalize($val)] ?? null;
                if ($resolved === null) {
                    return ['value' => null, 'error' => 'Impossibile trovare la sede "' . trim((string)$val) . '"'];
                }

                return ['value' => $resolved, 'error' => null];
            },
            'employees.role_description' => function ($val) {
                $trimmed = trim((string)$val);
                if ($trimmed !== '' && self::looksLikeMisplacedValue($trimmed)) {
                    return [
                        'value' => strtoupper($trimmed),
                        'error' => 'Mansione (role_description) non valida: sembra una data, ' .
                            'un orario o un CAP ("' . $trimmed . '")',
                    ];
                }

                return ['value' => strtoupper($trimmed), 'error' => null];
            },
            'employees.orario' => function ($val) {
                $trimmed = trim((string)$val);
                if ($trimmed !== '' && self::looksLikeMisplacedOrarioValue($trimmed)) {
                    return [
                        'value' => strtoupper($trimmed),
                        'error' => 'Orario di lavoro non valido: sembra una data, un CAP o un indirizzo email, ' .
                            'non "' . $trimmed . '"',
                    ];
                }

                return ['value' => strtoupper($trimmed), 'error' => null];
            },
            'employees.gender' => function ($val) {
                $trimmed = trim((string)$val);
                if ($trimmed === '') {
                    return ['value' => null, 'error' => null];
                }
                // Confronto case-insensitive esplicito (non in_array($v, [...], true),
                // che è case-sensitive): un file HR reale ha prodotto "MASCHILE"/
                // "FEMMINILE" (italiano, tutto maiuscolo), oltre alle varianti
                // inglesi/sigla già gestite.
                $upper = mb_strtoupper($trimmed, 'UTF-8');
                if (in_array($upper, ['FEMALE', 'F', 'FEMMINILE'], true)) {
                    return ['value' => 'F', 'error' => null];
                }
                if (in_array($upper, ['MALE', 'M', 'MASCHILE'], true)) {
                    return ['value' => 'M', 'error' => null];
                }

                return [
                    'value' => null,
                    'error' => 'Genere non valido: usare M o F (o Male/Female), non "' . $trimmed . '"',
                ];
            },
            'employees.dob' => function ($val) use ($year) {
                if ($val === null || trim((string)$val) === '') {
                    return ['value' => null, 'error' => null];
                }
                if (is_numeric($val)) {
                    $isoDate = date('Y-m-d', \PhpOffice\PhpSpreadsheet\Shared\Date::excelToTimestamp($val));
                } elseif (is_string($val) && preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', trim($val), $m)) {
                    [, $day, $month, $dobYear] = $m;
                    if (!checkdate((int)$month, (int)$day, (int)$dobYear)) {
                        return [
                            'value' => null,
                            'error' => 'Data di nascita non valida (data inesistente nel calendario): "' . trim($val) . '"',
                        ];
                    }
                    $isoDate = sprintf('%04d-%02d-%02d', (int)$dobYear, (int)$month, (int)$day);
                } else {
                    return [
                        'value' => null,
                        'error' => 'Formato data di nascita non valido: usare gg/mm/aaaa (es. 14/10/1996), ' .
                            'non ' . json_encode($val),
                    ];
                }

                // Età plausibile (18-90 anni compresi), calcolata al 31/12 dell'anno di
                // IMPORTAZIONE ($year, il parametro passato a import() - può essere un
                // anno passato, non necessariamente quello corrente), non alla data odierna.
                // Il limite superiore era 75 ma un import reale (un cliente,
                // 2026-08-11) ha rigettato un dipendente di 80 anni
                // realmente in forza (es. un medico/dirigente sanitario senior) - vedi
                // docs/dev-notes/employee-import-validation.md.
                $age = self::ageAtYearEnd($isoDate, $year);
                if ($age < 18 || $age > 90) {
                    return [
                        'value' => null,
                        'error' => 'Data di nascita non plausibile: età calcolata di ' . $age . ' anni al 31/12/' .
                            (is_numeric($year) ? (int)$year : $year) . ' (deve essere compresa tra 18 e 90 anni), ' .
                            'dob "' . trim((string)$val) . '"',
                    ];
                }

                return ['value' => $isoDate, 'error' => null];
            },
            'origins.address' => function ($val) {
                $trimmed = trim((string)$val);
                if ($trimmed === '') {
                    return ['value' => null, 'error' => 'Indirizzo di residenza mancante'];
                }
                if (self::looksLikeMisplacedValue($trimmed, false)) {
                    return [
                        'value' => strtoupper($trimmed),
                        'error' => 'Indirizzo di residenza non valido: sembra un orario o un CAP ("' . $trimmed . '")',
                    ];
                }

                return ['value' => strtoupper($trimmed), 'error' => null];
            },
            'origins.district' => function ($val) use ($districts) {
                $trimmed = trim((string)$val);
                if ($trimmed === '') {
                    return ['value' => null, 'error' => null];
                }
                $normalized = strtoupper($trimmed);
                $districtsNorm = array_map('strtoupper', array_map('trim', $districts));
                if (!in_array($normalized, $districtsNorm, true)) {
                    return ['value' => null, 'error' => 'Quartiere non valido: "' . $trimmed . '"'];
                }

                return ['value' => $normalized, 'error' => null];
            },
            'origins.city' => function ($val) {
                $trimmed = trim((string)$val);
                if ($trimmed === '') {
                    return ['value' => null, 'error' => 'Comune di residenza mancante'];
                }
                if (self::looksLikeMisplacedValue($trimmed, false)) {
                    return [
                        'value' => strtoupper($trimmed),
                        'error' => 'Comune di residenza non valido: sembra un orario o un CAP ("' . $trimmed . '")',
                    ];
                }

                return ['value' => strtoupper($trimmed), 'error' => null];
            },
            'origins.province' => function ($val) use ($provinces, $provinceNameToCode, $provinceNameToCodeFuzzy) {
                $trimmed = trim((string)$val);
                if ($trimmed === '') {
                    return ['value' => null, 'error' => null];
                }
                $normalized = mb_strtoupper($trimmed, 'UTF-8');
                // 1) Sigla ISTAT a 2 lettere già valida (percorso invariato).
                if (array_key_exists($normalized, $provinces)) {
                    return ['value' => $normalized, 'error' => null];
                }
                // 2) Nome completo della provincia (case-insensitive) - normalizzato
                // alla sua sigla a 2 lettere invece di essere rifiutato.
                if (array_key_exists($normalized, $provinceNameToCode)) {
                    return ['value' => $provinceNameToCode[$normalized], 'error' => null];
                }
                // 3) Nome completo con parole di collegamento diverse/mancanti
                // (es. "MONZA E BRIANZA" al posto del nome canonico "Monza e della
                // Brianza") - confrontato dopo aver rimosso le parole di
                // collegamento italiane e la punteggiatura. Vedi
                // normalizeProvinceNameForFuzzyMatch().
                $fuzzy = self::normalizeProvinceNameForFuzzyMatch($trimmed);
                if ($fuzzy !== '' && array_key_exists($fuzzy, $provinceNameToCodeFuzzy)) {
                    return ['value' => $provinceNameToCodeFuzzy[$fuzzy], 'error' => null];
                }

                return [
                    'value' => $normalized,
                    'error' => 'Provincia non valida: "' . $trimmed . '" ' .
                        '(usare la sigla ISTAT a 2 lettere, es. TO, MI, RM, oppure il nome per esteso, ' .
                        'es. Torino, Milano, Roma)',
                ];
            },
            'origins.postal_code' => function ($val) {
                $trimmed = trim((string)$val);
                if ($trimmed === '') {
                    return ['value' => null, 'error' => null];
                }
                if (!preg_match('/^\d{5}$/', $trimmed)) {
                    return [
                        'value' => $trimmed,
                        'error' => 'CAP non valido: "' . $trimmed . '" (deve essere un codice postale italiano di 5 cifre)',
                    ];
                }

                return ['value' => $trimmed, 'error' => null];
            },
            'employees.shift' => function ($val) {
                if (is_string($val)) {
                    return ['value' => strtolower(trim($val)) == 'turni' ? 1 : 0, 'error' => null];
                }

                return ['value' => 0, 'error' => null];
            },
            'users.email' => function ($val) {
                $trimmed = strtolower(trim((string)$val));
                if ($trimmed !== '' && !filter_var($trimmed, FILTER_VALIDATE_EMAIL)) {
                    return [
                        'value' => $trimmed,
                        'error' => 'Email non valida: "' . $trimmed . '" (correggere l\'indirizzo nel file HR)',
                    ];
                }

                return ['value' => $trimmed, 'error' => null];
            },
            'users.first_name' => function ($val) {
                $trimmed = trim((string)$val);
                if ($trimmed !== '' && self::looksLikeMisplacedValue($trimmed)) {
                    return [
                        'value' => strtoupper($trimmed),
                        'error' => 'Nome non valido: sembra una data, un orario o un CAP ("' . $trimmed . '")',
                    ];
                }

                return ['value' => strtoupper($trimmed), 'error' => null];
            },
            'users.last_name' => function ($val) {
                $trimmed = trim((string)$val);
                if ($trimmed !== '' && self::looksLikeMisplacedValue($trimmed)) {
                    return [
                        'value' => strtoupper($trimmed),
                        'error' => 'Cognome non valido: sembra una data, un orario o un CAP ("' . $trimmed . '")',
                    ];
                }

                return ['value' => strtoupper($trimmed), 'error' => null];
            },
            'users.subcompany' => function ($val) {
                $trimmed = trim((string)$val);
                if ($trimmed !== '' && self::looksLikeMisplacedValue($trimmed)) {
                    return [
                        'value' => strtoupper($trimmed),
                        'error' => 'Ragione sociale (subcompany) non valida: sembra una data, ' .
                            'un orario o un CAP ("' . $trimmed . '")',
                    ];
                }

                return ['value' => strtoupper($trimmed), 'error' => null];
            },
            'users.cf' => function ($val) {
                $trimmed = strtoupper(trim((string)$val));
                if (!UsersTable::isValidCf($trimmed)) {
                    return [
                        'value' => $trimmed,
                        'error' => 'Codice Fiscale non valido: "' . $trimmed . '" (deve essere di 16 caratteri alfanumerici)',
                    ];
                }

                return ['value' => $trimmed === '' ? null : $trimmed, 'error' => null];
            },
        ];

        // Guardia sulla lunghezza della riga: una riga malformata/troncata a metà
        // (il bug originale che questa funzionalità è nata per intercettare) fa
        // atterrare un valore nella colonna sbagliata in modo silenzioso - qui la
        // riga viene scartata subito se ha MENO colonne di quelle configurate in
        // EmployeeImportFields ($fields, non $fieldMap: deve contare anche
        // eventuali placeholder null saltati sopra).
        // UNICA eccezione tollerata: un file HR il cui template non ha MAI incluso
        // una o più colonne FINALI facoltative (es. un file HR da 15
        // colonne quando la configurazione del sito ne prevede 16, l'ultima delle
        // quali è users.cf) - una riga più corta è accettata solo se, spostandosi
        // all'indietro dalla FINE dell'elenco configurato, ogni colonna mancante è
        // "value-optional" (il suo handler non genera errore quando invocato con un
        // valore assente/nullo - vedi isFieldOptionalWhenAbsent()) o un placeholder
        // null nella configurazione stessa. Una colonna NON in coda (cioè seguita da
        // almeno una colonna presente nella riga) non è mai interessata da questa
        // eccezione: la sua POSIZIONE resta fissa per ogni colonna realmente
        // presente nella riga, si tratta solo di tollerare l'ASSENZA totale delle
        // colonne finali.
        // Una riga PIÙ LUNGA del previsto NON viene rifiutata da questa guardia:
        // le celle oltre $fields sono semplicemente ignorate dal loop di
        // $fieldMap più sotto, esattamente come prima di questa modifica - è un
        // comportamento intenzionale e già coperto da test pre-esistenti
        // (vedi test*ShapeIgnoresColumnsBeyond... in EmployeesTableImportTest,
        // che dipendono esplicitamente dal fatto che colonne extra oltre la
        // shape configurata del sito vengano ignorate, non rifiutate).
        $configuredColumnCount = count($fields);
        $rowColumnCount = count($employee);
        if ($rowColumnCount < $configuredColumnCount) {
            $lengthOk = true;
            for ($i = $rowColumnCount; $i < $configuredColumnCount; $i++) {
                $tailFieldName = $fields[$i] ?? null;
                if ($tailFieldName !== null && !self::isFieldOptionalWhenAbsent($tailFieldName, $fieldHandlers)) {
                    $lengthOk = false;
                    break;
                }
            }
            if (!$lengthOk) {
                throw new EmployeeImportRowException([[
                    'field' => null,
                    'message' => 'Riga non valida: la riga ha ' . $rowColumnCount . ' colonne, ma la ' .
                        'configurazione di importazione del sito ne prevede ' . $configuredColumnCount .
                        ' (verificare che il file HR non abbia colonne mancanti o spostate)',
                ]]);
            }
        }

        // Costruisci fieldMap: colonna CSV (posizionale) => nome campo (es. "employees.dob")
        $fieldMap = [];
        foreach ($fields as $col => $field) {
            if ($field === null) {
                continue;
            }
            $fieldMap[$col] = $field;
        }

        // Valuta OGNI campo presente nella configurazione del sito, raccogliendo
        // TUTTI gli errori di validazione (non ci si ferma al primo) - solo alla
        // fine, se c'è almeno un errore, la riga viene scartata in blocco.
        $formatted = [];
        $errors = [];
        foreach ($fieldMap as $col => $fieldName) {
            $field = explode('.', $fieldName);
            $handler = $fieldHandlers[$fieldName] ?? function ($val) {
                return ['value' => $val, 'error' => null];
            };
            $raw = $employee[$col] ?? null;
            try {
                $result = $handler($raw);
            } catch (\Throwable $e) {
                Log::debug("Errore nella formattazione del campo $fieldName: " . $e->getMessage());
                $result = ['value' => null, 'error' => null];
            }
            $formatted[$field[0]][$field[1]] = $result['value'];
            if (!empty($result['error'])) {
                $errors[] = ['field' => $fieldName, 'message' => $result['error']];
            }
        }

        if (!empty($errors)) {
            throw new EmployeeImportRowException($errors);
        }

        //Uid
        $uid = \Cake\Utility\Text::uuid();

        //Creo/cerco l'utente e l'employee
        if (!empty($formatted['users']['email'])) {
            $user = $this->Users->find()
                ->where([
                    'OR' => [
                        // username non è cifrato e per la quasi totalità degli utenti
                        // coincide con l'email in chiaro (match case-insensitive
                        // grazie alla collation _ci).
                        ['username' => $formatted['users']['email']],
                        // email è cifrato a riposo (SecretField deterministico):
                        // cifro il termine per il match ciphertext-vs-ciphertext.
                        ['email' => $this->Users->emailSearchValue($formatted['users']['email'])],
                    ],
                ])
                ->first();
        } else {
            $formatted['users']['email'] = $uid . '@email.invalid';  //http://www.faqs.org/rfcs/rfc2606.html
        }

        if (empty($formatted['users']['first_name']) && empty($formatted['users']['last_name'])) {
            $formatted['users']['first_name'] = 'Anonimo importato da HR';
            $formatted['users']['last_name'] = substr($uid, -10);
        }

        // Il formato del Codice Fiscale è già stato validato nel loop sopra
        // (users.cf handler): qui resta solo la normalizzazione a null quando assente.
        $cf = $formatted['users']['cf'] ?? null;

        //Se non c'è l'utente devo crearlo
        if (empty($user)) {
            $yearArray = [];
            array_push($yearArray, $year);

            $batch = [
                'username' => $identity->get('email'),
                'user_id' => $identity->getIdentifier(),
                'created' => date('Y-m-d H:i:s'),
                'modified' => date('Y-m-d H:i:s'),
            ];
            $batchEntity = $this->Users->Batches->newEntity($batch);
            if (!$this->Users->Batches->save($batchEntity)) {
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
                'subcompany' =>  $formatted['users']['subcompany'] ?? null,
                'cf' => $cf,
                'id' => $uid,    //Forzo l'id lato php così viene uguale alla mail
                'years' => $yearArray,
                'badge_number' => $formatted['users']['badge_number'] ?? null,
                'batches' => [
                    ['id' => $batchEntity->id],
                ],
            ], [
                // Flusso fidato (import HR): l'entity User blocca questi campi al
                // mass-assignment per evitare escalation dalla UI, ma qui sono leciti.
                'accessibleFields' => [
                    'id' => true,
                    'role' => true,
                    'company_id' => true,
                    'office_id' => true,
                    'password' => true,
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
            // Non sovrascrivere un CF esistente con un valore vuoto/assente nel file.
            if ($cf !== null) {
                $user->cf = $cf;
            }
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
        $origin = $this->Origins->findByUserId($user_id)->where(['survey_id IS' => null, 'year' => $year])->first();
        if ($origin) {
            $formatted['origins']['id'] = $origin->id;
        }

        // salva l'origine
        $formatted['origins']['user_id'] = $user_id;
        $formatted['origins']['company_id'] = (int)$companyId;
        // and year
        $formatted['origins']['year'] = $year;
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

    /**
     * Shape-guard usato dai validatori di import() per intercettare un valore
     * "scivolato" da una colonna vicina (es. un orario o un CAP finiti nella
     * cella dell'indirizzo/comune/mansione/nome per colonne CSV disallineate).
     * `$includeDate` è false per i campi dove una data è un valore plausibile
     * (es. un indirizzo "14" civico non è un problema, ma una vera e propria
     * shape "gg/mm/aaaa" nella cella indirizzo/comune non ha senso comunque -
     * per ora `$includeDate` è usato solo per escludere il controllo data dai
     * campi address/city, dove non è nell'elenco richiesto).
     *
     * @param mixed $val Valore grezzo (già stringificabile) da controllare.
     * @param bool $includeDate Se true, considera invalida anche una shape data (gg/mm/aaaa).
     * @return bool
     */
    private static function looksLikeMisplacedValue($val, bool $includeDate = true): bool
    {
        if ($includeDate && self::looksLikeDateShape($val)) {
            return true;
        }

        return self::looksLikeTimeRangeShape($val) || self::looksLikeCapShape($val);
    }

    /**
     * Riconosce una data in formato gg/mm/aaaa (o gg/m/aa, non valida qui
     * calendaristicamente - solo la SHAPE, il controllo calendario vero è in
     * 'employees.dob'). Usata per intercettare una data finita per errore in
     * una colonna non-data (es. nome, mansione).
     *
     * @param mixed $val Valore grezzo da controllare.
     * @return bool
     */
    private static function looksLikeDateShape($val): bool
    {
        return (bool)preg_match('/^\d{1,2}\/\d{1,2}\/\d{2,4}$/', trim((string)$val));
    }

    /**
     * Riconosce un intervallo orario tipo "9-17" o "09:00-18:00". Usato SOLO
     * come controllo NEGATIVO/shape-guard per gli altri campi (es. indirizzo,
     * comune) - NON è più usato come controllo positivo per employees.orario
     * (vedi looksLikeMisplacedOrarioValue(): un intervallo orario è contenuto
     * legittimo/atteso in quel campo, non un errore).
     *
     * @param mixed $val Valore grezzo da controllare.
     * @return bool
     */
    private static function looksLikeTimeRangeShape($val): bool
    {
        return (bool)preg_match('/^\d{1,2}([:.]\d{2})?\s*-\s*\d{1,2}([:.]\d{2})?$/', trim((string)$val));
    }

    /**
     * Riconosce un CAP italiano a 5 cifre, anche con il suffisso decimale
     * ",00"/".00" che compare quando un CAP finisce (per errore di
     * disallineamento colonne) in una cella che un foglio Excel aveva
     * formattato come numero.
     *
     * @param mixed $val Valore grezzo da controllare.
     * @return bool
     */
    private static function looksLikeCapShape($val): bool
    {
        return (bool)preg_match('/^\d{5}(?:[.,]\d{1,2})?$/', trim((string)$val));
    }

    /**
     * Shape-guard specifico per employees.orario. A differenza di
     * looksLikeMisplacedValue(), qui un valore a intervallo orario (9-17,
     * 09:00-18:00, ...) NON è un segnale di errore - è esattamente il
     * contenuto atteso. Un'analisi di 600+ valori distinti realmente presenti
     * in produzione (demo_5t_moma_angelcake.employees.orario) conferma che il
     * campo è testo libero specifico per ogni azienda/ente (es. "full-time",
     * "turni", "H24", "SU TURNO", "6-12;12-18;18-24;24-06", "orario 9-18",
     * perfino un bare "TORINO" 66 volte, probabile artefatto storico di
     * disallineamento colonne ma non affrontato qui perché la validazione
     * riguarda solo import futuri) - una regex positiva sull'intervallo
     * orario rifiuterebbe erroneamente la stragrande maggioranza di questi
     * valori legittimi. Qui si intercetta solo un valore chiaramente
     * scivolato da un'altra colonna vicina: una data (gg/mm/aaaa), un CAP, o
     * un indirizzo email.
     *
     * @param mixed $val Valore grezzo da controllare.
     * @return bool
     */
    private static function looksLikeMisplacedOrarioValue($val): bool
    {
        $trimmed = trim((string)$val);

        return self::looksLikeDateShape($trimmed)
            || self::looksLikeCapShape($trimmed)
            || (bool)filter_var($trimmed, FILTER_VALIDATE_EMAIL);
    }

    /**
     * Normalizza un nome di provincia per un confronto tollerante alle "parole di collegamento" italiane
     * (e, di, del, della, dell', nell', nel) e alla punteggiatura, usato da 'origins.province' per accettare
     * varianti come "MONZA E BRIANZA" al posto del nome canonico "Monza e della Brianza" (caso reale
     * riscontrato in un import HR: 2026-08-11 - vedi
     * docs/dev-notes/employee-import-validation.md). Algoritmo: mb_strtoupper (accent-safe) - sostituzione di
     * ogni carattere che non sia una lettera Unicode o uno spazio (apostrofi, trattini, ecc.) con uno spazio -
     * split su spazi - rimozione dei token che sono parole di collegamento - riunione con un singolo spazio.
     * Applicato SIA al valore grezzo in ingresso SIA a ciascun nome canonico in src/Geocoder/province.php
     * (stessa funzione, stesso risultato per lo stesso nome), quindi il confronto resta simmetrico.
     * Verificato manualmente che questo non crea collisioni tra province DIVERSE nell'elenco ISTAT attuale:
     * le uniche voci multi-parola che contengono una parola di collegamento sono "Monza e della Brianza"
     * (diventa "MONZA BRIANZA") e "Pesaro e Urbino" (diventa "PESARO URBINO"), token set entrambi univoci.
     * Coppie potenzialmente ambigue come "Reggio Calabria" e "Reggio Emilia" non contengono alcuna parola
     * di collegamento e restano quindi invariate (e distinte) dopo la normalizzazione. Se in futuro l'elenco
     * ISTAT cambiasse e si creasse un'ambiguità reale, va preferito il confronto esatto (byte-per-byte) per
     * quella coppia specifica piuttosto che indovinare.
     *
     * @param string $name Nome grezzo (valore in ingresso o nome canonico) da normalizzare.
     * @return string
     */
    private static function normalizeProvinceNameForFuzzyMatch(string $name): string
    {
        $connectors = ['E', 'DI', 'DEL', 'DELLA', 'DELL', 'NELL', 'NEL'];
        $upper = mb_strtoupper(trim($name), 'UTF-8');
        $noPunctuation = preg_replace('/[^\p{L}\s]/u', ' ', $upper);
        $tokens = array_filter(
            preg_split('/\s+/', trim((string)$noPunctuation)),
            function ($token) use ($connectors) {
                return $token !== '' && !in_array($token, $connectors, true);
            }
        );

        return implode(' ', $tokens);
    }

    /**
     * Calcola l'età (anni interi compiuti) al 31/12 dell'anno indicato -
     * NON alla data odierna. Usata da 'employees.dob' per validare la
     * plausibilità dell'età (18-75 anni compresi): l'import può processare
     * dati storici di un anno passato, quindi l'età va giudicata rispetto
     * a QUELL'anno, non a `date('Y')`. Il 31/12 è scelto come riferimento
     * perché rappresenta l'ultimo giorno in cui una persona può ancora
     * risultare impiegata "durante" l'anno importato.
     *
     * @param string $isoDate Data di nascita in formato Y-m-d.
     * @param string|int|null $year Anno di riferimento (di norma il $year passato a import());
     *   se non numerico si usa l'anno corrente come fallback.
     * @return int
     */
    private static function ageAtYearEnd(string $isoDate, $year): int
    {
        $refYear = is_numeric($year) ? (int)$year : (int)date('Y');
        $reference = \DateTimeImmutable::createFromFormat('Y-m-d', sprintf('%04d-12-31', $refYear));
        $dob = \DateTimeImmutable::createFromFormat('Y-m-d', $isoDate);

        return $dob->diff($reference)->y;
    }

    /**
     * Usata dalla guardia sulla lunghezza della riga in import() per decidere se
     * una colonna FINALE completamente assente da una riga più corta del previsto
     * può essere tollerata. Risponde invocando l'handler del campo con un valore
     * nullo (esattamente il valore che `$employee[$col] ?? null` produrrebbe già
     * per una colonna mancante) e verificando che non generi un errore - non un
     * elenco statico di nomi campo, cosicché la risposta resti sempre sincronizzata
     * con `$fieldHandlers` anche se un handler viene modificato in futuro (un
     * elenco hardcoded si "arrugginirebbe" silenziosamente in quel caso).
     * Un campo privo di handler dedicato (fallback identity, mai in errore) è
     * considerato "optional": non riceve comunque alcuna validazione anche quando
     * la colonna È presente, quindi tollerarne l'assenza non introduce alcun
     * rischio nuovo.
     *
     * @param string $fieldName Nome campo dot-path (es. "users.cf").
     * @param array<string, callable> $fieldHandlers Lo stesso $fieldHandlers costruito in import().
     * @return bool
     */
    private static function isFieldOptionalWhenAbsent(string $fieldName, array $fieldHandlers): bool
    {
        $handler = $fieldHandlers[$fieldName] ?? function ($val) {
            return ['value' => $val, 'error' => null];
        };
        try {
            $probe = $handler(null);
        } catch (\Throwable $e) {
            return false;
        }

        return empty($probe['error']);
    }

    /**
     * Conta gli impiegati di una sede, opzionalmente filtrando per anno.
     *
     * L'anno NON è una colonna di `employees`: l'appartenenza di una persona a un
     * anno è registrata su `users.years` (JSON multi-valore, indicizzato da
     * `idx_users_years_mv`). Riusa {@see self::filterByYear()} per il join su Users.
     *
     * @param int $office_id Id della sede.
     * @param string|int|null $year Anno di riferimento; se null/non numerico conta tutti gli impiegati.
     * @return int
     */
    public function countForOffice($office_id, $year = null): int
    {
        $q = $this->find()->where(['Employees.office_id' => (int)$office_id]);

        return $this->filterByYear($q, $year)->count();
    }

    //Aggiunge il filtro per l'anno alla query degli impiegati che gli passi

    private function filterByYear($employees, $year)
    {
        if (is_numeric($year)) {
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
        $oid = (int)$office_id;
        $batch = $this->getStatsBatch([$oid], $year);

        return $batch[$oid] ?? $this->emptyStats();
    }

    public function emptyStats(): array
    {
        return [
            'num_impiegati' => 0,
            'num_impiegati_sesso' => [],
            'num_impiegati_citta' => [],
            'num_impiegati_orario' => [],
            'num_impiegati_orario_citta' => [],
            'num_impiegati_anno' => [],
            'num_impiegati_ruolo' => [],
            'distanza_media_pesata' => 0,
        ];
    }

    /**
     * Restituisce le statistiche aggregate per un insieme di office_id in una manciata di query,
     * invece di eseguire ~8 query per ufficio. Il risultato è indicizzato per office_id.
     */
    public function getStatsBatch(array $office_ids, $year): array
    {
        $office_ids = array_values(array_unique(array_map('intval', $office_ids)));
        if (empty($office_ids)) {
            return [];
        }

        $stats = [];
        foreach ($office_ids as $oid) {
            $stats[$oid] = $this->emptyStats();
        }

        // 1) Totale impiegati per ufficio
        foreach ($this->aggregateCount($office_ids, $year) as $r) {
            $stats[(int)$r['office_id']]['num_impiegati'] = (int)$r['c'];
        }

        // 2) Sesso
        foreach ($this->aggregateMetric($office_ids, $year, 'Employees.gender', 'gender') as $r) {
            $stats[(int)$r['office_id']]['num_impiegati_sesso'][] = [
                'count' => (int)$r['count'],
                'gender' => $r['gender'],
            ];
        }

        // 3) Orario
        foreach ($this->aggregateMetric($office_ids, $year, 'Employees.orario', 'orario') as $r) {
            $stats[(int)$r['office_id']]['num_impiegati_orario'][] = [
                'count' => (int)$r['count'],
                'orario' => $r['orario'],
            ];
        }

        // 4) Ruolo
        foreach ($this->aggregateMetric($office_ids, $year, 'Employees.role_description', 'role_description') as $r) {
            $stats[(int)$r['office_id']]['num_impiegati_ruolo'][] = [
                'count' => (int)$r['count'],
                'role_description' => $r['role_description'],
            ];
        }

        // 5) Orario + città (raw SQL: shape annidata { origin: { city } } per il frontend)
        foreach ($this->fetchOrarioCittaRows($office_ids, $year) as $r) {
            $stats[(int)$r['office_id']]['num_impiegati_orario_citta'][] = [
                'count' => (int)$r['count'],
                'orario' => $r['orario'],
                'origin' => ['city' => $r['city'] ?? '--'],
            ];
        }

        // 6) Città + haversine distance (raw SQL: serve offices.lat/lon nel join)
        foreach ($this->fetchCityHaversineRows($office_ids, $year) as $r) {
            $stats[(int)$r['office_id']]['num_impiegati_citta'][] = [
                'count' => (int)$r['count'],
                'city' => $r['city'],
                'lat' => $r['lat'],
                'lon' => $r['lon'],
                'distance' => $r['distance'],
            ];
        }

        // 7) Decade anagrafica (raw SQL)
        foreach ($this->fetchDecadeRows($office_ids, $year) as $r) {
            $stats[(int)$r['office_id']]['num_impiegati_anno'][] = [
                'decade' => $r['decade'],
                'count' => (int)$r['count'],
            ];
        }

        // 8) Distanza media pesata, calcolata in PHP dal bucket città di ogni ufficio
        foreach ($stats as $oid => &$s) {
            $s['distanza_media_pesata'] = (int)round($this->calcDistanzaMedia($s['num_impiegati_citta']));
        }

        return $stats;
    }

    private function aggregateCount(array $office_ids, $year): array
    {
        $q = $this->find();
        $q->select([
            'office_id' => 'Employees.office_id',
            'c' => $q->func()->count('*'),
        ])
        ->where(['Employees.office_id IN' => $office_ids])
        ->group(['Employees.office_id']);

        return $this->filterByYear($q, $year)->disableHydration()->toArray();
    }

    private function aggregateMetric(array $office_ids, $year, string $column, string $alias): array
    {
        $q = $this->find();
        $q->select([
            'office_id' => 'Employees.office_id',
            'count' => $q->func()->count('*'),
            $alias => $column,
        ])
        ->where(['Employees.office_id IN' => $office_ids])
        ->group(['Employees.office_id', $column])
        ->order(['Employees.office_id' => 'ASC', 'count' => 'DESC']);

        return $this->filterByYear($q, $year)->disableHydration()->toArray();
    }

    private function fetchOrarioCittaRows(array $office_ids, $year): array
    {
        $idList = implode(',', $office_ids);
        $hasYear = is_numeric($year);
        $userJoin = $hasYear
            ? 'INNER JOIN users ON employees.user_id = users.id AND JSON_CONTAINS(users.years, :year)'
            : '';

        $sql = "SELECT
                    employees.office_id,
                    employees.orario,
                    origins.city,
                    COUNT(*) AS count
                FROM employees
                LEFT JOIN origins ON employees.origin_id = origins.id
                $userJoin
                WHERE employees.office_id IN ($idList)
                GROUP BY employees.office_id, employees.orario, origins.city
                ORDER BY employees.office_id ASC, count DESC";

        $params = $hasYear ? ['year' => json_encode((string)(int)$year)] : [];

        return ConnectionManager::get('default')->execute($sql, $params)->fetchAll('assoc');
    }

    private function fetchCityHaversineRows(array $office_ids, $year): array
    {
        $idList = implode(',', $office_ids);
        $hasYear = is_numeric($year);
        $userJoin = $hasYear
            ? 'LEFT JOIN users ON employees.user_id = users.id AND JSON_CONTAINS(users.years, :year)'
            : '';
        $userWhere = $hasYear ? 'AND JSON_CONTAINS(users.years, :year)' : '';

        // origins.lat / origins.lon were dropped in migration
        // 20260508100000_CleanupOriginsLatLon.  Coordinates now live encoded in
        // origins.geohash; decode them inline via MySQL spatial functions.
        // For SRID 4326 (WGS84), ST_X returns latitude and ST_Y returns longitude.
        $latExpr = 'ST_X(ST_PointFromGeoHash(origins.geohash, 4326))';
        $lonExpr = 'ST_Y(ST_PointFromGeoHash(origins.geohash, 4326))';

        $sql = "SELECT
                    employees.office_id,
                    origins.city,
                    COUNT(*) AS count,
                    MAX($latExpr) AS lat,
                    MAX($lonExpr) AS lon,
                    ROUND(
                        2 * 6371 * ASIN(SQRT(
                            POWER(SIN((MAX($latExpr) - ABS(offices.lat)) * pi()/180 / 2), 2)
                            + COS(offices.lat * pi()/180)
                              * COS(MAX($latExpr) * pi()/180)
                              * POWER(SIN((MAX($lonExpr) - offices.lon) * pi()/180 / 2), 2)
                        )), 0
                    ) AS distance
                FROM employees
                LEFT JOIN origins ON employees.origin_id = origins.id
                INNER JOIN offices ON employees.office_id = offices.id
                $userJoin
                WHERE employees.office_id IN ($idList) $userWhere
                GROUP BY employees.office_id, origins.city
                ORDER BY employees.office_id ASC, count DESC";

        $params = $hasYear ? ['year' => json_encode((string)(int)$year)] : [];

        return ConnectionManager::get('default')->execute($sql, $params)->fetchAll('assoc');
    }

    private function fetchDecadeRows(array $office_ids, $year): array
    {
        $idList = implode(',', $office_ids);
        $hasYear = is_numeric($year);
        $userJoin = $hasYear
            ? 'INNER JOIN users ON employees.user_id = users.id AND JSON_CONTAINS(users.years, :year)'
            : '';

        $decade = '(YEAR(CURDATE()) - YEAR(employees.dob) - (RIGHT(CURDATE(), 5) < RIGHT(employees.dob, 5))) DIV 10 * 10';

        $sql = "SELECT
                    employees.office_id,
                    CONCAT($decade, '-', $decade + 9) AS decade,
                    COUNT(*) AS count
                FROM employees
                $userJoin
                WHERE employees.office_id IN ($idList)
                GROUP BY employees.office_id, $decade
                ORDER BY employees.office_id ASC, $decade DESC";

        $params = $hasYear ? ['year' => json_encode((string)(int)$year)] : [];

        return ConnectionManager::get('default')->execute($sql, $params)->fetchAll('assoc');
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
