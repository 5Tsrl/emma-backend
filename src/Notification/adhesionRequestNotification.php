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

namespace App\Notification;

use Cake\Core\Configure;
use Notifications\Notification\baseNotification;

/**
 * Notifica ai VALUTATORI (admin + moma_area di territorio): è stato ricevuto il PDF firmato
 * digitalmente e la richiesta è ora valutabile.
 *
 * ⚠️ Nota: un avviso informativo separato e più breve (adhesionSubmittedReviewerNotification)
 * è ora inviato anche al momento di submission della richiesta. Questa notifica rimane
 * il trigger principale della valutazione (specifica §11, "Upload PDF firmato completato").
 *
 * Il template deriva per reflection in `adhesion_request` (vedi baseNotification).
 */
class adhesionRequestNotification extends baseNotification
{
    /**
     * @param \App\Model\Entity\AdhesionRequest $request La richiesta di adesione.
     * @param string $recipientEmail Email del destinatario (admin o moma_area).
     */
    public function __construct($request, string $recipientEmail)
    {
        parent::__construct();

        $data = $request->data ?? [];
        $frontendUrl = Configure::read('FrontendUrl');

        $this->vars = [
            'request' => $request,
            'companyName' => $data['company_name'] ?? '',
            'referenceCity' => $request->reference_city ?? ($data['reference_city'] ?? ''),
            'mmName' => trim(($data['mm_first_name'] ?? '') . ' ' . ($data['mm_last_name'] ?? '')),
            'mmEmail' => $data['mm_email'] ?? '',
            'frontendUrl' => $frontendUrl,
            'adhesionsLink' => rtrim((string)$frontendUrl, '/') . '/adesioni',
            'peopleCount' => is_countable($request->people ?? null) ? count($request->people) : 0,
            'signatureFormat' => $request->signature_format,
        ];

        $this->subject = 'Richiesta di adesione da valutare (documento firmato ricevuto)';
        $this->to = $recipientEmail;
    }
}
