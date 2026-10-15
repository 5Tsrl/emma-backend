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
 * RevokedToken Entity — riga della denylist dei JWT revocati (anti VM1).
 *
 * @property int $id
 * @property string $jti
 * @property string|null $user_id
 * @property string $token_type
 * @property \Cake\I18n\FrozenTime $expires_at
 * @property \Cake\I18n\FrozenTime $revoked_at
 * @property \Cake\I18n\FrozenTime|null $created
 */
class RevokedToken extends Entity
{
    protected $_accessible = [
        'jti' => true,
        'user_id' => true,
        'token_type' => true,
        'expires_at' => true,
        'revoked_at' => true,
        'created' => true,
    ];
}
