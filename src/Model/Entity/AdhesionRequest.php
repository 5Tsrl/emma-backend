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

namespace App\Model\Entity;

use Cake\I18n\FrozenTime;
use Cake\ORM\Entity;

/**
 * AdhesionRequest Entity
 *
 * @property int $id
 * @property string $status
 * @property string|null $theme
 * @property string|null $reference_city
 * @property string|null $reference_province
 * @property array $data
 * @property string|null $ip
 * @property string|null $user_id
 * @property int|null $company_id
 * @property bool|null $company_created
 * @property int|null $survey_template_id
 * @property string|null $reject_reason
 * @property string|null $processed_by
 * @property \Cake\I18n\FrozenTime|null $processed_at
 * @property \Cake\I18n\FrozenTime|null $created
 * @property \Cake\I18n\FrozenTime|null $modified
 *
 * @property string $organization_type
 * @property int|null $company_type_id
 * @property \Cake\I18n\FrozenTime|null $pdf_generated_at
 * @property string|null $signed_pdf_filename
 * @property int|null $signed_pdf_size
 * @property string|null $signed_pdf_mime
 * @property string|null $signature_format
 * @property \Cake\I18n\FrozenTime|null $signed_pdf_uploaded_at
 * @property string|null $identity_doc_filename
 * @property int|null $identity_doc_size
 * @property string|null $identity_doc_mime
 * @property \Cake\I18n\FrozenTime|null $identity_doc_uploaded_at
 * @property string|null $access_token
 * @property \Cake\I18n\FrozenTime|null $access_token_expires
 * @property \Cake\I18n\FrozenTime|null $access_token_resent_at
 * @property int $access_token_resend_count
 * @property string|null $access_token_resent_by
 * @property bool $accept_privacy_informativa
 * @property string|null $company_doc_token
 * @property string|null $admin_decision
 * @property string|null $admin_decided_by
 * @property \Cake\I18n\FrozenTime|null $admin_decided_at
 * @property string|null $admin_reason
 * @property string|null $moma_decision
 * @property string|null $moma_decided_by
 * @property \Cake\I18n\FrozenTime|null $moma_decided_at
 * @property string|null $moma_reason
 * @property bool $territory_unmatched
 *
 * @property \App\Model\Entity\User $user
 * @property \App\Model\Entity\Company $company
 * @property \App\Model\Entity\AdhesionRequestPerson[] $people
 * @property \App\Model\Entity\AdhesionRequestDecision[] $decisions
 */
class AdhesionRequest extends Entity
{
    /**
     * Stati della richiesta (specifica §10). "Bozza" resta lato client: il form non
     * persiste nulla prima dell'invio.
     */
    public const STATUS_PDF_PENDING = 'pdf_pending';
    public const STATUS_IN_REVIEW = 'in_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    /**
     * Esiti di una singola valutazione (§7). `null` = non ancora deciso.
     */
    public const DECISION_APPROVED = 'approved';
    public const DECISION_REJECTED = 'rejected';

    /**
     * Attori competenti alla valutazione: un `admin` E un `moma_area` devono entrambi
     * approvare perché la richiesta venga abilitata. La chiave è il prefisso di colonna.
     *
     * @var array<string, string>
     */
    public const ACTOR_BY_ROLE = [
        'admin' => 'admin',
        'moma_area' => 'moma',
    ];

