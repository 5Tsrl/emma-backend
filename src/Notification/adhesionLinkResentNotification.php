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
 * Notifica inviata quando il link di adesione viene RIGENERATO (token ruotato):
 * il vecchio link, incluso quello dell'email di `adhesionSubmittedNotification`, smette
 * di funzionare da questo momento — sia per il PDF di riepilogo sia per il documento
 * aziendale, perché `access_token` e `company_doc_token` ruotano SEMPRE insieme (vedi
 * l'invariante in docs/dev-notes/rer-privacy-consent.md).
 *
 * Usata solo nel ramo `pdf_pending` con token scaduto/assente di
 * `AdhesionRequestsController::regenerateAccessTokenAndNotify()`. Non va confusa con
 * `adhesionSubmittedNotification`: quest'ultima descrive un invio appena ricevuto, e
 * settimane dopo leggerebbe come una submission duplicata/confusa.
 *
 * Template derivato per reflection: `adhesion_link_resent`.
 */
class adhesionLinkResentNotification extends baseNotification
{
    /**
     * @param \App\Model\Entity\AdhesionRequest $request La richiesta di adesione.
     * @param string $token Nuovo token di accesso in chiaro.
     */
    public function __construct($request, string $token)
    {
        parent::__construct();

        $data = $request->data ?? [];
        $frontendUrl = rtrim((string)Configure::read('FrontendUrl'), '/');

        $this->vars = [
            'request' => $request,
            'companyName' => $data['company_name'] ?? '',
            'completeLink' => $frontendUrl . '/adesione/completa/' . $token,
            'expiresAt' => $request->access_token_expires,
        ];

        $this->subject = 'Richiesta di adesione EMMA: nuovo link per il documento firmato';
        $this->to = $request->referentEmail();
    }
}
