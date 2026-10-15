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
 * Notifica inviata quando il link di adesione viene semplicemente REINVIATO, senza
 * alcuna rotazione del token: il referente (o chi per lui) ha chiesto di ricevere di
 * nuovo lo stesso link, che resta l'unico valido, con lo stesso contenuto di prima.
 *
 * Usata in due casi di `AdhesionRequestsController::regenerateAccessTokenAndNotify()`:
 * - `pdf_pending` con `access_token` ancora valido (evita di invalidare un link non
 *   ancora aperto per via di un doppio-click);
 * - `in_review`/`approved`, dove `company_doc_token` non ruota MAI una volta che il
 *   documento aziendale può già essere in preparazione/uso su quel link.
 *
 * A differenza di `adhesionLinkResentNotification`, il testo non deve mai suggerire che
 * qualcosa sia cambiato o che serva un'azione nuova: il link precedente, se già
 * ricevuto, resta perfettamente valido.
 *
 * Template derivato per reflection: `adhesion_link_unchanged`.
 */
class adhesionLinkUnchangedNotification extends baseNotification
{
    /**
     * @param \App\Model\Entity\AdhesionRequest $request La richiesta di adesione.
     * @param string $token Token di accesso (invariato) in chiaro.
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

        $this->subject = 'Richiesta di adesione EMMA: il tuo link, ancora valido';
        $this->to = $request->referentEmail();
    }
}
