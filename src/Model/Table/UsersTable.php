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

use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Datasource\EntityInterface;
use Cake\Event\EventInterface;
use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use SecretField\Crypto\SecretBoxCipher;

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
            'cascadeCallbacks' => true,
        ]);
        $this->belongsTo('Companies');
        $this->belongsTo('Offices');
        $this->hasOne('Employees');
        $this->addBehavior('Timestamp');

        if (Configure::read('SecretField.key')) {
            $this->addBehavior('SecretField.SecretField', [
                'fields' => ['email'],
                'key' => (string)Configure::read('SecretField.key'),
                'prefix' => 'sf1$',
                'deterministic' => true,
                'failOpen' => false,
            ]);
        }

        $this->belongsToMany('Areas', [
            'joinTable' => 'areas_users',
            'dependent' => true,
        ])->setProperty('area');

        $this->hasMany('Answers', [
            'foreignKey' => 'user_id',
            'dependent' => true,
            'cascadeCallbacks' => true,
        ]);
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
        // Extract special conditions that need custom handling
        $survey_id = $conditions['SurveyParticipants.survey_id'] ?? null;
        $search = $conditions['search'] ?? null;
        $company_id = $conditions['Users.company_id'] ?? null;
        $role = $conditions['Users.role'] ?? null;
        $office_id = $conditions['Users.office_id'] ?? null;

        // Remove keys handled separately so they are not passed to ->where()
        unset($conditions['search'], $conditions['SurveyParticipants.survey_id']);

        if (isset($conditions['geocoded'])) {
            $geocoded = $conditions['geocoded'];
            unset($conditions['geocoded']);
        } else {
            $geocoded = null;
        }

        // Build the base query using contain instead of joins so that the
        // paginator counts unique users, not user-survey pairs.
        $query = $this->find()
            ->select($this)
            ->contain([
                'SurveyParticipants' => function ($q) {
                    return $q->contain(['Surveys' => function ($sq) {
                        return $sq->select(['Surveys.id', 'name', 'year', 'sending_mode']);
                    }]);
                },
                'Origins' => ['fields' => ['id', 'user_id', 'geohash', 'survey_id']],
                'Companies' => ['fields' => ['name']],
                // Keep Offices.id selected: UsersTablePolicy::beforeScope() joins Offices
                // for moma_area and Cake needs the association PK for contain hydration.
                'Offices' => ['fields' => ['id', 'name']],
            ]);

        // Filter by specific survey via subquery (avoids JOIN duplication)
        if ($survey_id) {
            $subQuery = $this->SurveyParticipants->find()
                ->select(['user_id'])
                ->where(['survey_id' => $survey_id]);
            $query->where(['Users.id IN' => $subQuery]);
        }

        // Geocoded filters via Origins subquery
        if ($geocoded === 'y') {
            $originCond = ['geohash IS NOT' => null];
            if ($survey_id) {
                $originCond['survey_id'] = $survey_id;
            }
            $subQuery = $this->Origins->find()->select(['user_id'])->where($originCond);
            $query->where(['Users.id IN' => $subQuery]);
        } elseif ($geocoded === 'n') {
            $originCond = ['geohash IS NOT' => null];
            if ($survey_id) {
                $originCond['survey_id'] = $survey_id;
            }
            $subQuery = $this->Origins->find()->select(['user_id'])->where($originCond);
            $query->where(['Users.id NOT IN' => $subQuery]);
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
        if ($search) {
            $searchConditions = [
                // Raw SQL string (numeric array key) on purpose: with a keyed
                // condition like ['CONCAT(first_name, " ", last_name) LIKE' => ...],
                // Datasources.*.quoteIdentifiers=true makes CakePHP's naive
                // "function" regex in Driver\SqlDialectTrait::quoteIdentifier()
                // treat the whole CONCAT() argument list (including the literal
                // " " separator) as a single column name and mangle it, causing
                // "Unknown column 'first_name, " ", last_name'" (1054). A plain
                // string element bypasses QueryExpression::_parseCondition() and
                // the identifier quoter entirely (same reason the JSON_CONTAINS
                // condition further down in this method is built this way).
                'CONCAT(first_name, \' \', last_name) LIKE :fullNameSearch',
                // username non è cifrato e contiene l'email in chiaro per la quasi
                // totalità degli utenti: consente la ricerca parziale (e, grazie alla
                // collation _ci, insensibile al maiuscolo) sull'email anche quando la
                // colonna email è cifrata e quindi non ricercabile con LIKE.
                ['Users.username LIKE' => "%$search%"],
            ];

            // Email search: with SecretField deterministic mode, only exact match works.
            if (Configure::read('SecretField.key')) {
                if (filter_var($search, FILTER_VALIDATE_EMAIL)) {
                    // Termine = email completa: cifro (modalità deterministica) per il
                    // match esatto, così copro anche gli utenti il cui username non
                    // coincide con l'email. La ricerca parziale resta gestita dallo
                    // username qui sopra.
                    $searchConditions[] = ['Users.email' => $this->emailSearchValue($search)];
                }
            } else {
                // SecretField not enabled: use LIKE for email
                $searchConditions[] = ['Users.email LIKE' => "%$search%"];
            }

            $query->where(['or' => $searchConditions]);
            $query->bind(':fullNameSearch', "%$search%", 'string');
        }

        if (is_numeric($year)) {
            $query->where(
                function ($exp) {
                    return $exp->add('(JSON_CONTAINS(Users.years, :year))');
                }
            );
            $query->bind(':year', json_encode($year), 'string');
        }

        $query
            ->where($conditions)
            ->order(['last_name', 'first_name', 'Users.company_id']);

        return $query;
    }

    /**
     * Override the magic findByEmail() finder so that an email lookup works when the
     * `email` column is encrypted at rest (SecretField deterministic mode).
     *
     * The stored value is ciphertext, so a plaintext `WHERE email = :email` never
     * matches. We encrypt the search term to match exactly (same approach as the
     * `index()` search). When SecretField is disabled the value is matched as-is.
     *
     * Used by UsersController::requestResetPassword() and the registration check.
     *
     * @param string|null $email Plaintext email to look up.
     * @return \Cake\ORM\Query
     */
    public function findByEmail($email): \Cake\ORM\Query
    {
        return $this->find()->where(['Users.email' => $this->emailSearchValue($email)]);
    }

    /**
     * Map a plaintext email to the value actually stored in the column: the
     * deterministic ciphertext when SecretField is enabled, otherwise the plaintext.
     * Use this for any exact-match lookup or uniqueness check on `email`.
     *
     * @param mixed $email Plaintext email (or any value).
     * @return mixed Value to match against the stored column.
     */
    public function emailSearchValue($email)
    {
        if (is_string($email) && $email !== '' && Configure::read('SecretField.key')) {
            $cipher = new SecretBoxCipher(
                (string)Configure::read('SecretField.key'),
                'sf1$',
                true,   // deterministic
                false   // failOpen
            );

            return $cipher->encrypt($email);
        }

        return $email;
    }

    /**
     * Elenco minimo di campi "di contesto" (azienda/sede/ruolo) per la scelta del
     * profilo, senza dati sensibili. Condiviso da UsersController e dai flussi IdP
     * esterni (es. Rer\\Controller\\IamController).
     *
     * @param \App\Model\Entity\User[] $users Utenti candidati.
     * @return array<int, array<string, mixed>>
     */
    public function buildProfileChoices(array $users): array
    {
        $ids = array_map(fn ($u) => $u->id, $users);
        $summaries = $this->find()
            ->select(['Users.id', 'Users.role', 'Users.company_id', 'Users.office_id'])
            ->contain([
                'Companies' => ['fields' => ['id', 'name']],
                'Offices' => ['fields' => ['id', 'name']],
            ])
            ->where(['Users.id IN' => $ids])
            ->indexBy('id')
            ->toArray();

        $choices = [];
        foreach ($users as $user) {
            $summary = $summaries[$user->id] ?? null;
            $choices[] = [
                'id' => $user->id,
                'role' => $summary->role ?? $user->role,
                'company_id' => $summary->company_id ?? $user->company_id,
                'company_name' => $summary->company->name ?? null,
                'office_id' => $summary->office_id ?? $user->office_id,
                'office_name' => $summary->office->name ?? null,
            ];
        }

        return $choices;
    }

    /**
     * Restituisce tutti gli utenti che condividono lo stesso valore per un campo
     * potenzialmente duplicato (es. `username`, `cf`). Da usare nei flussi di login
     * che NON passano dal componente Authentication (IdP esterni come IAM/SPID nel
     * plugin Rer, che leggono header e interrogano Users direttamente) per decidere
     * se autenticare direttamente (un solo risultato) o proporre la scelta del
     * profilo all'utente (più risultati) — stesso schema seguito, per i flussi
     * form/auth0, da App\Identifier\MultiProfilePasswordIdentifier e
     * MultiProfileAuth0Identifier tramite EncryptedOrmResolver::findAll().
     * Il campo da usare per ciascun contesto esterno, quando non deducibile dal
     * modello di autenticazione, è configurabile in
     * Configure::read('MultiProfileLogin.externalFields.<contesto>') (es. 'cf' per iam/spid).
     *
     * `email` viene cercato con il valore cifrato quando SecretField è abilitato
     * (vedi emailSearchValue()); gli altri campi (username, cf, ...) sono cercati in chiaro.
     *
     * @param string $field Colonna users.* su cui cercare i duplicati (es. 'cf').
     * @param mixed $value Valore da cercare.
     * @return \Cake\ORM\Query
     */
    public function findLoginCandidates(string $field, $value): \Cake\ORM\Query
    {
        if ($field === 'email') {
            $value = $this->emailSearchValue($value);
        }

        return $this->find()->where(["Users.$field" => $value]);
    }

    /**
     * Dopo un login riuscito su UN profilo, cerca eventuali ALTRI Users che
     * condividono la stessa email o lo stesso Codice Fiscale (se non vuoto) di
     * quel profilo. Copre un caso NON coperto dal controllo pre-autenticazione
     * fatto dagli identifier (App\Identifier\MultiProfilePasswordIdentifier /
     * MultiProfileAuth0Identifier), che confrontano solo sul campo di login
     * (email): per il login a form, quando due account con la stessa email hanno
     * password diverse, l'identifier "si risolve da solo" restituendo l'unico
     * match verificato, senza mai segnalare l'ambiguità. Qui la verifico a
     * prescindere dalla password già validata, e aggiungo il CF come secondo
     * segnale di duplicazione (stessa persona, account diversi su aziende
     * diverse, email differenti ma stesso CF).
     *
     * Restituisce SEMPRE almeno lo user passato: chi chiama valuta l'ambiguità
     * sul count() del risultato (>1 → più profili, da proporre in scelta, vedi
     * UsersController::login()/respondWithProfileChoice()).
     *
     * @param \App\Model\Entity\User $user Utente già autenticato con successo.
     * @return \App\Model\Entity\User[]
     */
    public function findSiblingProfiles($user): array
    {
        $orConditions = [];

        if (!empty($user->email)) {
            $orConditions[] = ['Users.email' => $this->emailSearchValue($user->email)];
        }

        $cf = is_string($user->cf) ? trim($user->cf) : null;
        if (!empty($cf)) {
            $orConditions[] = ['Users.cf' => $cf];
        }

        if (empty($orConditions)) {
            // Niente su cui matchare oltre all'id stesso: nessuna ambiguità possibile.
            return [$user];
        }

        return $this->find()
            ->where(['OR' => $orConditions])
            ->all()
            ->toArray();
    }

    /**
     * Uniqueness check for `email` that works with SecretField encryption.
     *
     * The generic `validateUnique`/`isUnique` compare the plaintext input against the
     * stored ciphertext and never match, so duplicates slip through once emails are
     * encrypted. This encrypts the candidate (deterministic mode) before checking.
     *
     * Usable both as a table-provider validation rule and from buildRules().
     *
     * @param mixed $value The email being validated.
     * @param array $context Validation context (provides `data` for the current row).
     * @return bool True when no other user already has this email.
     */
    public function validateEmailUnique($value, array $context = []): bool
    {
        if (!is_string($value) || $value === '') {
            return true; // email is optional; emptiness handled elsewhere
        }

        $query = $this->find()->where(['Users.email' => $this->emailSearchValue($value)]);

        $currentId = $context['data']['id'] ?? null;
        if (!empty($currentId)) {
            $query->where(['Users.id !=' => $currentId]);
        }

        return $query->count() === 0;
    }

    /**
     * Set defaults for new user entities before validation.
     */
    public function beforeMarshal(\Cake\Event\EventInterface $event, \ArrayObject $data, \ArrayObject $options): void
    {
        if (!isset($data['active'])) {
            $data['active'] = true;
        }
        if (!isset($data['expiration_date'])) {
            $data['expiration_date'] = date('Y-m-d', strtotime('+365 days'));
        }
    }

    /**
     * Controllo unico e riutilizzabile del formato del Codice Fiscale.
     *
     * Il CF è opzionale: un valore vuoto/null è considerato valido (assente).
     * Quando è presente deve essere di 16 caratteri alfanumerici (stesso pattern
     * usato in AdhesionRequestsTable). Usato dalla validazione e da tutti i punti
     * che scrivono `cf` (import HR, abbonamenti, SSO) per non salvare spazzatura.
     *
     * @param mixed $value Valore del Codice Fiscale (verrà trimmato).
     * @return bool True se vuoto/null oppure se rispetta ^[A-Z0-9]{16}$.
     */
    public static function isValidCf($value): bool
    {
        if ($value === null) {
            return true;
        }
        if (!is_scalar($value)) {
            return false;
        }
        $value = trim((string)$value);
        if ($value === '') {
            return true;
        }

        return (bool)preg_match('/^[A-Z0-9]{16}$/i', $value);
    }

    /**
     * Default validation rules
     */
    public function validationDefault(Validator $validator): Validator
    {
        // Email can be null in schema, but if provided must be valid and unique
        $validator
            ->email('email', false, __('Indirizzo email non valido'))
            ->allowEmptyString('email');
            /** Massimoi: 18 Settembre 2026 - Tolgo il controllo che la mail sia unica
             * sia unico per gestire la possibilità di avere più utenti con lo stesso profilo
             */
            /*->add('email', 'unique', [
                // Encryption-aware uniqueness (SecretField stores ciphertext, so the
                // generic validateUnique can't match the plaintext input).
                'rule' => 'validateEmailUnique',
                'provider' => 'table',
                'message' => __('Email già in uso'),
            ]);*/

        // Il Codice Fiscale è opzionale: vuoto/null è valido. Quando è presente deve
        // essere di 16 caratteri alfanumerici (vedi self::isValidCf()). Un unico
        // controllo di formato sostituisce min/maxLength per dare un messaggio chiaro.
        $validator
            ->scalar('cf')
            ->allowEmptyString('cf')
            ->add('cf', 'format', [
                'rule' => fn ($value) => self::isValidCf($value),
                'message' => __('Il Codice Fiscale deve essere di 16 caratteri alfanumerici'),
            ]);
            /** Massimoi: 18 Settembre 2026 - Tolgo il controllo che il codice fiscale
             * sia unico per gestire la possibilità di avere più utenti con lo stesso profilo
             */
            /*->add('cf', 'unique', [
                'rule' => 'validateUnique',
                'provider' => 'table',
                'message' => __('Codice Fiscale già in uso'),
                'on' => function ($context) {
                    return !empty($context['data']['cf']);
                },
            ]); */
        // role not empty for add and edit
        $validator
            ->notEmptyString('role', __('Ruolo utente obbligatorio'))
            // Rifiuta stringhe di ruolo arbitrarie (anti escalation VH1). La lista
            // include i ruoli tecnici sportello/pendolare creati dai plugin
            // Subscriptions/Testcommuters; i ruoli "canonici" gestibili dalla UI
            // restano \App\Model\Entity\User::ROLES.
            ->inList('role', \App\Model\Entity\User::ASSIGNABLE_ROLES, __('Ruolo non valido'));

        return $validator;
    }

    /**
     * Application integrity rules
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        // Encryption-aware email uniqueness (isUnique would compare plaintext against
        // the stored ciphertext and never match). cf is not encrypted, so plain isUnique.
        /* $rules->add(
            function ($entity) {
                if (empty($entity->email)) {
                    return true; // allowMultipleNulls
                }

                return $this->validateEmailUnique($entity->email, ['data' => ['id' => $entity->id]]);
            },
            'uniqueEmail',
            ['errorField' => 'email', 'message' => __('Email già in uso')]
        );
        $rules->add($rules->isUnique(['cf'], ['message' => __('Codice Fiscale già in uso'), 'allowMultipleNulls' => true]));
        */
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
