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

use App\Model\Entity\AdhesionRequest;
use App\Validation\ItalianFiscalValidator;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * AdhesionRequests Model
 *
 * Tabella JSON-driven: la submission completa del form di adesione vive nella colonna
 * `data` (json). Solo i campi su cui il backend interroga/fa join/opera sono colonne
 * reali. Vedi la migrazione CreateAdhesionRequests.
 *
 * @property \App\Model\Table\UsersTable&\Cake\ORM\Association\BelongsTo $Users
 * @property \App\Model\Table\CompaniesTable&\Cake\ORM\Association\BelongsTo $Companies
 * @property \App\Model\Table\AdhesionRequestPeopleTable&\Cake\ORM\Association\HasMany $People
 * @method \App\Model\Entity\AdhesionRequest newEmptyEntity()
 * @method \App\Model\Entity\AdhesionRequest newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\AdhesionRequest[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\AdhesionRequest get($primaryKey, $options = [])
 * @method \App\Model\Entity\AdhesionRequest patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\AdhesionRequest|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\AdhesionRequest saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 */
class AdhesionRequestsTable extends Table
{
    // fetchTable() (usato da companyTypeIdForSchoolType() per interrogare CompanyTypes,
    // che questa Table non associa) non è fornito da Cake\ORM\Table di per sé in questa
    // versione di CakePHP: va importato esplicitamente, stesso pattern già usato da
    // SurveyParticipantsTable per lo stesso motivo.
    use LocatorAwareTrait;

    /**
     * Province della Regione Emilia-Romagna: unico insieme ammesso per
     * `reference_province`, che è la chiave del routing territoriale.
     *
     * @var array<int, string>
     */
    public const RER_PROVINCES = ['BO', 'FE', 'FC', 'MO', 'PR', 'PC', 'RA', 'RE', 'RN'];

    /**
     * Numero massimo di persone abilitabili per richiesta (richiesta del committente:
     * 5). Il frontend (`maxPeople` in `mixing/adesioneForm.js`) lo rispecchia ma questa
     * regola resta l'autorità, perché il form è pubblico e anonimo.
     *
     * @var int
     */
    public const MAX_PEOPLE = 5;

    /**
     * Numero massimo di righe ammesse in `district_companies` (Allegato 3, MM di distretto).
     *
     * Stessa logica anti-abuso di MAX_PEOPLE, su un array annidato del payload pubblico.
     *
     * @var int
     */
    public const MAX_DISTRICT_COMPANIES = 50;

    /**
     * Mappa Tipologia (Allegato 4, istituto scolastico) -> `company_types.id`.
     *
     * Id pinnati sul seed corrente (config/Seeds/CompanyTypesSeed.php, riconfermati via
     * MySQL su `demo_emma_rer` il 2026-09-03): 4 Scuola Superiore, 5 Scuola Media
     * Inferiore, 6 Scuola Primaria, 7 Scuola Materna. "Università o Accademia" (id 3) è
     * deliberatamente esclusa: l'Allegato 4 riguarda solo le scuole e le sue quattro
     * caselle Tipologia non includono quell'opzione. Su un ambiente il cui seed avesse
     * id diversi, companyTypeIdForSchoolType() degrada a null (mai un'eccezione) e
     * AdhesionRequestsController::resolveCompanyType() ripiega sul comportamento
     * preesistente — mai peggio di oggi.
     *
     * @var array<string, int>
     */
    public const SCHOOL_TYPE_COMPANY_TYPE_IDS = [
        'infanzia' => 7,
        'primaria' => 6,
        'secondaria_primo_grado' => 5,
        'secondaria_secondo_grado' => 4,
    ];

    /**
     * Allow-list delle chiavi ammesse in `data`.
     *
     * Il form pubblico accetta un array arbitrario: senza allow-list qualunque campo
     * inviato verrebbe persistito (e poi riversato in `companies.additional_data`).
     * Elencare le chiavi attese e scartare il resto è l'inverso di quanto si faceva prima.
     *
     * @var array<int, string>
     */
    public const ALLOWED_DATA_KEYS = [
        // Tipo organizzazione
        'organization_type',
        // Rappresentante legale
        'legal_rep_first_name', 'legal_rep_last_name',
        'legal_rep_qualifica', 'legal_rep_powers_reference', 'legal_rep_cf', 'legal_rep_email',
        // Organizzazione
        'company_name', 'legal_form', 'legal_address_city', 'legal_address_street',
        'legal_address_number', 'legal_address_cap', 'operational_address', 'fiscal_code',
        'vat_number', 'ateco', 'num_employees', 'pec', 'email', 'phone', 'mobile',
        // Specifici RER
        'registry_territory', 'registry_number', 'district_companies',
        'uses_district_mm', 'district_mm_appointment_reference', 'district_relationship_type',
        'district_employer_company', 'district_external_entity',
        // Identità del mobility manager di distretto (Allegato 3)
        'district_mm_first_name', 'district_mm_last_name', 'district_mm_cf',
        'district_mm_email', 'district_mm_phone',
        // Riferimento territoriale
        'reference_city', 'reference_province',
        // Mobility manager / referente
        'mm_last_name', 'mm_first_name', 'mm_cf', 'mm_email', 'mm_phone',
        'mm_relationship', 'mm_appointment_reference',
        // Classificazione ai fini dell'obbligo PSCL
        'pscl_obligation_status',
        // Istituto scolastico (Allegato 4)
        'school_mechanographic_code', 'school_type', 'school_legal_nature',
        'mandate_extension', 'school_principal_appointment_reference',
        // Dichiarazioni e luogo
        'accept_guidelines', 'accept_gdpr', 'accept_dpr445',
        'accept_data_processor_nomination', 'accept_comune_verification', 'place',
    ];

    /**
     * Lunghezze massime applicate ai campi testuali che finiscono in `data`.
     *
     * La colonna è `json NOT NULL` senza vincoli: senza questi limiti un payload da
     * megabyte sarebbe accettato. Le voci con un limite di colonna reale (reference_city
     * 45, reference_province 2, ateco 20) usano quel valore.
     *
     * @var array<string, int>
     */
    public const DATA_MAX_LENGTHS = [
        'legal_rep_first_name' => 255, 'legal_rep_last_name' => 255,
        'legal_rep_powers_reference' => 255, 'legal_rep_cf' => 16, 'legal_rep_email' => 255,
        'company_name' => 255, 'legal_form' => 255, 'legal_address_city' => 255,
        'legal_address_street' => 255, 'legal_address_number' => 20, 'legal_address_cap' => 10,
        'operational_address' => 255,
        'fiscal_code' => 16, 'vat_number' => 13, 'ateco' => 20,
        'pec' => 255, 'email' => 255, 'phone' => 45, 'mobile' => 45,
        'registry_territory' => 255, 'registry_number' => 100,
        // `district_companies` NON è qui: dalla compliance "Allegato 3" è diventato un
        // array di righe {name, cf, vat} (prima una stringa libera), e maxLength() di
        // Cake fallisce sempre su un valore non scalare (Validation::maxLength()
        // ritorna false se !is_scalar($check)) — bloccherebbe OGNI submission che lo
        // include. La lunghezza per riga è verificata da validateDistrictCompanies().
        'district_mm_appointment_reference' => 255, 'district_employer_company' => 255,
        'district_external_entity' => 255,
        'district_mm_first_name' => 255, 'district_mm_last_name' => 255, 'district_mm_cf' => 16,
        'district_mm_email' => 255, 'district_mm_phone' => 45,
        'reference_city' => 45, 'reference_province' => 2,
        'mm_last_name' => 255, 'mm_first_name' => 255, 'mm_cf' => 16,
        'mm_email' => 255, 'mm_phone' => 45, 'mm_appointment_reference' => 255, 'place' => 255,
        // Istituto scolastico (Allegato 4). `school_principal_appointment_reference`
        // usa la stessa lunghezza di `legal_rep_powers_reference` (255): stesso tipo di
        // campo ("estremi di un provvedimento/atto"), solo per il ruolo scuola.
        'school_mechanographic_code' => 10, 'mandate_extension' => 1000,
        'school_principal_appointment_reference' => 255,
    ];

    /**
     * Initialize method
     *
     * @param array $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('adhesion_requests');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        // Assicura che `data` sia marshallato/serializzato come json a prescindere
        // dallo schema introspettato (utile anche con le fixture di test).
        $this->getSchema()->setColumnType('data', 'json');

        $this->belongsTo('Users', [
            'foreignKey' => 'user_id',
        ]);
        $this->belongsTo('Companies', [
            'foreignKey' => 'company_id',
        ]);
        // Chi ha elaborato (approvato/rifiutato) la richiesta: associazione separata
        // da `Users` (che è l'utente MM via user_id). LEFT: null per le pending.
        $this->belongsTo('ProcessedByUser', [
            'className' => 'Users',
            'foreignKey' => 'processed_by',
            'joinType' => 'LEFT',
        ]);
        // Le due valutazioni indipendenti (§7): stesso pattern di ProcessedByUser, alias
        // distinti sulla stessa tabella Users, LEFT perché null finché non si decide.
        // `propertyName` esplicito: a differenza di ProcessedByUser (che si sottolinea
        // in "processed_by_user", diverso dalla colonna "processed_by"), l'alias
        // AdminDecidedBy/MomaDecidedBy si sottolineerebbe ESATTAMENTE come la colonna FK
        // omonima (admin_decided_by/moma_decided_by) — CakePHP lo segnala con un warning
        // ("Association property name clashes with field of same name"), che in modalità
        // debug viene reso come pagina HTML e rompe la risposta JSON a metà (visto live:
        // "Cannot modify header information", index() restituiva 0 risultati).
        $this->belongsTo('AdminDecidedBy', [
            'className' => 'Users',
            'foreignKey' => 'admin_decided_by',
            'joinType' => 'LEFT',
            'propertyName' => 'admin_decided_by_user',
        ]);
        $this->belongsTo('MomaDecidedBy', [
            'className' => 'Users',
            'foreignKey' => 'moma_decided_by',
            'joinType' => 'LEFT',
            'propertyName' => 'moma_decided_by_user',
        ]);

        // Elenco persone da abilitare (§5). dependent+cascadeCallbacks: eliminando la
        // richiesta si eliminano le righe figlie.
        $this->hasMany('People', [
            'className' => 'AdhesionRequestPeople',
            'foreignKey' => 'adhesion_request_id',
            'dependent' => true,
            'cascadeCallbacks' => true,
        ]);
        // Storico dei voti (#538): una riga per ogni voto nuovo/modificato.
        $this->hasMany('Decisions', [
            'className' => 'AdhesionRequestDecisions',
            'foreignKey' => 'adhesion_request_id',
            'dependent' => true,
            'sort' => ['Decisions.decided_at' => 'ASC', 'Decisions.id' => 'ASC'],
        ]);
    }

    /**
     * Default validation rules (livello entità/colonna).
     *
     * I campi del form vivono in `data`, non come colonne dell'entità: qui si validano
     * solo le colonne reali. La validazione della submission grezza è in
     * validationSubmission().
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->scalar('status')
            ->inList('status', AdhesionRequest::STATUSES, 'Stato non valido')
            ->notEmptyString('status');

        $validator
            ->scalar('organization_type')
            ->inList('organization_type', ['scuola', 'azienda'], 'Tipo di organizzazione non valido')
            ->notEmptyString('organization_type');

        $validator
            ->scalar('reference_city')
            ->maxLength('reference_city', 45, 'Il comune di riferimento non può superare i 45 caratteri')
            ->notEmptyString('reference_city', 'Il comune di riferimento è obbligatorio');

        $validator
            ->scalar('reference_province')
            ->maxLength('reference_province', 2)
            ->allowEmptyString('reference_province');

        $validator
            ->notEmptyArray('data', 'I dati della richiesta sono obbligatori');

        return $validator;
    }

    /**
     * Validatore dedicato applicato all'array POST grezzo in
     * AdhesionRequestsController::submit().
     *
     * I campi del form sono dentro `data` (non colonne dell'entità), quindi un
     * validatore separato è più pulito di regole per-colonna. L'elenco persone, essendo
     * un array di righe, è validato a parte da validatePeople() per poter restituire
     * errori indicizzati (`people.1.cf`) che il frontend usa per il focus.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationSubmission(Validator $validator): Validator
    {
        // --- Tipo organizzazione ------------------------------------------------------
        $validator
            ->requirePresence('organization_type', true, 'Indicare il tipo di organizzazione')
            ->inList(
                'organization_type',
                ['scuola', 'azienda'],
                'Il tipo di organizzazione deve essere "scuola" o "azienda"'
            );

        // --- Organizzazione -----------------------------------------------------------
        $validator
            ->notEmptyString('company_name', 'La denominazione dell\'azienda/ente è obbligatoria')
            ->maxLength('company_name', 255, 'La denominazione non può superare i 255 caratteri');

        // Forma giuridica: facoltativa, campo azienda-only (Allegato 3 — nascosto dal
        // form per organization_type 'scuola', vedi Adesione.vue). Senza questa regola
        // il campo finisce comunque in ValidationSet tramite il loop maxLength() su
        // DATA_MAX_LENGTHS più sotto, e CakePHP considera un campo con QUALUNQUE regola
        // ma senza allowEmptyString() come "non vuoto" per default — bloccando ogni
        // submission scuola con l'errore generico "_empty" (stesso motivo/pattern di
        // legal_rep_role, registry_territory e registry_number più sotto).
        $validator->allowEmptyString('legal_form');

        // Sede/i operativa/e oggetto del PSCL, se diversa dalla sede legale: facoltativa,
        // il PDF ripiega sulla sede legale quando assente (vedi templates/pdf/adhesion_summary.php).
        $validator->allowEmptyString('operational_address');

        // CAP della sede legale (Allegato 3): obbligatorio, esattamente 5 cifre.
        $validator
            ->notEmptyString('legal_address_cap', 'Il CAP della sede legale è obbligatorio')
            ->add('legal_address_cap', 'format', [
                'rule' => function ($value) {
                    return (bool)preg_match('/^\d{5}$/', (string)$value);
                },
                'message' => 'Il CAP deve essere composto da esattamente 5 cifre',
            ]);

        // --- Rappresentante legale (Allegato 3) ---------------------------------------
        $validator
            ->requirePresence('legal_rep_qualifica', true, 'Indicare la qualifica del rappresentante legale')
            ->inList(
                'legal_rep_qualifica',
                ['legale_rappresentante', 'procuratore'],
                'La qualifica deve essere "legale_rappresentante" o "procuratore"'
            );

        // Obbligatorio solo se procuratore: il controllo incrociato vive in
        // AdhesionRequestsController::submit() (stesso motivo di "almeno CF o P.IVA" sopra:
        // allowEmptyString() farebbe saltare un check incrociato aggiunto qui).
        $validator->allowEmptyString('legal_rep_powers_reference');

        // --- Codice fiscale ed email del rappresentante legale (Allegato 3) ----------
        $validator
            ->notEmptyString('legal_rep_cf', 'Il Codice Fiscale del legale rappresentante è obbligatorio')
            ->add('legal_rep_cf', 'format', [
                'rule' => function ($value) {
                    return ItalianFiscalValidator::isValidPersonalCf($value);
                },
                'message' => 'Codice Fiscale non valido (verificare il carattere di controllo)',
            ]);

        $validator
            ->email('legal_rep_email', false, 'Indirizzo email del legale rappresentante non valido')
            ->maxLength('legal_rep_email', 255)
            ->notEmptyString('legal_rep_email', 'L\'email del legale rappresentante è obbligatoria');

        $validator
            ->notEmptyString('mm_first_name', 'Il nome del mobility manager è obbligatorio')
            ->maxLength('mm_first_name', 255)
            ->notEmptyString('mm_last_name', 'Il cognome del mobility manager è obbligatorio')
            ->maxLength('mm_last_name', 255);

        // --- Mobility Manager aziendale: rapporto e atto di nomina (Allegato 3) -------
        // "Rapporto con l'azienda" non esiste per il MM scolastico (Allegato 4): obbligatorio
        // solo per azienda, stesso pattern D8 di pscl_obligation_status sopra.
        $validator
            ->requirePresence(
                'mm_relationship',
                fn ($context) => ($context['data']['organization_type'] ?? null) === 'azienda',
                'Indicare il rapporto tra il mobility manager e l\'azienda'
            )
            ->allowEmptyString(
                'mm_relationship',
                null,
                fn ($context) => ($context['data']['organization_type'] ?? null) === 'scuola'
            )
            ->inList(
                'mm_relationship',
                ['dipendente', 'consulente', 'altro'],
                'Il rapporto deve essere "dipendente", "consulente" o "altro"'
            );

        $validator
            ->notEmptyString(
                'mm_appointment_reference',
                'Indicare gli estremi dell\'atto di nomina del mobility manager'
            )
            ->maxLength('mm_appointment_reference', 255);

        // --- Classificazione ai fini dell'obbligo PSCL (Allegato 3, azienda) ----------
        // Obbligatoria solo per azienda: una submission scuola invia questo campo come
        // `null` (present-but-empty: il frontend spedisce sempre l'intero form), non
        // assente — serve sia requirePresence(callable) SIA allowEmptyString(callable)
        // insieme, altrimenti inList() rifiuterebbe il valore vuoto con un errore
        // fuorviante invece di saltare la regola (vedi docs/dev-notes/adesione-flow.md).
        $validator
            ->requirePresence(
                'pscl_obligation_status',
                fn ($context) => ($context['data']['organization_type'] ?? null) === 'azienda',
                'Indicare la classificazione ai fini dell\'obbligo PSCL'
            )
            ->allowEmptyString(
                'pscl_obligation_status',
                null,
                fn ($context) => ($context['data']['organization_type'] ?? null) === 'scuola'
            )
            ->inList(
                'pscl_obligation_status',
                ['obbligata_229', 'volontaria_azienda', 'volontaria_ente'],
                'La classificazione PSCL non è valida'
            );

        // --- Istituto scolastico (Allegato 4) ------------------------------------------
        // Tipologia e Natura giuridica: obbligatorie solo per scuola, stesso pattern D8
        // di pscl_obligation_status/mm_relationship sopra (un submit azienda invia questi
        // campi come `null`, present-but-empty, non assenti).
        $validator
            ->requirePresence(
                'school_type',
                fn ($context) => ($context['data']['organization_type'] ?? null) === 'scuola',
                'Indicare la tipologia dell\'istituto scolastico'
            )
            ->allowEmptyString(
                'school_type',
                null,
                fn ($context) => ($context['data']['organization_type'] ?? null) === 'azienda'
            )
            ->inList(
                'school_type',
                ['infanzia', 'primaria', 'secondaria_primo_grado', 'secondaria_secondo_grado'],
                'La tipologia dell\'istituto scolastico non è valida'
            );

        $validator
            ->requirePresence(
                'school_legal_nature',
                fn ($context) => ($context['data']['organization_type'] ?? null) === 'scuola',
                'Indicare la natura giuridica dell\'istituto scolastico'
            )
            ->allowEmptyString(
                'school_legal_nature',
                null,
                fn ($context) => ($context['data']['organization_type'] ?? null) === 'azienda'
            )
            ->inList(
                'school_legal_nature',
                ['statale', 'paritaria', 'privata'],
                'La natura giuridica dell\'istituto scolastico non è valida'
            );

        // Codice meccanografico: sempre facoltativo qui, solo una regola di formato
        // "soft" (euristica, non autoritativa — stessa impostazione già presa per
        // num_employees). L'obbligo per scuola è un controllo incrociato nel controller
        // (submit()), stesso motivo di "almeno CF o P.IVA" più sotto: allowEmptyString()
        // farebbe saltare qualunque altra regola aggiunta qui quando il campo è vuoto,
        // quindi l'obbligo condizionale non potrebbe vivere in questo Validator.
        $validator
            ->allowEmptyString('school_mechanographic_code')
            ->add('school_mechanographic_code', 'format', [
                'rule' => function ($value) {
                    return $value === null || $value === ''
                        || (bool)preg_match('/^[A-Z]{2}[A-Z0-9]{2}\d{5}[A-Z0-9]$/i', (string)$value);
                },
                'message' => 'Il codice meccanografico non ha un formato valido',
            ]);

        // Estensione del mandato: sempre facoltativa, per entrambi i tipi (Allegato 4:
        // "compilare nel caso in cui il Mobility Manager scolastico svolga la propria
        // funzione per più istituti").
        $validator->allowEmptyString('mandate_extension');

        // Estremi del provvedimento di conferimento dell'incarico al Dirigente
        // scolastico: campo DEDICATO (non un riuso di legal_rep_powers_reference, che
        // resta azienda/procuratore-only — vedi D2 in docs/dev-notes/adesione-flow.md).
        // Sempre facoltativo qui: l'obbligo per scuola è un controllo incrociato nel
        // controller, stesso motivo del codice meccanografico sopra.
        $validator->allowEmptyString('school_principal_appointment_reference');

        // --- Mobility Manager di distretto (RER, Allegato 3) --------------------------
        // `uses_district_mm` è un booleano facoltativo (default false, gestito lato
        // controller): nessuna regola di formato necessaria. Gli altri campi sono
        // facoltativi qui — obbligatori solo quando uses_district_mm è vero, controllo
        // incrociato in AdhesionRequestsController::submit(), stesso motivo di
        // legal_rep_powers_reference sopra.
        $validator->allowEmptyString('district_mm_appointment_reference');

        $validator
            ->allowEmptyString('district_relationship_type')
            ->inList(
                'district_relationship_type',
                ['dipendente_azienda_distretto', 'soggetto_esterno'],
                'Il rapporto con il mobility manager di distretto non è valido'
            );

        $validator->allowEmptyString('district_employer_company');
        $validator->allowEmptyString('district_external_entity');

        // Identità del mobility manager di distretto (Allegato 3, "Sezione dedicata —
        // Mobility Manager di distretto"): facoltativi qui, obbligatori solo quando
        // uses_district_mm è vero (controllo incrociato in
        // AdhesionRequestsController::submit(), stesso motivo degli altri campi
        // condizionali sopra). Le regole di formato "add()" restano attive anche con
        // allowEmptyString() quando il valore NON è vuoto, quindi un valore inviato viene
        // comunque verificato prima che il controller applichi l'obbligo condizionale.
        $validator->allowEmptyString('district_mm_first_name');
        $validator->allowEmptyString('district_mm_last_name');

        $validator
            ->allowEmptyString('district_mm_cf')
            ->add('district_mm_cf', 'format', [
                'rule' => function ($value) {
                    return $value === null || $value === ''
                        || ItalianFiscalValidator::isValidPersonalCf($value);
                },
                'message' => 'Codice Fiscale non valido (verificare il carattere di controllo)',
            ]);

        $validator
            ->email('district_mm_email', false, 'Indirizzo email del mobility manager di distretto non valido')
            ->allowEmptyString('district_mm_email');

        $validator->allowEmptyString('district_mm_phone');

        $validator
            ->notEmptyString('reference_city', 'Il comune di riferimento è obbligatorio')
            ->maxLength('reference_city', 45, 'Il comune di riferimento non può superare i 45 caratteri');

        // La provincia è la chiave del routing territoriale: solo le 9 province RER.
        $validator
            ->notEmptyString('reference_province', 'La provincia di riferimento è obbligatoria')
            ->inList(
                'reference_province',
                self::RER_PROVINCES,
                'La provincia deve essere una delle province della Regione Emilia-Romagna'
            );

        // --- Email: obbligatoria per azienda, facoltativa per scuola -----------------
        // Vedi docs/dev-notes/adesione-flow.md per la rationale del notEmptyString($field, $msg, $when):
        // la $when invert-condizione il valore returned e preserva il custom message, a differenza
        // di requirePresence+allowEmptyString che riscrivrebbe il messaggio con il generico.
        $validator
            ->email('email', false, 'Indirizzo email non valido')
            ->maxLength('email', 255)
            ->notEmptyString(
                'email',
                'L\'email aziendale è obbligatoria',
                fn ($context) => ($context['data']['organization_type'] ?? null) !== 'scuola'
            );

        $validator
            ->email('mm_email', false, 'Indirizzo email del mobility manager non valido')
            ->maxLength('mm_email', 255)
            ->notEmptyString('mm_email', 'L\'email del mobility manager è obbligatoria');

        // --- PEC: facoltativa per azienda, obbligatoria per scuola -------------------
        // Stesso pattern di email, con $when callback invertito. Vedi docs/dev-notes/adesione-flow.md.
        $validator
            ->email('pec', false, 'Indirizzo PEC non valido')
            ->maxLength('pec', 255)
            ->notEmptyString(
                'pec',
                'La PEC dell\'istituto scolastico è obbligatoria',
                fn ($context) => ($context['data']['organization_type'] ?? null) === 'scuola'
            );

        // --- Codice fiscale del mobility manager (validazione VERA, con CIN) ----------
        $validator
            ->notEmptyString('mm_cf', 'Il Codice Fiscale del mobility manager è obbligatorio')
            ->add('mm_cf', 'format', [
                'rule' => function ($value) {
                    return ItalianFiscalValidator::isValidPersonalCf($value);
                },
                'message' => 'Codice Fiscale non valido (verificare il carattere di controllo)',
            ]);

        // --- Identificativi fiscali dell'organizzazione -------------------------------
        // Almeno uno tra codice fiscale e partita IVA è obbligatorio; entrambi, se
        // presenti, devono essere formalmente corretti.
        $validator
            ->allowEmptyString('fiscal_code')
            ->add('fiscal_code', 'format', [
                'rule' => function ($value) {
                    return $value === null || $value === ''
                        || ItalianFiscalValidator::isValidAnyCf($value);
                },
                'message' => 'Codice Fiscale dell\'organizzazione non valido',
            ]);

        $validator
            ->allowEmptyString('vat_number')
            ->add('vat_number', 'format', [
                'rule' => function ($value) {
                    return $value === null || $value === ''
                        || ItalianFiscalValidator::isValidVat($value);
                },
                'message' => 'Partita IVA non valida (11 cifre, checksum errato)',
            ]);

        // Iscrizione al Registro Imprese (territorio/numero, specifico EMMA RE-R):
        // facoltativa, campo azienda-only (nascosto per 'scuola' — stesso motivo/pattern
        // di legal_form/legal_rep_role sopra).
        $validator->allowEmptyString('registry_territory');
        $validator->allowEmptyString('registry_number');

        // NB: "almeno CF o P.IVA" NON può essere una regola Cake su `fiscal_code`: quando
        // il campo è vuoto, `allowEmptyString()` fa saltare TUTTE le altre regole
        // aggiunte su quel campo (compreso questo check incrociato), quindi una
        // submission con entrambi i campi vuoti passerebbe. Il controllo vive perciò in
        // `AdhesionRequestsController::submit()`, con lo stesso trattamento "errore per
        // campo" usato per i controlli di duplicato.

        // --- Campi facoltativi con formato ------------------------------------------
        $validator
            ->allowEmptyString('ateco')
            ->add('ateco', 'format', [
                'rule' => function ($value) {
                    return $value === null || $value === ''
                        || ItalianFiscalValidator::isValidAteco($value);
                },
                'message' => 'Codice ATECO non valido (formato atteso: 62.01)',
            ]);

        $validator
            ->allowEmptyString('num_employees')
            ->add('num_employees', 'range', [
                'rule' => function ($value) {
                    if ($value === null || $value === '') {
                        return true;
                    }
                    // Rifiuta decimali, stringhe non numeriche, 0 e negativi.
                    if (!is_numeric($value) || (string)(int)$value !== trim((string)$value)) {
                        return false;
                    }

                    return (int)$value >= 1 && (int)$value <= 1000000;
                },
                'message' => 'Il numero di dipendenti deve essere un intero tra 1 e 1.000.000',
            ]);

        foreach (['phone', 'mobile', 'mm_phone'] as $phoneField) {
            $validator
                ->allowEmptyString($phoneField)
                ->add($phoneField, 'format', [
                    'rule' => function ($value) {
                        return $value === null || $value === ''
                            || ItalianFiscalValidator::isValidItalianPhone($value);
                    },
                    'message' => 'Numero di telefono non valido',
                ]);
        }

        // Lunghezze massime su tutti i campi testuali che finiscono in `data`.
        foreach (self::DATA_MAX_LENGTHS as $field => $limit) {
            $validator->maxLength($field, $limit, sprintf('Il campo non può superare i %d caratteri', $limit));
        }

        // --- Elenco persone da abilitare (0..MAX_PEOPLE) ----------------------------
        // Ora facoltativo: sia null/[] che una lista regolare sono accettati. La lunghezza
        // massima è ancora validata; la logica per escludere righe vuote vive in
        // validatePeople() e nel controller.
        $validator
            ->allowEmptyArray('people')
            ->add('people', 'maxCount', [
                'rule' => function ($value) {
                    return !is_array($value) || count($value) <= self::MAX_PEOPLE;
                },
                'message' => sprintf('Non è possibile abilitare più di %d persone per richiesta', self::MAX_PEOPLE),
            ]);

        // --- Le dichiarazioni obbligatorie devono essere accettate (true) -------------
        $mustBeTrue = [
            'accept_guidelines' => 'È necessario accettare le linee guida',
            'accept_gdpr' => 'È necessario accettare il trattamento dei dati (GDPR)',
            'accept_dpr445' => 'È necessario accettare le dichiarazioni ai sensi del DPR 445/2000',
            'accept_data_processor_nomination' => 'È necessario accettare la nomina della Regione '
                . 'Emilia-Romagna quale responsabile del trattamento',
            'accept_comune_verification' => 'È necessario prendere atto della verifica da parte del '
                . 'Comune territorialmente competente',
        ];
        foreach ($mustBeTrue as $field => $message) {
            $validator
                ->requirePresence($field)
                ->add($field, 'mustBeTrue', [
                    'rule' => function ($value) {
                        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
                    },
                    'message' => $message,
                ]);
        }

        // Requisito 2a della compliance privacy RER "EMMA RE-R" (vedi
        // docs/dev-notes/rer-privacy-consent.md): presa visione dell'informativa
        // privacy regionale, DISTINTA da accept_gdpr. Deliberatamente NON aggiunta a
        // $mustBeTrue sopra: il checkbox frontend (F4 nel piano, ondata separata da
        // questa) non esiste ancora, quindi imporlo qui adesso romperebbe ogni
        // submission reale finché il frontend non lo invia — oltre ai 13 test
        // preesistenti di questa suite che non lo impostano (rottura verificata: 422
        // su testSubmitHappyPathCreatesRequestPeopleAndInactiveReferent e altri 12).
        // Per ora si accetta e persiste il valore se presente (colonna reale, non
        // dentro `data`: esclusa da ALLOWED_DATA_KEYS/filterAllowedData(), scritta
        // esplicitamente sull'entity in submit()); l'obbligo va aggiunto quando F4
        // (piano frontend) inizia a inviare il campo.
        $validator->allowEmptyString('accept_privacy_informativa');

        return $validator;
    }

    /**
     * Valida l'elenco persone riga per riga e rileva i duplicati DENTRO il payload.
     *
     * Restituisce errori indicizzati (`people.1.email`) perché il frontend usa la chiave
     * per portare il focus sul campo sbagliato della riga giusta. Il validatore Cake non
     * gestisce bene gli array annidati, da qui il metodo dedicato.
     *
     * @param mixed $people L'elenco grezzo ricevuto dal form.
     * @param string|null $mmEmail Email del mobility manager, per rilevare la coincidenza.
     * @param string|null $mmCf Codice fiscale del mobility manager.
     * @return array<string, array<string, string>> Errori, vuoto se tutto valido.
     */
    public function validatePeople($people, ?string $mmEmail = null, ?string $mmCf = null): array
    {
        $errors = [];
        // Null/[] sono ora permessi: il validation è della forma (array), non del contenuto.
        // Solo un valore non-array è realmente un errore (e.g., una stringa accidentale).
        if ($people === null || $people === []) {
            return [];
        }
        if (!is_array($people)) {
            return ['people' => ['invalidList' => 'Formato dell\'elenco persone non valido']];
        }

        $seenEmails = [];
        $seenCfs = [];
        if (!empty($mmEmail)) {
            $seenEmails[strtolower(trim($mmEmail))] = 'mm';
        }
        if (!empty($mmCf)) {
            $seenCfs[strtoupper(trim($mmCf))] = 'mm';
        }

        foreach (array_values($people) as $index => $person) {
            $prefix = 'people.' . $index . '.';
            if (!is_array($person)) {
                $errors[$prefix . 'first_name'] = ['invalid' => 'Riga non valida'];
                continue;
            }

            $first = trim((string)($person['first_name'] ?? ''));
            $last = trim((string)($person['last_name'] ?? ''));
            $email = trim((string)($person['email'] ?? ''));
            $cf = strtoupper(trim((string)($person['cf'] ?? '')));

            if ($first === '') {
                $errors[$prefix . 'first_name'] = ['_empty' => 'Il nome è obbligatorio'];
            } elseif (mb_strlen($first) > 255) {
                $errors[$prefix . 'first_name'] = ['maxLength' => 'Il nome è troppo lungo'];
            }

            if ($last === '') {
                $errors[$prefix . 'last_name'] = ['_empty' => 'Il cognome è obbligatorio'];
            } elseif (mb_strlen($last) > 255) {
                $errors[$prefix . 'last_name'] = ['maxLength' => 'Il cognome è troppo lungo'];
            }

            if ($email === '') {
                $errors[$prefix . 'email'] = ['_empty' => 'L\'email è obbligatoria'];
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255) {
                $errors[$prefix . 'email'] = ['format' => 'Indirizzo email non valido'];
            } else {
                $key = strtolower($email);
                if (isset($seenEmails[$key])) {
                    $errors[$prefix . 'email'] = [
                        'duplicate' => $seenEmails[$key] === 'mm'
                            ? 'Questa email è già quella del mobility manager'
                            : 'Email ripetuta nell\'elenco',
                    ];
                } else {
                    $seenEmails[$key] = $index;
                }
            }

            if ($cf === '') {
                $errors[$prefix . 'cf'] = ['_empty' => 'Il Codice Fiscale è obbligatorio'];
            } elseif (!ItalianFiscalValidator::isValidPersonalCf($cf)) {
                $errors[$prefix . 'cf'] = [
                    'format' => 'Codice Fiscale non valido (verificare il carattere di controllo)',
                ];
            } elseif (isset($seenCfs[$cf])) {
                $errors[$prefix . 'cf'] = [
                    'duplicate' => $seenCfs[$cf] === 'mm'
                        ? 'Questo Codice Fiscale è già quello del mobility manager'
                        : 'Codice Fiscale ripetuto nell\'elenco',
                ];
            } else {
                $seenCfs[$cf] = $index;
            }
        }

        return $errors;
    }

    /**
     * Valida l'elenco delle aziende del distretto (Allegato 3, MM di distretto) riga per riga.
     *
     * Stesso stile/shape di validatePeople(): errori indicizzati (`district_companies.0.name`)
     * per il focus lato frontend. A differenza di `people`, questo elenco è facoltativo — un
     * valore assente/vuoto non è un errore qui: l'obbligo "almeno una riga quando
     * uses_district_mm è vero" è un controllo incrociato in
     * AdhesionRequestsController::submit(), non una regola di questo metodo.
     *
     * @param mixed $districtCompanies L'elenco grezzo ricevuto dal form (array di
     *   {name, cf, vat}), o null/vuoto se non inviato.
     * @return array<string, array<string, string>> Errori, vuoto se tutto valido/assente.
     */
    public function validateDistrictCompanies($districtCompanies): array
    {
        if ($districtCompanies === null || $districtCompanies === '') {
            return [];
        }
        if (!is_array($districtCompanies)) {
            return ['district_companies' => ['invalid' => 'Elenco aziende del distretto non valido']];
        }

        $errors = [];
        if (count($districtCompanies) > self::MAX_DISTRICT_COMPANIES) {
            $errors['district_companies'] = [
                'maxCount' => sprintf(
                    'Non è possibile indicare più di %d aziende del distretto',
                    self::MAX_DISTRICT_COMPANIES
                ),
            ];
        }

        foreach (array_values($districtCompanies) as $index => $company) {
            $prefix = 'district_companies.' . $index . '.';
            if (!is_array($company)) {
                $errors[$prefix . 'name'] = ['invalid' => 'Riga non valida'];
                continue;
            }

            $name = trim((string)($company['name'] ?? ''));
            $cf = trim((string)($company['cf'] ?? ''));
            $vat = trim((string)($company['vat'] ?? ''));

            if ($name === '') {
                $errors[$prefix . 'name'] = ['_empty' => 'La denominazione è obbligatoria'];
            } elseif (mb_strlen($name) > 255) {
                $errors[$prefix . 'name'] = ['maxLength' => 'La denominazione è troppo lunga'];
            }

            if ($cf !== '' && !ItalianFiscalValidator::isValidAnyCf($cf)) {
                $errors[$prefix . 'cf'] = ['format' => 'Codice Fiscale non valido'];
            }

            if ($vat !== '' && !ItalianFiscalValidator::isValidVat($vat)) {
                $errors[$prefix . 'vat'] = ['format' => 'Partita IVA non valida (11 cifre, checksum errato)'];
            }
        }

        return $errors;
    }

    /**
     * Filtra la submission tenendo solo le chiavi previste (allow-list).
     *
     * @param array $data Submission grezza.
     * @return array
     */
    public function filterAllowedData(array $data): array
    {
        return array_intersect_key($data, array_flip(self::ALLOWED_DATA_KEYS));
    }

    /**
     * Risolve `company_types.id` dalla Tipologia scuola dichiarata (Allegato 4).
     *
     * Il lookup è per id + `survey_template = 'scuola'`, MAI per `name`:
     * CompanyTypesTable ha il behavior Translate su `name` (vedi
     * CompanyTypesTable::initialize()), quindi un confronto sul nome sarebbe
     * locale-dipendente e si romperebbe silenziosamente sotto una richiesta in
     * francese. Ritorna null (il chiamante decide se loggare) sia quando `$schoolType`
     * non è mappato, sia quando l'id mappato non esiste più o non è (più)
     * `survey_template = 'scuola'` nel DB corrente — degradazione controllata, mai
     * un'eccezione.
     *
     * @param string|null $schoolType Valore di `school_type` (Allegato 4, Tipologia).
     * @return int|null
     */
    public function companyTypeIdForSchoolType(?string $schoolType): ?int
    {
        $id = self::SCHOOL_TYPE_COMPANY_TYPE_IDS[$schoolType] ?? null;
        if ($id === null) {
            return null;
        }

        $exists = $this->fetchTable('CompanyTypes')->find()
            ->where(['id' => $id, 'survey_template' => 'scuola'])
            ->count() > 0;

        return $exists ? $id : null;
    }
}
