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

namespace App\Controller;

use App\Controller\Traits\TerritoryScopeTrait;
use App\Model\Entity\AdhesionRequest;
use App\Notification\adhesionApprovedNotification;
use App\Notification\adhesionLinkRegeneratedReviewerNotification;
use App\Notification\adhesionLinkResentNotification;
use App\Notification\adhesionLinkUnchangedNotification;
use App\Notification\adhesionRejectedNotification;
use App\Notification\adhesionRequestNotification;
use App\Notification\adhesionSubmittedNotification;
use App\Notification\adhesionSubmittedReviewerNotification;
use App\Service\AdhesionDocumentService;
use App\Service\AdhesionSurveyPrefillService;
use App\Service\CompanyPrivacyDocumentService;
use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Datasource\EntityInterface;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Exception\NotFoundException;
use Cake\I18n\FrozenTime;
use Cake\Log\Log;

/**
 * AdhesionRequests Controller — "Modulo di adesione EMMA RE-R".
 *
 * Implementa il flusso della specifica funzionale ITL/RER "Flusso di registrazione e
 * abilitazione delle organizzazioni" (v0.1):
 *
 *   Step 1  submit()          form pubblico -> stato `pdf_pending`
 *   Step 2  pdf()             download del PDF di riepilogo (token del referente)
 *           uploadSigned()    ricarica del PDF firmato -> stato `in_review`
 *   Step 3  decide()          DUE valutazioni indipendenti (admin + moma_area)
 *   Step 4  (in decide)       doppia approvazione -> abilitazione 365 giorni
 *   Step 5  (in decide)       rifiuto di ENTRAMBI i valutatori (#538) -> stato `rejected` +
 *                             notifica al referente; un solo rifiuto o voti discordanti
 *                             lasciano la richiesta `in_review`
 *
 * Azioni pubbliche (non autenticate): `submit`, `comuni`, `pdf`, `uploadSigned`. Le tre
 * ultime sono protette dal token monouso della richiesta, perché il referente che compila
 * il form è anonimo e non può avere un account per rientrare. TUTTE controllano il feature
 * flag `AdhesionEnabled`: senza questo controllo il modulo risulterebbe disattivato solo a
 * metà.
 *
 * @property \App\Model\Table\AdhesionRequestsTable $AdhesionRequests
 */
class AdhesionRequestsController extends AppController
{
    use TerritoryScopeTrait;

    /**
     * Chiavi di controllo/honeypot rimosse dalla submission prima di salvarla in `data`.
     *
     * @var array<int, string>
     */
    private const CONTROL_KEYS = ['website', 'theme'];

    /**
     * Durata di validità del link inviato al referente per caricare il PDF firmato.
     *
     * @var int
     */
    private const TOKEN_TTL_DAYS = 30;

    /**
     * Cooldown tra un invio riuscito del link e il successivo (self-service). Serve
     * innanzitutto ad evitare che un doppio-click invalidi il link della prima email
     * prima ancora che il referente l'abbia aperta — l'anti-abuso è secondario, dato che
     * per arrivare qui occorre già possedere un token valido. Bypassato per l'azione di
     * backoffice `regenerateToken()` (staff, non self-service).
     *
     * @var int
     */
    private const RESEND_COOLDOWN_MINUTES = 15;

    /**
     * Durata dell'abilitazione concessa alle persone (specifica §8).
     *
     * @var int
     */
    private const ENABLEMENT_DAYS = 365;

    /**
     * Messaggio 422 quando la creazione del questionario dal template fallisce: l'intera
     * approvazione viene annullata, il valutatore può riprovare senza template
     * (`clear_survey_template: true`).
     */
    private const TEMPLATE_FAILURE_MESSAGE = 'Impossibile creare il questionario dal template selezionato: '
        . 'approvazione annullata. Riprovare senza template.';

    /**
     * Id del questionario creato da enable() (bug 5); enable() restituisce un bool, questo
     * è il carrier letto da decide() per aggiungere `survey_id` alla risposta.
     *
     * @var int|null
     */
    private ?int $createdSurveyId = null;

    /**
     * Messaggio specifico di enable() per la risposta 422 di decide(), se diverso dal generico.
     *
     * @var string|null
     */
    private ?string $enableFailureMessage = null;

    /**
     * Effetti collaterali non transazionali accodati da finalize()/enable() durante
     * decide() ed eseguiti dopo il commit. `null` = nessuna finestra aperta: i callback
     * girano subito (stesso comportamento di prima).
     *
     * @var array<int, callable>|null
     */
    private ?array $postCommitCallbacks = null;

    /**
     * Ruolo assegnato agli account creati alla doppia approvazione.
     *
     * @var string
     */
    private const GRANTED_ROLE = 'moma';

    /**
     * Servizio documenti (PDF di riepilogo + PDF firmato).
     *
     * @var \App\Service\AdhesionDocumentService|null
     */
    private $documents = null;

    /**
     * Servizio di storage del "Modulo trattamento dati personali dei dipendenti"
     * (requisito 3, compliance privacy RER "EMMA RE-R").
     *
     * @var \App\Service\CompanyPrivacyDocumentService|null
     */
    private $companyPrivacyDocumentService = null;

    /**
     * Initialize.
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();
        // Azioni pubbliche (sovrascrive intenzionalmente la lista del parent, che abilita
        // solo getConfig). `pdf`/`uploadSigned` sono protette dal token della richiesta.
        $this->Authentication->allowUnauthenticated([
            'submit', 'comuni', 'pdf', 'uploadSigned', 'employeePrivacyDocument',
            'linkStatus', 'resendLink', 'uploadIdentity',
        ]);
        $this->loadComponent('Paginator');

        // AppController carica Crud con skipAuthorization() globale e mappa add/edit:
        // senza implementazione verrebbero auto-servite a qualunque utente autenticato.
        // Le disabilitiamo (gli stub sotto garantiscono il 404). `delete` è implementata
        // sopra con la sua policy, quindi non va disabilitata.
        $this->Crud->disable(['add', 'edit']);
    }

    /**
     * Servizio documenti (lazy).
     *
     * @return \App\Service\AdhesionDocumentService
     */
    private function documents(): AdhesionDocumentService
    {
        if ($this->documents === null) {
            $this->documents = new AdhesionDocumentService();
        }

        return $this->documents;
    }

    /**
     * Servizio di storage del documento aziendale (lazy).
     *
     * @return \App\Service\CompanyPrivacyDocumentService
     */
    private function companyPrivacyDocumentService(): CompanyPrivacyDocumentService
    {
        if ($this->companyPrivacyDocumentService === null) {
            $this->companyPrivacyDocumentService = new CompanyPrivacyDocumentService();
        }

        return $this->companyPrivacyDocumentService;
    }

    /**
     * Risolve il "Modulo trattamento dati personali dei dipendenti" (requisito 3,
     * compliance privacy RER "EMMA RE-R") di una richiesta di adesione, secondo
     * l'invariante XOR di `CompanyPrivacyDocumentsTable`: **esattamente una tra**
     * `company_id` e `adhesion_request_id` è valorizzata — mai entrambe, mai nessuna
     * delle due. Prima dell'approvazione la riga vive agganciata a
     * `adhesion_request_id` (documento "pendente"); `enable()` la ri-aggancia
     * all'azienda (`company_id`) e nulla `adhesion_request_id` (D4).
     *
     * ⚠️ NON sostituire con un `contain(['CompanyPrivacyDocuments'])` keyed su
     * `adhesion_request_id`: per ogni richiesta APPROVATA (la stragrande maggioranza
     * dello stato reale, dato che `adhesion_request_id` viene nullata proprio
     * all'approvazione) tornerebbe silenziosamente nulla — un bug che passerebbe una
     * review e un test pre-approvazione restando comunque rotto in produzione.
     *
     * Tre chiamanti: il token action pubblico `employeePrivacyDocument()`, l'azione di
     * staff `staffEmployeePrivacyDoc()`, e `view()`.
     *
     * @param \App\Model\Entity\AdhesionRequest $adhesionRequest La richiesta di adesione.
     * @return \Cake\Datasource\EntityInterface|null Riga `CompanyPrivacyDocuments`, o null se nessun documento esiste.
     */
    private function findEmployeePrivacyDocument(AdhesionRequest $adhesionRequest): ?EntityInterface
    {
        $companyId = !empty($adhesionRequest->company_id) ? (int)$adhesionRequest->company_id : null;

        return $this->fetchTable('CompanyPrivacyDocuments')->find()
            ->where($companyId !== null
                ? ['company_id' => $companyId]
                : ['adhesion_request_id' => (int)$adhesionRequest->id])
            ->first();
    }

    /**
     * Documenti OBBLIGATORI mancanti per poter APPROVARE una richiesta.
     *
     * Codici: `signed_pdf` (modulo di riepilogo firmato) e `employee_privacy_doc`
     * (modulo trattamento dati dei dipendenti, SOLO se `EmployeePrivacyDocGate` è attivo).
     * "Esiste" = riga `CompanyPrivacyDocuments` E file su disco (un rimpiazzo fallito può
     * lasciare la riga puntata a un file già cancellato). Il documento d'identità NON è
     * mai incluso: è solo un avviso lato UI.
     *
     * @param \App\Model\Entity\AdhesionRequest $adhesionRequest La richiesta di adesione.
     * @return array<int, string> Codici dei documenti mancanti ([] = completa).
     */
    private function missingRequiredDocuments(AdhesionRequest $adhesionRequest): array
    {
        $missing = [];
        if (empty($adhesionRequest->signed_pdf_filename) || empty($adhesionRequest->signed_pdf_uploaded_at)) {
            $missing[] = 'signed_pdf';
        }
        if (Configure::read('EmployeePrivacyDocGate')) {
            $row = $this->findEmployeePrivacyDocument($adhesionRequest);
            if ($row === null || !is_file((string)$row->path)) {
                $missing[] = 'employee_privacy_doc';
            }
        }

        return $missing;
    }

    /**
     * Etichette italiane (tradotte via `__()`) dei codici di `missingRequiredDocuments()`.
     *
     * @param array<int, string> $codes Codici dei documenti mancanti.
     * @return string Elenco separato da virgole.
     */
    private function missingDocumentsLabel(array $codes): string
    {
        $labels = [
            'signed_pdf' => __('Modulo di riepilogo firmato'),
            'employee_privacy_doc' => __('Modulo trattamento dati personali dei dipendenti'),
        ];

        return implode(', ', array_map(fn($code) => $labels[$code] ?? $code, $codes));
    }

    /**
     * Arricchisce le righe della lista con lo stato del modulo dipendenti e i documenti
     * obbligatori mancanti, con UNA sola query batch.
     *
     * Le righe `approved` hanno il file sotto `company_id` (l'`adhesion_request_id` viene
     * nullato in `enable()`), le altre sotto `adhesion_request_id`. `missing_documents` è
     * valorizzato solo per `in_review` ([] altrove, niente falsi "Mancante").
     *
     * @param iterable $adhesionRequests Righe della pagina corrente.
     * @return void
     */
    private function annotateRequiredDocuments(iterable $adhesionRequests): void
    {
        $rows = [];
        $requestIds = [];
        $companyIds = [];
        foreach ($adhesionRequests as $row) {
            $rows[] = $row;
            $requestIds[] = (int)$row->id;
            if (!empty($row->company_id) && $row->status === AdhesionRequest::STATUS_APPROVED) {
                $companyIds[] = (int)$row->company_id;
            }
        }
        if ($rows === []) {
            return;
        }

        $byRequest = [];
        $byCompany = [];
        $gate = (bool)Configure::read('EmployeePrivacyDocGate');
        if ($gate) {
            $or = ['CompanyPrivacyDocuments.adhesion_request_id IN' => $requestIds];
            if ($companyIds !== []) {
                $or = ['OR' => [
                    $or,
                    'CompanyPrivacyDocuments.company_id IN' => array_values(array_unique($companyIds)),
                ]];
            }
            $docs = $this->fetchTable('CompanyPrivacyDocuments')->find()->where($or)->all();
            foreach ($docs as $doc) {
                if (!is_file((string)$doc->path)) {
                    continue;
                }
                if (!empty($doc->adhesion_request_id)) {
                    $byRequest[(int)$doc->adhesion_request_id] = $doc;
                }
                if (!empty($doc->company_id)) {
                    $byCompany[(int)$doc->company_id] = $doc;
                }
            }
        }

        foreach ($rows as $row) {
            $doc = null;
            if ($gate) {
                $companyId = !empty($row->company_id) ? (int)$row->company_id : null;
                $doc = $companyId !== null ? ($byCompany[$companyId] ?? null) : ($byRequest[(int)$row->id] ?? null);
            }
            $missing = [];
            if ($row->status === AdhesionRequest::STATUS_IN_REVIEW) {
                if (empty($row->signed_pdf_filename) || empty($row->signed_pdf_uploaded_at)) {
                    $missing[] = 'signed_pdf';
                }
                if ($gate && $doc === null) {
                    $missing[] = 'employee_privacy_doc';
                }
            }
            $row->set('employee_privacy_doc_uploaded', $doc !== null);
            $row->set('employee_privacy_doc_uploaded_at', $doc?->modified);
            $row->set('missing_documents', $missing);
        }
    }

    /**
     * 404 brand-safe se il modulo di adesione non è attivo su questo sito.
     *
     * @return void
     * @throws \Cake\Http\Exception\NotFoundException
     */
    private function assertModuleEnabled(): void
    {
        if (!Configure::read('AdhesionEnabled')) {
            throw new NotFoundException();
        }
    }

    /**
     * Risponde in JSON con uno status HTTP e un payload.
     *
     * @param int $status Codice HTTP.
     * @param array $payload Variabili da serializzare.
     * @return void
     */
    private function respond(int $status, array $payload): void
    {
        if ($status !== 200) {
            $this->response = $this->response->withStatus($status);
        }
        // Forza SEMPRE la vista JSON, indipendentemente dall'estensione di ROUTE della
        // richiesta: da quando employeePrivacyDocument() dispaccia su `_ext` (A2b), un
        // errore/gate (es. 409 "non ancora valutabile") può scattare PRIMA del ramo che
        // fa streaming, su una URL `.pdf` — senza questo, RequestHandler cerca un
        // template/vista "pdf" inesistente e la richiesta fallisce con un 500 anziché
        // il codice atteso (D7, docs/dev-notes/adesione-flow.md).
        $this->viewBuilder()->setClassName('Json');
        $this->set($payload);
        $this->viewBuilder()->setOption('serialize', array_keys($payload));
    }

