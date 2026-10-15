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
 * Notifica ai VALUTATORI (admin + moma_area di territorio): è stata ricevuta una richiesta
 * di adesione al momento di invio del form.
 *
 * È una notifica informativa breve che non contiene il token di accesso: la vera valutazione
 * avverrà solo dopo l'upload del PDF firmato, quando partirà l'adhesionRequestNotification.
 *
 * Il template deriva per reflection in `adhesion_submitted_reviewer` (vedi baseNotification).
 */
class adhesionSubmittedReviewerNotification extends baseNotification
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
        'referenceProvince' => $request->reference_province ?? ($data['reference_province'] ?? ''),
        'mmName' => trim(($data['mm_first_name'] ?? '') . ' ' . ($data['mm_last_name'] ?? '')),
        'mmEmail' => $data['mm_email'] ?? '',
        'frontendUrl' => $frontendUrl,
        'adhesionsLink' => rtrim((string)$frontendUrl, '/') . '/adesioni',
        'peopleCount' => is_countable($request->people ?? null) ? count($request->people) : 0,
        ];

        $this->subject = __('Nuova richiesta di adesione ricevuta: in attesa del documento firmato');
        $this->to = $recipientEmail;
    }
}
