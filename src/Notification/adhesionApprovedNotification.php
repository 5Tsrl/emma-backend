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
 * Notifica di esito POSITIVO, inviata dopo la DOPPIA approvazione (specifica §8/§11).
 *
 * Destinatari: il referente, le persone abilitate, e per conoscenza admin e MOMA d'Area.
 * Template derivato per reflection: `adhesion_approved`.
 */
class adhesionApprovedNotification extends baseNotification
{
    /**
     * @param \App\Model\Entity\AdhesionRequest $request La richiesta approvata.
     * @param string $recipientEmail Destinatario.
     * @param \Cake\I18n\FrozenTime|string|null $expirationDate Scadenza dell'abilitazione.
     */
    public function __construct($request, string $recipientEmail, $expirationDate = null)
    {
        parent::__construct();

        $data = $request->data ?? [];
        $frontendUrl = rtrim((string)Configure::read('FrontendUrl'), '/');

        $this->vars = [
            'request' => $request,
            'companyName' => $data['company_name'] ?? '',
            'peopleCount' => is_countable($request->people ?? null) ? count($request->people) : 0,
            'expirationDate' => $expirationDate,
            'loginLink' => $frontendUrl . '/login',
        ];

        $this->subject = 'Richiesta di adesione EMMA approvata';
        $this->to = $recipientEmail;
    }
}
