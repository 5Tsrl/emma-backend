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
 * Notifica inviata ai valutatori (admin + moma_area di territorio) quando il backoffice
 * rigenerano il link della richiesta di adesione (`regenerateToken()` con `$actorUserId !== null`).
 *
 * Contiene: nome azienda/ente, chi ha rigenerato il link (nome completo dell'utente),
 * stato corrente della richiesta, e (solo se `pdf_pending`) la nuova data di scadenza.
 *
 * Template derivato per reflection: `adhesion_link_regenerated_reviewer`.
 */
class adhesionLinkRegeneratedReviewerNotification extends baseNotification
{
    /**
     * @param \App\Model\Entity\AdhesionRequest $request La richiesta di adesione.
     * @param \App\Model\Entity\User $actor L'utente che ha rigenerato il link.
     * @param string $recipientEmail Email del destinatario (valutatore).
     */
    public function __construct($request, $actor, string $recipientEmail)
    {
        parent::__construct();

        $data = $request->data ?? [];

        $this->vars = [
            'request' => $request,
            'companyName' => $data['company_name'] ?? '',
            'actorFullName' => $actor->full_name ?? trim(($actor->first_name ?? '') . ' ' . ($actor->last_name ?? '')),
            'status' => $request->status,
            'expiresAt' => $request->access_token_expires,
        ];

        $this->subject = __('Richiesta di adesione EMMA: link rigenerato dal backoffice');
        $this->to = $recipientEmail;
    }
}
