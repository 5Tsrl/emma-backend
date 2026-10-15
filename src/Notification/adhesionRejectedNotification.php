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

use Notifications\Notification\baseNotification;

/**
 * Notifica informale di RIFIUTO al referente (specifica §9/§11).
 *
 * Inviata solo quando ENTRAMBI i valutatori hanno rifiutato (#538): `reason` è il testo
 * unito delle due motivazioni (admin per primo, separate da una riga vuota). La
 * motivazione è obbligatoria lato server, quindi qui è sempre valorizzata. Alla
 * comunicazione informale può seguire una comunicazione formale (PEC/lettera) le cui
 * modalità sono ancora da definire con RER: questo template lo anticipa esplicitamente,
 * senza prometterne i tempi.
 *
 * Template derivato per reflection: `adhesion_rejected`.
 */
class adhesionRejectedNotification extends baseNotification
{
    /**
     * @param \App\Model\Entity\AdhesionRequest $request La richiesta rifiutata.
     * @param string $recipientEmail Destinatario (il referente).
     */
    public function __construct($request, string $recipientEmail)
    {
        parent::__construct();

        $data = $request->data ?? [];

        $this->vars = [
            'request' => $request,
            'companyName' => $data['company_name'] ?? '',
            'reason' => $request->rejectionReason() ?? '',
        ];

        $this->subject = 'Richiesta di adesione EMMA non accolta';
        $this->to = $recipientEmail;
    }
}
