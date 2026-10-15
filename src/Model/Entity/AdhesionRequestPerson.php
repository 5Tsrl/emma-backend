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
 * AdhesionRequestPerson Entity — una persona dell'"Elenco persone da abilitare".
 *
 * `user_id` resta null finché la richiesta non ottiene la DOPPIA approvazione: solo in
 * quel momento viene creato l'account (365 giorni) e il legame viene scritto qui, così
 * l'eliminazione della richiesta sa quali utenti disfare.
 *
 * @property int $id
 * @property int $adhesion_request_id
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property string $cf
 * @property string|null $user_id
 * @property \Cake\I18n\FrozenTime|null $created
 * @property \Cake\I18n\FrozenTime|null $modified
 *
 * @property \App\Model\Entity\AdhesionRequest $adhesion_request
 * @property \App\Model\Entity\User $user
 */
class AdhesionRequestPerson extends Entity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * `user_id` è deliberatamente NON assegnabile in massa: viene scritto solo dal codice
     * di abilitazione, mai da un payload (il form di adesione è pubblico e anonimo).
     *
     * @var array<string, bool>
     */
    protected $_accessible = [
        'adhesion_request_id' => true,
        'first_name' => true,
        'last_name' => true,
        'email' => true,
        'cf' => true,
        'user_id' => false,
        'created' => true,
        'modified' => true,
    ];

    /**
     * Nome completo, comodo per le notifiche e per il PDF di riepilogo.
     *
     * @return string
     */
    protected function _getFullName(): string
    {
        return trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
    }
}
