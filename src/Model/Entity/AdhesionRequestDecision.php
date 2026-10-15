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

use Cake\ORM\Entity;

/**
 * AdhesionRequestDecision Entity — una riga dello storico dei voti (#538).
 *
 * Scritta SOLO da `AdhesionRequestsController::decide()` (mai da un payload): ogni voto
 * nuovo o modificato di un valutatore accoda una riga; le colonne `admin_*`/`moma_*` di
 * `adhesion_requests` restano il voto corrente.
 *
 * @property int $id
 * @property int $adhesion_request_id
 * @property string $actor 'admin'|'moma'
 * @property string $decision 'approved'|'rejected'
 * @property string|null $reason
 * @property string|null $decided_by
 * @property \Cake\I18n\FrozenTime $decided_at
 *
 * @property \App\Model\Entity\AdhesionRequest $adhesion_request
 * @property \App\Model\Entity\User $user
 */
class AdhesionRequestDecision extends Entity
{
    /**
     * Nessun campo assegnabile in massa: lo storico lo scrive solo il codice dedicato.
     *
     * @var array<string, bool>
     */
    protected $_accessible = [
        '*' => false,
    ];
}