    /**
     * Form pubblico di adesione (Step 1).
     *
     * Crea l'utente referente DISATTIVO, le righe dell'elenco persone e la richiesta in
     * stato `pdf_pending`, genera il PDF di riepilogo e invia al referente il link con il
     * token per caricare il documento firmato.
     *
     * NB: gli account delle persone da abilitare NON vengono creati qui — nascono solo
     * alla doppia approvazione (§8). L'utente referente invece viene creato subito (come
     * prima dell'allineamento alla specifica) perché "prenota" email e codice fiscale ed è
     * il perno dell'undo in delete(); resta però inattivo fino all'approvazione.
     *
     * @return void
     */
    public function submit(): void
    {
        $this->assertModuleEnabled();
        $this->request->allowMethod(['post']);
        $data = $this->request->getData();

        // Honeypot: bot che compilano `website` ricevono un 200 silenzioso senza nulla.
        if (!empty($data['website'])) {
            $this->respond(200, ['msg' => 'Richiesta ricevuta']);

            return;
        }

        // Usato sia dai controlli incrociati scuola-only sotto sia per risolvere
        // `company_type_id` più avanti (D9, docs/dev-notes/adesione-flow.md).
        $isScuola = ($data['organization_type'] ?? null) === 'scuola';

        // --- Validazione dei campi scalari -----------------------------------------
        $errors = $this->AdhesionRequests->getValidator('submission')->validate($data);

        // --- Validazione dell'elenco persone (errori indicizzati people.<i>.<campo>) -
        $peopleErrors = $this->AdhesionRequests->validatePeople(
            $data['people'] ?? null,
            $data['mm_email'] ?? null,
            $data['mm_cf'] ?? null
        );
        $errors = array_merge($errors, $peopleErrors);

        // Almeno uno tra CF e P.IVA dell'organizzazione. NON può essere una regola Cake
        // su `fiscal_code`: quando il campo è vuoto, `allowEmptyString()` fa saltare
        // anche le altre regole aggiunte su quel campo, quindi un controllo incrociato
        // lì non scatterebbe mai con entrambi i campi vuoti.
        if (empty($data['fiscal_code']) && empty($data['vat_number'])) {
            $errors['fiscal_code'] = [
                'atLeastOneFiscalId' => 'Indicare almeno il Codice Fiscale o la Partita IVA dell\'organizzazione',
            ];
        }

        // Gli estremi dei poteri di rappresentanza sono obbligatori solo per un
        // procuratore (stesso motivo dei check incrociati sopra: allowEmptyString() in
        // validationSubmission() farebbe saltare una regola aggiunta lì).
        if (
            ($data['legal_rep_qualifica'] ?? null) === 'procuratore'
            && trim((string)($data['legal_rep_powers_reference'] ?? '')) === ''
        ) {
            $errors['legal_rep_powers_reference'] = [
                'requiredIfProcuratore' => 'Indicare gli estremi dei poteri di rappresentanza del procuratore',
            ];
        }

        // --- Istituto scolastico (Allegato 4): campi obbligatori solo per scuola -----
        // Stesso motivo/pattern dei controlli incrociati sopra: questi campi sono
        // allowEmptyString() in validationSubmission(), quindi l'obbligo condizionale
        // non potrebbe vivere lì (vedi D8 in docs/dev-notes/adesione-flow.md).
        if ($isScuola) {
            if (trim((string)($data['fiscal_code'] ?? '')) === '') {
                $errors['fiscal_code'] = [
                    'requiredForSchool' => 'Il Codice Fiscale dell\'istituto scolastico è obbligatorio',
                ];
            }
            if (trim((string)($data['school_principal_appointment_reference'] ?? '')) === '') {
                $errors['school_principal_appointment_reference'] = [
                    'requiredForSchool' => 'Indicare gli estremi del provvedimento di conferimento '
                        . 'dell\'incarico al Dirigente scolastico',
                ];
            }
            if (trim((string)($data['school_mechanographic_code'] ?? '')) === '') {
                $errors['school_mechanographic_code'] = [
                    'requiredForSchool' => 'Il codice meccanografico dell\'istituto scolastico è obbligatorio',
                ];
            }
        }

        // --- Mobility Manager di distretto (RER, Allegato 3): campi condizionali ------
        // Tutti facoltativi in validationSubmission(): diventano obbligatori solo quando
        // uses_district_mm è vero, quindi il controllo è qui, non nel Validator.
        $usesDistrictMm = filter_var($data['uses_district_mm'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($usesDistrictMm) {
            if (trim((string)($data['district_mm_appointment_reference'] ?? '')) === '') {
                $errors['district_mm_appointment_reference'] = [
                    'requiredIfDistrictMm' => 'Indicare gli estremi dell\'atto di nomina collegiale del '
                        . 'mobility manager di distretto',
                ];
            }

            // Identità del mobility manager di distretto (Allegato 3): obbligatoria solo
            // quando uses_district_mm è vero, stesso motivo/pattern del blocco sopra.
            if (trim((string)($data['district_mm_first_name'] ?? '')) === '') {
                $errors['district_mm_first_name'] = [
                    'requiredIfDistrictMm' => 'Indicare il nome del mobility manager di distretto',
                ];
            }
            if (trim((string)($data['district_mm_last_name'] ?? '')) === '') {
                $errors['district_mm_last_name'] = [
                    'requiredIfDistrictMm' => 'Indicare il cognome del mobility manager di distretto',
                ];
            }
            if (trim((string)($data['district_mm_cf'] ?? '')) === '') {
                $errors['district_mm_cf'] = [
                    'requiredIfDistrictMm' => 'Indicare il codice fiscale del mobility manager di distretto',
                ];
            }
            if (trim((string)($data['district_mm_email'] ?? '')) === '') {
                $errors['district_mm_email'] = [
                    'requiredIfDistrictMm' => 'Indicare l\'indirizzo e-mail di servizio del mobility '
                        . 'manager di distretto',
                ];
            }
            if (trim((string)($data['district_mm_phone'] ?? '')) === '') {
                $errors['district_mm_phone'] = [
                    'requiredIfDistrictMm' => 'Indicare il numero di telefono del mobility manager di distretto',
                ];
            }

            $districtRelationshipType = $data['district_relationship_type'] ?? null;
            if (empty($districtRelationshipType)) {
                $errors['district_relationship_type'] = [
                    'requiredIfDistrictMm' => 'Indicare il rapporto del mobility manager di distretto con l\'azienda',
                ];
            } elseif (
                $districtRelationshipType === 'dipendente_azienda_distretto'
                && trim((string)($data['district_employer_company'] ?? '')) === ''
            ) {
                $errors['district_employer_company'] = [
                    'requiredIfDistrictMm' => 'Indicare l\'azienda del distretto di cui il mobility manager '
                        . 'è dipendente',
                ];
            } elseif (
                $districtRelationshipType === 'soggetto_esterno'
                && trim((string)($data['district_external_entity'] ?? '')) === ''
            ) {
                $errors['district_external_entity'] = [
                    'requiredIfDistrictMm' => 'Indicare il soggetto esterno incaricato come mobility '
                        . 'manager di distretto',
                ];
            }

            if (empty($data['district_companies']) || !is_array($data['district_companies'])) {
                $errors['district_companies'] = [
                    'requiredIfDistrictMm' => 'Indicare almeno un\'azienda del distretto',
                ];
            } else {
                // Validazione per riga (formato CF/P.IVA, lunghezza denominazione, ecc.)
                // eseguita SOLO quando il MM di distretto è attivo: se lasciata
                // incondizionata (com'era prima), il frontend invia sempre una riga
                // vuota di default anche a sezione nascosta e ogni richiesta ordinaria
                // fallisce con "denominazione obbligatoria" pur non usando la funzione.
                $errors = array_merge(
                    $errors,
                    $this->AdhesionRequests->validateDistrictCompanies($data['district_companies'])
                );
            }
        }

        if (!empty($errors)) {
            $this->respond(422, ['msg' => 'Dati non validi', 'errors' => $errors]);

            return;
        }

        // #536: senza MM di distretto i dati `district_*` (il form invia sempre una riga
        // vuota di default in `district_companies`) non vanno salvati: resta solo
        // `uses_district_mm`, che il backoffice mostra come "No". Dopo la validazione,
        // quindi gli esiti 422 non cambiano.
        if (!$usesDistrictMm) {
            foreach (array_keys($data) as $districtKey) {
                if (is_string($districtKey) && str_starts_with($districtKey, 'district_')) {
                    unset($data[$districtKey]);
                }
            }
        }

        $people = array_values($data['people'] ?? []);

        // --- Duplicati contro gli utenti esistenti e le richieste già aperte --------
        $duplicateErrors = $this->findDuplicateErrors($data, $people);
        if (!empty($duplicateErrors)) {
            // 409 quando il conflitto è con una richiesta ancora in corso (non è un
            // errore di compilazione: è la stessa pratica già inviata), 422 altrimenti.
            $status = !empty($duplicateErrors['__conflict']) ? 409 : 422;
            unset($duplicateErrors['__conflict']);
            $this->respond($status, [
                'msg' => $status === 409
                    ? 'Esiste già una richiesta di adesione in corso per questi dati'
                    : 'Alcuni dati risultano già presenti a sistema',
                'errors' => $duplicateErrors,
            ]);

            return;
        }

        $usersTable = $this->AdhesionRequests->Users;
        $connection = $this->AdhesionRequests->getConnection();

        $adhesionRequest = null;
        $token = bin2hex(random_bytes(32));

        $success = $connection->transactional(
            function () use ($usersTable, $data, $people, $token, $isScuola, &$adhesionRequest) {
                // Utente referente creato disabilitato. `active => false` esplicito
                // sopravvive a beforeMarshal (che default a true solo se la chiave è
                // assente). Password casuale: RER è IdP-only, ma la colonna è NOT NULL.
                $user = $usersTable->newEntity([
                    'email' => $data['mm_email'],
                    'username' => $data['mm_email'],
                    'first_name' => $data['mm_first_name'],
                    'last_name' => $data['mm_last_name'],
                    'cf' => $data['mm_cf'],
                    'mobile' => $data['mm_phone'] ?? null,
                    'role' => self::GRANTED_ROLE,
                    'active' => false,
                    'password' => bin2hex(random_bytes(32)),
                ], [
                    // Flusso fidato: l'entity blocca role/password al mass-assignment.
                    'accessibleFields' => [
                        'role' => true,
                        'password' => true,
                    ],
                ]);
                if (!$usersTable->save($user)) {
                    return false;
                }

                // `data` = solo le chiavi previste (allow-list). Senza di essa qualunque
                // campo arbitrario inviato al form pubblico verrebbe persistito e poi
                // riversato in companies.additional_data.
                $payload = $this->AdhesionRequests->filterAllowedData($data);
                foreach (self::CONTROL_KEYS as $key) {
                    unset($payload[$key]);
                }

                // Stessa normalizzazione applicata a people[].cf più sotto: il codice
                // meccanografico va sempre maiuscolo prima di essere persistito in `data`.
                if (!empty($payload['school_mechanographic_code'])) {
                    $payload['school_mechanographic_code'] = strtoupper(
                        trim((string)$payload['school_mechanographic_code'])
                    );
                }

                $city = trim((string)($data['reference_city'] ?? ''));
                $province = strtoupper(trim((string)($data['reference_province'] ?? '')));

                $adhesionRequest = $this->AdhesionRequests->newEntity([
                    'status' => AdhesionRequest::STATUS_PDF_PENDING,
                    'theme' => $data['theme'] ?? Configure::read('theme'),
                    'organization_type' => $data['organization_type'],
                    // Risolto SOLO qui, da questo array esplicito — mai dallo spread dei
                    // dati client, anche se la colonna è `_accessible => true` sull'entity
                    // (D9, docs/dev-notes/adesione-flow.md): è l'array esplicito, non il
                    // flag di accessibilità, a impedire l'iniezione lato client.
                    'company_type_id' => $isScuola
                        ? $this->AdhesionRequests->companyTypeIdForSchoolType($data['school_type'] ?? null)
                        : null,
                    'reference_city' => $city !== '' ? $city : null,
                    'reference_province' => $province !== '' ? $province : null,
                    'data' => $payload,
                    // Requisito 2a (compliance privacy RER "EMMA RE-R"): colonna reale,
                    // non dentro `data` (esclusa da ALLOWED_DATA_KEYS/filterAllowedData()).
                    // Il checkbox dedicato è stato rimosso dal form pubblico (vedi
                    // docs/dev-notes/rer-privacy-consent.md): se assente dal payload si
                    // deriva da accept_gdpr (obbligatorio, già validato `mustBeTrue` più
                    // sopra) invece di azzerarlo silenziosamente a false, per non far
                    // apparire nel backoffice audit un rifiuto che non c'è mai stato.
                    'accept_privacy_informativa' => array_key_exists('accept_privacy_informativa', $data)
                        ? filter_var($data['accept_privacy_informativa'], FILTER_VALIDATE_BOOLEAN)
                        : filter_var($data['accept_gdpr'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'ip' => $this->request->clientIp(),
                    'user_id' => $user->id,
                    // Se nessuna area copre il comune/provincia nessun moma_area vedrà la
                    // richiesta: con la doppia approvazione resterebbe bloccata per sempre.
                    // Non si rifiuta l'invio, lo si segnala al backoffice.
                    'territory_unmatched' => !$this->territoryIsCovered($city, $province),
                    'people' => array_map(function ($person) {
                        return [
                            'first_name' => trim((string)($person['first_name'] ?? '')),
                            'last_name' => trim((string)($person['last_name'] ?? '')),
                            'email' => strtolower(trim((string)($person['email'] ?? ''))),
                            'cf' => strtoupper(trim((string)($person['cf'] ?? ''))),
                        ];
                    }, $people),
                ], [
                    'associated' => ['People'],
                ]);

                // Token e scadenza non sono mass-assignable: si scrivono qui.
                $adhesionRequest->access_token = $token;
                $adhesionRequest->access_token_expires = FrozenTime::now()
                    ->addDays(self::TOKEN_TTL_DAYS);
                // Requisito 3 (compliance privacy RER "EMMA RE-R"): STESSO valore di
                // access_token, ma su una colonna che uploadSigned() non azzera mai —
                // vedi la migrazione AddEmployeePrivacyDocFieldsToAdhesionRequests per
                // il perché access_token da solo non può servire a questo scopo.
                $adhesionRequest->company_doc_token = $token;

                if (!$this->AdhesionRequests->save($adhesionRequest, ['associated' => ['People']])) {
                    return false;
                }

                return true;
            }
        );

        if (!$success) {
            $this->respond(422, [
                'msg' => 'Impossibile salvare la richiesta di adesione',
                'errors' => $adhesionRequest ? $adhesionRequest->getErrors() : [],
            ]);

            return;
        }

        // PDF e notifica dopo il commit: un fallimento qui non deve rompere il 200. Il PDF
        // viene comunque rigenerato al primo download se manca.
        try {
            $this->documents()->generateSummary($adhesionRequest);
            $adhesionRequest->pdf_generated_at = FrozenTime::now();
            $this->AdhesionRequests->save($adhesionRequest);
        } catch (\Throwable $e) {
            Log::write('error', 'Adhesion PDF generation failed: ' . $e->getMessage(), ['scope' => ['adhesion']]);
        }

        try {
            (new adhesionSubmittedNotification($adhesionRequest, $token))->toMail();
        } catch (\Throwable $e) {
            Log::write('error', 'Adhesion notification failed: ' . $e->getMessage(), ['scope' => ['adhesion']]);
        }

        // Notify reviewers (admin + moma_area di territorio) at submission (B2).
        try {
            $reviewerEmails = $this->reviewerEmails(
                $adhesionRequest->reference_city,
                $adhesionRequest->reference_province
            );
            foreach ($reviewerEmails as $email) {
                (new adhesionSubmittedReviewerNotification($adhesionRequest, $email))->toMail();
            }
        } catch (\Throwable $e) {
            Log::write('error', 'Adhesion reviewer notification failed: ' . $e->getMessage(), ['scope' => ['adhesion']]);
        }

        $this->respond(200, [
            'msg' => 'Richiesta di adesione inviata con successo',
            'id' => $adhesionRequest->id,
            // Il token torna UNA sola volta, così il frontend può proporre subito il
            // download del PDF senza attendere l'email.
            'token' => $token,
        ]);
    }

    /**
     * Elenco pubblico dei comuni noti per una provincia (Fase 3.4).
     *
     * Serve a sostituire il campo libero "comune di riferimento" con un elenco chiuso:
     * un comune scritto a mano con una variante ("Reggio Emilia" invece di "REGGIO
     * NELL'EMILIA") non combacia con nessuna `areas` e rende la richiesta invisibile a
     * ogni moma_area. Se la lista è vuota il frontend torna al testo libero, per non
     * bloccare il form su una configurazione incompleta delle aree.
     *
     * @return void
     */
    public function comuni(): void
    {
        $this->assertModuleEnabled();
        $this->request->allowMethod(['get']);

        $province = strtoupper(trim((string)$this->request->getQuery('province')));

        $query = $this->fetchTable('Areas')->find()
            ->select(['city'])
            ->where(['city IS NOT' => null, 'city !=' => ''])
            ->order(['city' => 'ASC'])
            ->distinct(['city']);
        if ($province !== '') {
            $query->where(['province' => $province]);
        }

        $comuni = [];
        // Lista ISTAT statica solo se è indicata una provincia nota (senza provincia
        // il comportamento resta quello storico: solo Areas.city).
        if ($province !== '') {
            $static = require CONFIG . 'data' . DS . 'comuni_emilia_romagna.php';
            foreach ($static[$province] ?? [] as $name) {
                $comuni[mb_strtoupper(trim($name))] = $name;
            }
        }
        // Le città delle Areas si aggiungono solo se non già presenti (vince l'ortografia ISTAT).
        foreach ($query->all() as $area) {
            $name = trim((string)$area->city);
            $comuni[mb_strtoupper($name)] ??= $name;
        }
        $comuni = array_values($comuni);
        $collator = new \Collator('it_IT');
        $collator->sort($comuni);

        $this->respond(200, ['comuni' => $comuni]);
    }

    /**
     * Download del PDF di riepilogo da firmare (Step 2), autorizzato dal token.
     *
     * @param string|null $token Token di accesso della richiesta.
     * @return \Cake\Http\Response
     */
    public function pdf($token = null)
    {
        $this->assertModuleEnabled();
        $this->request->allowMethod(['get']);

        $adhesionRequest = $this->getByToken($token);

        $path = $this->documents()->summaryPath((int)$adhesionRequest->id);
        if (!file_exists($path)) {
            // Rigenerazione difensiva: se la generazione al submit è fallita (o i file
            // sono stati ripuliti) il referente non deve restare bloccato.
            $adhesionRequest = $this->AdhesionRequests->get($adhesionRequest->id, [
                'contain' => ['People'],
            ]);
            $path = $this->documents()->generateSummary($adhesionRequest);
            $adhesionRequest->pdf_generated_at = FrozenTime::now();
            $this->AdhesionRequests->save($adhesionRequest);
        }

        return $this->serveFileInline($path, 'modulo-adesione-' . $adhesionRequest->id . '.pdf');
    }

    /**
     * Caricamento del PDF firmato digitalmente (Step 2 -> Step 3).
     *
     * Al termine la richiesta passa in `in_review` e i valutatori vengono avvisati: prima
     * di questo momento la richiesta non è valutabile.
     *
     * @param string|null $token Token di accesso della richiesta.
     * @return void
     */
    public function uploadSigned($token = null): void
    {
        $this->assertModuleEnabled();
        $this->request->allowMethod(['post']);

        $adhesionRequest = $this->getByToken($token);

        if ($adhesionRequest->status !== AdhesionRequest::STATUS_PDF_PENDING) {
            $this->respond(409, ['msg' => 'Il documento firmato per questa richiesta è già stato caricato']);

            return;
        }

        try {
            $stored = $this->documents()->storeSignedFile(
                $this->request->getUploadedFile('signed_pdf'),
                (int)$adhesionRequest->id
            );
        } catch (\RuntimeException $e) {
            $this->respond(422, [
                'msg' => $e->getMessage(),
                'errors' => ['signed_pdf' => ['upload' => $e->getMessage()]],
            ]);

            return;
        }

        $adhesionRequest->signed_pdf_filename = $stored['filename'];
        $adhesionRequest->signed_pdf_size = $stored['size'];
        $adhesionRequest->signed_pdf_mime = $stored['mime'];
        $adhesionRequest->signature_format = $stored['format'];
        $adhesionRequest->signed_pdf_uploaded_at = FrozenTime::now();
        $adhesionRequest->status = AdhesionRequest::STATUS_IN_REVIEW;
        // Token consumato: il link non deve permettere un secondo caricamento.
        $adhesionRequest->access_token = null;
        $adhesionRequest->access_token_expires = null;

        if (!$this->AdhesionRequests->save($adhesionRequest)) {
            $this->respond(422, [
                'msg' => 'Impossibile registrare il documento firmato',
                'errors' => $adhesionRequest->getErrors(),
            ]);

            return;
        }

        // Ora la richiesta è valutabile: avvisa admin + moma_area competenti (§11).
        try {
            $this->notifyReviewers($adhesionRequest);
        } catch (\Throwable $e) {
            Log::write('error', 'Adhesion notification failed: ' . $e->getMessage(), ['scope' => ['adhesion']]);
        }

        $this->respond(200, [
            'msg' => 'Documento firmato caricato: la richiesta è ora in valutazione',
            'status' => $adhesionRequest->status,
        ]);
    }

    /**
     * Caricamento FACOLTATIVO del documento di identità del referente (bug 6),
     * consigliato a chi non firma digitalmente. Risolto dal token PERMANENTE
     * `company_doc_token` (l'`access_token` è azzerato da uploadSigned()), stesso trust
     * model di employeePrivacyDocument(): token sconosciuto -> 404 senza indizi.
     * Ammesso solo in `pdf_pending`/`in_review`, altrimenti 409. Un solo file per
     * richiesta (fronte e retro insieme): un nuovo upload sostituisce il precedente.
     * Il file non è mai linkato da pagine pubbliche/email; lo scarica solo il
     * backoffice via staffIdentityDocument().
     *
     * @param string|null $token Token permanente della richiesta.
     * @return void
     */
    public function uploadIdentity($token = null): void
    {
        $this->assertModuleEnabled();
        $this->request->allowMethod(['post']);

        $adhesionRequest = $this->getByCompanyDocToken($token);

        $allowedStatuses = [AdhesionRequest::STATUS_PDF_PENDING, AdhesionRequest::STATUS_IN_REVIEW];
        if (!in_array($adhesionRequest->status, $allowedStatuses, true)) {
            $this->respond(409, [
                'msg' => 'Il documento di identità non può più essere caricato per questa richiesta',
            ]);

            return;
        }

        try {
            $stored = $this->documents()->storeIdentityFile(
                $this->request->getUploadedFile('identity_doc'),
                (int)$adhesionRequest->id
            );
        } catch (\RuntimeException $e) {
            $this->respond(422, [
                'msg' => $e->getMessage(),
                'errors' => ['identity_doc' => ['upload' => $e->getMessage()]],
            ]);

            return;
        }

        $adhesionRequest->identity_doc_filename = $stored['filename'];
        $adhesionRequest->identity_doc_size = $stored['size'];
        $adhesionRequest->identity_doc_mime = $stored['mime'];
        $adhesionRequest->identity_doc_uploaded_at = FrozenTime::now();

        if (!$this->AdhesionRequests->save($adhesionRequest)) {
            // Niente file orfano se la riga non si aggiorna, ma il documento valido già
            // caricato (se c'è) resta: si scarta SOLO il file appena salvato.
            $this->documents()->deleteIdentityFile((int)$adhesionRequest->id, $stored['filename']);
            $this->respond(422, [
                'msg' => 'Impossibile registrare il documento di identità',
                'errors' => $adhesionRequest->getErrors(),
            ]);

            return;
        }

        // Solo ora, a riga salvata, si rimuovono i file che il nuovo sostituisce.
        $this->documents()->purgeIdentityFile((int)$adhesionRequest->id, $stored['filename']);

        $this->respond(200, [
            'msg' => 'Documento di identità caricato',
            'filename' => $stored['filename'],
        ]);
    }

    /**
     * Stato (GET) e caricamento (POST) del "Modulo trattamento dati personali dei
     * dipendenti" (requisito 3 della compliance privacy RER "EMMA RE-R"), tramite lo
     * stesso link permanente `/adesione/completa/:token` usato per il PDF di riepilogo
     * dell'adesione — stesso modello di fiducia di uploadSigned() (il token è l'unica
     * credenziale, nessun controllo di sessione utente), ma su un token DIVERSO e MAI
     * consumato (`company_doc_token`, non `access_token`): vedi la migrazione
     * AddEmployeePrivacyDocFieldsToAdhesionRequests per il perché.
     *
     * Consentito in `pdf_pending`, `in_review` e `approved` (dal 2026-10-01 anche prima
     * del PDF di adesione firmato: i due documenti sono indipendenti e il MoMa aziendale
     * deve poterli caricare nell'ordine che preferisce). Solo `rejected` (richiesta
     * chiusa) risponde 409. Un token sconosciuto/malformato resta 404, mai 409 (nessun
     * oracolo di token-guessing).
     *
     * Prima dell'approvazione non esiste ancora un `company_id`: la riga viene
     * risolta/creata via `adhesion_request_id` (documento "pendente"); dopo
     * l'approvazione, enable() la ri-aggancia all'azienda e nulla `adhesion_request_id`
     * (vedi CompanyPrivacyDocumentsTable per l'invariante "esattamente uno dei due").
     *
     * GET con estensione `.json` (default) -> stato `{exists, filename, uploaded_at}`
     * (contratto invariato, consumato da `AdesioneCompleta.vue`); GET con qualunque
     * altra estensione (es. `.pdf`) sullo stesso URL -> streaming del documento
     * stesso, per il download da parte dell'azienda titolare del token — stesso
     * pattern di `SurveysController::employeePrivacyDocument()`. Nessuna nuova voce in
     * `allowUnauthenticated()`: è lo stesso endpoint pubblico di sempre.
     *
     * @param string|null $token Token permanente della richiesta.
     */
    public function employeePrivacyDocument($token = null)
    {
        $this->assertModuleEnabled();
        $this->assertEmployeePrivacyDocEnabled();
        $this->request->allowMethod(['get', 'post']);

        $adhesionRequest = $this->getByCompanyDocToken($token);

        $allowedStatuses = [
            AdhesionRequest::STATUS_PDF_PENDING,
            AdhesionRequest::STATUS_IN_REVIEW,
            AdhesionRequest::STATUS_APPROVED,
        ];
        if (!in_array($adhesionRequest->status, $allowedStatuses, true)) {
            $this->respond(409, [
                'msg' => 'Il documento non può più essere caricato: la richiesta di adesione è chiusa',
            ]);

            return null;
        }

        $companyId = !empty($adhesionRequest->company_id) ? (int)$adhesionRequest->company_id : null;
        $adhesionRequestId = (int)$adhesionRequest->id;
        $documents = $this->fetchTable('CompanyPrivacyDocuments');
        $existing = $this->findEmployeePrivacyDocument($adhesionRequest);

        if ($this->request->is('get')) {
            // Estensione `.json` (default) -> stato; qualunque altra estensione (es. `.pdf`)
            // sullo stesso URL -> streaming del file. Stesso pattern di
            // SurveysController::employeePrivacyDocument() (righe 2118-2124): un solo
            // endpoint, due chiamanti, nessuna voce nuova in allowUnauthenticated().
            // NB: `respond()` renderizza una view JSON — per lo streaming si ritorna
            // direttamente la Response. Estensione di ROUTE, non garanzia sul payload
            // (D8, docs/dev-notes/adesione-flow.md): il file può essere un `.p7m`, il
            // nome/mime reali vengono comunque da `withFile()`.
            $ext = $this->request->getParam('_ext');
            if ($ext && $ext !== 'json') {
                if ($existing === null || !file_exists($existing->path)) {
                    throw new NotFoundException('Documento non disponibile');
                }

                return $this->serveFileInline(
                    $existing->path,
                    $existing->filename,
                    null,
                    $existing->filename
                );
            }

            $this->respond(200, [
                'exists' => $existing !== null,
                'filename' => $existing->filename ?? null,
                'uploaded_at' => $existing->modified ?? null,
            ]);

            return null;
        }

        try {
            $stored = $companyId !== null
                ? $this->companyPrivacyDocumentService()->storeFile(
                    $this->request->getUploadedFile('document'),
                    $companyId
                )
                : $this->companyPrivacyDocumentService()->storeFileForRequest(
                    $this->request->getUploadedFile('document'),
                    $adhesionRequestId
                );
        } catch (\RuntimeException $e) {
            $this->respond(422, [
                'msg' => $e->getMessage(),
                'errors' => ['document' => ['upload' => $e->getMessage()]],
            ]);

            return null;
        }

        if ($existing === null) {
            $existing = $documents->newEntity(
                $companyId !== null
                    ? ['company_id' => $companyId]
                    : ['adhesion_request_id' => $adhesionRequestId]
            );
        }
        $existing->filename = $stored['filename'];
        $existing->path = $stored['path'];

        if (!$documents->save($existing)) {
            $this->respond(422, [
                'msg' => 'Impossibile registrare il documento',
                'errors' => $existing->getErrors(),
            ]);

            return null;
        }

        $this->respond(200, [
            'msg' => 'Documento caricato con successo',
            'filename' => $existing->filename,
        ]);

        return null;
    }

    /**
     * Stato pubblico e privo di PII del link di adesione (Item 1/3), risolto dal token
     * PERMANENTE (`company_doc_token`, mai azzerato) invece di `access_token`, che può
     * già essere nullo (documento firmato già caricato, o token scaduto) senza che
     * questo renda il link "sconosciuto" — stesso trust model di
     * `employeePrivacyDocument()`: il token è l'unica credenziale, nessun controllo di
     * sessione, e un token sconosciuto/malformato resta 404 (nessun oracolo di
     * token-guessing). Il payload è deliberatamente minimale: niente `data` (contiene
     * `mm_email`/CF), nessuno dei due token, nessun `company_id`, nessun
     * `reject_reason` (testo libero d'ufficio, leggibile da chiunque abbia il token).
     *
     * @param string|null $token Token permanente della richiesta.
     * @return void
     */
    public function linkStatus($token = null): void
    {
        $this->assertModuleEnabled();
        $this->request->allowMethod(['get']);

        $adhesionRequest = $this->getByCompanyDocToken($token);

        $this->respond(200, [
            'status' => $adhesionRequest->status,
            'signed_pdf_uploaded' => !empty($adhesionRequest->signed_pdf_filename),
            'signed_pdf_filename' => $adhesionRequest->signed_pdf_filename,
            'signed_pdf_uploaded_at' => $adhesionRequest->signed_pdf_uploaded_at,
            // Solo un booleano: mai nome/mime del documento di identità su un endpoint pubblico.
            'identity_doc_uploaded' => !empty($adhesionRequest->identity_doc_filename),
            'access_token_valid' => $adhesionRequest->accessTokenIsLive(),
            'access_token_expires' => $adhesionRequest->access_token_expires,
            'can_resend_link' => $adhesionRequest->canResendLink(self::RESEND_COOLDOWN_MINUTES),
            'resend_available_at' => $adhesionRequest->resendAvailableAt(self::RESEND_COOLDOWN_MINUTES),
        ]);
    }

    /**
     * Reinvio self-service del link di adesione (Item 3), risolto dal token permanente
     * `company_doc_token` come `linkStatus()`. `pdf_pending` ruota il token (o riusa
     * quello ancora valido, per non invalidare un link non ancora aperto da un
     * doppio-click); `in_review`/`approved` reinviano lo STESSO `company_doc_token`,
     * mai ruotato. `rejected` è una pratica morta: 409, nessun reinvio possibile.
     *
     * @param string|null $token Token permanente della richiesta.
     * @return void
     */
    public function resendLink($token = null): void
    {
        $this->assertModuleEnabled();
        $this->request->allowMethod(['post']);

        $adhesionRequest = $this->getByCompanyDocToken($token);

        if ($adhesionRequest->status === AdhesionRequest::STATUS_REJECTED) {
            $this->respond(409, ['msg' => 'La richiesta è stata rifiutata: non è possibile reinviare il link']);

            return;
        }

        if (!$adhesionRequest->canResendLink(self::RESEND_COOLDOWN_MINUTES)) {
            $this->respond(429, [
                'msg' => 'Hai già richiesto un nuovo invio di recente: riprova tra qualche minuto',
                'resend_available_at' => $adhesionRequest->resendAvailableAt(self::RESEND_COOLDOWN_MINUTES),
            ]);

            return;
        }

        $result = $this->regenerateAccessTokenAndNotify($adhesionRequest, null);
        if (!$result['ok']) {
            $this->respondToRegenerationFailure($result);

            return;
        }

        $this->respond(200, [
            'msg' => 'Link inviato: controlla la tua casella di posta',
            'email' => $adhesionRequest->maskedReferentEmail(),
            'access_token_expires' => $adhesionRequest->access_token_expires,
        ]);
    }

    /**
     * Rigenerazione/reinvio del link da backoffice (Item 3), per admin/moma_area di
     * territorio: utile quando il referente ha perso il link o il token è scaduto senza
     * che nessuno lo richieda da sé. Il cooldown self-service è deliberatamente
     * bypassato (azione di staff, non un doppio-click accidentale), ma contatore e
     * `access_token_resent_by` restano scritti per l'audit. La risposta non contiene
     * MAI il token (vedi testSubmitDoesNotLeakAccessTokenInBackofficeResponses per il
     * pattern equivalente su submit()): lo staff può innescare un invio, non leggere o
     * copiare il link.
     *
     * @param string|null $id Adhesion request id.
     * @return void
     */
    public function regenerateToken($id = null): void
    {
        $this->assertModuleEnabled();
        $this->allowRolesOnly(['admin', 'moma_area']);
        $this->request->allowMethod(['post']);

        $adhesionRequest = $this->AdhesionRequests->get($id);
        $this->assertTerritoryFor($adhesionRequest->reference_city, $adhesionRequest->reference_province);

        if ($adhesionRequest->status === AdhesionRequest::STATUS_REJECTED) {
            $this->respond(409, ['msg' => 'La richiesta è stata rifiutata: non è possibile rigenerare il link']);

            return;
        }

        $identity = $this->Authentication->getIdentity();
        $actorUserId = $identity ? $identity->get('id') : null;

        $result = $this->regenerateAccessTokenAndNotify($adhesionRequest, $actorUserId);
        if (!$result['ok']) {
            $this->respondToRegenerationFailure($result);

            return;
        }

        $this->respond(200, [
            'msg' => 'Link rigenerato e inviato',
            'access_token_expires' => $adhesionRequest->access_token_expires,
            'access_token_resent_at' => $adhesionRequest->access_token_resent_at,
            'access_token_resend_count' => $adhesionRequest->access_token_resend_count,
        ]);
    }

    /**
     * Download del PDF firmato, per i valutatori (autenticato + territorio).
     *
     * @param string|null $id Adhesion request id.
     * @return \Cake\Http\Response
     */
    public function signedPdf($id = null)
    {
        $this->allowRolesOnly(['admin', 'moma_area']);

        $adhesionRequest = $this->AdhesionRequests->get($id);
        $this->assertTerritoryFor($adhesionRequest->reference_city, $adhesionRequest->reference_province);

        $path = $this->documents()->signedPath($adhesionRequest);
        if ($path === null) {
            throw new NotFoundException('Documento firmato non disponibile');
        }

        return $this->serveFileInline(
            $path,
            $adhesionRequest->signed_pdf_filename,
            $adhesionRequest->signature_format
        );
    }

    /**
     * Download del "Modulo trattamento dati personali dei dipendenti" (requisito 3,
     * compliance privacy RER "EMMA RE-R"), per i valutatori (autenticato + territorio)
     * — mirror di `signedPdf()` per autenticazione/territorio e di
     * `SurveysController::employeePrivacyDocument()` per lo streaming.
     *
     * ⚠️ L'URL termina `.pdf` per estensione di ROUTE (`config/routes.php` la elenca
     * già in `setExtensions()`), non per garanzia sul payload (D8,
     * docs/dev-notes/adesione-flow.md): il file caricato può essere un `.p7m`
     * (CAdES), e `withFile()` imposta comunque nome/mime reali dalla riga DB.
     *
     * @param string|null $id Adhesion request id.
     * @return \Cake\Http\Response
     */
    public function staffEmployeePrivacyDoc($id = null)
    {
        $this->allowRolesOnly(['admin', 'moma_area']);
        $this->assertModuleEnabled();
        $this->assertEmployeePrivacyDocEnabled();
        $this->request->allowMethod(['get']);

        $adhesionRequest = $this->AdhesionRequests->get($id);
        $this->assertTerritoryFor($adhesionRequest->reference_city, $adhesionRequest->reference_province);

        $document = $this->findEmployeePrivacyDocument($adhesionRequest);
        if ($document === null || !file_exists($document->path)) {
            throw new NotFoundException('Modulo trattamento dati dipendenti non disponibile');
        }

        return $this->serveFileInline(
            $document->path,
            $document->filename,
            null,
            $document->filename
        );
    }

    /**
     * Download del documento di identità facoltativo, per i valutatori (autenticato +
     * territorio, come signedPdf()). URL SENZA estensione (`/adhesion-requests/
     * staff-identity-document/{id}`): `.jpg`/`.png` non sono estensioni di route
     * (config/routes.php), quindi il tipo si decide dalla riga DB. `inline` + nosniff.
     * Ogni download lascia una riga di audit (attore, richiesta).
     *
     * @param string|null $id Adhesion request id.
     * @return \Cake\Http\Response
     */
    public function staffIdentityDocument($id = null)
    {
        $this->allowRolesOnly(['admin', 'moma_area']);
        $this->assertModuleEnabled();
        $this->request->allowMethod(['get']);

        $adhesionRequest = $this->AdhesionRequests->get($id);
        $this->assertTerritoryFor($adhesionRequest->reference_city, $adhesionRequest->reference_province);

        $path = $this->documents()->identityPath($adhesionRequest);
        if ($path === null) {
            throw new NotFoundException('Documento di identità non disponibile');
        }

        $identity = $this->Authentication->getIdentity();
        Log::info(
            sprintf(
                'Identity document downloaded: actor=%s adhesion_request=%d',
                $identity ? (string)$identity->get('id') : 'unknown',
                (int)$adhesionRequest->id
            ),
            ['scope' => ['adhesion']]
        );

        // Documento di identità: mai in cache di browser/proxy condivisi.
        return $this->serveFileInline(
            $path,
            $adhesionRequest->identity_doc_filename,
            null,
            null,
            $adhesionRequest->identity_doc_mime
        )->withHeader('Cache-Control', 'private, no-store');
    }

    /**
     * Lista paginata delle richieste (admin / moma_area di territorio).
     *
     * @return void
     */
    public function index(): void
    {
        $this->allowRolesOnly(['admin', 'moma_area']);

        $query = $this->AdhesionRequests->find()
            ->contain(['Users', 'Companies', 'ProcessedByUser', 'AdminDecidedBy', 'MomaDecidedBy', 'People'])
            ->order(['AdhesionRequests.created' => 'DESC']);

        $status = $this->request->getQuery('status');
        if (!empty($status)) {
            $query->where(['AdhesionRequests.status' => $status]);
        }

        $this->scopeQueryToTerritory(
            $query,
            'AdhesionRequests.reference_city',
            'AdhesionRequests.reference_province'
        );

        // La pagina arriva dal frontend: prima veniva ignorata, quindi la paginazione del
        // backoffice non funzionava oltre la prima pagina.
        $page = (int)$this->request->getQuery('page');
        if ($page > 0) {
            $this->paginate = ['page' => $page] + (array)$this->paginate;
        }

        try {
            $adhesionRequests = $this->paginate($query);
        } catch (NotFoundException $e) {
            $adhesionRequests = [];
        }
        $this->annotateRequiredDocuments($adhesionRequests);
        $pagination = $this->Paginator->getPagingParams();

        $this->set(compact('adhesionRequests', 'pagination'));
        $this->viewBuilder()->setOption('serialize', ['adhesionRequests', 'pagination']);
    }

    /**
     * Dettaglio di una richiesta (admin / moma_area di territorio).
     *
     * @param string|null $id Adhesion request id.
     * @return void
     */
    public function view($id = null): void
    {
        $this->allowRolesOnly(['admin', 'moma_area']);

        $adhesionRequest = $this->AdhesionRequests->get($id, [
            'contain' => [
                'Users', 'Companies', 'ProcessedByUser', 'AdminDecidedBy', 'MomaDecidedBy', 'People',
                // Storico dei voti (#538), dal più vecchio; del votante solo nome/email
                // (mai password/token).
                'Decisions' => [
                    'Users' => function ($q) {
                        return $q->select(['Users.id', 'Users.first_name', 'Users.last_name', 'Users.email']);
                    },
                ],
            ],
        ]);
        $this->assertTerritoryFor($adhesionRequest->reference_city, $adhesionRequest->reference_province);

        // Metadati (mai il path assoluto su disco, né una download_url — vedi
        // staffEmployeePrivacyDoc()/adesione.service.js lato frontend) del "Modulo
        // trattamento dati personali dei dipendenti", risolto con la STESSA logica XOR
        // usata dal token action pubblico e dall'azione di staff — NON un `contain`
        // (D4, findEmployeePrivacyDocument()).
        $employeePrivacyDocument = $this->findEmployeePrivacyDocument($adhesionRequest);
        $adhesionRequest->set('employee_privacy_document', $employeePrivacyDocument === null ? null : [
            'exists' => true,
            'filename' => $employeePrivacyDocument->filename,
            'uploaded_at' => $employeePrivacyDocument->modified,
        ]);
        $adhesionRequest->set('missing_documents', $adhesionRequest->status === AdhesionRequest::STATUS_IN_REVIEW
            ? $this->missingRequiredDocuments($adhesionRequest)
            : []);

        // Segnalazione al valutatore: esiste già un'azienda con questo CF/P.IVA? In tal
        // caso conviene collegarla (company_id) invece di crearne una nuova.
        $existingCompany = $this->findExistingCompany((array)$adhesionRequest->data);

        $this->set(compact('adhesionRequest', 'existingCompany'));
        $this->viewBuilder()->setOption('serialize', ['adhesionRequest', 'existingCompany']);
    }

    /**
     * Registra la valutazione dell'attore corrente (Step 3).
     *
     * Le due valutazioni sono INDIPENDENTI: un `admin` scrive solo le colonne `admin_*`,
     * un `moma_area` solo le `moma_*`. Nessuno può decidere per l'altro (§7.1), e il
     * controllo è server-side: il gating dei bottoni nel backoffice è solo UX.
     *
     * #538: ogni attore (qualunque utente con quel ruolo) può CAMBIARE il proprio voto
     * finché la richiesta è `in_review`; ogni voto nuovo o modificato accoda una riga allo
     * storico `adhesion_request_decisions`. Rivotare identico (stesso esito e motivazione)
     * è idempotente e non scrive lo storico. Voto + storico + esito sono atomici (richiesta
     * letta con `FOR UPDATE`); mail di rifiuto, purge del documento di identità e post-
     * processing dell'abilitazione girano solo DOPO il commit.
     *
     * Esiti:
     *  - entrambe positive  -> `approved` + abilitazione degli account (365 giorni)
     *  - entrambe negative  -> `rejected` + notifica al referente (con le due motivazioni)
     *  - una sola valutazione, o valutazioni discordanti -> resta `in_review` (nessuna
     *    mail, nessun purge): le discordanze si risolvono offline finché i voti si allineano
     *
     * @param string|null $id Adhesion request id.
     * @return void
     */
    public function decide($id = null): void
    {
        $this->allowRolesOnly(['admin', 'moma_area']);
        $this->request->allowMethod(['post', 'put', 'patch']);

        $adhesionRequest = $this->AdhesionRequests->get($id, ['contain' => ['People']]);
        $this->assertTerritoryFor($adhesionRequest->reference_city, $adhesionRequest->reference_province);

        $identity = $this->Authentication->getIdentity();
        $role = $identity ? $identity->get('role') : null;
        $actor = AdhesionRequest::actorForRole($role);
        if ($actor === null) {
            throw new ForbiddenException('Il tuo ruolo non è competente alla valutazione delle adesioni');
        }

        // Valutabile solo dopo il caricamento del documento firmato.
        if ($adhesionRequest->status !== AdhesionRequest::STATUS_IN_REVIEW) {
            $message = $adhesionRequest->status === AdhesionRequest::STATUS_PDF_PENDING
                ? 'La richiesta non è ancora valutabile: manca il documento firmato'
                : 'La richiesta è già stata elaborata';
            $this->respond(409, ['msg' => $message]);

            return;
        }

        $decision = (string)$this->request->getData('decision');
        if (!in_array($decision, [AdhesionRequest::DECISION_APPROVED, AdhesionRequest::DECISION_REJECTED], true)) {
            $this->respond(422, [
                'msg' => 'Esito non valido',
                'errors' => ['decision' => ['inList' => 'L\'esito deve essere "approved" o "rejected"']],
            ]);

            return;
        }

        // Motivazione obbligatoria in caso di rifiuto (§7.1): prima era obbligatoria solo
        // lato client, il server accettava null.
        $reason = trim((string)$this->request->getData('reason'));
        if ($decision === AdhesionRequest::DECISION_REJECTED && $reason === '') {
            $this->respond(422, [
                'msg' => 'La motivazione del rifiuto è obbligatoria',
                'errors' => ['reason' => ['_empty' => 'Indicare la motivazione del rifiuto']],
            ]);

            return;
        }

        // Bug 5: template di questionario da associare all'azienda alla doppia approvazione.
        // Solo con esito "approved" (ignorato su un rifiuto). Assente/null/'' = MANTIENE il
        // valore già salvato (la select intatta del secondo valutatore non deve cancellare
        // la scelta del primo); per svuotarlo serve `clear_survey_template: true`.
        // Modifica del template da applicare all'entità BLOCCATA (letta più sotto).
        $templateChange = null;
        if ($decision === AdhesionRequest::DECISION_APPROVED) {
            // Gate documenti obbligatori (modulo firmato + modulo dipendenti se il gate è
            // attivo): PRIMA di qualunque mutazione. Il rifiuto non è mai bloccato.
            $missingDocuments = $this->missingRequiredDocuments($adhesionRequest);
            if ($missingDocuments !== []) {
                $this->respond(409, [
                    'msg' => __(
                        'Impossibile approvare: documenti obbligatori mancanti ({0})',
                        $this->missingDocumentsLabel($missingDocuments)
                    ),
                    'code' => 'missing_documents',
                    'missing' => $missingDocuments,
                ]);

                return;
            }

            // #534: l'azienda esistente scelta all'approvazione (`company_id`, poi usata da
            // enable()) va autorizzata PRIMA di qualunque mutazione, anche per il primo
            // approvatore: altrimenti un moma_area potrebbe agganciare persone a un'azienda
            // fuori dal proprio territorio.
            $bodyCompanyId = $this->request->getData('company_id');
            if (!empty($bodyCompanyId)) {
                $chosenId = filter_var($bodyCompanyId, FILTER_VALIDATE_INT);
                $chosenExists = $chosenId !== false && $chosenId >= 1
                    && $this->fetchTable('Companies')->exists(['Companies.id' => $chosenId]);
                if (!$chosenExists) {
                    $this->respond(422, [
                        'msg' => 'L\'azienda selezionata non esiste',
                        'errors' => ['company_id' => ['invalidCompany' => 'Selezionare un\'azienda esistente']],
                    ]);

                    return;
                }
                if ($this->findCompanyInViewerScope($chosenId) === null) {
                    $this->respond(403, ['msg' => 'Non sei autorizzato a usare questa azienda']);

                    return;
                }
            }

            $templateInput = $this->request->getData('survey_template_id');
            if ($templateInput !== null && $templateInput !== '') {
                $templateId = filter_var($templateInput, FILTER_VALIDATE_INT);
                if ($templateId === false || $templateId < 1 || !$this->isSurveyTemplate($templateId)) {
                    $this->respond(422, [
                        'msg' => 'Il questionario selezionato non è un template valido',
                        'errors' => ['survey_template_id' => [
                            'invalidTemplate' => 'Selezionare un template di questionario esistente',
                        ]],
                    ]);

                    return;
                }
                $templateChange = $templateId;
            } elseif (filter_var($this->request->getData('clear_survey_template'), FILTER_VALIDATE_BOOLEAN)) {
                $templateChange = false;
            }
        }

        $identityId = $identity ? $identity->get('id') : null;
        $connection = $this->AdhesionRequests->getConnection();
        $lockedRequest = null;
        $conflictStatus = null;
        // Gli effetti collaterali non transazionali (mail, purge, geocoding, spostamento
        // file) si accodano qui e girano solo dopo il commit.
        $this->postCommitCallbacks = [];
        try {
            $saved = $connection->transactional(function () use (
                $id,
                $actor,
                $decision,
                $reason,
                $identityId,
                $templateChange,
                &$lockedRequest,
                &$conflictStatus
            ) {
                // Rilettura sotto lock: due valutatori che votano insieme vengono
                // serializzati, e chi arriva secondo vede lo stato già chiuso.
                $locked = $this->AdhesionRequests->find()
                    ->where(['AdhesionRequests.id' => $id])
                    ->contain(['People'])
                    ->epilog('FOR UPDATE')
                    ->first();
                if ($locked === null || $locked->status !== AdhesionRequest::STATUS_IN_REVIEW) {
                    $conflictStatus = $locked === null ? 'missing' : (string)$locked->status;

                    return true;
                }
                $lockedRequest = $locked;

                if ($templateChange === false) {
                    $locked->set('survey_template_id', null);
                } elseif ($templateChange !== null) {
                    $locked->set('survey_template_id', $templateChange);
                }

                $newReason = $reason !== '' ? $reason : null;
                $isNoOp = $locked->decisionOf($actor) === $decision
                    && ($locked->get($actor . '_reason') ?? null) === $newReason;
                if (!$isNoOp) {
                    $now = FrozenTime::now();
                    $locked->set($actor . '_decision', $decision);
                    $locked->set($actor . '_decided_by', $identityId);
                    $locked->set($actor . '_decided_at', $now);
                    $locked->set($actor . '_reason', $newReason);

                    // Storico (#538): una riga per ogni voto nuovo o modificato.
                    $decisionsTable = $this->fetchTable('AdhesionRequestDecisions');
                    $row = $decisionsTable->newEmptyEntity();
                    $row->set([
                        'adhesion_request_id' => (int)$locked->id,
                        'actor' => $actor,
                        'decision' => $decision,
                        'reason' => $newReason,
                        'decided_by' => $identityId,
                        'decided_at' => $now,
                    ], ['guard' => false]);
                    if (!$decisionsTable->save($row)) {
                        return false;
                    }
                }

                return $this->finalize($locked);
            });
        } catch (\Throwable $e) {
            $this->postCommitCallbacks = null;
            throw $e;
        }

        $postCommit = $this->postCommitCallbacks ?? [];
        $this->postCommitCallbacks = null;

        if ($conflictStatus !== null) {
            $message = $conflictStatus === AdhesionRequest::STATUS_PDF_PENDING
                ? 'La richiesta non è ancora valutabile: manca il documento firmato'
                : 'La richiesta è già stata elaborata';
            $this->respond(409, ['msg' => $message]);

            return;
        }

        $adhesionRequest = $lockedRequest;
        if ($saved === false) {
            $this->respond(422, [
                'msg' => $this->enableFailureMessage ?? 'Impossibile registrare la valutazione',
                'errors' => $adhesionRequest->getErrors(),
            ]);

            return;
        }

        foreach ($postCommit as $callback) {
            $callback();
        }

        $messages = [
            AdhesionRequest::STATUS_APPROVED => 'Richiesta approvata: utenti abilitati per 365 giorni',
            AdhesionRequest::STATUS_REJECTED => 'Richiesta rifiutata',
            AdhesionRequest::STATUS_IN_REVIEW => 'Valutazione registrata: in attesa dell\'altro valutatore',
        ];

        $message = $messages[$adhesionRequest->status] ?? 'Valutazione registrata';
        if ($adhesionRequest->status === AdhesionRequest::STATUS_IN_REVIEW && $adhesionRequest->isDisagreement()) {
            $message = 'Valutazioni discordanti: la richiesta resta in valutazione';
        }

        $response = [
            'msg' => $message,
            'adhesionRequest' => $adhesionRequest,
        ];
        if ($this->createdSurveyId !== null) {
            $response['survey_id'] = $this->createdSurveyId;
        }
        $this->respond(200, $response);
    }

    /**
     * True se l'id indica un questionario template (`surveys.version_tag = 'template'`).
     *
     * @param int $surveyId Id del questionario.
     * @return bool
     */
    private function isSurveyTemplate(int $surveyId): bool
    {
        return $this->fetchTable('Surveys')->find()
            ->where(['Surveys.id' => $surveyId, 'Surveys.version_tag' => 'template'])
            ->count() > 0;
    }

    /**
     * Alias retrocompatibile: registra un esito POSITIVO dell'attore corrente.
     *
     * Manteniamo l'URL storico `/adhesion-requests/approve/{id}.json`, ma la semantica è
     * quella nuova: NON approva da sola la richiesta, registra una delle due valutazioni.
     *
     * @param string|null $id Adhesion request id.
     * @return void
     */
    public function approve($id = null): void
    {
        $this->request = $this->request->withData('decision', AdhesionRequest::DECISION_APPROVED);
        $this->decide($id);
    }

    /**
     * Alias retrocompatibile: registra un esito NEGATIVO dell'attore corrente.
     *
     * Accetta `reject_reason` (nome storico) oltre a `reason`.
     *
     * @param string|null $id Adhesion request id.
     * @return void
     */
    public function reject($id = null): void
    {
        $reason = $this->request->getData('reason') ?? $this->request->getData('reject_reason');
        $this->request = $this->request
            ->withData('decision', AdhesionRequest::DECISION_REJECTED)
            ->withData('reason', $reason);
        $this->decide($id);
    }

    /**
     * Stub: disabilita l'azione Crud `add` per questo controller.
     *
     * @return void
     * @throws \Cake\Http\Exception\NotFoundException
     */
    public function add(): void
    {
        throw new NotFoundException();
    }

    /**
     * Stub: disabilita l'azione Crud `edit` per questo controller.
     *
     * @param string|null $id Unused.
     * @return void
     * @throws \Cake\Http\Exception\NotFoundException
     */
    public function edit($id = null): void
    {
        throw new NotFoundException();
    }

    /**
     * Elimina una richiesta di adesione.
     *
     * - non conclusa (`pdf_pending`/`in_review`, anche con un solo voto o voti
     *   discordanti, #538): non eliminabile (409) finché i due valutatori non si allineano.
     * - rejected: admin + moma_area (territorio). Rimuove la richiesta e l'utente
     *   referente auto-creato (inattivo). Nessun account persona esiste per le rifiutate.
     * - approved: solo admin. Rimuove sempre la richiesta. Rimuove anche l'azienda:
     *   - cascata COMPLETA (B1, bug 1) se `company_created === true` (questa richiesta
     *     l'ha creata) E nessun'altra `adhesion_requests` referenzia la stessa azienda —
     *     vedi cascadeDeleteApprovedCompany();
     *   - altrimenti comportamento legacy: rimossa solo se `companyIsClean()` (nessuna
     *     sede/questionario/altro utente) — copre sia le aziende riusate
     *     (`company_created === false`) sia le righe pre-migrazione (`company_created`
     *     `null`, nessun backfill). Se l'azienda non viene rimossa, `company_kept_reason`
     *     spiega perché.
     *
     * @param string|null $id Adhesion request id.
     * @return void
     */
    public function delete($id = null): void
    {
        $this->allowRolesOnly(['admin', 'moma_area']);
        $this->request->allowMethod(['post', 'delete']);

        $adhesionRequest = $this->AdhesionRequests->get($id, ['contain' => ['People']]);
        $this->assertTerritoryFor($adhesionRequest->reference_city, $adhesionRequest->reference_province);

        $openStates = [AdhesionRequest::STATUS_PDF_PENDING, AdhesionRequest::STATUS_IN_REVIEW];
        if (in_array($adhesionRequest->status, $openStates, true)) {
            $this->respond(409, [
                'msg' => 'Le richieste in attesa non sono eliminabili: entrambi i valutatori devono prima '
                    . 'approvarle o rifiutarle in modo concorde',
            ]);

            return;
        }

        $identity = $this->Authentication->getIdentity();
        $role = $identity ? $identity->get('role') : null;

        // Le richieste approvate (utenti attivi + azienda reale) le elimina solo l'admin.
        if ($adhesionRequest->status === AdhesionRequest::STATUS_APPROVED && $role !== 'admin') {
            throw new ForbiddenException('Solo un amministratore può eliminare una richiesta approvata');
        }

        $usersTable = $this->AdhesionRequests->Users;
        $companiesTable = $this->AdhesionRequests->Companies;
        $connection = $this->AdhesionRequests->getConnection();

        $errorEntity = null;
        $deletedCounts = [];
        $companyKeptReason = null;
        $companyCodeForCleanup = null;
        $success = $connection->transactional(function () use (
            $usersTable,
            $companiesTable,
            $adhesionRequest,
            &$errorEntity,
            &$deletedCounts,
            &$companyKeptReason,
            &$companyCodeForCleanup
        ) {
            // Tutti gli utenti nati da questa richiesta: il referente più le persone
            // abilitate alla doppia approvazione (prima esisteva solo il referente).
            $userIds = $this->userIdsOf($adhesionRequest);

            if ($adhesionRequest->status === AdhesionRequest::STATUS_APPROVED) {
                $companyId = $adhesionRequest->company_id;
                if (!empty($companyId)) {
                    // B1: la cascata completa è ammissibile SOLO se questa richiesta ha
                    // creato l'azienda (mai per una riusata, `company_created === false`,
                    // né per una riga legacy `null` — nessun backfill, vedi la migrazione)
                    // E nessun'altra richiesta referenzia la stessa azienda (una seconda
                    // adesione sulla stessa azienda, es. re-invio dopo un rifiuto altrove,
                    // ne fa una risorsa condivisa che questa delete() non può decidere di
                    // portarsi via da sola).
                    $sharedByOtherRequest = $this->AdhesionRequests->find()
                        ->where(['company_id' => $companyId, 'id !=' => $adhesionRequest->id])
                        ->count() > 0;

                    if ($adhesionRequest->company_created === true && !$sharedByOtherRequest) {
                        $company = $companiesTable->find()->select(['id', 'company_code'])
                            ->where(['id' => $companyId])->first();
                        $companyCodeForCleanup = $company ? $company->company_code : null;
                        $deletedCounts = $this->cascadeDeleteApprovedCompany((int)$companyId, $userIds);
                    } elseif ($this->companyIsClean($companyId, $userIds)) {
                        // Comportamento legacy invariato: `deleteAll` senza cascata ORM,
                        // sicuro solo perché companyIsClean() ha già verificato che
                        // l'azienda non ha sedi/questionari/altri utenti.
                        if (!empty($userIds)) {
                            $usersTable->deleteAll(['id IN' => $userIds]);
                            $deletedCounts['users'] = count($userIds);
                        }
                        $companiesTable->deleteAll(['id' => $companyId]);
                        $deletedCounts['companies'] = 1;
                    } else {
                        $companyKeptReason = $sharedByOtherRequest
                            ? 'company_shared_by_other_request'
                            : ($adhesionRequest->company_created === false
                                ? 'company_reused'
                                : 'company_not_clean');
                    }
                }
            } elseif (!empty($userIds)) {
                // Rifiutata: rimuovi gli utenti auto-creati (inattivi).
                $usersTable->deleteAll(['id IN' => $userIds]);
                $deletedCounts['users'] = count($userIds);
            }

            if (!$this->AdhesionRequests->delete($adhesionRequest)) {
                $errorEntity = $adhesionRequest;

                return false;
            }

            return true;
        });

        if (!$success) {
            $this->respond(422, [
                'msg' => 'Impossibile eliminare la richiesta',
                'errors' => $errorEntity ? $errorEntity->getErrors() : [],
            ]);

            return;
        }

        // Documenti (PDF di riepilogo e firmato) rimossi dopo il commit.
        try {
            $this->documents()->deleteFolder((int)$adhesionRequest->id);
        } catch (\Throwable $e) {
            Log::write('error', 'Adhesion files cleanup failed: ' . $e->getMessage(), ['scope' => ['adhesion']]);
        }

        // Documento privacy pendente (se mai caricato e mai ri-agganciato a
        // un'azienda): la riga DB la gestisce già la FK CASCADE su
        // `adhesion_request_id` (D4), ma la cartella fisica no-op se assente.
        try {
            $this->companyPrivacyDocumentService()->deletePendingFolder((int)$adhesionRequest->id);
        } catch (\Throwable $e) {
            Log::write(
                'error',
                'Pending privacy document cleanup failed: ' . $e->getMessage(),
                ['scope' => ['adhesion']]
            );
        }

        // B1: documento privacy definitivo + upload PSCL dell'azienda appena cancellata
        // (best-effort, dopo il commit — il filesystem non è transazionale). Skip
        // silenzioso se non c'è nulla da rimuovere: né un'eliminazione parziale/kept né
        // un fallimento qui devono mai far fallire la risposta.
        if (isset($deletedCounts['companies']) && !empty($adhesionRequest->company_id)) {
            try {
                $this->companyPrivacyDocumentService()->deleteExisting((int)$adhesionRequest->company_id, true);
            } catch (\Throwable $e) {
                Log::write(
                    'error',
                    'Company privacy document cleanup failed: ' . $e->getMessage(),
                    ['scope' => ['adhesion']]
                );
            }

            if (!empty($companyCodeForCleanup)) {
                try {
                    $this->deletePsclFolders((string)$companyCodeForCleanup);
                } catch (\Throwable $e) {
                    Log::write('error', 'PSCL folder cleanup failed: ' . $e->getMessage(), ['scope' => ['adhesion']]);
                }
            }
        }

        $this->invalidateCompanyCaches();

        $payload = ['msg' => 'Richiesta eliminata', 'deleted' => $deletedCounts];
        if ($companyKeptReason !== null) {
            $payload['company_kept_reason'] = $companyKeptReason;
        }

        $this->respond(200, $payload);
    }

    // -----------------------------------------------------------------------------
    // Helper privati
    // -----------------------------------------------------------------------------

    /**
     * Recupera la richiesta dal token pubblico, o 404 se il token è assente/errato/scaduto.
     *
     * Un token non valido restituisce 404 (non 403) per non rivelare l'esistenza di una
     * richiesta a chi tira a indovinare.
     *
     * @param string|null $token Token ricevuto.
     * @return \App\Model\Entity\AdhesionRequest
     * @throws \Cake\Http\Exception\NotFoundException
     */
    private function getByToken($token): AdhesionRequest
    {
        $token = is_string($token) ? trim($token) : '';
        if ($token === '' || strlen($token) !== 64) {
            throw new NotFoundException();
        }

        $adhesionRequest = $this->AdhesionRequests->find()
            ->where(['AdhesionRequests.access_token' => $token])
            ->contain(['People'])
            ->first();

        if ($adhesionRequest === null || !$adhesionRequest->isAccessTokenValid($token)) {
            throw new NotFoundException();
        }

        return $adhesionRequest;
    }

    /**
     * Recupera la richiesta dal token PERMANENTE del "Modulo trattamento dati
     * personali dei dipendenti" (requisito 3), o 404 se assente/errato — stesso
     * trattamento "nessun dettaglio distintivo" di getByToken(), per non offrire un
     * oracolo di token-guessing.
     *
     * @param string|null $token Token ricevuto.
     */
    private function getByCompanyDocToken($token): AdhesionRequest
    {
        $token = is_string($token) ? trim($token) : '';
        if ($token === '' || strlen($token) !== 64) {
            throw new NotFoundException();
        }

        $adhesionRequest = $this->AdhesionRequests->find()
            ->where(['AdhesionRequests.company_doc_token' => $token])
            ->first();

        if ($adhesionRequest === null || !$adhesionRequest->isCompanyDocTokenValid($token)) {
            throw new NotFoundException();
        }

        return $adhesionRequest;
    }

    /**
     * Unico punto in cui il link di adesione viene rigenerato/reinviato: usato sia dal
     * self-service `resendLink()` sia dal backoffice `regenerateToken()`, così le due
     * vie non possono divergere sull'invariante "access_token e company_doc_token
     * ruotano SEMPRE insieme" (vedi docs/dev-notes/rer-privacy-consent.md).
     *
     * Ordine deliberato: persiste PRIMA, invia DOPO (mai il contrario — un token
     * emailato ma non persistito sarebbe un link morto senza alcuna traccia). A
     * differenza di ogni altro punto di invio email di questo controller (che ingoiano
     * il fallimento con un solo Log::write, perché il record esiste comunque
     * indipendentemente dalla mail), QUI il fallimento dell'invio NON viene ingoiato:
     * l'email è l'intero deliverable di questa azione, quindi un `\Throwable` da
     * `toMail()` risale come esito negativo (il chiamante risponde 502). Il timestamp
     * di cooldown (`access_token_resent_at`) viene scritto SOLO dopo un invio riuscito:
     * se l'invio fallisce, il cooldown non si attiva, e l'utente può ritentare subito
     * senza restare bloccato dal proprio tentativo fallito.
     *
     * Chiavi del valore restituito: `ok` (bool) e, solo su esito negativo, `reason`
     * (string) ed eventualmente `errors` (array).
     *
     * Invariante `company_doc_token` (in_review/approved): NON ruota mai una volta
     * impostato — romperebbe un link già in uso dalla card documento aziendale. Ma se
     * è NULL (riga orfana: `20260901090100_AddEmployeePrivacyDocFieldsToAdhesionRequests`
     * ha aggiunto la colonna senza backfill, quindi ogni riga caricata prima di quella
     * migrazione non l'ha mai avuta) questo è l'unico punto che la ripara, mintandone
     * uno nuovo. In breve: mai ruota una volta impostato; NULL è una riga rotta,
     * riparata qui. Vedi plan "Repair orphaned company_doc_token" (2026-09-09) e
     * docs/dev-notes/adesione-flow.md.
     *
     * @param \App\Model\Entity\AdhesionRequest $adhesionRequest La richiesta.
     * @param string|null $actorUserId Id utente di backoffice, o null per un reinvio self-service.
     */
    private function regenerateAccessTokenAndNotify(AdhesionRequest $adhesionRequest, ?string $actorUserId): array
    {
        // Non ruotare/contare nulla se non c'è nessun destinatario: peggiorerebbe solo
        // le cose (link invalidato e mai consegnato a nessuno).
        if (empty($adhesionRequest->referentEmail())) {
            return ['ok' => false, 'reason' => 'no_email'];
        }

        $connection = $this->AdhesionRequests->getConnection();
        $token = null;
        $notificationClass = null;
        $errorEntity = null;

        $success = $connection->transactional(
            function () use ($adhesionRequest, $actorUserId, &$token, &$notificationClass, &$errorEntity) {
                if ($adhesionRequest->status === AdhesionRequest::STATUS_PDF_PENDING) {
                    if ($adhesionRequest->accessTokenIsLive()) {
                        // Riusa il token ancora valido: un doppio-click non deve
                        // invalidare il link della prima email prima che sia aperto.
                        $token = $adhesionRequest->access_token;
                        if ($adhesionRequest->company_doc_token !== $token) {
                            // Difensivo (D4): chiude una finestra di drift per una riga
                            // creata tra la migrazione che ha aggiunto company_doc_token
                            // e il deploy di questo fix. Il token emesso resta lo stesso
                            // access_token riusato, quindi la notifica "invariato" sotto
                            // resta corretta.
                            $adhesionRequest->company_doc_token = $token;
                        }
                        $notificationClass = adhesionLinkUnchangedNotification::class;
                    } else {
                        $token = bin2hex(random_bytes(32));
                        $adhesionRequest->access_token = $token;
                        $adhesionRequest->access_token_expires = FrozenTime::now()
                            ->addDays(self::TOKEN_TTL_DAYS);
                        // Invariante: le due colonne ruotano SEMPRE insieme.
                        $adhesionRequest->company_doc_token = $token;
                        $notificationClass = adhesionLinkResentNotification::class;
                    }
                } else {
                    // in_review / approved: company_doc_token è permanente e non ruota
                    // MAI una volta impostato, altrimenti romperebbe un link già in uso
                    // (card documento aziendale) — si reinvia semplicemente il valore
                    // invariato. Ma se è NULL (riga orfana, vedi il docblock del metodo)
                    // non c'è nulla da reinviare: lo si ripara mintandone uno nuovo, e in
                    // quel caso (solo in quel caso) si notifica con
                    // adhesionLinkResentNotification perché il vecchio link, se mai
                    // esistito, non risolveva comunque nulla.
                    if (empty($adhesionRequest->company_doc_token)) {
                        $token = bin2hex(random_bytes(32));
                        $adhesionRequest->company_doc_token = $token;
                        $notificationClass = adhesionLinkResentNotification::class;
                    } else {
                        $token = $adhesionRequest->company_doc_token;
                        $notificationClass = adhesionLinkUnchangedNotification::class;
                    }
                }

                $adhesionRequest->access_token_resend_count += 1;
                $adhesionRequest->access_token_resent_by = $actorUserId;

                if (!$this->AdhesionRequests->save($adhesionRequest)) {
                    $errorEntity = $adhesionRequest;

                    return false;
                }

                return true;
            }
        );

        if (!$success) {
            return [
                'ok' => false,
                'reason' => 'save_failed',
                'errors' => $errorEntity ? $errorEntity->getErrors() : [],
            ];
        }

        try {
            (new $notificationClass($adhesionRequest, $token))->toMail();
        } catch (\Throwable $e) {
            // Deliberatamente NON un semplice Log::write come altrove in questo
            // controller: qui l'email È il deliverable, quindi il fallimento risale al
            // chiamante (502), invece di restituire un 200 che promette un'email mai
            // partita.
            Log::write(
                'error',
                'Adhesion link resend mail failed: ' . $e->getMessage(),
                ['scope' => ['adhesion']]
            );

            return ['ok' => false, 'reason' => 'mail_failed'];
        }

        // Notifica ai valutatori solo se il rigeneramento è stato fatto dal backoffice
        // (actorUserId !== null), non dal self-service del referente (resendLink).
        if ($actorUserId !== null) {
            try {
                $actor = $this->AdhesionRequests->Users->get($actorUserId);
                $reviewerEmails = $this->reviewerEmails(
                    $adhesionRequest->reference_city,
                    $adhesionRequest->reference_province
                );
                foreach ($reviewerEmails as $email) {
                    (new adhesionLinkRegeneratedReviewerNotification(
                        $adhesionRequest,
                        $actor,
                        $email
                    ))->toMail();
                }
            } catch (\Throwable $e) {
                // Log del fallimento, ma non risale al chiamante: l'email del referente
                // è già stata inviata, quindi il richiedente ha già ricevuto il link.
                Log::write(
                    'warning',
                    'Adhesion reviewer notification failed: ' . $e->getMessage(),
                    ['scope' => ['adhesion']]
                );
            }
        }

        // Il cooldown si attiva SOLO ora, dopo l'invio riuscito.
        $adhesionRequest->access_token_resent_at = FrozenTime::now();
        if (!$this->AdhesionRequests->save($adhesionRequest)) {
            Log::write('error', 'Adhesion resend cooldown stamp failed', ['scope' => ['adhesion']]);
        }

        return ['ok' => true];
    }

    /**
     * Traduce l'esito negativo di regenerateAccessTokenAndNotify() nella risposta HTTP
     * condivisa da resendLink() e regenerateToken().
     *
     * @param array $result Esito di regenerateAccessTokenAndNotify() (chiavi `ok`/`reason`/`errors`).
     * @return void
     */
    private function respondToRegenerationFailure(array $result): void
    {
        if (($result['reason'] ?? null) === 'no_email') {
            $this->respond(422, [
                'msg' => 'Nessun indirizzo email a cui inviare il link: contatta l\'assistenza',
            ]);

            return;
        }

        if (($result['reason'] ?? null) === 'save_failed') {
            $this->respond(422, [
                'msg' => 'Impossibile registrare la rigenerazione del link',
                'errors' => $result['errors'] ?? [],
            ]);

            return;
        }

        $this->respond(502, ['msg' => 'Impossibile inviare l\'email con il link: riprova più tardi']);
    }

    /**
     * Permette di inviare il modulo con email e/o CF di un utente già esistente: la persona
     * ottiene un nuovo profilo con lo stesso indirizzo (login multi-profilo, vedi
     * docs/dev-notes/login-multi-profile.md). Attiva di DEFAULT in tutti gli ambienti, produzione
     * compresa; si disattiva con `ADHESION_ALLOW_EXISTING_USERS=false` nell'.env del sito.
     * Non influisce sul controllo delle richieste aperte. L'eliminazione di una richiesta
     * rimuove solo gli utenti collegati per id, mai quelli già esistenti.
     *
     * @return bool
     */
    private function allowExistingUsers(): bool
    {
        return filter_var(env('ADHESION_ALLOW_EXISTING_USERS', true), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Duplicati contro il resto del sistema: utenti già esistenti e richieste ancora aperte.
     *
     * `findByEmail()` è il finder SecretField-aware (le email sono cifrate a riposo), quindi
     * è l'unico modo corretto di cercare per email.
     *
     * @param array $data Submission validata.
     * @param array $people Elenco persone.
     * @return array Errori (con la chiave speciale `__conflict` per i 409).
     */
    private function findDuplicateErrors(array $data, array $people): array
    {
        $usersTable = $this->AdhesionRequests->Users;
        $errors = [];

        // Con la deroga attiva (vedi allowExistingUsers(), default on) le persone già presenti
        // tra gli utenti non bloccano il submit. Le richieste aperte restano
        // controllate sotto.
        $skipUserChecks = $this->allowExistingUsers();

        // --- Referente ------------------------------------------------------------
        if (!$skipUserChecks && $usersTable->findByEmail($data['mm_email'])->count() > 0) {
            $errors['mm_email'] = ['duplicate' => 'Esiste già un utente con questa email'];
        }
        if (!$skipUserChecks && $usersTable->find()->where(['Users.cf' => $data['mm_cf']])->count() > 0) {
            $errors['mm_cf'] = ['duplicate' => 'Esiste già un utente con questo Codice Fiscale'];
        }

        // --- Persone da abilitare (errore per riga) --------------------------------
        foreach ($people as $index => $person) {
            $email = strtolower(trim((string)($person['email'] ?? '')));
            $cf = strtoupper(trim((string)($person['cf'] ?? '')));
            $prefix = 'people.' . $index . '.';

            if (!$skipUserChecks && $email !== '' && $usersTable->findByEmail($email)->count() > 0) {
                $errors[$prefix . 'email'] = ['duplicate' => 'Esiste già un utente con questa email'];
            }
            if (!$skipUserChecks && $cf !== '' && $usersTable->find()->where(['Users.cf' => $cf])->count() > 0) {
                $errors[$prefix . 'cf'] = ['duplicate' => 'Esiste già un utente con questo Codice Fiscale'];
            }
        }

        if (!empty($errors)) {
            return $errors;
        }

        // --- Richieste già aperte con gli stessi dati ------------------------------
        // Evita che lo stesso referente reinvii più volte il modulo mentre attende di
        // firmare: prima nulla lo impediva.
        $emails = [strtolower((string)$data['mm_email'])];
        foreach ($people as $person) {
            $email = strtolower(trim((string)($person['email'] ?? '')));
            if ($email !== '') {
                $emails[] = $email;
            }
        }

        $openStates = [AdhesionRequest::STATUS_PDF_PENDING, AdhesionRequest::STATUS_IN_REVIEW];
        $open = $this->AdhesionRequests->find()
            ->where(['AdhesionRequests.status IN' => $openStates])
            ->contain(['People'])
            ->all();

        foreach ($open as $existing) {
            $existingEmails = [strtolower((string)$existing->referentEmail())];
            foreach ((array)$existing->people as $person) {
                $existingEmails[] = strtolower((string)$person->email);
            }
            $shared = array_intersect($emails, array_filter($existingEmails));
            if (!empty($shared)) {
                return [
                    '__conflict' => true,
                    'mm_email' => [
                        'openRequest' => sprintf(
                            'Esiste già la richiesta n. %d in corso per uno degli indirizzi indicati',
                            $existing->id
                        ),
                    ],
                ];
            }
        }

        return [];
    }

    /**
     * True se esiste almeno un'area che copre comune o provincia indicati.
     *
     * @param string $city Comune.
     * @param string $province Provincia.
     * @return bool
     */
    private function territoryIsCovered(string $city, string $province): bool
    {
        if ($city === '' && $province === '') {
            return false;
        }

        $conditions = [];
        if ($city !== '') {
            $conditions['Areas.city'] = $city;
        }
        if ($province !== '') {
            $conditions['Areas.province'] = $province;
        }

        return $this->fetchTable('Areas')->find()
            ->where(['OR' => $conditions])
            ->count() > 0;
    }

    /**
     * Azienda già presente con lo stesso CF o partita IVA, se esiste.
     *
     * Non blocca nulla: è un'informazione per il valutatore, che può collegare l'azienda
     * esistente (passando `company_id`) invece di crearne un duplicato.
     *
     * @param array $data Dati della richiesta.
     * @return \App\Model\Entity\Company|null
     */
    private function findExistingCompany(array $data)
    {
        $codes = array_values(array_filter([
            $data['fiscal_code'] ?? null,
            $data['vat_number'] ?? null,
        ]));
        if (empty($codes)) {
            return null;
        }

        $company = $this->AdhesionRequests->Companies->find()
            ->where(['Companies.company_code IN' => $codes])
            ->first();

        // #534: mai rivelare (nome incluso) un'azienda fuori dal territorio del valutatore.
        if ($company !== null && $this->findCompanyInViewerScope((int)$company->id) === null) {
            return null;
        }

        return $company;
    }

    /**
     * L'azienda indicata, solo se l'utente corrente può "vederla" (stesso scope di
     * `CompaniesController`): un admin qualunque azienda, un moma_area solo quelle con
     * almeno una sede nel proprio territorio.
     *
     * L'azione DEVE essere 'view': `CompaniesTablePolicy` ha solo scopeList/scopeView/
     * scopeExportList e, per l'admin, `beforeScope` restituisce null e la libreria ricade
     * su `scope<Action>` (con 'decide' lancerebbe MissingMethodException). Il
     * leftJoinWith('Offices') serve perché il ramo moma_area della policy filtra su
     * Offices.province/city senza fare il join. Un'azienda SENZA sedi non supera mai il
     * filtro per un moma_area (fail-closed: riuso riservato all'admin).
     *
     * @param int $companyId Id azienda.
     * @return \App\Model\Entity\Company|null
     */
    private function findCompanyInViewerScope(int $companyId)
    {
        $identity = $this->Authentication->getIdentity();
        $role = $identity ? $identity->get('role') : null;
        if ($role === 'admin') {
            // L'admin non è mai limitato per territorio. beforeScope() restringerebbe un
            // utente con company_id valorizzato a quella sola azienda: non vale per l'admin.
            return $this->fetchTable('Companies')->find()
                ->where(['Companies.id' => $companyId])
                ->first();
        }

        $query = $this->fetchTable('Companies')->find()
            ->where(['Companies.id' => $companyId])
            ->leftJoinWith('Offices');

        return $this->Authorization->applyScope($query, 'view')->first();
    }

    /**
     * Id di tutti gli utenti nati da questa richiesta (referente + persone abilitate).
     *
     * @param \App\Model\Entity\AdhesionRequest $adhesionRequest La richiesta.
     * @return array<int, string>
     */
    private function userIdsOf(AdhesionRequest $adhesionRequest): array
    {
        $ids = [];
        if (!empty($adhesionRequest->user_id)) {
            $ids[] = $adhesionRequest->user_id;
        }
        foreach ((array)$adhesionRequest->people as $person) {
            if (!empty($person->user_id)) {
                $ids[] = $person->user_id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * L'azienda è "pulita" se non ha sedi, questionari né utenti oltre a quelli nati da
     * questa richiesta: solo in tal caso l'eliminazione fa l'undo completo.
     *
     * @param int $companyId Id azienda.
     * @param array<int, string> $excludeUserIds Id utenti da escludere dal conteggio.
     * @return bool
     */
    private function companyIsClean($companyId, array $excludeUserIds): bool
    {
        $companies = $this->AdhesionRequests->Companies;
        $users = $this->AdhesionRequests->Users;

        $offices = $companies->Offices->find()->where(['company_id' => $companyId])->count();
        $surveys = $companies->Surveys->find()->where(['company_id' => $companyId])->count();

        $otherUsersQuery = $users->find()->where(['company_id' => $companyId]);
        if (!empty($excludeUserIds)) {
            $otherUsersQuery->where(['id NOT IN' => $excludeUserIds]);
        }
        $otherUsers = $otherUsersQuery->count();

        return $offices === 0 && $surveys === 0 && $otherUsers === 0;
    }

    /**
     * Cascata completa di eliminazione (B1, bug 1) per un'azienda creata da QUESTA
     * richiesta approvata (`company_created === true`, nessun'altra `adhesion_requests`
     * che la referenzi — verificato dal chiamante). Chiamata DENTRO la transazione di
     * `delete()`: `Connection::transactional()` non annida una seconda transazione reale,
     * quindi ogni `deleteAll()` qui gira nella stessa transazione del chiamante.
     *
     * Ordine e tabelle verificati via `information_schema` su `emma_rer` (vedi
     * docs/dev-notes/adesione-flow.md): alcune FK sono già `ON DELETE CASCADE`
     * (`offices.company_id`, `pscl.company_id/office_id/survey_id`,
     * `monitorings.office_id/survey_id/pscl_id`,
     * `answers/survey_participants/questions_surveys.survey_id`), ma l'ordine esplicito
     * resta necessario per le tabelle `SET NULL` o prive di FK
     * (`surveys.company_id`, `origins.*`, `employees.office_id`, `od`, `tpl_operators`,
     * `company_survey_history`, `timeslots`/`timetables`, `subscriptions`, `actions`) —
     * la DB da sola non le ripulirebbe.
     *
     * Gli utenti sono filtrati SEMPRE per `role = <ruolo abilitato> AND NOT is_superuser`,
     * mai per id soltanto: un admin/moma_area che condividesse `company_id` (caso limite,
     * mai creato da questo modulo ma non impedito a livello DB) non viene mai toccato.
     *
     * @param int $companyId Id azienda da eliminare.
     * @param array<int, string> $userIdsToDelete Utenti nati dalla richiesta (referente + persone).
     * @return array<string, int> Conteggi eliminati per tabella (solo chiavi con almeno una riga toccata).
     */
    private function cascadeDeleteApprovedCompany(int $companyId, array $userIdsToDelete): array
    {
        $companiesTable = $this->AdhesionRequests->Companies;
        $usersTable = $this->AdhesionRequests->Users;
        $officesTable = $this->fetchTable('Offices');
        $surveysTable = $this->fetchTable('Surveys');
        $psclTable = $this->fetchTable('Pscl');
        $timetablesTable = $this->fetchTable('Timetables');
        // 'Od' pluralizza in 'ods' per convenzione CakePHP, ma la tabella reale è 'od'
        // (nessun plurale, verificato via information_schema): il nome tabella va forzato.
        $odTable = $this->getTableLocator()->get('Od', ['table' => 'od']);

        $counts = [];

        $officeIds = $officesTable->find()->select(['id'])
            ->where(['company_id' => $companyId])->all()->extract('id')->toArray();

        $surveyConditions = array_values(array_filter([
            ['company_id' => $companyId],
            !empty($officeIds) ? ['office_id IN' => $officeIds] : null,
        ]));
        $surveyIds = $surveysTable->find()->select(['id'])
            ->where(['OR' => $surveyConditions])->all()->extract('id')->toArray();

        $psclConditions = array_values(array_filter([
            ['company_id' => $companyId],
            !empty($officeIds) ? ['office_id IN' => $officeIds] : null,
            !empty($surveyIds) ? ['survey_id IN' => $surveyIds] : null,
        ]));
        $psclIds = $psclTable->find()->select(['id'])
            ->where(['OR' => $psclConditions])->all()->extract('id')->toArray();

        $timetableIds = !empty($officeIds)
            ? $timetablesTable->find()->select(['id'])
                ->where(['office_id IN' => $officeIds])->all()->extract('id')->toArray()
            : [];

        // 1. Per questionario S.
        if (!empty($surveyIds)) {
            $counts['answers'] = (int)$this->fetchTable('Answers')->deleteAll(['survey_id IN' => $surveyIds]);
            $counts['survey_participants'] = (int)$this->fetchTable('SurveyParticipants')
                ->deleteAll(['survey_id IN' => $surveyIds]);
            $counts['questions_surveys'] = (int)$this->fetchTable('QuestionsSurveys')
                ->deleteAll(['survey_id IN' => $surveyIds]);
            $counts['survey_delivery_configs'] = (int)$this->fetchTable('SurveyDeliveryConfigs')
                ->deleteAll(['survey_id IN' => $surveyIds]);
        }

        // 2. Per sede O.
        if (!empty($officeIds)) {
            if (!empty($timetableIds)) {
                $counts['timeslots'] = (int)$this->fetchTable('Timeslots')
                    ->deleteAll(['timetable_id IN' => $timetableIds]);
            }
            $counts['timetables'] = (int)$timetablesTable->deleteAll(['office_id IN' => $officeIds]);
            $counts['employees'] = (int)$this->fetchTable('Employees')->deleteAll(['office_id IN' => $officeIds]);
            $counts['subscriptions'] = (int)$this->fetchTable('Subscriptions')
                ->deleteAll(['office_id IN' => $officeIds]);
        }

        // od/monitorings: per questionario O per sede (compaiono in entrambi gli step).
        $odConditions = array_values(array_filter([
            !empty($surveyIds) ? ['survey_id IN' => $surveyIds] : null,
            !empty($officeIds) ? ['office_id IN' => $officeIds] : null,
        ]));
        if (!empty($odConditions)) {
            $counts['od'] = (int)$odTable->deleteAll(['OR' => $odConditions]);
        }
        $monitoringConditions = array_values(array_filter([
            !empty($surveyIds) ? ['survey_id IN' => $surveyIds] : null,
            !empty($officeIds) ? ['office_id IN' => $officeIds] : null,
            !empty($psclIds) ? ['pscl_id IN' => $psclIds] : null,
        ]));
        if (!empty($monitoringConditions)) {
            $counts['monitorings'] = (int)$this->fetchTable('Monitorings')
                ->deleteAll(['OR' => $monitoringConditions]);
        }

        // 3. Per pscl di O/S.
        if (!empty($psclIds)) {
            $counts['actions'] = (int)$this->fetchTable('Actions')->deleteAll(['pscl_id IN' => $psclIds]);
            $counts['pscl'] = (int)$psclTable->deleteAll(['id IN' => $psclIds]);
        }

        // 4. Per azienda X.
        $counts['company_survey_history'] = (int)$this->fetchTable('CompanySurveyHistory')
            ->deleteAll(['company_id' => $companyId]);
        $counts['tpl_operators'] = (int)$this->fetchTable('TplOperators')->deleteAll(['company_id' => $companyId]);
        $counts['origins'] = (int)$this->fetchTable('Origins')->deleteAll(['company_id' => $companyId]);
        if (!empty($surveyIds)) {
            $counts['surveys'] = (int)$surveysTable->deleteAll(['id IN' => $surveyIds]);
        }

        // 5. Utenti: SOLO company_id = X AND role IN (<ruolo abilitato>, 'user') AND NOT
        // is_superuser — mai per id soltanto (un admin/moma_area con lo stesso company_id,
        // caso limite mai creato da questo modulo, non viene mai toccato). Il ruolo
        // 'user' sono gli account dei dipendenti/partecipanti ai questionari
        // dell'azienda (creati dall'invito, non da questo modulo): senza, restavano
        // attivi e orfani (users.company_id è ON DELETE SET NULL) dopo la cancellazione
        // dei loro survey_participants. $userIdsToDelete non è usato come filtro: è già
        // coperto dal predicato ruolo/company_id (referente + persone sono sempre creati
        // con role = self::GRANTED_ROLE), e includerlo rischierebbe di IMPEDIRE la
        // stessa protezione se un id noto finisse associato a un ruolo diverso.
        $safeUserIds = $usersTable->find()->select(['id'])
            ->where([
                'company_id' => $companyId,
                'role IN' => [self::GRANTED_ROLE, 'user'],
                'is_superuser' => false,
            ])->all()->extract('id')->toArray();
        if (!empty($safeUserIds)) {
            $this->fetchTable('RevokedTokens')->deleteAll(['user_id IN' => $safeUserIds]);
            $counts['users'] = (int)$usersTable->deleteAll(['id IN' => $safeUserIds]);
        }

        // 6. Sedi, documento privacy definitivo (riga DB), azienda.
        if (!empty($officeIds)) {
            $counts['offices'] = (int)$officesTable->deleteAll(['id IN' => $officeIds]);
        }
        $counts['company_privacy_documents'] = (int)$this->fetchTable('CompanyPrivacyDocuments')
            ->deleteAll(['company_id' => $companyId]);
        $counts['companies'] = (int)$companiesTable->deleteAll(['id' => $companyId]);

        return array_filter($counts, function ($count) {
            return $count > 0;
        });
    }

    /**
     * Rimuove (best-effort, dopo il commit) le cartelle di export/upload PSCL
     * dell'azienda cancellata. `HtmlExporter::resultPath()` le nomina per
     * `company_code` (mai per id) sotto `WWW_ROOT/{sitedir}/pscl/{year}/{company_code}`
     * o `{company_code}-{office_code}`: senza il codice, riconoscere le cartelle da
     * cancellare non sarebbe possibile. No-op silenzioso se non ce n'è nessuna.
     *
     * @param string $companyCode `companies.company_code` dell'azienda appena cancellata.
     * @return void
     */

    /**
     * `deleteAll()` e i save fuori dai controller dedicati non passano dai callback che
     * svuotano le cache di sedi e statistiche: senza questo gli elenchi mostrano sedi
     * e aziende già cancellate (o non ancora le nuove) fino alla scadenza della cache.
     */
    private function invalidateCompanyCaches(): void
    {
        try {
            Cache::clearGroup('offices', 'offices');
            Cache::clearGroup('employee_stats');
        } catch (\Throwable $e) {
            Log::write('warning', 'Adhesion cache invalidation failed: ' . $e->getMessage(), ['scope' => ['adhesion']]);
        }
    }

    private function deletePsclFolders(string $companyCode): void
    {
        if (trim($companyCode) === '') {
            return;
        }

        // Stessa forma di concatenazione di HtmlExporter::resultPath() ("sitedir" ha
        // già uno slash finale, niente DS aggiuntivo tra WWW_ROOT e sitedir).
        $pattern = WWW_ROOT . Configure::read('sitedir') . 'pscl' . DS . '*' . DS . $companyCode . '*';
        foreach ((array)glob($pattern, GLOB_ONLYDIR) as $dir) {
            if (is_string($dir) && is_dir($dir)) {
                (new \Cake\Filesystem\Folder())->delete($dir);
            }
        }
    }

    /**
     * Ricalcola lo stato dopo una valutazione e, se serve, esegue l'abilitazione.
     *
     * Chiamato DENTRO la transazione di decide(): gli effetti collaterali non
     * transazionali (purge del documento di identità, mail di rifiuto) vengono accodati in
     * `$postCommitCallbacks` ed eseguiti da decide() solo dopo il commit.
     *
     * @param \App\Model\Entity\AdhesionRequest $adhesionRequest La richiesta con la nuova decisione.
     * @return bool False se il salvataggio è fallito.
     */
    private function finalize(AdhesionRequest $adhesionRequest): bool
    {
        // #538: la richiesta si chiude con un rifiuto solo se ENTRAMBI i valutatori hanno
        // rifiutato (prima bastava un rifiuto, §9).
        if ($adhesionRequest->isFullyRejected()) {
            $adhesionRequest->status = AdhesionRequest::STATUS_REJECTED;
            $adhesionRequest->reject_reason = $adhesionRequest->rejectionReason();
            $this->stampProcessedBy($adhesionRequest);

            if (!$this->AdhesionRequests->save($adhesionRequest)) {
                return false;
            }

            $this->afterCommit(function () use ($adhesionRequest): void {
                // Retention: un documento di identità non serve più a una pratica chiusa
                // con rifiuto. Solo DOPO il commit (nessun file perso se fallisce).
                $this->purgeIdentityDocument($adhesionRequest);

                $referent = $adhesionRequest->referentEmail();
                if (!empty($referent)) {
                    try {
                        (new adhesionRejectedNotification($adhesionRequest, $referent))->toMail();
                    } catch (\Throwable $e) {
                        Log::write(
                            'error',
                            'Adhesion rejection mail failed: ' . $e->getMessage(),
                            ['scope' => ['adhesion']]
                        );
                    }
                }
            });

            return true;
        }

        // Doppia approvazione: solo ora nascono gli account (§8).
        if ($adhesionRequest->isFullyApproved()) {
            return $this->enable($adhesionRequest);
        }

        // Una sola valutazione espressa, oppure valutazioni discordanti: resta in
        // valutazione, senza purge né mail.
        return (bool)$this->AdhesionRequests->save($adhesionRequest);
    }

    /**
     * Esegue subito il callback, oppure lo accoda se decide() ha aperto la finestra
     * "dopo il commit" (`$postCommitCallbacks` non null).
     *
     * @param callable $callback Effetto collaterale non transazionale.
     * @return void
     */
    private function afterCommit(callable $callback): void
    {
        if ($this->postCommitCallbacks === null) {
            $callback();

            return;
        }
        $this->postCommitCallbacks[] = $callback;
    }

    /**
     * Elimina il file di identità e azzera le quattro colonne (best-effort: un errore
     * di filesystem/DB qui non deve far fallire il rifiuto già salvato).
     *
     * @param \App\Model\Entity\AdhesionRequest $adhesionRequest La richiesta.
     * @return void
     */
    private function purgeIdentityDocument(AdhesionRequest $adhesionRequest): void
    {
        try {
            $this->documents()->purgeIdentityFile((int)$adhesionRequest->id);
            if (!empty($adhesionRequest->identity_doc_filename) || !empty($adhesionRequest->identity_doc_uploaded_at)) {
                $adhesionRequest->identity_doc_filename = null;
                $adhesionRequest->identity_doc_size = null;
                $adhesionRequest->identity_doc_mime = null;
                $adhesionRequest->identity_doc_uploaded_at = null;
                $this->AdhesionRequests->save($adhesionRequest);
            }
        } catch (\Throwable $e) {
            Log::write('error', 'Identity document purge failed: ' . $e->getMessage(), ['scope' => ['adhesion']]);
        }
    }

    /**
     * Denormalizza "chi ha chiuso la pratica" nei campi storici.
     *
     * `processed_by`/`processed_at`/`reject_reason` restano popolati perché il backoffice
     * (colonna "Elaborata da", blocco "Esito") e i test e2e esistenti li leggono.
     *
     * @param \App\Model\Entity\AdhesionRequest $adhesionRequest La richiesta.
     * @return void
     */
    private function stampProcessedBy(AdhesionRequest $adhesionRequest): void
    {
        // L'ultima decisione in ordine di tempo è quella che ha chiuso la pratica.
        $adminAt = $adhesionRequest->admin_decided_at;
        $momaAt = $adhesionRequest->moma_decided_at;

        $useAdmin = $adminAt !== null && ($momaAt === null || $adminAt->greaterThanOrEquals($momaAt));
        $adhesionRequest->processed_by = $useAdmin
            ? $adhesionRequest->admin_decided_by
            : $adhesionRequest->moma_decided_by;
        $adhesionRequest->processed_at = $useAdmin ? $adminAt : $momaAt;
    }

    /**
     * Abilitazione (Step 4): crea/collega l'azienda, attiva il referente e crea un account
     * per ogni persona indicata, con scadenza a 365 giorni DALLA DATA DI DOPPIA APPROVAZIONE.
     *
     * ⚠️ `UsersTable::beforeMarshal()` imposta `expiration_date` a +365 giorni dalla
     * CREAZIONE. Per il referente, creato al submit, quella data partirebbe dall'invio del
     * form e non verrebbe mai ricalcolata: la specifica §8 chiede 365 giorni dalla doppia
     * approvazione, quindi qui la si scrive esplicitamente per tutti.
     *
     * `company_created` è scritto qui (mai altrove): `true` quando questa richiesta crea
     * una nuova azienda, `false` quando riusa un'azienda esistente (`$bodyCompanyId`).
     * `delete()` lo usa per decidere se la cascata completa (B1) è ammissibile. Solo per
     * una nuova azienda viene anche auto-creata una sede "Sede legale" dall'indirizzo
     * legale della richiesta (B2, bug 2) — un'azienda riusata ha già le proprie sedi.
     *
     * @param \App\Model\Entity\AdhesionRequest $adhesionRequest La richiesta approvata.
     * @return bool
     */
    private function enable(AdhesionRequest $adhesionRequest): bool
    {
        $companiesTable = $this->AdhesionRequests->Companies;
        $usersTable = $this->AdhesionRequests->Users;
        $peopleTable = $this->AdhesionRequests->People;
        // B2: la "Sede legale" auto-creata solo per una nuova azienda (mai per una
        // riusata) — vedi il blocco --- Azienda --- più sotto.
        $officesTable = $this->fetchTable('Offices');
        $connection = $this->AdhesionRequests->getConnection();

        $data = (array)$adhesionRequest->data;
        $bodyCompanyId = $this->request->getData('company_id');
        $approvedAt = FrozenTime::now();
        $expirationDate = $approvedAt->addDays(self::ENABLEMENT_DAYS)->i18nFormat('yyyy-MM-dd');

        $errorEntity = null;
        $pendingDocumentToRelocate = null;
        $officeToGeocode = null;
        $success = $connection->transactional(function () use (
            $companiesTable,
            $usersTable,
            $peopleTable,
            $officesTable,
            $adhesionRequest,
            $data,
            $bodyCompanyId,
            $approvedAt,
            $expirationDate,
            &$errorEntity,
            &$pendingDocumentToRelocate,
            &$officeToGeocode
        ) {
            // --- Azienda ----------------------------------------------------------
            // company_created traccia se QUESTA richiesta ha creato l'azienda o ne ha
            // riusata una esistente: delete() lo usa per decidere se la cascata
            // completa (B1) è ammissibile — vedi docs/dev-notes/adesione-flow.md.
            if (!empty($bodyCompanyId)) {
                $company = $companiesTable->get($bodyCompanyId);
                $adhesionRequest->company_created = false;
            } else {
                $street = (string)($data['legal_address_street'] ?? '');
                $number = (string)($data['legal_address_number'] ?? '');
                $address = trim($street . ' ' . $number);

                // Solo i campi tematici non mappati su colonne finiscono in
                // additional_data, e solo se previsti dall'allow-list (il payload è già
                // filtrato al submit).
                $mappedKeys = [
                    'company_name', 'email', 'pec', 'phone', 'ateco', 'num_employees',
                    'fiscal_code', 'vat_number', 'reference_city', 'reference_province',
                    'legal_address_street', 'legal_address_number',
                ];
                $additionalData = array_diff_key($data, array_flip($mappedKeys));

                // Pre-compilazione dei questionari (solo colonne `survey` live, MAI una riga
                // company_survey_history: altrimenti il box "conferma anno" sparirebbe) —
                // vedi AdhesionSurveyPrefillService e docs/dev-notes/adesione-flow.md.
                $companyTypeId = $this->resolveCompanyType($adhesionRequest);
                $companyTypeRow = $this->fetchTable('CompanyTypes')->find()
                    ->select(['id', 'survey_template'])
                    ->where(['id' => $companyTypeId])
                    ->first();
                $prefillData = $data + ['reference_city' => $adhesionRequest->reference_city];
                $prefill = new AdhesionSurveyPrefillService();

                $company = $companiesTable->newEntity([
                    'name' => $data['company_name'] ?? null,
                    'email' => $data['email'] ?? null,
                    'pec' => $data['pec'] ?? null,
                    'tel' => $data['phone'] ?? null,
                    'ateco' => $data['ateco'] ?? null,
                    'num_employees' => isset($data['num_employees']) && $data['num_employees'] !== ''
                        ? (int)$data['num_employees']
                        : null,
                    // company_code ospita il CF/P.IVA dell'organizzazione: serve anche a
                    // riconoscere i duplicati nelle richieste successive.
                    'company_code' => $data['fiscal_code'] ?? ($data['vat_number'] ?? null),
                    'city' => $adhesionRequest->reference_city,
                    'province' => $adhesionRequest->reference_province,
                    'address' => $address !== '' ? $address : null,
                    'type' => $companyTypeId,
                    'additional_data' => $additionalData,
                    'survey' => $prefill->companySurvey(
                        $prefillData,
                        (string)($companyTypeRow->survey_template ?? 'azienda')
                    ),
                ]);
                if (!$companiesTable->save($company)) {
                    $errorEntity = $company;

                    return false;
                }
                $adhesionRequest->company_created = true;

                // --- B2: "Sede legale" auto-creata, solo per una nuova azienda -----
                // Una richiesta con azienda RIUSATA ($bodyCompanyId) non crea mai una
                // sede: l'azienda esistente ha già le proprie. Coordinate inizialmente null
                // (geocoding avviene DOPO il commit della transazione, mai dentro).
                $office = $officesTable->newEntity([
                    'name' => 'Sede legale',
                    'address' => $address !== '' ? $address : null,
                    'cap' => $data['legal_address_cap'] ?? null,
                    'city' => !empty($data['legal_address_city'])
                        ? $data['legal_address_city']
                        : $adhesionRequest->reference_city,
                    'province' => $adhesionRequest->reference_province,
                    'company_id' => $company->id,
                    'survey' => $prefill->officeSurvey($prefillData),
                ]);
                if (!$officesTable->save($office)) {
                    $errorEntity = $office;

                    return false;
                }

                // Track the office for geocoding after commit
                $officeToGeocode = $office;
            }

            // --- Referente: attivazione + scadenza ricalcolata ---------------------
            if (!empty($adhesionRequest->user_id)) {
                $user = $usersTable->get($adhesionRequest->user_id);
                $user->company_id = $company->id;
                $user->active = true;
                $user->expiration_date = $expirationDate;
                if (!$usersTable->save($user)) {
                    $errorEntity = $user;

                    return false;
                }
            }

            // --- Un account per ogni persona indicata ------------------------------
            foreach ((array)$adhesionRequest->people as $person) {
                if (!empty($person->user_id)) {
                    // Già abilitata (rientro difensivo): non duplicare.
                    continue;
                }

                $newUser = $usersTable->newEntity([
                    'email' => $person->email,
                    'username' => $person->email,
                    'first_name' => $person->first_name,
                    'last_name' => $person->last_name,
                    'cf' => $person->cf,
                    'role' => self::GRANTED_ROLE,
                    'company_id' => $company->id,
                    'active' => true,
                    'expiration_date' => $expirationDate,
                    'password' => bin2hex(random_bytes(32)),
                ], [
                    'accessibleFields' => [
                        'role' => true,
                        'company_id' => true,
                        'password' => true,
                    ],
                ]);
                $newUser->is_superuser = false;
                if (!$usersTable->save($newUser)) {
                    $errorEntity = $newUser;

                    return false;
                }

                // `user_id` non è mass-assignable sull'entity: si scrive qui.
                $person->user_id = $newUser->id;
                if (!$peopleTable->save($person)) {
                    $errorEntity = $person;

                    return false;
                }
            }

            // --- Bug 5: questionario dal template scelto dal valutatore -------------
            // Solo per un'azienda CREATA da questa richiesta e solo se un template è stato
            // scelto. Dentro la transazione: un fallimento annulla l'intera approvazione,
            // così non esiste mai un'azienda senza il questionario che il valutatore ha
            // scelto. createNewSurveyFromTemplate() lancia su errore, mentre
            // cloneQuestions() ignora i save() delle singole righe: si verifica quindi
            // anche che il numero di domande clonate coincida con quello del template.
            if ($adhesionRequest->company_created === true && !empty($adhesionRequest->survey_template_id)) {
                $templateId = (int)$adhesionRequest->survey_template_id;
                try {
                    $newSurveyId = (int)$this->fetchTable('Surveys')
                        ->createNewSurveyFromTemplate($company, $templateId, (int)date('Y'));
                    $questionsSurveys = $this->fetchTable('QuestionsSurveys');
                    $expected = $questionsSurveys->find()->where(['survey_id' => $templateId])->count();
                    $cloned = $questionsSurveys->find()->where(['survey_id' => $newSurveyId])->count();
                } catch (\Throwable $e) {
                    Log::write(
                        'error',
                        'Adhesion survey-from-template failed: ' . $e->getMessage(),
                        ['scope' => ['adhesion']]
                    );
                    $this->enableFailureMessage = self::TEMPLATE_FAILURE_MESSAGE;
                    $errorEntity = $adhesionRequest;

                    return false;
                }
                if ($cloned !== $expected) {
                    Log::write(
                        'error',
                        "Adhesion survey-from-template incomplete: $cloned of $expected questions cloned",
                        ['scope' => ['adhesion']]
                    );
                    $this->enableFailureMessage = self::TEMPLATE_FAILURE_MESSAGE;
                    $errorEntity = $adhesionRequest;

                    return false;
                }
                $this->createdSurveyId = $newSurveyId;
            }

            $adhesionRequest->status = AdhesionRequest::STATUS_APPROVED;
            $adhesionRequest->company_id = $company->id;

            // --- Documento privacy pendente (D4/D5): ri-aggancio all'azienda ------
            // Un documento caricato durante `in_review` è agganciato alla richiesta
            // (`adhesion_request_id`), non ancora all'azienda. Va ri-agganciato QUI,
            // dentro la transazione, nullando `adhesion_request_id` — lasciarla
            // valorizzata farebbe cascare l'eliminazione futura di QUESTA richiesta
            // (già approvata) sul documento reale e ormai in uso dell'azienda, per via
            // della FK CASCADE pensata per il caso "rifiutata e poi cancellata" (D4).
            $documentsTable = $this->fetchTable('CompanyPrivacyDocuments');
            $pendingDocument = $documentsTable->find()
                ->where(['adhesion_request_id' => $adhesionRequest->id])
                ->first();
            if ($pendingDocument !== null) {
                // D5: una re-adesione può ri-approvare un'azienda che ha già un
                // documento definitivo — va rimosso prima di ri-agganciare quello
                // pendente, altrimenti l'UPDATE violerebbe UNIQUE(company_id) e
                // farebbe fallire l'intera approvazione.
                $documentsTable->deleteAll(['company_id' => $company->id]);

                $pendingDocument->company_id = $company->id;
                $pendingDocument->adhesion_request_id = null;
                if (!$documentsTable->save($pendingDocument)) {
                    $errorEntity = $pendingDocument;

                    return false;
                }

                // Lo spostamento fisico del file avviene DOPO il commit (il filesystem
                // non è transazionale): qui si registra solo cosa spostare.
                $pendingDocumentToRelocate = [
                    'adhesion_request_id' => (int)$adhesionRequest->id,
                    'company_id' => (int)$company->id,
                    'current_path' => (string)$pendingDocument->path,
                ];
            }

            $this->stampProcessedBy($adhesionRequest);
            if (!$this->AdhesionRequests->save($adhesionRequest)) {
                $errorEntity = $adhesionRequest;

                return false;
            }

            return true;
        });

        if (!$success) {
            if ($errorEntity !== null && $errorEntity !== $adhesionRequest) {
                // Riporta gli errori dell'entità che ha fatto fallire la transazione.
                foreach ($errorEntity->getErrors() as $field => $error) {
                    $adhesionRequest->setError($field, $error);
                }
            }

            return false;
        }

        // Tutto ciò che segue è non transazionale (cache, geocoding, filesystem, mail): se
        // enable() gira dentro la transazione di decide() (#538), si accoda e parte solo
        // dopo il commit; altrimenti parte subito.
        $this->afterCommit(function () use (
            $adhesionRequest,
            $officeToGeocode,
            $pendingDocumentToRelocate,
            $expirationDate
        ): void {
            // La "Sede legale" appena creata deve comparire subito negli elenchi in cache.
            $this->invalidateCompanyCaches();

            // Geocoding della "Sede legale" creata (B2): DOPO il commit della transazione DB
            // (esterno il geocoding non è transazionale). Non deve mai far fallire l'approvazione:
            // se fallisce, la sede resta con coordinate NULL — ancora usabile, mai rotto.
            if ($officeToGeocode !== null) {
                try {
                    $officesTable = $this->fetchTable('Offices');
                    if ($officesTable->geocodeOffice($officeToGeocode, false)) {
                        $officesTable->save($officeToGeocode);
                    }
                } catch (\Throwable $e) {
                    Log::write(
                        'error',
                        'Geocoding of auto-created office failed: ' . $e->getMessage(),
                        ['scope' => ['adhesion', 'geocoding']]
                    );
                }
            }

            // Spostamento fisico del documento privacy pendente (se presente) nella
            // cartella definitiva dell'azienda, DOPO il commit della transazione DB (D3:
            // il filesystem non è transazionale). Non deve mai far fallire l'approvazione:
            // se fallisce, `path` resta puntato alla cartella pendente — ancora leggibile,
            // mai rotto — e l'errore viene solo loggato.
            if ($pendingDocumentToRelocate !== null) {
                try {
                    $newPath = $this->companyPrivacyDocumentService()->relocateToCompany(
                        $pendingDocumentToRelocate['adhesion_request_id'],
                        $pendingDocumentToRelocate['company_id'],
                        $pendingDocumentToRelocate['current_path']
                    );
                    if ($newPath !== null) {
                        $this->fetchTable('CompanyPrivacyDocuments')->updateAll(
                            ['path' => $newPath],
                            ['company_id' => $pendingDocumentToRelocate['company_id']]
                        );
                    }
                } catch (\Throwable $e) {
                    Log::write(
                        'error',
                        'Company privacy document relocation failed: ' . $e->getMessage(),
                        ['scope' => ['adhesion']]
                    );
                }
            }

            // Notifiche di esito positivo (§11): referente, persone abilitate, e per
            // conoscenza admin + moma_area competenti.
            try {
                $this->notifyApproved($adhesionRequest, $expirationDate);
            } catch (\Throwable $e) {
                Log::write('error', 'Adhesion approval mail failed: ' . $e->getMessage(), ['scope' => ['adhesion']]);
            }
        });

        return true;
    }

    /**
     * Determina `companies.type` dal tipo di organizzazione dichiarato.
     *
     * Riusa `company_types.survey_template` ('azienda'|'scuola'), già seminato in
     * config/Seeds/CompanyTypesSeed.php: nessuna nuova tassonomia.
     *
     * @param \App\Model\Entity\AdhesionRequest $adhesionRequest La richiesta.
     * @return int
     */
    private function resolveCompanyType(AdhesionRequest $adhesionRequest): int
    {
        if (!empty($adhesionRequest->company_type_id)) {
            return (int)$adhesionRequest->company_type_id;
        }

        $template = $adhesionRequest->organization_type === 'scuola' ? 'scuola' : 'azienda';
        $type = $this->fetchTable('CompanyTypes')->find()
            ->where(['survey_template' => $template])
            ->order(['id' => 'ASC'])
            ->first();

        return $type ? (int)$type->id : 1;
    }

    /**
     * Notifica ai valutatori che la richiesta è ora valutabile (§11).
     *
     * @param \App\Model\Entity\AdhesionRequest $adhesionRequest La richiesta.
     * @return void
     */
    private function notifyReviewers(AdhesionRequest $adhesionRequest): void
    {
        $recipients = $this->reviewerEmails(
            $adhesionRequest->reference_city,
            $adhesionRequest->reference_province
        );

        foreach ($recipients as $email) {
            (new adhesionRequestNotification($adhesionRequest, $email))->toMail();
        }
    }

    /**
     * Notifiche di esito positivo: referente + persone abilitate + valutatori.
     *
     * @param \App\Model\Entity\AdhesionRequest $adhesionRequest La richiesta approvata.
     * @param string $expirationDate Data di scadenza dell'abilitazione (yyyy-MM-dd).
     * @return void
     */
    private function notifyApproved(AdhesionRequest $adhesionRequest, string $expirationDate): void
    {
        $recipients = [];

        $referent = $adhesionRequest->referentEmail();
        if (!empty($referent)) {
            $recipients[strtolower($referent)] = $referent;
        }
        foreach ((array)$adhesionRequest->people as $person) {
            if (!empty($person->email)) {
                $recipients[strtolower($person->email)] = $person->email;
            }
        }
        // Per conoscenza ai valutatori.
        $recipients += $this->reviewerEmails(
            $adhesionRequest->reference_city,
            $adhesionRequest->reference_province
        );

        foreach ($recipients as $email) {
            (new adhesionApprovedNotification($adhesionRequest, $email, $expirationDate))->toMail();
        }
    }

    /**
     * Helper per servire PDFs inline (vs. download). I file .p7m rimangono attachments.
     *
     * @param string $path Percorso assoluto del file.
     * @param string $filename Nome del file per Content-Disposition.
     * @param string|null $signatureFormat Formato di firma (es. 'p7m' o 'cades') per rilevare .p7m.
     * @param string|null $storedFilename Nome del file memorizzato nel DB, usato come fallback per rilevare estensione.
     * @param string|null $mime Mime type da servire (default `pdf`); usato per le immagini di identità.
     * @return \Cake\Http\Response
     */
    private function serveFileInline(
        string $path,
        string $filename,
        ?string $signatureFormat = null,
        ?string $storedFilename = null,
        ?string $mime = null
    ): \Cake\Http\Response {
        // Rileva .p7m dal signatureFormat o dall'estensione del nome memorizzato.
        $isP7m = ($signatureFormat === 'p7m') ||
            (($storedFilename ?? $filename) !== null &&
             strtolower(pathinfo($storedFilename ?? $filename, PATHINFO_EXTENSION)) === 'p7m');

        if ($isP7m) {
            // .p7m rimane attachment.
            return $this->response->withFile($path, [
                'name' => $filename,
                'download' => true,
            ]);
        }

        // PDF inline: sanitizza il nome da quote/newline e imposta Content-Disposition.
        $safeName = str_replace(['"', "\n", "\r"], '', $filename);

        return $this->response
            ->withFile($path)
            ->withType($mime ?? 'pdf')
            ->withHeader('Content-Disposition', 'inline; filename="' . $safeName . '"')
            ->withHeader('X-Content-Type-Options', 'nosniff');
    }
}