    /**
     * Tutti gli stati validi.
     *
     * @var array<int, string>
     */
    public const STATUSES = [
        self::STATUS_PDF_PENDING,
        self::STATUS_IN_REVIEW,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
    ];

    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * I campi di decisione e il token di accesso NON sono assegnabili in massa: li scrive
     * solo il codice dedicato (`decide()`, generazione token), mai un payload. Il form di
     * adesione è pubblico e anonimo, quindi la superficie va tenuta minima.
     *
     * @var array<string, bool>
     */
    protected $_accessible = [
        'status' => true,
        'theme' => true,
        'reference_city' => true,
        'reference_province' => true,
        'organization_type' => true,
        'company_type_id' => true,
        'data' => true,
        'ip' => true,
        'user_id' => true,
        'company_id' => true,
        // Scritto SOLO da AdhesionRequestsController::enable(), mai da un payload — la
        // richiesta è la sola fonte di verità su se l'azienda è stata creata o riusata.
        'company_created' => false,
        // Template di questionario scelto dal valutatore (bug 5): scritto SOLO da
        // AdhesionRequestsController::decide(), mai da un payload pubblico.
        'survey_template_id' => false,
        'reject_reason' => true,
        'processed_by' => true,
        'processed_at' => true,
        'territory_unmatched' => true,
        'accept_privacy_informativa' => true,
        'created' => true,
        'modified' => true,
        'user' => true,
        'company' => true,
        'people' => true,
        // Mai dal payload:
        'access_token' => false,
        'access_token_expires' => false,
        // Audit/cooldown della rigenerazione del link (requisito "Item 3", vedi
        // docs/dev-notes/rer-privacy-consent.md): scritti SOLO da
        // regenerateAccessTokenAndNotify(), mai da un payload — stessa cautela di
        // access_token/access_token_expires, di cui condividono il ciclo di vita.
        'access_token_resent_at' => false,
        'access_token_resend_count' => false,
        'access_token_resent_by' => false,
        // Token permanente del "Modulo trattamento dati personali dei dipendenti"
        // (requisito 3): scritto SOLO da AdhesionRequestsController::submit(), mai da
        // un payload — stessa cautela di access_token.
        'company_doc_token' => false,
        'admin_decision' => false,
        'admin_decided_by' => false,
        'admin_decided_at' => false,
        'admin_reason' => false,
        'moma_decision' => false,
        'moma_decided_by' => false,
        'moma_decided_at' => false,
        'moma_reason' => false,
        'signed_pdf_filename' => false,
        'signed_pdf_size' => false,
        'signed_pdf_mime' => false,
        'signature_format' => false,
        'signed_pdf_uploaded_at' => false,
        'pdf_generated_at' => false,
        // Documento di identità facoltativo (bug 6): scritto SOLO da
        // AdhesionRequestsController::uploadIdentity() e azzerato da finalize() alla chiusura con rifiuto (di entrambi i valutatori, #538).
        'identity_doc_filename' => false,
        'identity_doc_size' => false,
        'identity_doc_mime' => false,
        'identity_doc_uploaded_at' => false,
    ];

    /**
     * Campi mai serializzati verso il client.
     *
     * `access_token` è una credenziale (dà accesso al PDF e all'upload firmato) e non deve
     * comparire nelle risposte di backoffice; `ip` è un dato di audit che non serve alla UI.
     *
     * @var array<int, string>
     */
    protected $_hidden = [
        'access_token',
        'company_doc_token',
        'ip',
    ];

