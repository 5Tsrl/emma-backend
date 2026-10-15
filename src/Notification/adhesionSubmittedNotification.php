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
 * Notifica inviata al REFERENTE subito dopo l'invio del form (specifica §6/§11).
 *
 * Contiene il link con il token monouso per scaricare il PDF di riepilogo, firmarlo
 * digitalmente e ricaricarlo: il referente è anonimo (non ha un account con cui rientrare),
 * quindi questo link è l'unico modo per completare la richiesta.
 *
 * Template derivato per reflection: `adhesion_submitted`.
 */
class adhesionSubmittedNotification extends baseNotification
{
    /**
     * @param \App\Model\Entity\AdhesionRequest $request La richiesta appena creata.
     * @param string $token Token di accesso in chiaro (non è persistito nell'entity serializzata).
     */
    public function __construct($request, string $token)
    {
        parent::__construct();

        $data = $request->data ?? [];
        $frontendUrl = rtrim((string)Configure::read('FrontendUrl'), '/');

        $this->vars = [
            'request' => $request,
            'companyName' => $data['company_name'] ?? '',
            'mmName' => trim(($data['mm_first_name'] ?? '') . ' ' . ($data['mm_last_name'] ?? '')),
            'peopleCount' => is_countable($request->people ?? null) ? count($request->people) : 0,
            'completeLink' => $frontendUrl . '/adesione/completa/' . $token,
            'expiresAt' => $request->access_token_expires,
        ];

        $this->subject = 'Richiesta di adesione EMMA: completa con il documento firmato';
        $this->to = $request->referentEmail();
    }
}