    /**
     * Restituisce `data` sempre come array.
     *
     * Con una colonna json reale CakePHP decodifica automaticamente; questo accessor
     * è una rete di sicurezza per i casi in cui il valore arrivi come stringa JSON
     * (es. alcune fixture o sorgenti grezze), così il resto del codice può sempre
     * trattare `data` come array.
     *
     * @param mixed $data Il valore grezzo della proprietà.
     * @return array
     */
    protected function _getData($data): array
    {
        if (is_string($data)) {
            $decoded = json_decode($data, true);

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($data) ? $data : [];
    }

    /**
     * Prefisso di colonna ('admin'|'moma') competente per un ruolo, o null se il ruolo
     * non è un valutatore.
     *
     * @param string|null $role Ruolo dell'identità.
     * @return string|null
     */
    public static function actorForRole(?string $role): ?string
    {
        return $role !== null ? (self::ACTOR_BY_ROLE[$role] ?? null) : null;
    }

    /**
     * Esito già espresso dall'attore indicato ('admin'|'moma'), o null se non ha deciso.
     *
     * @param string $actor Prefisso di colonna.
     * @return string|null
     */
    public function decisionOf(string $actor): ?string
    {
        return $this->get($actor . '_decision');
    }

    /**
     * True se entrambe le valutazioni sono positive: è la sola condizione che abilita gli
     * account (specifica §8).
     *
     * @return bool
     */
    public function isFullyApproved(): bool
    {
        return $this->decisionOf('admin') === self::DECISION_APPROVED
            && $this->decisionOf('moma') === self::DECISION_APPROVED;
    }

    /**
     * True se ENTRAMBE le valutazioni sono negative: è la sola condizione che chiude la
     * richiesta con un rifiuto (#538: prima bastava un rifiuto, specifica §9).
     *
     * @return bool
     */
    public function isFullyRejected(): bool
    {
        return $this->decisionOf('admin') === self::DECISION_REJECTED
            && $this->decisionOf('moma') === self::DECISION_REJECTED;
    }

    /**
     * True se i due valutatori hanno votato in modo discordante (entrambi espressi e
     * diversi). La richiesta resta `in_review` finché non si allineano.
     *
     * @return bool
     */
    public function isDisagreement(): bool
    {
        $admin = $this->decisionOf('admin');
        $moma = $this->decisionOf('moma');

        return $admin !== null && $moma !== null && $admin !== $moma;
    }

    /**
     * Motivazioni dei valutatori che hanno rifiutato (admin per primo), solo non vuote.
     *
     * @return array<int, string>
     */
    public function rejectionReasons(): array
    {
        $reasons = [];
        foreach (['admin', 'moma'] as $actor) {
            if ($this->decisionOf($actor) !== self::DECISION_REJECTED) {
                continue;
            }
            $reason = trim((string)$this->get($actor . '_reason'));
            if ($reason !== '') {
                $reasons[] = $reason;
            }
        }

        return $reasons;
    }

    /**
     * Motivazione del rifiuto unita: le motivazioni dei valutatori che hanno rifiutato,
     * separate da una riga vuota. Null se nessuno ha rifiutato / nessuna motivazione.
     *
     * @return string|null
     */
    public function rejectionReason(): ?string
    {
        $reasons = $this->rejectionReasons();

        return $reasons === [] ? null : implode("\n\n", $reasons);
    }

    /**
     * True se il token di accesso passato è valido e non scaduto.
     *
     * Confronto con hash_equals per non esporre differenze di tempo.
     *
     * @param string|null $token Token ricevuto dalla richiesta HTTP.
     * @return bool
     */
    public function isAccessTokenValid(?string $token): bool
    {
        if (empty($token) || empty($this->access_token)) {
            return false;
        }
        if (!hash_equals((string)$this->access_token, (string)$token)) {
            return false;
        }
        if ($this->access_token_expires !== null && $this->access_token_expires->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * True se il token permanente del "Modulo trattamento dati personali dei
     * dipendenti" (requisito 3) passato corrisponde a quello registrato.
     *
     * A differenza di `isAccessTokenValid()`, questo token non ha scadenza: è pensato
     * per restare valido indefinitamente, anche anni dopo l'approvazione della
     * richiesta (vedi la migrazione AddEmployeePrivacyDocFieldsToAdhesionRequests per
     * il perché). Confronto con hash_equals per non esporre differenze di tempo.
     *
     * @param string|null $token Token ricevuto dalla richiesta HTTP.
     */
    public function isCompanyDocTokenValid(?string $token): bool
    {
        if (empty($token) || empty($this->company_doc_token)) {
            return false;
        }

        return hash_equals((string)$this->company_doc_token, (string)$token);
    }

    /**
     * Email del referente (il mobility manager indicato nel form): destinatario di tutte
     * le notifiche rivolte a chi ha presentato la richiesta.
     *
     * @return string|null
     */
    public function referentEmail(): ?string
    {
        $data = (array)$this->data;

        return !empty($data['mm_email']) ? (string)$data['mm_email'] : null;
    }

    /**
     * True se `access_token` è presente e non scaduto.
     *
     * A differenza di `isAccessTokenValid()`, non confronta un token candidato: serve
     * solo a sapere se il link "riepilogo" è ancora spendibile (es. per decidere se
     * riusare il token esistente invece di rigenerarlo — vedi
     * regenerateAccessTokenAndNotify() in AdhesionRequestsController).
     *
     * @return bool
     */
    public function accessTokenIsLive(): bool
    {
        if (empty($this->access_token)) {
            return false;
        }

        return $this->access_token_expires === null || !$this->access_token_expires->isPast();
    }

    /**
     * Email del referente mascherata (primo carattere della parte locale + `***` +
     * dominio completo), per confermare un invio riuscito senza rivelare l'indirizzo
     * completo a chi guarda solo la risposta HTTP.
     *
     * @return string|null
     */
    public function maskedReferentEmail(): ?string
    {
        $email = $this->referentEmail();
        if (empty($email)) {
            return null;
        }

        $atPosition = strpos($email, '@');
        if ($atPosition === false || $atPosition === 0) {
            return null;
        }

        $localPart = mb_substr($email, 0, 1);
        $domain = substr($email, $atPosition + 1);

        return $localPart . '***@' . $domain;
    }

    /**
     * True se il link può essere rigenerato/reinviato ORA: lo stato lo consente (mai
     * `rejected`, richiesta morta) e il cooldown anti-doppio-click è scaduto (o non è
     * mai stato attivato).
     *
     * @param int $cooldownMinutes Minuti di cooldown tra un invio riuscito e il successivo.
     * @return bool
     */
    public function canResendLink(int $cooldownMinutes): bool
    {
        $resendableStatuses = [self::STATUS_PDF_PENDING, self::STATUS_IN_REVIEW, self::STATUS_APPROVED];
        if (!in_array($this->status, $resendableStatuses, true)) {
            return false;
        }
        if ($this->access_token_resent_at === null) {
            return true;
        }

        return $this->access_token_resent_at->addMinutes($cooldownMinutes)->isPast();
    }

    /**
     * Istante esatto in cui il cooldown termina, o null se il link è già rigenerabile
     * ora (`canResendLink()` vero) o se non è mai stato inviato un resend (nessun
     * cooldown da attendere).
     *
     * @param int $cooldownMinutes Minuti di cooldown tra un invio riuscito e il successivo.
     * @return \Cake\I18n\FrozenTime|null
     */
    public function resendAvailableAt(int $cooldownMinutes): ?FrozenTime
    {
        if ($this->canResendLink($cooldownMinutes) || $this->access_token_resent_at === null) {
            return null;
        }

        return $this->access_token_resent_at->addMinutes($cooldownMinutes);
    }
}
